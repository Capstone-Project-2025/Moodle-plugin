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
 * Update Overdue Attempts Task
 *
 * @package    mod_progcontest
 * @copyright  2017 Michael Hughes
 * @author Michael Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_progcontest\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/progcontest/locallib.php');

/**
 * Update Overdue Attempts Task
 *
 * @package    mod_progcontest
 * @copyright  2017 Michael Hughes
 * @author Michael Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class update_overdue_attempts extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('updateoverdueattemptstask', 'mod_progcontest');
    }

    /**
     *
     * Close off any overdue attempts.
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/mod/progcontest/cronlib.php');
        $timenow = time();
        $overduehander = new \mod_progcontest_overdue_attempt_updater();

        $processto = $timenow - get_config('progcontest', 'graceperiodmin');

        mtrace('  Looking for progcontest overdue progcontest attempts...');

        list($count, $progcontestcount) = $overduehander->update_overdue_attempts($timenow, $processto);

        mtrace('  Considered ' . $count . ' attempts in ' . $progcontestcount . ' progcontestzes.');
    }
}
