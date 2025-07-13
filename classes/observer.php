<?php
namespace mod_dmojorganize;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function enrol_instance_created(\core\event\enrol_instance_created $event) {
        $data = $event->get_data();

        $userid = $data['relateduserid']; // the user who was added
        $courseid = $data['courseid'];

        // Call your custom function here
        self::do_something_when_user_added($userid, $courseid);
    }

    private static function do_something_when_user_added(int $userid, int $courseid): void {
        // Your logic here
        debugging("User $userid added to course $courseid", DEBUG_DEVELOPER);
    }
}
