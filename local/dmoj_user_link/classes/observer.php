<?php

require_once(__DIR__ . '../../link_dmoj.php');
use core_h5p\core;

class local_dmoj_user_link_observer {
    public static function link_moodle_admin_with_dmoj(\core\event\base $event) {
        $userid = $event->userid;
        $newvalue = get_config('local_dmoj_user_link', 'dmoj_domain');

        // trylinking admins
        try {
            $response = \local_dmoj_user_link\link_dmoj();
            $status = $response['status'];
            $body = $response['body'];

            // Add a notification so it appears after redirect
            if ($status == 207 || $status == 201) {
                \core\notification::add('DMOJ accounts linked successfully', \core\output\notification::NOTIFY_SUCCESS);
            } else {
                \core\notification::add('DMOJ linking failed. Status: ' . $status . ' Body: ' . json_encode($body), \core\output\notification::NOTIFY_ERROR);
            }
        } catch (Exception $e) {
            \core\notification::add('DMOJ linking failed. Error: ' . $e->getMessage(), \core\output\notification::NOTIFY_ERROR);
        }
    }

    public static function course_org_syncing(\core\event\base $event) {
        $courseid = $event->courseid;
        \local_dmoj_user_link\link_orgs($courseid);
    }

    public static function unlink_dmoj(\core\event\base $event) {
        $userid = $event->objectid;
        debugging("Unlinking DMOJ for user ID: {$userid}");
        \local_dmoj_user_link\unlink_dmoj($userid);
    }
}
