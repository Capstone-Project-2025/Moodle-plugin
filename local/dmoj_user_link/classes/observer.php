<?php
require_once(__DIR__ . '/../link_dmoj.php');

class local_dmoj_user_link_observer {
    public static function link_moodle_admin_with_dmoj(\core\event\base $event) {
        $userid = $event->userid;
        $newvalue = get_config('local_dmoj_user_link', 'dmoj_domain');

        // trylinking admins
        try {
            $response = link_dmoj();
            $status = $response['status'];
            $body = $response['body'];

            // Add a notification so it appears after redirect
            if ($status == 207 || $status == 201) {
                \core\notification::add('DMOJ accounts linked successfully!', \core\output\notification::NOTIFY_SUCCESS);
            } else {
                \core\notification::add('DMOJ linking failed. Status: ' . $status . ' Body: ' . json_encode($body), \core\output\notification::NOTIFY_ERROR);
            }
        } catch (Exception $e) {
            \core\notification::add('DMOJ linking failed. Error: ' . $e->getMessage(), \core\output\notification::NOTIFY_ERROR);
        }
    }

    public static function create_org_and_course_user_with_dmoj(\core\event\base $event) {
        $courseid = $event->courseid;

        // try linking course users
        try {
            $response = link_dmoj($courseid);
            $status = $response['status'];
            $body = $response['body'];

            // Add a notification so it appears after redirect
            if ($status == 207 || $status == 201) {
                \core\notification::add('course accounts linked successfully!' . json_encode($body), \core\output\notification::NOTIFY_SUCCESS);

                // if users are created, try to add them to the org on DMOJ based on this side course permission
                $data = \local_dmoj_user_link\get_users_and_roles_in_course_with_dmoj_id($courseid);

                // getting members and admins
                $members = array_values(array_filter($data, fn($user) => $user->roleshortname === 'student'));
                $admins  = array_values(array_filter($data, fn($user) => in_array($user->roleshortname, ['editingteacher', 'manager', 'teacher'])));


                // Sending requests to create org with users from course
                $payload = [
                    "name" => "Course Organization - " . $courseid,
                    "slug" => "course-organization-" . $courseid,
                    "short_name" => "Course Org - " . $courseid,
                    "about" => "This organization is for the course: " . $courseid,
                    "members" => array_map(fn($user) => $user->dmojuserid, $members),
                    "admins" => array_map(fn($user) => $user->dmojuserid, $admins)
                ];
                debugging("Creating DMOJ org with payload: " . json_encode($payload));
                if($DB->get_records('myplugin_course_org_link', ['course_id' => $courseid])) {
                    // \core\notification::add('DMOJ org already exists for this course', \core\output\notification::NOTIFY_INFO);
                    return;
                } else {
                    // if the org that match course doesn't exist create it
                    $request = new \local_dmoj_user_link\api\CreateOrg($payload);
                    $response = $request->run();
                    $body = $response['body'];
                    $insertdata = new stdClass();
                    $insertdata->course_id = $courseid;
                    $insertdata->dmoj_org_id = $body['id'] ?? 0;
                    $DB->insert_record('myplugin_course_org_link', $insertdata);
                }
                debugging("DMOJ org creation response: " . json_encode($response['body']));
            } else {
                \core\notification::add('course linking failed. Status: ' . $status . ' Body: ' . json_encode($body), \core\output\notification::NOTIFY_ERROR);
            }
        } catch (Exception $e) {
            \core\notification::add('course linking failed. Error: ' . $e->getMessage(), \core\output\notification::NOTIFY_ERROR);
        }
    }

    public static function unlink_dmoj(\core\event\base $event) {
        $userid = $event->objectid;
        debugging("Unlinking DMOJ for user ID: {$userid}");
        unlink_dmoj($userid);
    }
}
