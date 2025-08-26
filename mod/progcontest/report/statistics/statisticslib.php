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
 * Common functions for the progcontest statistics report.
 *
 * @package    progcontest_statistics
 * @copyright  2013 The Open University
 * @author     James Pratt me@jamiep.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * SQL to fetch relevant 'progcontest_attempts' records.
 *
 * @param int    $progcontestid        progcontest id to get attempts for
 * @param \core\dml\sql_join $groupstudentsjoins Contains joins, wheres, params, empty if not using groups
 * @param string $whichattempts which attempts to use, represented internally as one of the constants as used in
 *                                   $progcontest->grademethod ie.
 *                                   progcontest_GRADEAVERAGE, progcontest_GRADEHIGHEST, progcontest_ATTEMPTLAST or progcontest_ATTEMPTFIRST
 *                                   we calculate stats based on which attempts would affect the grade for each student.
 * @param bool   $includeungraded whether to fetch ungraded attempts too
 * @return array FROM and WHERE sql fragments and sql params
 */
function progcontest_statistics_attempts_sql($progcontestid, \core\dml\sql_join $groupstudentsjoins,
        $whichattempts = progcontest_GRADEAVERAGE, $includeungraded = false) {
    $fromqa = "{progcontest_attempts} progcontesta ";
    $whereqa = 'progcontesta.progcontest = :progcontestid AND progcontesta.preview = 0 AND progcontesta.state = :progconteststatefinished';
    $qaparams = array('progcontestid' => (int)$progcontestid, 'progconteststatefinished' => progcontest_attempt::FINISHED);

    if (!empty($groupstudentsjoins->joins)) {
        $fromqa .= "\nJOIN {user} u ON u.id = progcontesta.userid
            {$groupstudentsjoins->joins} ";
        $whereqa .= " AND {$groupstudentsjoins->wheres}";
        $qaparams += $groupstudentsjoins->params;
    }

    $whichattemptsql = progcontest_report_grade_method_sql($whichattempts);
    if ($whichattemptsql) {
        $whereqa .= ' AND ' . $whichattemptsql;
    }

    if (!$includeungraded) {
        $whereqa .= ' AND progcontesta.sumgrades IS NOT NULL';
    }

    return array($fromqa, $whereqa, $qaparams);
}

/**
 * Return a {@link qubaid_condition} from the values returned by {@link progcontest_statistics_attempts_sql}.
 *
 * @param int     $progcontestid
 * @param \core\dml\sql_join $groupstudentsjoins Contains joins, wheres, params
 * @param string $whichattempts which attempts to use, represented internally as one of the constants as used in
 *                                   $progcontest->grademethod ie.
 *                                   progcontest_GRADEAVERAGE, progcontest_GRADEHIGHEST, progcontest_ATTEMPTLAST or progcontest_ATTEMPTFIRST
 *                                   we calculate stats based on which attempts would affect the grade for each student.
 * @param bool    $includeungraded
 * @return        \qubaid_join
 */
function progcontest_statistics_qubaids_condition($progcontestid, \core\dml\sql_join $groupstudentsjoins,
        $whichattempts = progcontest_GRADEAVERAGE, $includeungraded = false) {
    list($fromqa, $whereqa, $qaparams) = progcontest_statistics_attempts_sql(
            $progcontestid, $groupstudentsjoins, $whichattempts, $includeungraded);
    return new qubaid_join($fromqa, 'progcontesta.uniqueid', $whereqa, $qaparams);
}

/**
 * This helper function returns a sequence of colours each time it is called.
 * Used for choosing colours for graph data series.
 * @return string colour name.
 * @deprecated since Moodle 3.2
 */
function progcontest_statistics_graph_get_new_colour() {
    debugging('The function progcontest_statistics_graph_get_new_colour() is deprecated, please do not use it any more. '
        . 'Colours will be handled by the charting library directly.', DEBUG_DEVELOPER);

    static $colourindex = -1;
    $colours = array('red', 'green', 'yellow', 'orange', 'purple', 'black',
        'maroon', 'blue', 'ltgreen', 'navy', 'ltred', 'ltltgreen', 'ltltorange',
        'olive', 'gray', 'ltltred', 'ltorange', 'lime', 'ltblue', 'ltltblue');

    $colourindex = ($colourindex + 1) % count($colours);

    return $colours[$colourindex];
}
