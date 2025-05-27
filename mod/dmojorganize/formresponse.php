<?php
require_once('../../config.php');

$courseid = required_param('id', PARAM_INT); // 'id' must be passed in the URL

$course = get_course($courseid);

// Displays the Course/Settings/Participants/.../"More" bar
require_login($course);

// Carries the course full name from previous page, using the course_id collected
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

$option_selected;

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    $option_selected = $_POST["dmoj_options"];

    if ($option_selected == "yes"){
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

        $stmt = $conn->prepare("INSERT INTO mdl_dmoj_organize (course_id, organization_id) VALUES (?, ?)");
        // Placeholder DMOJ organization ID, in the finished code this should be taken from a DMOJ website API request
        $organization_id_testing = 555; 
        $stmt->bind_param("ii", $courseid, $organization_id_testing);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html>
    <form action="view.php?id=<?php echo $courseid; ?>" method="POST">
        <?php if ($option_selected === "yes"): ?>
            <label>DMOJ organization linked successfully.</label>
        <?php else: ?>
            <label>Cancelled DMOJ organization linking.</label>
        <?php endif; ?>
        <br>
        <button type="submit">Back to previous page</button>
    </form>
</html>
<?php
echo $OUTPUT->footer();
?>