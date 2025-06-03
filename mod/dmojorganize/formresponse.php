<?php
require_once('../../config.php');

$courseid = required_param('id', PARAM_INT); // 'id' must be passed in the URL

$course = get_course($courseid);

// Displays the Course/Settings/Participants/.../"More" bar
require_login($course);

// Carries the course full name from previous page, using the course_id collected
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Configuration
$apiurl = 'http://139.59.105.152';
$username = 'admin';
$password = 'admin';

function get_latest_organization_ID($apiurl){
    // latest_organization_ID required for create_organization
    $url = "$apiurl/api/v2/organizations";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    if (isset($data)){

        $objects = $data['data']['objects'];
        $maxId = 0;
        foreach ($objects as $obj) {
            if ($obj['id'] > $maxId) {
                $maxId = $obj['id'];
            }
        }
        return $maxId;
    } else {
        return -1;
    }
}

function get_token($apiurl, $username, $password) {
    // Token required for create_organization
    $curl = curl_init("$apiurl/api/token/");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'username' => $username,
        'password' => $password
    ]));
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    return $data['access'] ?? null;
}

function create_organization($apiurl, $token, $courseid){
    // API request to create a new organization
    $data = [
        "name" => get_course($courseid)->fullname,
        "slug" => "sample",
        "short_name" => "SAM",
        "about" => "A sample org for testing.",
        "is_open" => false,
        "access_code" => "ABC1234"
    ];
    $jsonData = json_encode($data);

    $curl = curl_init("$apiurl/api/v2/organizations");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Return response as string
    curl_setopt($curl, CURLOPT_POST, true);           // Use POST method
    curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData); // Attach the JSON data
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $token"
    ]);
    curl_exec($curl);
    if (curl_errno($curl)) {
        echo "cURL Error: " . curl_error($curl);
        curl_close($curl);
        exit;
    }
    curl_close($curl);

    // Moodle database modification
    $latest_organization_ID = get_latest_organization_ID($apiurl);
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "moodle_db";
    
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("INSERT INTO mdl_dmoj_organize (course_id, organization_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $courseid, $latest_organization_ID);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

$token = get_token($apiurl, $username, $password);

$option_selected;

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    $option_selected = $_POST["dmoj_options"];

    if ($option_selected == "yes"){
        create_organization($apiurl, $token, $courseid);
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