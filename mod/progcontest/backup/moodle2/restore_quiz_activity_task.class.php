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
 * @package    mod_progcontest
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/progcontest/backup/moodle2/restore_progcontest_stepslib.php');


/**
 * progcontest restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_progcontest_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Programmingcontest only has one structure step.
        $this->add_step(new restore_progcontest_activity_structure_step('progcontest_structure', 'progcontest.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('progcontest', array('intro'), 'progcontest');
        $contents[] = new restore_decode_content('progcontest_feedback',
                array('feedbacktext'), 'progcontest_feedback');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('QUIZVIEWBYID',
                '/mod/progcontest/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('QUIZVIEWBYQ',
                '/mod/progcontest/view.php?q=$1', 'progcontest');
        $rules[] = new restore_decode_rule('QUIZINDEX',
                '/mod/progcontest/index.php?id=$1', 'course');

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * progcontest logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    public static function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('progcontest', 'add',
                'view.php?id={course_module}', '{progcontest}');
        $rules[] = new restore_log_rule('progcontest', 'update',
                'view.php?id={course_module}', '{progcontest}');
        $rules[] = new restore_log_rule('progcontest', 'view',
                'view.php?id={course_module}', '{progcontest}');
        $rules[] = new restore_log_rule('progcontest', 'preview',
                'view.php?id={course_module}', '{progcontest}');
        $rules[] = new restore_log_rule('progcontest', 'report',
                'report.php?id={course_module}', '{progcontest}');
        $rules[] = new restore_log_rule('progcontest', 'editquestions',
                'view.php?id={course_module}', '{progcontest}');
        $rules[] = new restore_log_rule('progcontest', 'delete attempt',
                'report.php?id={course_module}', '[oldattempt]');
        $rules[] = new restore_log_rule('progcontest', 'edit override',
                'overrideedit.php?id={progcontest_override}', '{progcontest}');
        $rules[] = new restore_log_rule('progcontest', 'delete override',
                'overrides.php.php?cmid={course_module}', '{progcontest}');
        $rules[] = new restore_log_rule('progcontest', 'addcategory',
                'view.php?id={course_module}', '{question_category}');
        $rules[] = new restore_log_rule('progcontest', 'view summary',
                'summary.php?attempt={progcontest_attempt}', '{progcontest}');
        $rules[] = new restore_log_rule('progcontest', 'manualgrade',
                'comment.php?attempt={progcontest_attempt}&question={question}', '{progcontest}');
        $rules[] = new restore_log_rule('progcontest', 'manualgrading',
                'report.php?mode=grading&q={progcontest}', '{progcontest}');
        // All the ones calling to review.php have two rules to handle both old and new urls
        // in any case they are always converted to new urls on restore.
        // TODO: In Moodle 2.x (x >= 5) kill the old rules.
        // Note we are using the 'progcontest_attempt' mapping because that is the
        // one containing the progcontest_attempt->ids old an new for progcontest-attempt.
        $rules[] = new restore_log_rule('progcontest', 'attempt',
                'review.php?id={course_module}&attempt={progcontest_attempt}', '{progcontest}',
                null, null, 'review.php?attempt={progcontest_attempt}');
        $rules[] = new restore_log_rule('progcontest', 'attempt',
                'review.php?attempt={progcontest_attempt}', '{progcontest}',
                null, null, 'review.php?attempt={progcontest_attempt}');
        // Old an new for progcontest-submit.
        $rules[] = new restore_log_rule('progcontest', 'submit',
                'review.php?id={course_module}&attempt={progcontest_attempt}', '{progcontest}',
                null, null, 'review.php?attempt={progcontest_attempt}');
        $rules[] = new restore_log_rule('progcontest', 'submit',
                'review.php?attempt={progcontest_attempt}', '{progcontest}');
        // Old an new for progcontest-review.
        $rules[] = new restore_log_rule('progcontest', 'review',
                'review.php?id={course_module}&attempt={progcontest_attempt}', '{progcontest}',
                null, null, 'review.php?attempt={progcontest_attempt}');
        $rules[] = new restore_log_rule('progcontest', 'review',
                'review.php?attempt={progcontest_attempt}', '{progcontest}');
        // Old an new for progcontest-start attemp.
        $rules[] = new restore_log_rule('progcontest', 'start attempt',
                'review.php?id={course_module}&attempt={progcontest_attempt}', '{progcontest}',
                null, null, 'review.php?attempt={progcontest_attempt}');
        $rules[] = new restore_log_rule('progcontest', 'start attempt',
                'review.php?attempt={progcontest_attempt}', '{progcontest}');
        // Old an new for progcontest-close attemp.
        $rules[] = new restore_log_rule('progcontest', 'close attempt',
                'review.php?id={course_module}&attempt={progcontest_attempt}', '{progcontest}',
                null, null, 'review.php?attempt={progcontest_attempt}');
        $rules[] = new restore_log_rule('progcontest', 'close attempt',
                'review.php?attempt={progcontest_attempt}', '{progcontest}');
        // Old an new for progcontest-continue attempt.
        $rules[] = new restore_log_rule('progcontest', 'continue attempt',
                'review.php?id={course_module}&attempt={progcontest_attempt}', '{progcontest}',
                null, null, 'review.php?attempt={progcontest_attempt}');
        $rules[] = new restore_log_rule('progcontest', 'continue attempt',
                'review.php?attempt={progcontest_attempt}', '{progcontest}');
        // Old an new for progcontest-continue attemp.
        $rules[] = new restore_log_rule('progcontest', 'continue attemp',
                'review.php?id={course_module}&attempt={progcontest_attempt}', '{progcontest}',
                null, 'continue attempt', 'review.php?attempt={progcontest_attempt}');
        $rules[] = new restore_log_rule('progcontest', 'continue attemp',
                'review.php?attempt={progcontest_attempt}', '{progcontest}',
                null, 'continue attempt');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('progcontest', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
