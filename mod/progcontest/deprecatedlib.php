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
 * List of deprecated mod_progcontest functions.
 *
 * @package   mod_progcontest
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Internal function used in progcontest_get_completion_state. Check passing grade (or no attempts left) requirement for completion.
 *
 * @deprecated since Moodle 3.11
 * @todo MDL-71196 Final deprecation in Moodle 4.3
 * @see \mod_progcontest\completion\custom_completion
 * @param stdClass $course
 * @param cm_info|stdClass $cm
 * @param int $userid
 * @param stdClass $progcontest
 * @return bool True if the passing grade (or no attempts left) requirement is disabled or met.
 * @throws coding_exception
 */
function progcontest_completion_check_passing_grade_or_all_attempts($course, $cm, $userid, $progcontest) {
    global $CFG;

    debugging('progcontest_completion_check_passing_grade_or_all_attempts has been deprecated.', DEBUG_DEVELOPER);

    if (!$progcontest->completionpass) {
        return true;
    }

    // Check for passing grade.
    require_once($CFG->libdir . '/gradelib.php');
    $item = grade_item::fetch(array('courseid' => $course->id, 'itemtype' => 'mod',
            'itemmodule' => 'progcontest', 'iteminstance' => $cm->instance, 'outcomeid' => null));
    if ($item) {
        $grades = grade_grade::fetch_users_grades($item, array($userid), false);
        if (!empty($grades[$userid]) && $grades[$userid]->is_passed($item)) {
            return true;
        }
    }

    // If a passing grade is required and exhausting all available attempts is not accepted for completion,
    // then this progcontest is not complete.
    if (!$progcontest->completionattemptsexhausted) {
        return false;
    }

    // Check if all attempts are used up.
    $attempts = progcontest_get_user_attempts($progcontest->id, $userid, 'finished', true);
    if (!$attempts) {
        return false;
    }
    $lastfinishedattempt = end($attempts);
    $context = context_module::instance($cm->id);
    $progcontestobj = progcontest::create($progcontest->id, $userid);
    $accessmanager = new progcontest_access_manager($progcontestobj, time(),
            has_capability('mod/progcontest:ignoretimelimits', $context, $userid, false));

    return $accessmanager->is_finished(count($attempts), $lastfinishedattempt);
}

/**
 * Internal function used in progcontest_get_completion_state. Check minimum attempts requirement for completion.
 *
 * @deprecated since Moodle 3.11
 * @todo MDL-71196 Final deprecation in Moodle 4.3
 * @see \mod_progcontest\completion\custom_completion
 * @param int $userid
 * @param stdClass $progcontest
 * @return bool True if minimum attempts requirement is disabled or met.
 */
function progcontest_completion_check_min_attempts($userid, $progcontest) {

    debugging('progcontest_completion_check_min_attempts has been deprecated.', DEBUG_DEVELOPER);

    if (empty($progcontest->completionminattempts)) {
        return true;
    }

    // Check if the user has done enough attempts.
    $attempts = progcontest_get_user_attempts($progcontest->id, $userid, 'finished', true);
    return $progcontest->completionminattempts <= count($attempts);
}

/**
 * Obtains the automatic completion state for this progcontest on any conditions
 * in progcontest settings, such as if all attempts are used or a certain grade is achieved.
 *
 * @deprecated since Moodle 3.11
 * @todo MDL-71196 Final deprecation in Moodle 4.3
 * @see \mod_progcontest\completion\custom_completion
 * @param stdClass $course Course
 * @param cm_info|stdClass $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function progcontest_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // No need to call debugging here. Deprecation debugging notice already being called in \completion_info::internal_get_state().

    $progcontest = $DB->get_record('progcontest', array('id' => $cm->instance), '*', MUST_EXIST);
    if (!$progcontest->completionattemptsexhausted && !$progcontest->completionpass && !$progcontest->completionminattempts) {
        return $type;
    }

    if (!progcontest_completion_check_passing_grade_or_all_attempts($course, $cm, $userid, $progcontest)) {
        return false;
    }

    if (!progcontest_completion_check_min_attempts($userid, $progcontest)) {
        return false;
    }

    return true;
}
