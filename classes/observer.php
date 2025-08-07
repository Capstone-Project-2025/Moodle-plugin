<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');
require_once($CFG->dirroot . '/apirequest.php');

$apiurl = DOMAIN;
$username = 'admin';
$password = 'admin';

define('SERVERNAME', "localhost");
define('USERNAME', "root");
define('PASSWORD', "");
define('DBNAME', "moodle40");

class mod_dmojorganize_observer {
    public static function user_enrolment_created(\core\event\base $event) {
        $data = $event->get_data();

        $userid = $data['relateduserid']; // the user who was added
        $courseid = $data['courseid'];

        // Call your custom function here
        debugging("User added to course", DEBUG_DEVELOPER);
        debugging("User $userid added to course $courseid", DEBUG_DEVELOPER);
        //echo("User $userid added to course $courseid");
    }

    private static function do_something_when_user_added(int $userid, int $courseid): void {
        // Your logic here
        debugging("User $userid added to course $courseid", DEBUG_DEVELOPER);
        echo("User $userid added to course $courseid");
        error_log('A new user was enrolled!');
    }
    public static function course_created(\core\event\base $event) {
        $data = $event->get_data();

        $courseid = $data['courseid'];
        $userid = $data['userid'];

        $course = get_course($courseid);

        $name = $course->fullname;
        $slug = $course->shortname;
        $short_name = $course->shortname;
        $about = "A sample org for testing.";
        $is_open = false;
        $access_code = "ABC1234";

        $payload = [
            "name" => $name,
            "slug" => $slug,
            "short_name" => $short_name,
            "about" => $about,
            "is_open" => $is_open,
            "access_code" => $access_code
        ];

        $DMOJorg_creation_class = new CreateDMOJOrganization($payload);
        $response = $DMOJorg_creation_class->run();
        $status_code = $response["status"];
        debugging("New course created on Moodle and new organization created on DMOJ");
        debugging("DMOJ organization name: $name");
        debugging("Status code of organization creation: $status_code");
        echo "<pre>" . json_encode($response["body"], JSON_PRETTY_PRINT) . "</pre>";

        global $apiurl;

        $DMOJ_org_info = new GetAllDMOJOrganizations();
        $DMOJ_org_info_response = $DMOJ_org_info->run();
        $DMOJ_org_info_response['body'] = json_decode($DMOJ_org_info_response['body'], true);
        $objects = $DMOJ_org_info_response["body"]["data"]["objects"];

        $maxId = 0;
        foreach ($objects as $obj) {
            if ($obj['id'] > $maxId) {
                $maxId = $obj['id'];
            }
        }
        debugging("Latest organization ID so far: $maxId");
        
        $new_DMOJ_id = $maxId + 1;

        $conn = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("INSERT INTO mdl_dmojorganize (course_id, organization_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $courseid, $new_DMOJ_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }
    public static function course_updated(\core\event\base $event) {
        $data = $event->get_data();

        $courseid = $data['courseid'];
        $userid = $data['userid'];

        $course = get_course($courseid);

        $name = $course->fullname;
        $slug = $course->shortname;
        $short_name = $course->shortname;
        $about = "A sample org for testing.";
        $is_open = false;
        $access_code = "ABC1234";

        $payload = [
            "name" => $name,
            "slug" => $slug,
            "short_name" => $short_name,
            "about" => $about,
            "is_open" => $is_open,
            "access_code" => $access_code
        ];

        debugging("Course settings modified: $course->fullname");

        $courseid = $data['courseid'];
        $linked_dmoj_organization_id = find_dmoj_organization_id($courseid);
        $DMOJorg_changed_class = new ChangeOrgInfo($linked_dmoj_organization_id, $payload);
        $response = $DMOJorg_changed_class->run();
    }
    public static function course_deleted(\core\event\base $event) {
        $data = $event->get_data();

        $courseid = $data['courseid'];
        $course = get_course($courseid);
        debugging("Course deleted: $course->fullname");

        $linked_dmoj_organization_id = find_dmoj_organization_id($courseid);

        $DMOJorg_deletion_class = new DeleteDMOJOrg($linked_dmoj_organization_id);
        $response = $DMOJorg_deletion_class->run();
    }
}
