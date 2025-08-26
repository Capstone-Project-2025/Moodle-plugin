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

/**
 * Programmingcontest events tests.
 *
 * @package    mod_progcontest
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_progcontest\event;

use progcontest;
use progcontest_attempt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/progcontest/attemptlib.php');

/**
 * Unit tests for progcontest events.
 *
 * @package    mod_progcontest
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_test extends \advanced_testcase {

    /**
     * Setup a progcontest.
     *
     * @return progcontest the generated progcontest.
     */
    protected function prepare_progcontest() {

        $this->resetAfterTest(true);

        // Create a course
        $course = $this->getDataGenerator()->create_course();

        // Make a progcontest.
        $progcontestgenerator = $this->getDataGenerator()->get_plugin_generator('mod_progcontest');

        $progcontest = $progcontestgenerator->create_instance(array('course' => $course->id, 'questionsperpage' => 0,
                'grade' => 100.0, 'sumgrades' => 2));

        $cm = get_coursemodule_from_instance('progcontest', $progcontest->id, $course->id);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));

        // Add them to the progcontest.
        progcontest_add_progcontest_question($saq->id, $progcontest);
        progcontest_add_progcontest_question($numq->id, $progcontest);

        // Make a user to do the progcontest.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        return progcontest::create($progcontest->id, $user1->id);
    }

    /**
     * Setup a progcontest attempt at the progcontest created by {@link prepare_progcontest()}.
     *
     * @param progcontest $progcontestobj the generated progcontest.
     * @param bool $ispreview Make the attempt a preview attempt when true.
     * @return array with three elements, array($progcontestobj, $quba, $attempt)
     */
    protected function prepare_progcontest_attempt($progcontestobj, $ispreview = false) {
        // Start the attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj->get_context());
        $quba->set_preferred_behaviour($progcontestobj->get_progcontest()->preferredbehaviour);

        $timenow = time();
        $attempt = progcontest_create_attempt($progcontestobj, 1, false, $timenow, $ispreview);
        progcontest_start_new_attempt($progcontestobj, $quba, $attempt, 1, $timenow);
        progcontest_attempt_save_started($progcontestobj, $quba, $attempt);

        return array($progcontestobj, $quba, $attempt);
    }

    /**
     * Setup some convenience test data with a single attempt.
     *
     * @param bool $ispreview Make the attempt a preview attempt when true.
     * @return array with three elements, array($progcontestobj, $quba, $attempt)
     */
    protected function prepare_progcontest_data($ispreview = false) {
        $progcontestobj = $this->prepare_progcontest();
        return $this->prepare_progcontest_attempt($progcontestobj, $ispreview);
    }

    public function test_attempt_submitted() {

        list($progcontestobj, $quba, $attempt) = $this->prepare_progcontest_data();
        $attemptobj = progcontest_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();

        $timefinish = time();
        $attemptobj->process_finish($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(3, $events);
        $event = $events[2];
        $this->assertInstanceOf('\mod_progcontest\event\attempt_submitted', $event);
        $this->assertEquals('progcontest_attempts', $event->objecttable);
        $this->assertEquals($progcontestobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals(null, $event->other['submitterid']); // Should be the user, but PHP Unit complains...
        $this->assertEquals('progcontest_attempt_submitted', $event->get_legacy_eventname());
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_progcontest';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $progcontestobj->get_cmid();
        $legacydata->courseid = $progcontestobj->get_courseid();
        $legacydata->progcontestid = $progcontestobj->get_progcontestid();
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $legacydata->submitterid = null;
        $legacydata->timefinish = $timefinish;
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_becameoverdue() {

        list($progcontestobj, $quba, $attempt) = $this->prepare_progcontest_data();
        $attemptobj = progcontest_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_going_overdue($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_progcontest\event\attempt_becameoverdue', $event);
        $this->assertEquals('progcontest_attempts', $event->objecttable);
        $this->assertEquals($progcontestobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertNotEmpty($event->get_description());
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEquals('progcontest_attempt_overdue', $event->get_legacy_eventname());
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_progcontest';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $progcontestobj->get_cmid();
        $legacydata->courseid = $progcontestobj->get_courseid();
        $legacydata->progcontestid = $progcontestobj->get_progcontestid();
        $legacydata->submitterid = null; // Should be the user, but PHP Unit complains...
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_abandoned() {

        list($progcontestobj, $quba, $attempt) = $this->prepare_progcontest_data();
        $attemptobj = progcontest_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_abandon($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_progcontest\event\attempt_abandoned', $event);
        $this->assertEquals('progcontest_attempts', $event->objecttable);
        $this->assertEquals($progcontestobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEquals('progcontest_attempt_abandoned', $event->get_legacy_eventname());
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_progcontest';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $progcontestobj->get_cmid();
        $legacydata->courseid = $progcontestobj->get_courseid();
        $legacydata->progcontestid = $progcontestobj->get_progcontestid();
        $legacydata->submitterid = null; // Should be the user, but PHP Unit complains...
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_started() {
        $progcontestobj = $this->prepare_progcontest();

        $quba = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj->get_context());
        $quba->set_preferred_behaviour($progcontestobj->get_progcontest()->preferredbehaviour);

        $timenow = time();
        $attempt = progcontest_create_attempt($progcontestobj, 1, false, $timenow);
        progcontest_start_new_attempt($progcontestobj, $quba, $attempt, 1, $timenow);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        progcontest_attempt_save_started($progcontestobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\attempt_started', $event);
        $this->assertEquals('progcontest_attempts', $event->objecttable);
        $this->assertEquals($attempt->id, $event->objectid);
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals($progcontestobj->get_context(), $event->get_context());
        $this->assertEquals('progcontest_attempt_started', $event->get_legacy_eventname());
        $this->assertEquals(\context_module::instance($progcontestobj->get_cmid()), $event->get_context());
        // Check legacy log data.
        $expected = array($progcontestobj->get_courseid(), 'progcontest', 'attempt', 'review.php?attempt=' . $attempt->id,
            $progcontestobj->get_progcontestid(), $progcontestobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        // Check legacy event data.
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_progcontest';
        $legacydata->attemptid = $attempt->id;
        $legacydata->timestart = $attempt->timestart;
        $legacydata->timestamp = $attempt->timestart;
        $legacydata->userid = $attempt->userid;
        $legacydata->progcontestid = $progcontestobj->get_progcontestid();
        $legacydata->cmid = $progcontestobj->get_cmid();
        $legacydata->courseid = $progcontestobj->get_courseid();
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the edit page viewed event.
     *
     * There is no external API for updating a progcontest, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_edit_page_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        $params = array(
            'courseid' => $course->id,
            'context' => \context_module::instance($progcontest->cmid),
            'other' => array(
                'progcontestid' => $progcontest->id
            )
        );
        $event = \mod_progcontest\event\edit_page_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\edit_page_viewed', $event);
        $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
        $expected = array($course->id, 'progcontest', 'editquestions', 'view.php?id=' . $progcontest->cmid, $progcontest->id, $progcontest->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt deleted event.
     */
    public function test_attempt_deleted() {
        list($progcontestobj, $quba, $attempt) = $this->prepare_progcontest_data();

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        progcontest_delete_attempt($attempt, $progcontestobj->get_progcontest());
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\attempt_deleted', $event);
        $this->assertEquals(\context_module::instance($progcontestobj->get_cmid()), $event->get_context());
        $expected = array($progcontestobj->get_courseid(), 'progcontest', 'delete attempt', 'report.php?id=' . $progcontestobj->get_cmid(),
            $attempt->id, $progcontestobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test that preview attempt deletions are not logged.
     */
    public function test_preview_attempt_deleted() {
        // Create progcontest with preview attempt.
        list($progcontestobj, $quba, $previewattempt) = $this->prepare_progcontest_data(true);

        // Delete a preview attempt, capturing events.
        $sink = $this->redirectEvents();
        progcontest_delete_attempt($previewattempt, $progcontestobj->get_progcontest());

        // Verify that no events were generated.
        $this->assertEmpty($sink->get_events());
    }

    /**
     * Test the report viewed event.
     *
     * There is no external API for viewing reports, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_report_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        $params = array(
            'context' => $context = \context_module::instance($progcontest->cmid),
            'other' => array(
                'progcontestid' => $progcontest->id,
                'reportname' => 'overview'
            )
        );
        $event = \mod_progcontest\event\report_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\report_viewed', $event);
        $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
        $expected = array($course->id, 'progcontest', 'report', 'report.php?id=' . $progcontest->cmid . '&mode=overview',
            $progcontest->id, $progcontest->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt reviewed event.
     *
     * There is no external API for reviewing attempts, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_reviewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($progcontest->cmid),
            'other' => array(
                'progcontestid' => $progcontest->id
            )
        );
        $event = \mod_progcontest\event\attempt_reviewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\attempt_reviewed', $event);
        $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
        $expected = array($course->id, 'progcontest', 'review', 'review.php?attempt=1', $progcontest->id, $progcontest->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt summary viewed event.
     *
     * There is no external API for viewing the attempt summary, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_summary_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($progcontest->cmid),
            'other' => array(
                'progcontestid' => $progcontest->id
            )
        );
        $event = \mod_progcontest\event\attempt_summary_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\attempt_summary_viewed', $event);
        $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
        $expected = array($course->id, 'progcontest', 'view summary', 'summary.php?attempt=1', $progcontest->id, $progcontest->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override created event.
     *
     * There is no external API for creating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => \context_module::instance($progcontest->cmid),
            'other' => array(
                'progcontestid' => $progcontest->id
            )
        );
        $event = \mod_progcontest\event\user_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\user_override_created', $event);
        $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override created event.
     *
     * There is no external API for creating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'context' => \context_module::instance($progcontest->cmid),
            'other' => array(
                'progcontestid' => $progcontest->id,
                'groupid' => 2
            )
        );
        $event = \mod_progcontest\event\group_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\group_override_created', $event);
        $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override updated event.
     *
     * There is no external API for updating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => \context_module::instance($progcontest->cmid),
            'other' => array(
                'progcontestid' => $progcontest->id
            )
        );
        $event = \mod_progcontest\event\user_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\user_override_updated', $event);
        $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
        $expected = array($course->id, 'progcontest', 'edit override', 'overrideedit.php?id=1', $progcontest->id, $progcontest->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override updated event.
     *
     * There is no external API for updating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'context' => \context_module::instance($progcontest->cmid),
            'other' => array(
                'progcontestid' => $progcontest->id,
                'groupid' => 2
            )
        );
        $event = \mod_progcontest\event\group_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\group_override_updated', $event);
        $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
        $expected = array($course->id, 'progcontest', 'edit override', 'overrideedit.php?id=1', $progcontest->id, $progcontest->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override deleted event.
     */
    public function test_user_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        // Create an override.
        $override = new \stdClass();
        $override->progcontest = $progcontest->id;
        $override->userid = 2;
        $override->id = $DB->insert_record('progcontest_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        progcontest_delete_override($progcontest, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\user_override_deleted', $event);
        $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
        $expected = array($course->id, 'progcontest', 'delete override', 'overrides.php?cmid=' . $progcontest->cmid, $progcontest->id, $progcontest->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override deleted event.
     */
    public function test_group_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        // Create an override.
        $override = new \stdClass();
        $override->progcontest = $progcontest->id;
        $override->groupid = 2;
        $override->id = $DB->insert_record('progcontest_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        progcontest_delete_override($progcontest, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\group_override_deleted', $event);
        $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
        $expected = array($course->id, 'progcontest', 'delete override', 'overrides.php?cmid=' . $progcontest->cmid, $progcontest->id, $progcontest->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt viewed event.
     *
     * There is no external API for continuing an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($progcontest->cmid),
            'other' => array(
                'progcontestid' => $progcontest->id
            )
        );
        $event = \mod_progcontest\event\attempt_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\attempt_viewed', $event);
        $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
        $expected = array($course->id, 'progcontest', 'continue attempt', 'review.php?attempt=1', $progcontest->id, $progcontest->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt previewed event.
     */
    public function test_attempt_preview_started() {
        $progcontestobj = $this->prepare_progcontest();

        $quba = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj->get_context());
        $quba->set_preferred_behaviour($progcontestobj->get_progcontest()->preferredbehaviour);

        $timenow = time();
        $attempt = progcontest_create_attempt($progcontestobj, 1, false, $timenow, true);
        progcontest_start_new_attempt($progcontestobj, $quba, $attempt, 1, $timenow);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        progcontest_attempt_save_started($progcontestobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\attempt_preview_started', $event);
        $this->assertEquals(\context_module::instance($progcontestobj->get_cmid()), $event->get_context());
        $expected = array($progcontestobj->get_courseid(), 'progcontest', 'preview', 'view.php?id=' . $progcontestobj->get_cmid(),
            $progcontestobj->get_progcontestid(), $progcontestobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the question manually graded event.
     *
     * There is no external API for manually grading a question, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_question_manually_graded() {
        list($progcontestobj, $quba, $attempt) = $this->prepare_progcontest_data();

        $params = array(
            'objectid' => 1,
            'courseid' => $progcontestobj->get_courseid(),
            'context' => \context_module::instance($progcontestobj->get_cmid()),
            'other' => array(
                'progcontestid' => $progcontestobj->get_progcontestid(),
                'attemptid' => 2,
                'slot' => 3
            )
        );
        $event = \mod_progcontest\event\question_manually_graded::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_progcontest\event\question_manually_graded', $event);
        $this->assertEquals(\context_module::instance($progcontestobj->get_cmid()), $event->get_context());
        $expected = array($progcontestobj->get_courseid(), 'progcontest', 'manualgrade', 'comment.php?attempt=2&slot=3',
            $progcontestobj->get_progcontestid(), $progcontestobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt regraded event.
     *
     * There is no external API for regrading attempts, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_regraded() {
      $this->resetAfterTest();

      $this->setAdminUser();
      $course = $this->getDataGenerator()->create_course();
      $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

      $params = array(
        'objectid' => 1,
        'relateduserid' => 2,
        'courseid' => $course->id,
        'context' => \context_module::instance($progcontest->cmid),
        'other' => array(
          'progcontestid' => $progcontest->id
        )
      );
      $event = \mod_progcontest\event\attempt_regraded::create($params);

      // Trigger and capture the event.
      $sink = $this->redirectEvents();
      $event->trigger();
      $events = $sink->get_events();
      $event = reset($events);

      // Check that the event data is valid.
      $this->assertInstanceOf('\mod_progcontest\event\attempt_regraded', $event);
      $this->assertEquals(\context_module::instance($progcontest->cmid), $event->get_context());
      $this->assertEventContextNotUsed($event);
    }
}
