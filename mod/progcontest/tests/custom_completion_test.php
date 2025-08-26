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

declare(strict_types=1);

namespace mod_progcontest;

use advanced_testcase;
use cm_info;
use grade_item;
use mod_progcontest\completion\custom_completion;
use question_engine;
use progcontest;
use progcontest_attempt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Class for unit testing mod_progcontest/custom_completion.
 *
 * @package   mod_progcontest
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_progcontest\completion\custom_completion
 */
class custom_completion_test extends advanced_testcase {

    /**
     * Setup function for all tests.
     *
     * @param array $completionoptions ['nbstudents'] => int, ['qtype'] => string, ['progcontestoptions'] => array
     * @return array [$students, $progcontest, $cm]
     */
    private function setup_progcontest_for_testing_completion(array $completionoptions): array {
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
        $cm = cm_info::create($cm);

        // Create a question.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question($completionoptions['qtype'], null, ['category' => $cat->id]);
        progcontest_add_progcontest_question($question->id, $progcontest);

        // Set grade to pass.
        $item = grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'progcontest',
            'iteminstance' => $progcontest->id, 'outcomeid' => null]);
        $item->gradepass = 80;
        $item->update();

        return [
            $students,
            $progcontest,
            $cm
        ];
    }

    /**
     * Helper function for tests.
     * Starts an attempt, processes responses and finishes the attempt.
     *
     * @param array $attemptoptions ['progcontest'] => object, ['student'] => object, ['tosubmit'] => array, ['attemptnumber'] => int
     */
    private function do_attempt_progcontest(array $attemptoptions) {
        $progcontestobj = progcontest::create($attemptoptions['progcontest']->id);

        // Start the passing attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj->get_context());
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
     *
     * @covers ::get_state
     * @covers ::get_custom_rule_descriptions
     */
    public function test_completionpass() {
        list($students, $progcontest, $cm) = $this->setup_progcontest_for_testing_completion([
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
        $customcompletion = new custom_completion($cm, (int) $passstudent->id);
        $this->assertArrayHasKey('completionpassorattemptsexhausted', $cm->customdata['customcompletionrules']);
        $this->assertEquals(COMPLETION_COMPLETE, $customcompletion->get_state('completionpassorattemptsexhausted'));
        $this->assertEquals(
            'Receive a pass grade',
            $customcompletion->get_custom_rule_descriptions()['completionpassorattemptsexhausted']
        );

        // Do a failing attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $failstudent,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results.
        $customcompletion = new custom_completion($cm, (int) $failstudent->id);
        $this->assertArrayHasKey('completionpassorattemptsexhausted', $cm->customdata['customcompletionrules']);
        $this->assertEquals(COMPLETION_INCOMPLETE, $customcompletion->get_state('completionpassorattemptsexhausted'));
        $this->assertEquals(
            'Receive a pass grade',
            $customcompletion->get_custom_rule_descriptions()['completionpassorattemptsexhausted']
        );
    }

    /**
     * Test checking the completion state of a progcontest.
     * To be completed, this progcontest requires either a passing grade or for all attempts to be used up.
     *
     * @covers ::get_state
     * @covers ::get_custom_rule_descriptions
     */
    public function test_completionexhausted() {
        list($students, $progcontest, $cm) = $this->setup_progcontest_for_testing_completion([
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
        $customcompletion = new custom_completion($cm, (int) $passstudent->id);
        $this->assertArrayHasKey('completionpassorattemptsexhausted', $cm->customdata['customcompletionrules']);
        $this->assertEquals(COMPLETION_COMPLETE, $customcompletion->get_state('completionpassorattemptsexhausted'));
        $this->assertEquals(
            'Receive a pass grade or complete all available attempts',
            $customcompletion->get_custom_rule_descriptions()['completionpassorattemptsexhausted']
        );

        // Do a failing attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $exhauststudent,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results. Programmingcontest is not completed by $exhauststudent yet because of failing grade and of remaining attempts.
        $customcompletion = new custom_completion($cm, (int) $exhauststudent->id);
        $this->assertArrayHasKey('completionpassorattemptsexhausted', $cm->customdata['customcompletionrules']);
        $this->assertEquals(COMPLETION_INCOMPLETE, $customcompletion->get_state('completionpassorattemptsexhausted'));
        $this->assertEquals(
            'Receive a pass grade or complete all available attempts',
            $customcompletion->get_custom_rule_descriptions()['completionpassorattemptsexhausted']
        );

        // Do a second failing attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $exhauststudent,
            'attemptnumber' => 2,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results. Programmingcontest is completed by $exhauststudent because there are no remaining attempts.
        $customcompletion = new custom_completion($cm, (int) $exhauststudent->id);
        $this->assertArrayHasKey('completionpassorattemptsexhausted', $cm->customdata['customcompletionrules']);
        $this->assertEquals(COMPLETION_COMPLETE, $customcompletion->get_state('completionpassorattemptsexhausted'));
        $this->assertEquals(
            'Receive a pass grade or complete all available attempts',
            $customcompletion->get_custom_rule_descriptions()['completionpassorattemptsexhausted']
        );

    }

    /**
     * Test checking the completion state of a progcontest.
     * To be completed, this progcontest requires a minimum number of attempts.
     *
     * @covers ::get_state
     * @covers ::get_custom_rule_descriptions
     */
    public function test_completionminattempts() {
        list($students, $progcontest, $cm) = $this->setup_progcontest_for_testing_completion([
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
        $customcompletion = new custom_completion($cm, (int) $student->id);
        $this->assertArrayHasKey('completionminattempts', $cm->customdata['customcompletionrules']);
        $this->assertEquals(COMPLETION_INCOMPLETE, $customcompletion->get_state('completionminattempts'));
        $this->assertEquals(
            'Make attempts: 2',
            $customcompletion->get_custom_rule_descriptions()['completionminattempts']
        );

        // Do a second attempt.
        $this->do_attempt_progcontest([
            'progcontest' => $progcontest,
            'student' => $student,
            'attemptnumber' => 2,
            'tosubmit' => [1 => ['answer' => 'Lorem ipsum.', 'answerformat' => '1']]
        ]);

        // Check the results. Programmingcontest is completed by $student because two attempts were done.
        $customcompletion = new custom_completion($cm, (int) $student->id);
        $this->assertArrayHasKey('completionminattempts', $cm->customdata['customcompletionrules']);
        $this->assertEquals(COMPLETION_COMPLETE, $customcompletion->get_state('completionminattempts'));
        $this->assertEquals(
            'Make attempts: 2',
            $customcompletion->get_custom_rule_descriptions()['completionminattempts']
        );
    }

    /**
     * Test for get_defined_custom_rules().
     *
     * @covers ::get_defined_custom_rules
     */
    public function test_get_defined_custom_rules() {
        $rules = custom_completion::get_defined_custom_rules();
        $this->assertCount(2, $rules);
        $this->assertEquals(
            ['completionpassorattemptsexhausted', 'completionminattempts'],
            $rules
        );
    }
}
