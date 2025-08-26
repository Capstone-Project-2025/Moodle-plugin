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
 * Library code used by progcontest cron.
 *
 * @package   mod_progcontest
 * @copyright 2012 the Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/progcontest/locallib.php');


/**
 * This class holds all the code for automatically updating all attempts that have
 * gone over their time limit.
 *
 * @copyright 2012 the Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_progcontest_overdue_attempt_updater {

    /**
     * Do the processing required.
     * @param int $timenow the time to consider as 'now' during the processing.
     * @param int $processto only process attempt with timecheckstate longer ago than this.
     * @return array with two elements, the number of attempt considered, and how many different progcontestzes that was.
     */
    public function update_overdue_attempts($timenow, $processto) {
        global $DB;

        $attemptstoprocess = $this->get_list_of_overdue_attempts($processto);

        $course = null;
        $progcontest = null;
        $cm = null;

        $count = 0;
        $progcontestcount = 0;
        foreach ($attemptstoprocess as $attempt) {
            try {

                // If we have moved on to a different progcontest, fetch the new data.
                if (!$progcontest || $attempt->progcontest != $progcontest->id) {
                    $progcontest = $DB->get_record('progcontest', array('id' => $attempt->progcontest), '*', MUST_EXIST);
                    $cm = get_coursemodule_from_instance('progcontest', $attempt->progcontest);
                    $progcontestcount += 1;
                }

                // If we have moved on to a different course, fetch the new data.
                if (!$course || $course->id != $progcontest->course) {
                    $course = $DB->get_record('course', array('id' => $progcontest->course), '*', MUST_EXIST);
                }

                // Make a specialised version of the progcontest settings, with the relevant overrides.
                $progcontestforuser = clone($progcontest);
                $progcontestforuser->timeclose = $attempt->usertimeclose;
                $progcontestforuser->timelimit = $attempt->usertimelimit;

                // Trigger any transitions that are required.
                $attemptobj = new progcontest_attempt($attempt, $progcontestforuser, $cm, $course);
                $attemptobj->handle_if_time_expired($timenow, false);
                $count += 1;

            } catch (moodle_exception $e) {
                // If an error occurs while processing one attempt, don't let that kill cron.
                mtrace("Error while processing attempt {$attempt->id} at {$attempt->progcontest} progcontest:");
                mtrace($e->getMessage());
                mtrace($e->getTraceAsString());
                // Close down any currently open transactions, otherwise one error
                // will stop following DB changes from being committed.
                $DB->force_transaction_rollback();
            }
        }

        $attemptstoprocess->close();
        return array($count, $progcontestcount);
    }

    /**
     * @return moodle_recordset of progcontest_attempts that need to be processed because time has
     *     passed. The array is sorted by courseid then progcontestid.
     */
    public function get_list_of_overdue_attempts($processto) {
        global $DB;


        // SQL to compute timeclose and timelimit for each attempt:
        $progcontestausersql = progcontest_get_attempt_usertime_sql(
                "iprogcontesta.state IN ('inprogress', 'overdue') AND iprogcontesta.timecheckstate <= :iprocessto");

        // This query should have all the progcontest_attempts columns.
        return $DB->get_recordset_sql("
         SELECT progcontesta.*,
                progcontestauser.usertimeclose,
                progcontestauser.usertimelimit

           FROM {progcontest_attempts} progcontesta
           JOIN {progcontest} progcontest ON progcontest.id = progcontesta.progcontest
           JOIN ( $progcontestausersql ) progcontestauser ON progcontestauser.id = progcontesta.id

          WHERE progcontesta.state IN ('inprogress', 'overdue')
            AND progcontesta.timecheckstate <= :processto
       ORDER BY progcontest.course, progcontesta.progcontest",

                array('processto' => $processto, 'iprocessto' => $processto));
    }
}
