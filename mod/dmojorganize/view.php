<?php

use core_customfield\data;

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

// GET list of organizations from DMOJ
$url = "http://139.59.105.152/api/v2/organizations";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);

if (isset($data)){
    echo "List of our DMOJ organizations collected by GET request:" . "<br>";
    echo '<pre>';
    echo json_encode($data, JSON_PRETTY_PRINT);
    echo '</pre>';

    $objects = $data['data']['objects'];
    $maxId = 0;
    foreach ($objects as $obj) {
        if ($obj['id'] > $maxId) {
            $maxId = $obj['id'];
        }
    }
    echo "<br>" . "ID just created: " . $maxId . "<br>";
} else {
    echo "Error: " . $data['error'];
}
/*
Example output:
{
    "api_version": "2.0",
    "method": "get",
    "fetched": "2025-05-31T13:44:52.589440+00:00",
    "data": {
        "current_object_count": 6,
        "objects_per_page": 1000,
        "page_index": 1,
        "has_more": false,
        "objects": [
            {
                "id": 1,
                "slug": "dmoj",
                "short_name": "DMOJ",
                "is_open": true,
                "member_count": 4
            },
            {
                "id": 2,
                "slug": "private-org",
                "short_name": "Private org",
                "is_open": false,
                "member_count": 1
            },
            {
                "id": 3,
                "slug": "teacherk",
                "short_name": "Private orgk",
                "is_open": true,
                "member_count": 0
            },
            {
                "id": 8,
                "slug": "sample",
                "short_name": "SAM",
                "is_open": true,
                "member_count": 0
            },
            {
                "id": 9,
                "slug": "sample",
                "short_name": "SAM",
                "is_open": true,
                "member_count": 0
            },
            {
                "id": 11,
                "slug": "sample",
                "short_name": "SAM",
                "is_open": true,
                "member_count": 0
            }
        ],
        "total_objects": 6,
        "total_pages": 1
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