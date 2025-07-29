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
 * Unit tests for (some of) mod/progcontest/locallib.php.
 *
 * @package    mod_progcontest
 * @category   test
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
namespace mod_progcontest;

use progcontest;
use progcontest_attempt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/progcontest/lib.php');

/**
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class lib_test extends \advanced_testcase {
    public function test_progcontest_has_grades() {
        $progcontest = new \stdClass();
        $progcontest->grade = '100.0000';
        $progcontest->sumgrades = '100.0000';
        $this->assertTrue(progcontest_has_grades($progcontest));
        $progcontest->sumgrades = '0.0000';
        $this->assertFalse(progcontest_has_grades($progcontest));
        $progcontest->grade = '0.0000';
        $this->assertFalse(progcontest_has_grades($progcontest));
        $progcontest->sumgrades = '100.0000';
        $this->assertFalse(progcontest_has_grades($progcontest));
    }

    public function test_progcontest_format_grade() {
        $progcontest = new \stdClass();
        $progcontest->decimalpoints = 2;
        $this->assertEquals(progcontest_format_grade($progcontest, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(progcontest_format_grade($progcontest, 0), format_float(0, 2));
        $this->assertEquals(progcontest_format_grade($progcontest, 1.000000000000), format_float(1, 2));
        $progcontest->decimalpoints = 0;
        $this->assertEquals(progcontest_format_grade($progcontest, 0.12345678), '0');
    }

    public function test_progcontest_get_grade_format() {
        $progcontest = new \stdClass();
        $progcontest->decimalpoints = 2;
        $this->assertEquals(progcontest_get_grade_format($progcontest), 2);
        $this->assertEquals($progcontest->questiondecimalpoints, -1);
        $progcontest->questiondecimalpoints = 2;
        $this->assertEquals(progcontest_get_grade_format($progcontest), 2);
        $progcontest->decimalpoints = 3;
        $progcontest->questiondecimalpoints = -1;
        $this->assertEquals(progcontest_get_grade_format($progcontest), 3);
        $progcontest->questiondecimalpoints = 4;
        $this->assertEquals(progcontest_get_grade_format($progcontest), 4);
    }

    public function test_progcontest_format_question_grade() {
        $progcontest = new \stdClass();
        $progcontest->decimalpoints = 2;
        $progcontest->questiondecimalpoints = 2;
        $this->assertEquals(progcontest_format_question_grade($progcontest, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(progcontest_format_question_grade($progcontest, 0), format_float(0, 2));
        $this->assertEquals(progcontest_format_question_grade($progcontest, 1.000000000000), format_float(1, 2));
        $progcontest->decimalpoints = 3;
        $progcontest->questiondecimalpoints = -1;
        $this->assertEquals(progcontest_format_question_grade($progcontest, 0.12345678), format_float(0.123, 3));
        $this->assertEquals(progcontest_format_question_grade($progcontest, 0), format_float(0, 3));
        $this->assertEquals(progcontest_format_question_grade($progcontest, 1.000000000000), format_float(1, 3));
        $progcontest->questiondecimalpoints = 4;
        $this->assertEquals(progcontest_format_question_grade($progcontest, 0.12345678), format_float(0.1235, 4));
        $this->assertEquals(progcontest_format_question_grade($progcontest, 0), format_float(0, 4));
        $this->assertEquals(progcontest_format_question_grade($progcontest, 1.000000000000), format_float(1, 4));
    }

    /**
     * Test deleting a progcontest instance.
     */
    public function test_progcontest_delete_instance() {
        global $SITE, $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Setup a progcontest with 1 standard and 1 random question.
        $progcontestgenerator = $this->getDataGenerator()->get_plugin_generator('mod_progcontest');
        $progcontest = $progcontestgenerator->create_instance(array('course' => $SITE->id, 'questionsperpage' => 3, 'grade' => 100.0));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $standardq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));

        progcontest_add_progcontest_question($standardq->id, $progcontest);
        progcontest_add_random_questions($progcontest, 0, $cat->id, 1, false);

        // Get the random question.
        $randomq = $DB->get_record('question', array('qtype' => 'random'));

        progcontest_delete_instance($progcontest->id);

        // Check that the random question was deleted.
        $count = $DB->count_records('question', array('id' => $randomq->id));
        $this->assertEquals(0, $count);
        // Check that the standard question was not deleted.
        $count = $DB->count_records('question', array('id' => $standardq->id));
        $this->assertEquals(1, $count);

        // Check that all the slots were removed.
        $count = $DB->count_records('progcontest_slots', array('progcontestid' => $progcontest->id));
        $this->assertEquals(0, $count);

        // Check that the progcontest was removed.
        $count = $DB->count_records('progcontest', array('id' => $progcontest->id));
        $this->assertEquals(0, $count);
    }

    /**
     * Setup function for all test_progcontest_get_completion_state_* tests.
     *
     * @param array $completionoptions ['nbstudents'] => int, ['qtype'] => string, ['progcontestoptions'] => array
     * @throws dml_exception
     * @return array [$course, $students, $progcontest, $cm]
     */
    private function setup_progcontest_for_testing_completion(array $completionoptions) {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        // Enable completion before creating modules, otherwise the completion data is not written in DB.
        $CFG->enablecompletion = true;

        // Create a course and students.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);
        $students = [];
        for ($i = 0; $i < $completionoptions['nbstudents']; $i++) {
            $students[$i] = $this->getDataGenerator()->create_user();
            $this->assertTrue($this->getDataGenerator()->enrol_user($students[$i]->id, $course->id, $studentrole->id));
        }

        // Make a progcontest.
        $progcontestgenerator = $this->getDataGenerator()->get_plugin_generator('mod_progcontest');
        $data = array_merge([
            'course' => $course->id,
            'grade' => 100.0,
            'questionsperpage' => 0,
            'sumgrades' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC
        ], $completionoptions['progcontestoptions']);
        $progcontest = $progcontestgenerator->create_instance($data);
        $cm = get_coursemodule_from_id('progcontest', $progcontest->cmid);

        // Create a question.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question($completionoptions['qtype'], null, ['category' => $cat->id]);
        progcontest_add_progcontest_question($question->id, $progcontest);

        // Set grade to pass.
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'progcontest',
            'iteminstance' => $progcontest->id, 'outcomeid' => null]);
        $item->gradepass = 80;
        $item->update();

        return [
            $course,
            $students,
            $progcontest,
            $cm
        ];
    }

    /**
     * Helper function for all test_progcontest_get_completion_state_* tests.
     * Starts an attempt, processes responses and finishes the attempt.
     *
     * @param $attemptoptions ['progcontest'] => object, ['student'] => object, ['tosubmit'] => array, ['attemptnumber'] => int
     */
    private function do_attempt_progcontest($attemptoptions) {
        $progcontestobj = progcontest::create($attemptoptions['progcontest']->id);

        // Start the passing attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj->get_context());
        $quba->set_preferred_behaviour($progcontestobj->get_progcontest()->preferredbehaviour);

        $timenow = time();
        $attempt = progcontest_create_attempt($progcontestobj, $attemptoptions['attemptnumber'], false, $timenow, false,
            $attemptoptions['student']->id);
        progcontest_start_new_attempt($progcontestobj, $quba, $attempt, $attemptoptions['attemptnumber'], $timenow);
        progcontest_attempt_save_started($progcontestobj, $quba, $attempt);

        // Process responses from the student.
        $attemptobj = progcontest_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, $attemptoptions['tosubmit']);

        // Finish the attempt.
        $attemptobj = progcontest_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);
    }

    /**
     * Test checking the completion state of a progcontest.
     * The progcontest requires a passing grade to be completed.
     */
    public function test_progcontest_get_completion_state_completionpass() {

        list($course, $students, $progcontest, $cm) = $this->setup_progcontest_for_testing_completion([
            'nbstudents' => 2,
            'qtype' => 'numerical',
            'progcontestoptions' => [
                'completionusegrade' => 1,
                'completionpass' => 1
            ]
        ]);

        list($passstudent, $failstudent) = $students;

        // Do a passing attempt.
        $this->do_attempt_progcontest([
           'progcontest' => $progcontest,
           'student' => $passstudent,
           'attemptnumber' => 1,
           'tosubmit' => [1 => ['answer' => '3.14']]
        ]);

        // Check the results.
        $this->assertTrue(progcontest_get_completion_state($course, $cm, $passstudent->id, 'return'));

        // Do a failing attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $failstudent,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results.
        $this->assertFalse(progcontest_get_completion_state($course, $cm, $failstudent->id, 'return'));

        $this->assertDebuggingCalledCount(3, [
            'progcontest_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'progcontest_completion_check_min_attempts has been deprecated.',
            'progcontest_completion_check_passing_grade_or_all_attempts has been deprecated.',
        ]);
    }

    /**
     * Test checking the completion state of a progcontest.
     * To be completed, this progcontest requires either a passing grade or for all attempts to be used up.
     */
    public function test_progcontest_get_completion_state_completionexhausted() {

        list($course, $students, $progcontest, $cm) = $this->setup_progcontest_for_testing_completion([
            'nbstudents' => 2,
            'qtype' => 'numerical',
            'progcontestoptions' => [
                'attempts' => 2,
                'completionusegrade' => 1,
                'completionpass' => 1,
                'completionattemptsexhausted' => 1
            ]
        ]);

        list($passstudent, $exhauststudent) = $students;

        // Start a passing attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $passstudent,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '3.14']]
        ]);

        // Check the results. Programmingcontest is completed by $passstudent because of passing grade.
        $this->assertTrue(progcontest_get_completion_state($course, $cm, $passstudent->id, 'return'));

        // Do a failing attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $exhauststudent,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results. Programmingcontest is not completed by $exhauststudent yet because of failing grade and of remaining attempts.
        $this->assertFalse(progcontest_get_completion_state($course, $cm, $exhauststudent->id, 'return'));

        // Do a second failing attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $exhauststudent,
            'attemptnumber' => 2,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results. Programmingcontest is completed by $exhauststudent because there are no remaining attempts.
        $this->assertTrue(progcontest_get_completion_state($course, $cm, $exhauststudent->id, 'return'));

        $this->assertDebuggingCalledCount(5, [
            'progcontest_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'progcontest_completion_check_min_attempts has been deprecated.',
            'progcontest_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'progcontest_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'progcontest_completion_check_min_attempts has been deprecated.',
        ]);
    }

    /**
     * Test checking the completion state of a progcontest.
     * To be completed, this progcontest requires a minimum number of attempts.
     */
    public function test_progcontest_get_completion_state_completionminattempts() {

        list($course, $students, $progcontest, $cm) = $this->setup_progcontest_for_testing_completion([
            'nbstudents' => 1,
            'qtype' => 'essay',
            'progcontestoptions' => [
                'completionminattemptsenabled' => 1,
                'completionminattempts' => 2
            ]
        ]);

        list($student) = $students;

        // Do a first attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $student,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => 'Lorem ipsum.', 'answerformat' => '1']]
        ]);

        // Check the results. Programmingcontest is not completed yet because only one attempt was done.
        $this->assertFalse(progcontest_get_completion_state($course, $cm, $student->id, 'return'));

        // Do a second attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $student,
            'attemptnumber' => 2,
            'tosubmit' => [1 => ['answer' => 'Lorem ipsum.', 'answerformat' => '1']]
        ]);

        // Check the results. Programmingcontest is completed by $student because two attempts were done.
        $this->assertTrue(progcontest_get_completion_state($course, $cm, $student->id, 'return'));

        $this->assertDebuggingCalledCount(4, [
            'progcontest_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'progcontest_completion_check_min_attempts has been deprecated.',
            'progcontest_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'progcontest_completion_check_min_attempts has been deprecated.',
        ]);
    }

    /**
     * Test checking the completion state of a progcontest.
     * To be completed, this progcontest requires a minimum number of attempts AND a passing grade.
     * This is somewhat of an edge case as it is hard to imagine a scenario in which these precise settings are useful.
     * Nevertheless, this test makes sure these settings interact as intended.
     */
    public function  test_progcontest_get_completion_state_completionminattempts_pass() {

        list($course, $students, $progcontest, $cm) = $this->setup_progcontest_for_testing_completion([
            'nbstudents' => 1,
            'qtype' => 'numerical',
            'progcontestoptions' => [
                'attempts' => 2,
                'completionusegrade' => 1,
                'completionpass' => 1,
                'completionminattemptsenabled' => 1,
                'completionminattempts' => 2
            ]
        ]);

        list($student) = $students;

        // Start a first attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $student,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '3.14']]
        ]);

        // Check the results. Even though one requirement is met (passing grade) progcontest is not completed yet because only
        // one attempt was done.
        $this->assertFalse(progcontest_get_completion_state($course, $cm, $student->id, 'return'));

        // Start a second attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $student,
            'attemptnumber' => 2,
            'tosubmit' => [1 => ['answer' => '42']]
        ]);

        // Check the results. Programmingcontest is completed by $student because two attempts were done AND a passing grade was obtained.
        $this->assertTrue(progcontest_get_completion_state($course, $cm, $student->id, 'return'));

        $this->assertDebuggingCalledCount(4, [
            'progcontest_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'progcontest_completion_check_min_attempts has been deprecated.',
            'progcontest_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'progcontest_completion_check_min_attempts has been deprecated.',
        ]);
    }

    public function test_progcontest_get_user_attempts() {
        global $DB;
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $progcontestgen = $dg->get_plugin_generator('mod_progcontest');
        $course = $dg->create_course();
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();
        $u4 = $dg->create_user();
        $role = $DB->get_record('role', ['shortname' => 'student']);

        $dg->enrol_user($u1->id, $course->id, $role->id);
        $dg->enrol_user($u2->id, $course->id, $role->id);
        $dg->enrol_user($u3->id, $course->id, $role->id);
        $dg->enrol_user($u4->id, $course->id, $role->id);

        $progcontest1 = $progcontestgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);
        $progcontest2 = $progcontestgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);

        // Questions.
        $questgen = $dg->get_plugin_generator('core_question');
        $progcontestcat = $questgen->create_question_category();
        $question = $questgen->create_question('numerical', null, ['category' => $progcontestcat->id]);
        progcontest_add_progcontest_question($question->id, $progcontest1);
        progcontest_add_progcontest_question($question->id, $progcontest2);

        $progcontestobj1a = progcontest::create($progcontest1->id, $u1->id);
        $progcontestobj1b = progcontest::create($progcontest1->id, $u2->id);
        $progcontestobj1c = progcontest::create($progcontest1->id, $u3->id);
        $progcontestobj1d = progcontest::create($progcontest1->id, $u4->id);
        $progcontestobj2a = progcontest::create($progcontest2->id, $u1->id);

        // Set attempts.
        $quba1a = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj1a->get_context());
        $quba1a->set_preferred_behaviour($progcontestobj1a->get_progcontest()->preferredbehaviour);
        $quba1b = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj1b->get_context());
        $quba1b->set_preferred_behaviour($progcontestobj1b->get_progcontest()->preferredbehaviour);
        $quba1c = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj1c->get_context());
        $quba1c->set_preferred_behaviour($progcontestobj1c->get_progcontest()->preferredbehaviour);
        $quba1d = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj1d->get_context());
        $quba1d->set_preferred_behaviour($progcontestobj1d->get_progcontest()->preferredbehaviour);
        $quba2a = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj2a->get_context());
        $quba2a->set_preferred_behaviour($progcontestobj2a->get_progcontest()->preferredbehaviour);

        $timenow = time();

        // User 1 passes progcontest 1.
        $attempt = progcontest_create_attempt($progcontestobj1a, 1, false, $timenow, false, $u1->id);
        progcontest_start_new_attempt($progcontestobj1a, $quba1a, $attempt, 1, $timenow);
        progcontest_attempt_save_started($progcontestobj1a, $quba1a, $attempt);
        $attemptobj = progcontest_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);
        $attemptobj->process_finish($timenow, false);

        // User 2 goes overdue in progcontest 1.
        $attempt = progcontest_create_attempt($progcontestobj1b, 1, false, $timenow, false, $u2->id);
        progcontest_start_new_attempt($progcontestobj1b, $quba1b, $attempt, 1, $timenow);
        progcontest_attempt_save_started($progcontestobj1b, $quba1b, $attempt);
        $attemptobj = progcontest_attempt::create($attempt->id);
        $attemptobj->process_going_overdue($timenow, true);

        // User 3 does not finish progcontest 1.
        $attempt = progcontest_create_attempt($progcontestobj1c, 1, false, $timenow, false, $u3->id);
        progcontest_start_new_attempt($progcontestobj1c, $quba1c, $attempt, 1, $timenow);
        progcontest_attempt_save_started($progcontestobj1c, $quba1c, $attempt);

        // User 4 abandons the progcontest 1.
        $attempt = progcontest_create_attempt($progcontestobj1d, 1, false, $timenow, false, $u4->id);
        progcontest_start_new_attempt($progcontestobj1d, $quba1d, $attempt, 1, $timenow);
        progcontest_attempt_save_started($progcontestobj1d, $quba1d, $attempt);
        $attemptobj = progcontest_attempt::create($attempt->id);
        $attemptobj->process_abandon($timenow, true);

        // User 1 attempts the progcontest three times (abandon, finish, in progress).
        $quba2a = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj2a->get_context());
        $quba2a->set_preferred_behaviour($progcontestobj2a->get_progcontest()->preferredbehaviour);

        $attempt = progcontest_create_attempt($progcontestobj2a, 1, false, $timenow, false, $u1->id);
        progcontest_start_new_attempt($progcontestobj2a, $quba2a, $attempt, 1, $timenow);
        progcontest_attempt_save_started($progcontestobj2a, $quba2a, $attempt);
        $attemptobj = progcontest_attempt::create($attempt->id);
        $attemptobj->process_abandon($timenow, true);

        $quba2a = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj2a->get_context());
        $quba2a->set_preferred_behaviour($progcontestobj2a->get_progcontest()->preferredbehaviour);

        $attempt = progcontest_create_attempt($progcontestobj2a, 2, false, $timenow, false, $u1->id);
        progcontest_start_new_attempt($progcontestobj2a, $quba2a, $attempt, 2, $timenow);
        progcontest_attempt_save_started($progcontestobj2a, $quba2a, $attempt);
        $attemptobj = progcontest_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        $quba2a = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj2a->get_context());
        $quba2a->set_preferred_behaviour($progcontestobj2a->get_progcontest()->preferredbehaviour);

        $attempt = progcontest_create_attempt($progcontestobj2a, 3, false, $timenow, false, $u1->id);
        progcontest_start_new_attempt($progcontestobj2a, $quba2a, $attempt, 3, $timenow);
        progcontest_attempt_save_started($progcontestobj2a, $quba2a, $attempt);

        // Check for user 1.
        $attempts = progcontest_get_user_attempts($progcontest1->id, $u1->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($progcontest1->id, $attempt->progcontest);

        $attempts = progcontest_get_user_attempts($progcontest1->id, $u1->id, 'finished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($progcontest1->id, $attempt->progcontest);

        $attempts = progcontest_get_user_attempts($progcontest1->id, $u1->id, 'unfinished');
        $this->assertCount(0, $attempts);

        // Check for user 2.
        $attempts = progcontest_get_user_attempts($progcontest1->id, $u2->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::OVERDUE, $attempt->state);
        $this->assertEquals($u2->id, $attempt->userid);
        $this->assertEquals($progcontest1->id, $attempt->progcontest);

        $attempts = progcontest_get_user_attempts($progcontest1->id, $u2->id, 'finished');
        $this->assertCount(0, $attempts);

        $attempts = progcontest_get_user_attempts($progcontest1->id, $u2->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::OVERDUE, $attempt->state);
        $this->assertEquals($u2->id, $attempt->userid);
        $this->assertEquals($progcontest1->id, $attempt->progcontest);

        // Check for user 3.
        $attempts = progcontest_get_user_attempts($progcontest1->id, $u3->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u3->id, $attempt->userid);
        $this->assertEquals($progcontest1->id, $attempt->progcontest);

        $attempts = progcontest_get_user_attempts($progcontest1->id, $u3->id, 'finished');
        $this->assertCount(0, $attempts);

        $attempts = progcontest_get_user_attempts($progcontest1->id, $u3->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u3->id, $attempt->userid);
        $this->assertEquals($progcontest1->id, $attempt->progcontest);

        // Check for user 4.
        $attempts = progcontest_get_user_attempts($progcontest1->id, $u4->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u4->id, $attempt->userid);
        $this->assertEquals($progcontest1->id, $attempt->progcontest);

        $attempts = progcontest_get_user_attempts($progcontest1->id, $u4->id, 'finished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u4->id, $attempt->userid);
        $this->assertEquals($progcontest1->id, $attempt->progcontest);

        $attempts = progcontest_get_user_attempts($progcontest1->id, $u4->id, 'unfinished');
        $this->assertCount(0, $attempts);

        // Multiple attempts for user 1 in progcontest 2.
        $attempts = progcontest_get_user_attempts($progcontest2->id, $u1->id, 'all');
        $this->assertCount(3, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($progcontest2->id, $attempt->progcontest);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($progcontest2->id, $attempt->progcontest);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($progcontest2->id, $attempt->progcontest);

        $attempts = progcontest_get_user_attempts($progcontest2->id, $u1->id, 'finished');
        $this->assertCount(2, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::ABANDONED, $attempt->state);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::FINISHED, $attempt->state);

        $attempts = progcontest_get_user_attempts($progcontest2->id, $u1->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);

        // Multiple progcontest attempts fetched at once.
        $attempts = progcontest_get_user_attempts([$progcontest1->id, $progcontest2->id], $u1->id, 'all');
        $this->assertCount(4, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($progcontest1->id, $attempt->progcontest);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($progcontest2->id, $attempt->progcontest);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($progcontest2->id, $attempt->progcontest);
        $attempt = array_shift($attempts);
        $this->assertEquals(progcontest_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($progcontest2->id, $attempt->progcontest);
    }

    /**
     * Test for progcontest_get_group_override_priorities().
     */
    public function test_progcontest_get_group_override_priorities() {
        global $DB;
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $progcontestgen = $dg->get_plugin_generator('mod_progcontest');
        $course = $dg->create_course();

        $progcontest = $progcontestgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);

        $this->assertNull(progcontest_get_group_override_priorities($progcontest->id));

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));

        $now = 100;
        $override1 = (object)[
            'progcontest' => $progcontest->id,
            'groupid' => $group1->id,
            'timeopen' => $now,
            'timeclose' => $now + 20
        ];
        $DB->insert_record('progcontest_overrides', $override1);

        $override2 = (object)[
            'progcontest' => $progcontest->id,
            'groupid' => $group2->id,
            'timeopen' => $now - 10,
            'timeclose' => $now + 10
        ];
        $DB->insert_record('progcontest_overrides', $override2);

        $priorities = progcontest_get_group_override_priorities($progcontest->id);
        $this->assertNotEmpty($priorities);

        $openpriorities = $priorities['open'];
        // Override 2's time open has higher priority since it is sooner than override 1's.
        $this->assertEquals(2, $openpriorities[$override1->timeopen]);
        $this->assertEquals(1, $openpriorities[$override2->timeopen]);

        $closepriorities = $priorities['close'];
        // Override 1's time close has higher priority since it is later than override 2's.
        $this->assertEquals(1, $closepriorities[$override1->timeclose]);
        $this->assertEquals(2, $closepriorities[$override2->timeclose]);
    }

    public function test_progcontest_core_calendar_provide_event_action_open() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a progcontest.
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id,
            'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id, progcontest_EVENT_TYPE_OPEN);
        // Now, log in as student.
        $this->setUser($student);
        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_progcontest_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attemptprogcontestnow', 'progcontest'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_progcontest_core_calendar_provide_event_action_open_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a progcontest.
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id,
            'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id, progcontest_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_progcontest_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attemptprogcontestnow', 'progcontest'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_progcontest_core_calendar_provide_event_action_closed() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a progcontest.
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id,
            'timeclose' => time() - DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id, progcontest_EVENT_TYPE_CLOSE);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm the result was null.
        $this->assertNull(mod_progcontest_core_calendar_provide_event_action($event, $factory));
    }

    public function test_progcontest_core_calendar_provide_event_action_closed_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a progcontest.
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id,
            'timeclose' => time() - DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id, progcontest_EVENT_TYPE_CLOSE);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm the result was null.
        $this->assertNull(mod_progcontest_core_calendar_provide_event_action($event, $factory, $student->id));
    }

    public function test_progcontest_core_calendar_provide_event_action_open_in_future() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a progcontest.
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id,
            'timeopen' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id, progcontest_EVENT_TYPE_CLOSE);
        // Now, log in as student.
        $this->setUser($student);
        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_progcontest_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attemptprogcontestnow', 'progcontest'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_progcontest_core_calendar_provide_event_action_open_in_future_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a progcontest.
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id,
            'timeopen' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id, progcontest_EVENT_TYPE_CLOSE);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_progcontest_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attemptprogcontestnow', 'progcontest'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_progcontest_core_calendar_provide_event_action_no_capability() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a progcontest.
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        // Remove the permission to attempt or review the progcontest for the student role.
        $coursecontext = \context_course::instance($course->id);
        assign_capability('mod/progcontest:reviewmyattempts', CAP_PROHIBIT, $studentrole->id, $coursecontext);
        assign_capability('mod/progcontest:attempt', CAP_PROHIBIT, $studentrole->id, $coursecontext);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id, progcontest_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Set current user to the student.
        $this->setUser($student);

        // Confirm null is returned.
        $this->assertNull(mod_progcontest_core_calendar_provide_event_action($event, $factory));
    }

    public function test_progcontest_core_calendar_provide_event_action_no_capability_for_user() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a progcontest.
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id));

        // Remove the permission to attempt or review the progcontest for the student role.
        $coursecontext = \context_course::instance($course->id);
        assign_capability('mod/progcontest:reviewmyattempts', CAP_PROHIBIT, $studentrole->id, $coursecontext);
        assign_capability('mod/progcontest:attempt', CAP_PROHIBIT, $studentrole->id, $coursecontext);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id, progcontest_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm null is returned.
        $this->assertNull(mod_progcontest_core_calendar_provide_event_action($event, $factory, $student->id));
    }

    public function test_progcontest_core_calendar_provide_event_action_already_finished() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a progcontest.
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id,
            'sumgrades' => 1));

        // Add a question to the progcontest.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        progcontest_add_progcontest_question($question->id, $progcontest);

        // Get the progcontest object.
        $progcontestobj = progcontest::create($progcontest->id, $student->id);

        // Create an attempt for the student in the progcontest.
        $timenow = time();
        $attempt = progcontest_create_attempt($progcontestobj, 1, false, $timenow, false, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj->get_context());
        $quba->set_preferred_behaviour($progcontestobj->get_progcontest()->preferredbehaviour);
        progcontest_start_new_attempt($progcontestobj, $quba, $attempt, 1, $timenow);
        progcontest_attempt_save_started($progcontestobj, $quba, $attempt);

        // Finish the attempt.
        $attemptobj = progcontest_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id, progcontest_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Set current user to the student.
        $this->setUser($student);

        // Confirm null is returned.
        $this->assertNull(mod_progcontest_core_calendar_provide_event_action($event, $factory));
    }

    public function test_progcontest_core_calendar_provide_event_action_already_finished_for_user() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a progcontest.
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id,
            'sumgrades' => 1));

        // Add a question to the progcontest.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        progcontest_add_progcontest_question($question->id, $progcontest);

        // Get the progcontest object.
        $progcontestobj = progcontest::create($progcontest->id, $student->id);

        // Create an attempt for the student in the progcontest.
        $timenow = time();
        $attempt = progcontest_create_attempt($progcontestobj, 1, false, $timenow, false, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj->get_context());
        $quba->set_preferred_behaviour($progcontestobj->get_progcontest()->preferredbehaviour);
        progcontest_start_new_attempt($progcontestobj, $quba, $attempt, 1, $timenow);
        progcontest_attempt_save_started($progcontestobj, $quba, $attempt);

        // Finish the attempt.
        $attemptobj = progcontest_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id, progcontest_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm null is returned.
        $this->assertNull(mod_progcontest_core_calendar_provide_event_action($event, $factory, $student->id));
    }

    public function test_progcontest_core_calendar_provide_event_action_already_completed() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Get some additional data.
        $cm = get_coursemodule_from_instance('progcontest', $progcontest->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_progcontest_core_calendar_provide_event_action($event, $factory);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    public function test_progcontest_core_calendar_provide_event_action_already_completed_for_user() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $progcontest = $this->getDataGenerator()->create_module('progcontest', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Enrol a student in the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Get some additional data.
        $cm = get_coursemodule_from_instance('progcontest', $progcontest->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $progcontest->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed for the student.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm, $student->id);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_progcontest_core_calendar_provide_event_action($event, $factory, $student->id);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid
     * @param int $instanceid The progcontest id.
     * @param string $eventtype The event type. eg. progcontest_EVENT_TYPE_OPEN.
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype) {
        $event = new \stdClass();
        $event->name = 'Calendar event';
        $event->modulename  = 'progcontest';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->timestart = time();

        return \calendar_event::create($event);
    }

    /**
     * Test the callback responsible for returning the completion rule descriptions.
     * This function should work given either an instance of the module (cm_info), such as when checking the active rules,
     * or if passed a stdClass of similar structure, such as when checking the the default completion settings for a mod type.
     */
    public function test_mod_progcontest_completion_get_active_rule_descriptions() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Two activities, both with automatic completion. One has the 'completionsubmit' rule, one doesn't.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 2]);
        $progcontest1 = $this->getDataGenerator()->create_module('progcontest', [
            'course' => $course->id,
            'completion' => 2,
            'completionusegrade' => 1,
            'completionattemptsexhausted' => 1,
            'completionpass' => 1
        ]);
        $progcontest2 = $this->getDataGenerator()->create_module('progcontest', [
            'course' => $course->id,
            'completion' => 2,
            'completionusegrade' => 0
        ]);
        $cm1 = \cm_info::create(get_coursemodule_from_instance('progcontest', $progcontest1->id));
        $cm2 = \cm_info::create(get_coursemodule_from_instance('progcontest', $progcontest2->id));

        // Data for the stdClass input type.
        // This type of input would occur when checking the default completion rules for an activity type, where we don't have
        // any access to cm_info, rather the input is a stdClass containing completion and customdata attributes, just like cm_info.
        $moddefaults = new \stdClass();
        $moddefaults->customdata = ['customcompletionrules' => [
            'completionattemptsexhausted' => 1,
            'completionpass' => 1
        ]];
        $moddefaults->completion = 2;

        $activeruledescriptions = [
            get_string('completionpassorattemptsexhausteddesc', 'progcontest'),
        ];
        $this->assertEquals(mod_progcontest_get_completion_active_rule_descriptions($cm1), $activeruledescriptions);
        $this->assertEquals(mod_progcontest_get_completion_active_rule_descriptions($cm2), []);
        $this->assertEquals(mod_progcontest_get_completion_active_rule_descriptions($moddefaults), $activeruledescriptions);
        $this->assertEquals(mod_progcontest_get_completion_active_rule_descriptions(new \stdClass()), []);
    }

    /**
     * A user who does not have capabilities to add events to the calendar should be able to create a progcontest.
     */
    public function test_creation_with_no_calendar_capabilities() {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = self::getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = self::getDataGenerator()->create_role();
        self::getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        assign_capability('moodle/calendar:manageentries', CAP_PROHIBIT, $roleid, $context, true);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_progcontest');
        // Create an instance as a user without the calendar capabilities.
        $this->setUser($user);
        $time = time();
        $params = array(
            'course' => $course->id,
            'timeopen' => $time + 200,
            'timeclose' => $time + 2000,
        );
        $generator->create_instance($params);
    }
}
