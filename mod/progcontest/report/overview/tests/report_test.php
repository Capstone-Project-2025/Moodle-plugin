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

namespace progcontest_overview;

use question_engine;
use progcontest;
use progcontest_attempt;
use progcontest_attempts_report;
use progcontest_overview_options;
use progcontest_overview_report;
use progcontest_overview_table;
use testable_progcontest_attempts_report;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/progcontest/locallib.php');
require_once($CFG->dirroot . '/mod/progcontest/report/reportlib.php');
require_once($CFG->dirroot . '/mod/progcontest/report/default.php');
require_once($CFG->dirroot . '/mod/progcontest/report/overview/report.php');
require_once($CFG->dirroot . '/mod/progcontest/report/overview/tests/helpers.php');

/**
 * Tests for the progcontest overview report.
 *
 * @package    progcontest_overview
 * @copyright  2014 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_test extends \advanced_testcase {

    /**
     * Data provider for test_report_sql.
     *
     * @return array the data for the test sub-cases.
     */
    public function report_sql_cases() {
        return [[null], ['csv']]; // Only need to test on or off, not all download types.
    }

    /**
     * Test how the report queries the database.
     *
     * @param bool $isdownloading a download type, or null.
     * @dataProvider report_sql_cases
     */
    public function test_report_sql($isdownloading) {
        global $DB;
        $this->resetAfterTest(true);

        // Create a course and a progcontest.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $progcontestgenerator = $generator->get_plugin_generator('mod_progcontest');
        $progcontest = $progcontestgenerator->create_instance(array('course' => $course->id,
                'grademethod' => progcontest_GRADEHIGHEST, 'grade' => 100.0, 'sumgrades' => 10.0,
                'attempts' => 10));

        // Add one question.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('essay', 'plain', ['category' => $cat->id]);
        progcontest_add_progcontest_question($q->id, $progcontest, 0 , 10);

        // Create some students and enrol them in the course.
        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $student3 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id);
        $generator->enrol_user($student2->id, $course->id);
        $generator->enrol_user($student3->id, $course->id);
        // This line is not really necessary for the test asserts below,
        // but what it does is add an extra user row returned by
        // get_enrolled_with_capabilities_join because of a second enrolment.
        // The extra row returned used to make $table->query_db complain
        // about duplicate records. So this is really a test that an extra
        // student enrolment does not cause duplicate records in this query.
        $generator->enrol_user($student2->id, $course->id, null, 'self');

        // Also create a user who should not appear in the reports,
        // because they have a role with neither 'mod/progcontest:attempt'
        // nor 'mod/progcontest:reviewmyattempts'.
        $tutor = $generator->create_user();
        $generator->enrol_user($tutor->id, $course->id, 'teacher');

        // The test data.
        $timestamp = 1234567890;
        $attempts = array(
            array($progcontest, $student1, 1, 0.0,  progcontest_attempt::FINISHED),
            array($progcontest, $student1, 2, 5.0,  progcontest_attempt::FINISHED),
            array($progcontest, $student1, 3, 8.0,  progcontest_attempt::FINISHED),
            array($progcontest, $student1, 4, null, progcontest_attempt::ABANDONED),
            array($progcontest, $student1, 5, null, progcontest_attempt::IN_PROGRESS),
            array($progcontest, $student2, 1, null, progcontest_attempt::ABANDONED),
            array($progcontest, $student2, 2, null, progcontest_attempt::ABANDONED),
            array($progcontest, $student2, 3, 7.0,  progcontest_attempt::FINISHED),
            array($progcontest, $student2, 4, null, progcontest_attempt::ABANDONED),
            array($progcontest, $student2, 5, null, progcontest_attempt::ABANDONED),
        );

        // Load it in to progcontest attempts table.
        foreach ($attempts as $attemptdata) {
            list($progcontest, $student, $attemptnumber, $sumgrades, $state) = $attemptdata;
            $timestart = $timestamp + $attemptnumber * 3600;

            $progcontestobj = progcontest::create($progcontest->id, $student->id);
            $quba = question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj->get_context());
            $quba->set_preferred_behaviour($progcontestobj->get_progcontest()->preferredbehaviour);

            // Create the new attempt and initialize the question sessions.
            $attempt = progcontest_create_attempt($progcontestobj, $attemptnumber, null, $timestart, false, $student->id);

            $attempt = progcontest_start_new_attempt($progcontestobj, $quba, $attempt, $attemptnumber, $timestamp);
            $attempt = progcontest_attempt_save_started($progcontestobj, $quba, $attempt);

            // Process some responses from the student.
            $attemptobj = progcontest_attempt::create($attempt->id);
            switch ($state) {
                case progcontest_attempt::ABANDONED:
                    $attemptobj->process_abandon($timestart + 300, false);
                    break;

                case progcontest_attempt::IN_PROGRESS:
                    // Do nothing.
                    break;

                case progcontest_attempt::FINISHED:
                    // Save answer and finish attempt.
                    $attemptobj->process_submitted_actions($timestart + 300, false, [
                            1 => ['answer' => 'My essay by ' . $student->firstname, 'answerformat' => FORMAT_PLAIN]]);
                    $attemptobj->process_finish($timestart + 600, false);

                    // Manually grade it.
                    $quba = $attemptobj->get_question_usage();
                    $quba->get_question_attempt(1)->manual_grade(
                            'Comment', $sumgrades, FORMAT_HTML, $timestart + 1200);
                    question_engine::save_questions_usage_by_activity($quba);
                    $update = new \stdClass();
                    $update->id = $attemptobj->get_attemptid();
                    $update->timemodified = $timestart + 1200;
                    $update->sumgrades = $quba->get_total_mark();
                    $DB->update_record('progcontest_attempts', $update);
                    progcontest_save_best_grade($attemptobj->get_progcontest(), $student->id);
                    break;
            }
        }

        // Actually getting the SQL to run is quite hard. Do a minimal set up of
        // some objects.
        $context = \context_module::instance($progcontest->cmid);
        $cm = get_coursemodule_from_id('progcontest', $progcontest->cmid);
        $qmsubselect = progcontest_report_qm_filter_select($progcontest);
        $studentsjoins = get_enrolled_with_capabilities_join($context, '',
                array('mod/progcontest:attempt', 'mod/progcontest:reviewmyattempts'));
        $empty = new \core\dml\sql_join();

        // Set the options.
        $reportoptions = new progcontest_overview_options('overview', $progcontest, $cm, null);
        $reportoptions->attempts = progcontest_attempts_report::ENROLLED_ALL;
        $reportoptions->onlygraded = true;
        $reportoptions->states = array(progcontest_attempt::IN_PROGRESS, progcontest_attempt::OVERDUE, progcontest_attempt::FINISHED);

        // Now do a minimal set-up of the table class.
        $q->slot = 1;
        $q->maxmark = 10;
        $table = new progcontest_overview_table($progcontest, $context, $qmsubselect, $reportoptions,
                $empty, $studentsjoins, array(1 => $q), null);
        $table->download = $isdownloading; // Cannot call the is_downloading API, because it gives errors.
        $table->define_columns(array('fullname'));
        $table->sortable(true, 'uniqueid');
        $table->define_baseurl(new \moodle_url('/mod/progcontest/report.php'));
        $table->setup();

        // Run the query.
        $table->setup_sql_queries($studentsjoins);
        $table->query_db(30, false);

        // Should be 4 rows, matching count($table->rawdata) tested below.
        // The count is only done if not downloading.
        if (!$isdownloading) {
            $this->assertEquals(4, $table->totalrows);
        }

        // Verify what was returned: Student 1's best and in progress attempts.
        // Student 2's finshed attempt, and Student 3 with no attempt.
        // The array key is {student id}#{attempt number}.
        $this->assertEquals(4, count($table->rawdata));
        $this->assertArrayHasKey($student1->id . '#3', $table->rawdata);
        $this->assertEquals(1, $table->rawdata[$student1->id . '#3']->gradedattempt);
        $this->assertArrayHasKey($student1->id . '#3', $table->rawdata);
        $this->assertEquals(0, $table->rawdata[$student1->id . '#5']->gradedattempt);
        $this->assertArrayHasKey($student2->id . '#3', $table->rawdata);
        $this->assertEquals(1, $table->rawdata[$student2->id . '#3']->gradedattempt);
        $this->assertArrayHasKey($student3->id . '#0', $table->rawdata);
        $this->assertEquals(0, $table->rawdata[$student3->id . '#0']->gradedattempt);

        // Check the calculation of averages.
        $averagerow = $table->compute_average_row('overallaverage', $studentsjoins);
        $this->assertStringContainsString('75.00', $averagerow['sumgrades']);
        $this->assertStringContainsString('75.00', $averagerow['qsgrade1']);
        if (!$isdownloading) {
            $this->assertStringContainsString('(2)', $averagerow['sumgrades']);
            $this->assertStringContainsString('(2)', $averagerow['qsgrade1']);
        }

        // Ensure that filtering by initial does not break it.
        // This involves setting a private properly of the base class, which is
        // only really possible using reflection :-(.
        $reflectionobject = new \ReflectionObject($table);
        while ($parent = $reflectionobject->getParentClass()) {
            $reflectionobject = $parent;
        }
        $prefsproperty = $reflectionobject->getProperty('prefs');
        $prefsproperty->setAccessible(true);
        $prefs = $prefsproperty->getValue($table);
        $prefs['i_first'] = 'A';
        $prefsproperty->setValue($table, $prefs);

        list($fields, $from, $where, $params) = $table->base_sql($studentsjoins);
        $table->set_count_sql("SELECT COUNT(1) FROM (SELECT $fields FROM $from WHERE $where) temp WHERE 1 = 1", $params);
        $table->set_sql($fields, $from, $where, $params);
        $table->query_db(30, false);
        // Just verify that this does not cause a fatal error.
    }

    /**
     * Bands provider.
     * @return array
     */
    public function get_bands_count_and_width_provider() {
        return [
            [10, [20, .5]],
            [20, [20, 1]],
            [30, [15, 2]],
            // TODO MDL-55068 Handle bands better when grade is 50.
            // [50, [10, 5]],
            [100, [20, 5]],
            [200, [20, 10]],
        ];
    }

    /**
     * Test bands.
     *
     * @dataProvider get_bands_count_and_width_provider
     * @param int $grade grade
     * @param array $expected
     */
    public function test_get_bands_count_and_width($grade, $expected) {
        $this->resetAfterTest(true);
        $progcontestgenerator = $this->getDataGenerator()->get_plugin_generator('mod_progcontest');
        $progcontest = $progcontestgenerator->create_instance(['course' => SITEID, 'grade' => $grade]);
        $this->assertEquals($expected, progcontest_overview_report::get_bands_count_and_width($progcontest));
    }

    /**
     * Test delete_selected_attempts function.
     */
    public function test_delete_selected_attempts() {
        $this->resetAfterTest(true);

        $timestamp = 1234567890;
        $timestart = $timestamp + 3600;

        // Create a course and a progcontest.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $progcontestgenerator = $generator->get_plugin_generator('mod_progcontest');
        $progcontest = $progcontestgenerator->create_instance([
                'course' => $course->id,
                'grademethod' => progcontest_GRADEHIGHEST,
                'grade' => 100.0,
                'sumgrades' => 10.0,
                'attempts' => 10
        ]);

        // Add one question.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('essay', 'plain', ['category' => $cat->id]);
        progcontest_add_progcontest_question($q->id, $progcontest, 0 , 10);

        // Create student and enrol them in the course.
        // Note: we create two enrolments, to test the problem reported in MDL-67942.
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id);
        $generator->enrol_user($student->id, $course->id, null, 'self');

        $context = \context_module::instance($progcontest->cmid);
        $cm = get_coursemodule_from_id('progcontest', $progcontest->cmid);
        $allowedjoins = get_enrolled_with_capabilities_join($context, '', ['mod/progcontest:attempt', 'mod/progcontest:reviewmyattempts']);
        $progcontestattemptsreport = new testable_progcontest_attempts_report();

        // Create the new attempt and initialize the question sessions.
        $progcontestobj = progcontest::create($progcontest->id, $student->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj->get_context());
        $quba->set_preferred_behaviour($progcontestobj->get_progcontest()->preferredbehaviour);
        $attempt = progcontest_create_attempt($progcontestobj, 1, null, $timestart, false, $student->id);
        $attempt = progcontest_start_new_attempt($progcontestobj, $quba, $attempt, 1, $timestamp);
        $attempt = progcontest_attempt_save_started($progcontestobj, $quba, $attempt);

        // Delete the student's attempt.
        $progcontestattemptsreport->delete_selected_attempts($progcontest, $cm, [$attempt->id], $allowedjoins);
    }

}
