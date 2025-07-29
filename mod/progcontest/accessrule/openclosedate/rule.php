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
 * Implementaton of the progcontestaccess_openclosedate plugin.
 *
 * @package    progcontestaccess
 * @subpackage openclosedate
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/progcontest/accessrule/accessrulebase.php');


/**
 * A rule enforcing open and close dates.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progcontestaccess_openclosedate extends progcontest_access_rule_base {

    public static function make(progcontest $progcontestobj, $timenow, $canignoretimelimits) {
        // This rule is always used, even if the progcontest has no open or close date.
        return new self($progcontestobj, $timenow);
    }

    public function prevent_access() {
        $message = get_string('notavailable', 'progcontestaccess_openclosedate');

        if ($this->timenow < $this->progcontest->timeopen) {
            return $message;
        }

        if (!$this->progcontest->timeclose) {
            return false;
        }

        if ($this->timenow <= $this->progcontest->timeclose) {
            return false;
        }

        if ($this->progcontest->overduehandling != 'graceperiod') {
            return $message;
        }

        if ($this->timenow <= $this->progcontest->timeclose + $this->progcontest->graceperiod) {
            return false;
        }

        return $message;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        return $this->progcontest->timeclose && $this->timenow > $this->progcontest->timeclose;
    }

    public function end_time($attempt) {
        if ($this->progcontest->timeclose) {
            return $this->progcontest->timeclose;
        }
        return false;
    }

    public function time_left_display($attempt, $timenow) {
        // If this is a teacher preview after the close date, do not show
        // the time.
        if ($attempt->preview && $timenow > $this->progcontest->timeclose) {
            return false;
        }
        // Otherwise, return to the time left until the close date, providing that is
        // less than progcontest_SHOW_TIME_BEFORE_DEADLINE.
        $endtime = $this->end_time($attempt);
        if ($endtime !== false && $timenow > $endtime - progcontest_SHOW_TIME_BEFORE_DEADLINE) {
            return $endtime - $timenow;
        }
        return false;
    }
}
