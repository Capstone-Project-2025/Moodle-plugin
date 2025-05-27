<?php
require_once('../../config.php');

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
$dbname = "moodle_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$check_id = $courseid;

// Prepare and run the query
$sql = "SELECT * FROM mdl_dmoj_organize WHERE course_id = ?";
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

// After the OK button in the HTML form below is clicked
/*
if ($_SERVER["REQUEST_METHOD"] == "POST"){
    $option_selected = $_POST["dmoj_options"];

    if ($option_selected == "yes"){
        // Create connection
        $conn = new mysqli($servername, $username, $password, $dbname);

        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("INSERT INTO mdl_dmoj_organize (course_id, organization_id) VALUES (?, ?)");
        // Placeholder DMOJ organization ID, in the finished code this should be taken from a DMOJ website API request
        $organization_id_testing = 555; 
        $stmt->bind_param("ii", $courseid, $organization_id_testing);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }
}
*/
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
    <?php endif; ?>
  <br>
  </body>
</html>
<?php
echo $OUTPUT->footer();
?>