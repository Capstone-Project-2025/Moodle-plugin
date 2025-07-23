<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/apirequest.php');

$courseid = required_param('id', PARAM_INT); // 'id' must be passed in the URL

$course = get_course($courseid);

// Displays the Course/Settings/Participants/.../"More" bar
require_login($course);

// Carries the course full name from previous page, using the course_id collected
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

/*
class FetchDMOJid extends APIRequest {
    public function __construct($params = [], $moodle_ids = []) {
        $method = "POST";
        $url = DOMAIN . "/api/v2/moodle-to-dmoj/";
        $payload = [
            "provider" => "moodle",
            "uid" => $moodle_ids
        ];
        parent::__construct($url, $method, [], $params, $payload);
    }
}
*/

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
/**
 * @return int
 */
function find_dmoj_organization_id(int $moodle_course_id){
    global $CFG;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = $CFG->dbname;

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT * FROM mdl_dmojorganize WHERE course_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $moodle_course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $dmoj_organization_id_found = 0;
    if ($row = $result->fetch_assoc()) {
        $dmoj_organization_id_found = $row['organization_id'];
    }
    $stmt->close();
    $conn->close();

    return $dmoj_organization_id_found;
}
/**
 * @return int[]
 */
function get_moodle_course_participants_ids(int $courseid) {
    $context = context_course::instance($courseid);
    $users = get_enrolled_users($context);

    /** @var int[] $moodle_ids */
    $moodle_ids = [];

    // Loop through users and print user ID and their roles
    foreach ($users as $user) {
        array_push($moodle_ids, (int) $user->id);
    }
    return $moodle_ids;
}
function get_moodle_course_teachers_managers(int $courseid) {
    $context = context_course::instance($courseid);
    $users = get_enrolled_users($context);

    /** @var int[] $admin_ids */
    $admin_ids = [];

    foreach ($users as $user){
        $roles = get_user_roles($context, $user->id);

        $admin_role_found = false;
        foreach ($roles as $role) {
            //echo "Role shortname: ". $role->shortname . " <br>";
            if (!$admin_role_found && (in_array($role->shortname, ['editingteacher', 'manager'], true))){
                $admin_role_found = true;
                array_push($admin_ids, (int) $user->id);
            }
        }
    }
    return $admin_ids;
}
/**
 * @return array Returns an associative array with the Moodle user IDs as keys, and each value is a subset of username, email, firstname, lastname.
 */
function get_moodle_course_participants_full_info(int $courseid) {
    $context = context_course::instance($courseid);
    $users = get_enrolled_users($context);
    $result = [];
    foreach ($users as $user) {
        $result[$user->id] = [
            "username" => $user->username,
            "email" => $user->email,
            "first_name" => $user->firstname,
            "last_name" => $user->lastname
        ];
    }
    return $result;
}

