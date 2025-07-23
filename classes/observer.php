<?php
defined('MOODLE_INTERNAL') || die();

$apiurl = 'http://172.29.64.156:4000/';
$username = 'admin';
$password = 'admin';

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
        debugging("New course created: $courseid by user $userid", DEBUG_DEVELOPER);
    }
    public static function course_updated(\core\event\base $event) {
        $data = $event->get_data();
    }
    public static function course_deleted(\core\event\base $event) {
        $data = $event->get_data();
    }
}
