<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_progcontest;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/progcontest/lib.php');

/**
 * Unit tests for the calendar event modification callbacks used
 * for dragging and dropping progcontest calendar events in the calendar
 * UI.
 *
 * @package    mod_progcontest
 * @category   test
 * @copyright  2017 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class calendar_event_modified_test extends \advanced_testcase {

    /**
     * Create an instance of the progcontest activity.
     *
     * @param array $properties Properties to set on the activity
     * @return stdClass Programmingcontest activity instance
     */
    protected function create_progcontest_instance(array $properties) {
        global $DB;

        $generator = $this->getDataGenerator();

        if (empty($properties['course'])) {
            $course = $generator->create_course();
            $courseid = $course->id;
        } else {
            $courseid = $properties['course'];
        }

        $progcontestgenerator = $generator->get_plugin_generator('mod_progcontest');
        $progcontest = $progcontestgenerator->create_instance(array_merge(['course' => $courseid], $properties));

        if (isset($properties['timemodified'])) {
            // The generator overrides the timemodified value to set it as
            // the current time even if a value is provided so we need to
            // make sure it's set back to the requested value.
            $progcontest->timemodified = $properties['timemodified'];
            $DB->update_record('progcontest', $progcontest);
        }

        return $progcontest;
    }

    /**
     * Create a calendar event for a progcontest activity instance.
     *
     * @param \stdClass $progcontest The activity instance
     * @param array $eventproperties Properties to set on the calendar event
     * @return calendar_event
     */
    protected function create_progcontest_calendar_event(\stdClass $progcontest, array $eventproperties) {
        $defaultproperties = [
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $progcontest->course,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'progcontest',
            'instance' => $progcontest->id,
            'eventtype' => progcontest_EVENT_TYPE_OPEN,
            'timestart' => time(),
            'timeduration' => 86400,
            'visible' => 1
        ];

        return new \calendar_event(array_merge($defaultproperties, $eventproperties));
    }

    /**
     * An unkown event type should not change the progcontest instance.
     */
    public function test_mod_progcontest_core_calendar_event_timestart_updated_unknown_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $progcontest = $this->create_progcontest_instance(['timeopen' => $timeopen, 'timeclose' => $timeclose]);
        $event = $this->create_progcontest_calendar_event($progcontest, [
            'eventtype' => progcontest_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1
        ]);

        mod_progcontest_core_calendar_event_timestart_updated($event, $progcontest);

        $progcontest = $DB->get_record('progcontest', ['id' => $progcontest->id]);
        $this->assertEquals($timeopen, $progcontest->timeopen);
        $this->assertEquals($timeclose, $progcontest->timeclose);
    }

    /**
     * A progcontest_EVENT_TYPE_OPEN event should update the timeopen property of
     * the progcontest activity.
     */
    public function test_mod_progcontest_core_calendar_event_timestart_updated_open_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeopen = $timeopen - DAYSECS;
        $progcontest = $this->create_progcontest_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timemodified' => $timemodified
        ]);
        $event = $this->create_progcontest_calendar_event($progcontest, [
            'eventtype' => progcontest_EVENT_TYPE_OPEN,
            'timestart' => $newtimeopen
        ]);

        mod_progcontest_core_calendar_event_timestart_updated($event, $progcontest);

        $progcontest = $DB->get_record('progcontest', ['id' => $progcontest->id]);
        // Ensure the timeopen property matches the event timestart.
        $this->assertEquals($newtimeopen, $progcontest->timeopen);
        // Ensure the timeclose isn't changed.
        $this->assertEquals($timeclose, $progcontest->timeclose);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $progcontest->timemodified);
    }

    /**
     * A progcontest_EVENT_TYPE_CLOSE event should update the timeclose property of
     * the progcontest activity.
     */
    public function test_mod_progcontest_core_calendar_event_timestart_updated_close_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeclose = $timeclose + DAYSECS;
        $progcontest = $this->create_progcontest_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timemodified' => $timemodified
        ]);
        $event = $this->create_progcontest_calendar_event($progcontest, [
            'eventtype' => progcontest_EVENT_TYPE_CLOSE,
            'timestart' => $newtimeclose
        ]);

        mod_progcontest_core_calendar_event_timestart_updated($event, $progcontest);

        $progcontest = $DB->get_record('progcontest', ['id' => $progcontest->id]);
        // Ensure the timeclose property matches the event timestart.
        $this->assertEquals($newtimeclose, $progcontest->timeclose);
        // Ensure the timeopen isn't changed.
        $this->assertEquals($timeopen, $progcontest->timeopen);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $progcontest->timemodified);
    }

    /**
     * A progcontest_EVENT_TYPE_OPEN event should not update the timeopen property of
     * the progcontest activity if it's an override.
     */
    public function test_mod_progcontest_core_calendar_event_timestart_updated_open_event_override() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeopen = $timeopen - DAYSECS;
        $progcontest = $this->create_progcontest_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timemodified' => $timemodified
        ]);
        $event = $this->create_progcontest_calendar_event($progcontest, [
            'userid' => $user->id,
            'eventtype' => progcontest_EVENT_TYPE_OPEN,
            'timestart' => $newtimeopen
        ]);
        $record = (object) [
            'progcontest' => $progcontest->id,
            'userid' => $user->id
        ];

        $DB->insert_record('progcontest_overrides', $record);

        mod_progcontest_core_calendar_event_timestart_updated($event, $progcontest);

        $progcontest = $DB->get_record('progcontest', ['id' => $progcontest->id]);
        // Ensure the timeopen property doesn't change.
        $this->assertEquals($timeopen, $progcontest->timeopen);
        // Ensure the timeclose isn't changed.
        $this->assertEquals($timeclose, $progcontest->timeclose);
        // Ensure the timemodified property has not been changed.
        $this->assertEquals($timemodified, $progcontest->timemodified);
    }

    /**
     * If a student somehow finds a way to update the progcontest calendar event
     * then the callback should not update the progcontest activity otherwise that
     * would be a security issue.
     */
    public function test_student_role_cant_update_progcontest_activity() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $roleid = $generator->create_role();
        $now = time();
        $timeopen = (new \DateTime())->setTimestamp($now);
        $newtimeopen = (new \DateTime())->setTimestamp($now)->modify('+1 day');
        $progcontest = $this->create_progcontest_instance([
            'course' => $course->id,
            'timeopen' => $timeopen->getTimestamp()
        ]);

        $generator->enrol_user($user->id, $course->id, 'student');
        $generator->role_assign($roleid, $user->id, $context->id);

        $event = $this->create_progcontest_calendar_event($progcontest, [
            'eventtype' => progcontest_EVENT_TYPE_OPEN,
            'timestart' => $timeopen->getTimestamp()
        ]);

        assign_capability('moodle/course:manageactivities', CAP_PROHIBIT, $roleid, $context, true);

        $this->setUser($user);

        mod_progcontest_core_calendar_event_timestart_updated($event, $progcontest);

        $newprogcontest = $DB->get_record('progcontest', ['id' => $progcontest->id]);
        // The time open shouldn't have changed even though we updated the calendar
        // event.
        $this->assertEquals($timeopen->getTimestamp(), $newprogcontest->timeopen);
    }

    /**
     * A teacher with the capability to modify a progcontest module should be
     * able to update the progcontest activity dates by changing the calendar
     * event.
     */
    public function test_teacher_role_can_update_progcontest_activity() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $roleid = $generator->create_role();
        $now = time();
        $timeopen = (new \DateTime())->setTimestamp($now);
        $newtimeopen = (new \DateTime())->setTimestamp($now)->modify('+1 day');
        $progcontest = $this->create_progcontest_instance([
            'course' => $course->id,
            'timeopen' => $timeopen->getTimestamp()
        ]);

        $generator->enrol_user($user->id, $course->id, 'teacher');
        $generator->role_assign($roleid, $user->id, $context->id);

        $event = $this->create_progcontest_calendar_event($progcontest, [
            'eventtype' => progcontest_EVENT_TYPE_OPEN,
            'timestart' => $newtimeopen->getTimestamp()
        ]);

        assign_capability('moodle/course:manageactivities', CAP_ALLOW, $roleid, $context, true);

        $this->setUser($user);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        mod_progcontest_core_calendar_event_timestart_updated($event, $progcontest);

        $triggeredevents = $sink->get_events();
        $moduleupdatedevents = array_filter($triggeredevents, function($e) {
            return is_a($e, 'core\event\course_module_updated');
        });

        $newprogcontest = $DB->get_record('progcontest', ['id' => $progcontest->id]);
        // The should be updated along with the event because the user has sufficient
        // capabilities.
        $this->assertEquals($newtimeopen->getTimestamp(), $newprogcontest->timeopen);
        // Confirm that a module updated event is fired when the module
        // is changed.
        $this->assertNotEmpty($moduleupdatedevents);
    }


    /**
     * An unkown event type should not have any limits
     */
    public function test_mod_progcontest_core_calendar_get_valid_event_timestart_range_unknown_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $progcontest = $this->create_progcontest_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose
        ]);
        $event = $this->create_progcontest_calendar_event($progcontest, [
            'eventtype' => progcontest_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1
        ]);

        list ($min, $max) = mod_progcontest_core_calendar_get_valid_event_timestart_range($event, $progcontest);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * The open event should be limited by the progcontest's timeclose property, if it's set.
     */
    public function test_mod_progcontest_core_calendar_get_valid_event_timestart_range_open_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $progcontest = $this->create_progcontest_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose
        ]);
        $event = $this->create_progcontest_calendar_event($progcontest, [
            'eventtype' => progcontest_EVENT_TYPE_OPEN,
            'timestart' => 1
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_progcontest_core_calendar_get_valid_event_timestart_range($event, $progcontest);

        $this->assertNull($min);
        $this->assertEquals($timeclose, $max[0]);

        // No timeclose value should result in no upper limit.
        $progcontest->timeclose = 0;
        list ($min, $max) = mod_progcontest_core_calendar_get_valid_event_timestart_range($event, $progcontest);

        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * An override event should not have any limits.
     */
    public function test_mod_progcontest_core_calendar_get_valid_event_timestart_range_override_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $progcontest = $this->create_progcontest_instance([
            'course' => $course->id,
            'timeopen' => $timeopen,
            'timeclose' => $timeclose
        ]);
        $event = $this->create_progcontest_calendar_event($progcontest, [
            'userid' => $user->id,
            'eventtype' => progcontest_EVENT_TYPE_OPEN,
            'timestart' => 1
        ]);
        $record = (object) [
            'progcontest' => $progcontest->id,
            'userid' => $user->id
        ];

        $DB->insert_record('progcontest_overrides', $record);

        list ($min, $max) = mod_progcontest_core_calendar_get_valid_event_timestart_range($event, $progcontest);

        $this->assertFalse($min);
        $this->assertFalse($max);
    }

    /**
     * The close event should be limited by the progcontest's timeopen property, if it's set.
     */
    public function test_mod_progcontest_core_calendar_get_valid_event_timestart_range_close_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $progcontest = $this->create_progcontest_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose
        ]);
        $event = $this->create_progcontest_calendar_event($progcontest, [
            'eventtype' => progcontest_EVENT_TYPE_CLOSE,
            'timestart' => 1,
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_progcontest_core_calendar_get_valid_event_timestart_range($event, $progcontest);

        $this->assertEquals($timeopen, $min[0]);
        $this->assertNull($max);

        // No timeclose value should result in no upper limit.
        $progcontest->timeopen = 0;
        list ($min, $max) = mod_progcontest_core_calendar_get_valid_event_timestart_range($event, $progcontest);

        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * When the close date event is changed and it results in the time close value of
     * the progcontest being updated then the open progcontest attempts should also be updated.
     */
    public function test_core_calendar_event_timestart_updated_update_progcontest_attempt() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $roleid = $generator->create_role();
        $now = time();
        $timelimit = 600;
        $timeopen = (new \DateTime())->setTimestamp($now);
        $timeclose = (new \DateTime())->setTimestamp($now)->modify('+1 day');
        // The new close time being earlier than the time open + time limit should
        // result in an update to the progcontest attempts.
        $newtimeclose = $timeopen->getTimestamp() + $timelimit - 10;
        $progcontest = $this->create_progcontest_instance([
            'course' => $course->id,
            'timeopen' => $timeopen->getTimestamp(),
            'timeclose' => $timeclose->getTimestamp(),
            'timelimit' => $timelimit
        ]);

        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->role_assign($roleid, $teacher->id, $context->id);

        $event = $this->create_progcontest_calendar_event($progcontest, [
            'eventtype' => progcontest_EVENT_TYPE_CLOSE,
            'timestart' => $newtimeclose
        ]);

        assign_capability('moodle/course:manageactivities', CAP_ALLOW, $roleid, $context, true);

        $attemptid = $DB->insert_record(
            'progcontest_attempts',
            [
                'progcontest' => $progcontest->id,
                'userid' => $student->id,
                'state' => 'inprogress',
                'timestart' => $timeopen->getTimestamp(),
                'timecheckstate' => 0,
                'layout' => '',
                'uniqueid' => 1
            ]
        );

        $this->setUser($teacher);

        mod_progcontest_core_calendar_event_timestart_updated($event, $progcontest);

        $progcontest = $DB->get_record('progcontest', ['id' => $progcontest->id]);
        $attempt = $DB->get_record('progcontest_attempts', ['id' => $attemptid]);
        // When the close date is changed so that it's earlier than the time open
        // plus the time limit of the progcontest then the attempt's timecheckstate should
        // be updated to the new time close date of the progcontest.
        $this->assertEquals($newtimeclose, $attempt->timecheckstate);
        $this->assertEquals($newtimeclose, $progcontest->timeclose);
    }
}