function create_organization($apiurl, $token, $courseid){
    /*
    Steps:
    - Create a new organization with the "Create a new organization" POST request
    - Insert a (course_id, organization_id) pair to local database table mdl_dmojorganize
    - Get Moodle user IDs of all participants of this course
    - Call the "fetch dmoj uid with moodle uid" POST request to get the list of DMOJ user_ids and profile_ids of the Moodle users already linked to DMOJ

    */
    // API request to create a new organization
    $course = get_course($courseid);

    /**
     * @var array{
     *     name: string,
     *     slug: string,
     *     short_name: string,
     *     about: string,
     *     is_open: bool,
     *     access_code: string
     * }
     */
    $data = [
        "name" => $course->fullname,
        "slug" => $course->shortname,
        "short_name" => $course->shortname,
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
    $dbname = "moodle40";
    
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("INSERT INTO mdl_dmojorganize (course_id, organization_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $courseid, $latest_organization_ID);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    $moodle_members_ids = get_moodle_course_participants_ids($courseid);
    $moodle_admin_ids = get_moodle_course_teachers_managers($courseid);
    $moodle_course_participants_full_info = get_moodle_course_participants_full_info($courseid);
    //echo "Moodle member user IDs: <br> <pre>" . json_encode($moodle_members_ids, JSON_PRETTY_PRINT) . "</pre>";
    //echo "Moodle admin IDs: <br> <pre>" . json_encode($moodle_admin_ids, JSON_PRETTY_PRINT) . "</pre>";

    $DMOJ_members_ids = [];
    $DMOJ_IDs_class = new FetchDMOJid([], $moodle_members_ids);
    $response = $DMOJ_IDs_class->run();
    if ($response["status"] == 200){
        $response['body'] = json_decode($response['body'], true);
        //echo "DMOJ user IDs found: <br> <pre>" . json_encode($response["body"], JSON_PRETTY_PRINT) . "</pre>";

        $ids_found = $response['body'];

        foreach ($ids_found as $key => $value) {
            //echo "Key: $key, Value: $value" . PHP_EOL . "<br>";
            if ($value == "Not found"){
                /*
                echo "Member not created, will be created: <br>";
                echo "- " . $moodle_course_participants_full_info[$key]["username"] . "<br>";
                echo "- " . $moodle_course_participants_full_info[$key]["email"] . "<br>";
                echo "- " . $moodle_course_participants_full_info[$key]["first_name"] . "<br>";
                echo "- " . $moodle_course_participants_full_info[$key]["last_name"] . "<br>";
                */
                // If not, force create a DMOJ admin account and link the Moodle member to this account
                $payload = [
                    $key => [
                        "username" => $moodle_course_participants_full_info[$key]["username"],
                        "email" => $moodle_course_participants_full_info[$key]["email"],
                        "first_name" => $moodle_course_participants_full_info[$key]["first_name"],
                        "last_name" => $moodle_course_participants_full_info[$key]["last_name"]
                    ]
                ];
                $force_create_DMOJ_user = new ForceCreateDMOJUser($payload);
                $response = $force_create_DMOJ_user->run();
                $response['body'] = json_decode($response['body'], true);
                //echo "Force create response: <br> <pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
            }
        }

        $response = $DMOJ_IDs_class->run();
        $response['body'] = json_decode($response['body'], true);
        //echo "DMOJ user IDs found: <br> <pre>" . json_encode($response["body"], JSON_PRETTY_PRINT) . "</pre>";
        $ids_found = $response['body'];

        foreach ($ids_found as $key => $value) {
            //echo "Key: $key, Value: $value" . PHP_EOL . "<br>";
            array_push($DMOJ_members_ids, $value["profile_id"]);
        }
        /*
        // Add in any members of the Moodle course already linked to DMOJ
        foreach ($ids_found as $key => $value) {
            echo "Key: $key, Value: $value" . PHP_EOL . "<br>";
            if ($value != "Not found"){
                array_push($DMOJ_members_ids, $value["profile_id"]);
            } else {
                echo "Member not created, will be created: <br>";
                echo "- " . $moodle_course_participants_full_info[$key]["username"] . "<br>";
                echo "- " . $moodle_course_participants_full_info[$key]["email"] . "<br>";
                echo "- " . $moodle_course_participants_full_info[$key]["first_name"] . "<br>";
                echo "- " . $moodle_course_participants_full_info[$key]["last_name"] . "<br>";
                // If not, force create a DMOJ admin account and link the Moodle member to this account
                $payload = [
                    $key => [
                        "username" => $moodle_course_participants_full_info[$key]["username"],
                        "email" => $moodle_course_participants_full_info[$key]["email"],
                        "first_name" => $moodle_course_participants_full_info[$key]["first_name"],
                        "last_name" => $moodle_course_participants_full_info[$key]["last_name"]
                    ]
                ];
                $force_create_DMOJ_user = new ForceCreateDMOJUser($payload);
                $response = $force_create_DMOJ_user->run();
                $response['body'] = json_decode($response['body'], true);
                echo "Force create response: <br> <pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
                $DMOJ_id_created = $response["body"]["success"][$key]["dmoj_uid"];//["success"][$key]["dmoj_uid"];
                array_push($DMOJ_members_ids, $DMOJ_id_created);
            }
        }
        */
    }

    $DMOJ_admin_ids = [];
    $DMOJ_admin_IDs_class = new FetchDMOJid([], $moodle_admin_ids);
    $response = $DMOJ_admin_IDs_class->run();
    if ($response["status"] == 200){
        $response['body'] = json_decode($response['body'], true);
        $ids_found = $response['body'];

        foreach ($ids_found as $key => $value) {
            array_push($DMOJ_admin_ids, $value["profile_id"]);
        }
    }

    //echo "Current Moodle course ID: $courseid <br>";
    $dmoj_organization_id_found = find_dmoj_organization_id($courseid);
    //echo "Linked DMOJ organization ID: " . $dmoj_organization_id_found;

    $DMOJ_organization_collected = new GetOrgDetail($dmoj_organization_id_found);
    $response = $DMOJ_organization_collected->run();
    $response['body'] = json_decode($response['body'], true);
    if ($response["status"] == 200) {
        //echo "<br> DMOJ organization info: <br> <pre>" . json_encode($response["body"]["data"]["object"], JSON_PRETTY_PRINT) . "</pre>";
    }
    $name = $response["body"]["data"]["object"]["name"];
    $slug = $response["body"]["data"]["object"]["slug"];
    $short_name = $response["body"]["data"]["object"]["short_name"];
    $about = "A sample org for testing."; // This can't be found in Get Org Detail request
    $members = $response["body"]["data"]["object"]["members"];
    $access_code = $response["body"]["data"]["object"]["access_code"];

    $payload_for_editing_org_members = [
        "name" => $name,
        "slug" => $slug,
        "short_name" => $short_name,
        "about" => $about,
        "members" => $DMOJ_members_ids,
        "admins" => $DMOJ_admin_ids,
        "access_code" => $access_code
    ];

    //echo "DMOJ_members_ids = <br> <pre>" . json_encode($DMOJ_members_ids, JSON_PRETTY_PRINT) . "</pre>";
    //echo "DMOJ_admin_ids = <br> <pre>" . json_encode($DMOJ_admin_ids, JSON_PRETTY_PRINT) . "</pre>";
    $DMOJ_members_edit = new ChangeOrgInfo($dmoj_organization_id_found, $payload_for_editing_org_members);
    $response = $DMOJ_members_edit->run();
    $response['body'] = json_decode($response['body'], true);
    //echo "Change Org Info response: <pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
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