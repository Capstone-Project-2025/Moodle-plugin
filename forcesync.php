<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/apirequest.php');

$courseid = required_param('id', PARAM_INT);
$DMOJ_organization_ID = required_param('organization_id', PARAM_INT);

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

function get_token($apiurl, $username, $password) {
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
function modify_DMOJ_organization($apiurl, $token, $courseid, $DMOJ_organization_ID){
    /*
    Need the following details to modify:
    $payload_for_editing_org_members = [
        "name" => $name,
        "slug" => $slug,
        "short_name" => $short_name,
        "about" => $about,
        "members" => $DMOJ_members_ids,
        "admins" => $DMOJ_admin_ids,
        "access_code" => $access_code
    ];
    */
    $course = get_course($courseid);

    $name = $course->fullname;
    $slug = $course->shortname;
    $short_name = $course->shortname;

    // Get members and admins
    $moodle_members_ids = get_moodle_course_participants_ids($courseid);
    $moodle_admin_ids = get_moodle_course_teachers_managers($courseid);
    $moodle_course_participants_full_info = get_moodle_course_participants_full_info($courseid);

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
            array_push($DMOJ_members_ids, $value["profile_id"]);
        }
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

    $payload_for_editing_org_members = [
        "name" => $name,
        "slug" => $slug,
        "short_name" => $short_name,
        "members" => $DMOJ_members_ids,
        "admins" => $DMOJ_admin_ids
    ];
    $DMOJ_members_edit = new ChangeOrgInfo($DMOJ_organization_ID, $payload_for_editing_org_members);
    $response = $DMOJ_members_edit->run();
}

$token = get_token($apiurl, $username, $password);

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    modify_DMOJ_organization($apiurl, $token, $courseid, $DMOJ_organization_ID);
}

?>
<!DOCTYPE html>
<html>
    <form action="view.php?id=<?php echo $courseid; ?>" method="POST">
        <label>DMOJ organization info (organization name, admins, members) updated to match to Moodle course.</label>
        <br>
        <button type="submit">Back to previous page</button>
    </form>
</html>
<?php
echo $OUTPUT->footer();
?>