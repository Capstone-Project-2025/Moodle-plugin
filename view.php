<?php

use core_customfield\data;

require_once('../../config.php');
require_once($CFG->dirroot . '/apirequest.php');

$courseid = required_param('id', PARAM_INT); // 'id' must be passed in the URL

$course = get_course($courseid);

// Displays the Course/Settings/Participants/.../"More" bar
require_login($course);

// Carries the course full name from previous page, using the course_id collected
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "moodle40";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$check_id = $courseid;

// Prepare and run the query
$sql = "SELECT * FROM mdl_dmojorganize WHERE course_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $check_id);
$stmt->execute();
$result = $stmt->get_result();

$found = $result->num_rows > 0;

$dmoj_organization_id_found;
if ($row = $result->fetch_assoc()) {
    $dmoj_organization_id_found = $row['organization_id'];
}
$stmt->close();
$conn->close();

// Get context of the course
$context = context_course::instance($courseid);

// Get enrolled users
$users = get_enrolled_users($context);

// Get roles assigned in the course context
$role_assignments = get_role_users(null, $context, false);

$moodle_ids = [];

// Loop through users and print user ID and their roles
foreach ($users as $user) {
    //echo "User ID: {$user->id}<br>";
    //echo "Username: {$user->username}<br>";
    //echo "User email: {$user->email}<br>";
    //echo "User first name: {$user->firstname}<br>";
    //echo "User last name: {$user->lastname}<br>";

    // $user->id is string, but API request requires integers
    array_push($moodle_ids, (int) $user->id);

    // Get roles assigned to this user in the course context
    $user_roles = get_user_roles($context, $user->id, false);
    
    //echo "Roles: ";
    if (!empty($user_roles)) {
        foreach ($user_roles as $role) {
            //echo role_get_name($role, $context) . ' ';
        }
    } else {
        //echo 'No role assigned';
    }
    //echo "<br><br>";
}
//print_r($moodle_ids);
//echo "<br>";

$DMOJ_IDs_class = new FetchDMOJid([], $moodle_ids);
$response = $DMOJ_IDs_class->run();
$response['body'] = json_decode($response['body'], true);
//echo "<pre> DMOJ user IDs found: " . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";

$DMOJ_organization_collected = new GetOrgDetail($dmoj_organization_id_found);
$response = $DMOJ_organization_collected->run();
$response['body'] = json_decode($response['body'], true);
if ($response["status"] == 200) {
    //echo "<pre> DMOJ organization info: " . json_encode($response["body"]["data"]["object"], JSON_PRETTY_PRINT) . "</pre>";
}
?>
<!DOCTYPE html>
<html>
    <body>
    <?php if (!$found): ?>
        <form action="formresponse.php?id=<?php echo $courseid; ?>" method="POST">
            <label for="dmoj_links">Link to DMOJ organization</label>
            <select name="dmoj_options" id="dmoj_options">
                <option value="yes">Yes</option>
                <option value="no" selected>No</option>
            </select>
            <br>
            <button type="submit">OK</button>
        </form>
    <?php else: ?>
        <label for="dmoj_links">Link to DMOJ organization</label>
        <select name="dmoj_options" id="dmoj_options" disabled>
        <option value="yes">Yes</option>
        </select>
        <br>
        <label>This course has already been linked to a DMOJ organization and this option cannot be disabled.</label>
        <br>
        <label>DMOJ organization ID linked: <?php echo htmlspecialchars($dmoj_organization_id_found); ?></label>
        <br>
        <form action="forcesync.php?id=<?php echo $courseid; ?>&organization_id=<?php echo $dmoj_organization_id_found; ?>" method="POST">
            <button type="submit">Force sync Moodle participants list to DMOJ organization member list</button>
        </form>
    <?php endif; ?>
    <br>
  </body>
</html>
<?php
echo $OUTPUT->footer();
?>