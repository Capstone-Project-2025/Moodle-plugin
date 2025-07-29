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

namespace mod_progcontest;

use mod_progcontest_display_options;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/progcontest/locallib.php');

/**
 * Unit tests for {@link mod_progcontest_display_options}.
 *
 * @package    mod_progcontest
 * @category   test
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progcontestdisplayoptions_test extends \basic_testcase {
    public function test_num_attempts_access_rule() {
        $progcontest = new \stdClass();
        $progcontest->decimalpoints = 2;
        $progcontest->questiondecimalpoints = -1;
        $progcontest->reviewattempt          = 0x11110;
        $progcontest->reviewcorrectness      = 0x10000;
        $progcontest->reviewmarks            = 0x01110;
        $progcontest->reviewspecificfeedback = 0x10000;
        $progcontest->reviewgeneralfeedback  = 0x01000;
        $progcontest->reviewrightanswer      = 0x00100;
        $progcontest->reviewoverallfeedback  = 0x00010;

        $options = mod_progcontest_display_options::make_from_progcontest($progcontest,
            mod_progcontest_display_options::DURING);

        $this->assertEquals(true, $options->attempt);
        $this->assertEquals(mod_progcontest_display_options::VISIBLE, $options->correctness);
        $this->assertEquals(mod_progcontest_display_options::MAX_ONLY, $options->marks);
        $this->assertEquals(mod_progcontest_display_options::VISIBLE, $options->feedback);
        // The next two should be controlled by the same settings as ->feedback.
        $this->assertEquals(mod_progcontest_display_options::VISIBLE, $options->numpartscorrect);
        $this->assertEquals(mod_progcontest_display_options::VISIBLE, $options->manualcomment);
        $this->assertEquals(2, $options->markdp);

        $progcontest->questiondecimalpoints = 5;
        $options = mod_progcontest_display_options::make_from_progcontest($progcontest,
            mod_progcontest_display_options::IMMEDIATELY_AFTER);

        $this->assertEquals(mod_progcontest_display_options::MARK_AND_MAX, $options->marks);
        $this->assertEquals(mod_progcontest_display_options::VISIBLE, $options->generalfeedback);
        $this->assertEquals(mod_progcontest_display_options::HIDDEN, $options->feedback);
        // The next two should be controlled by the same settings as ->feedback.
        $this->assertEquals(mod_progcontest_display_options::HIDDEN, $options->numpartscorrect);
        $this->assertEquals(mod_progcontest_display_options::HIDDEN, $options->manualcomment);
        $this->assertEquals(5, $options->markdp);

        $options = mod_progcontest_display_options::make_from_progcontest($progcontest,
            mod_progcontest_display_options::LATER_WHILE_OPEN);

        $this->assertEquals(mod_progcontest_display_options::VISIBLE, $options->rightanswer);
        $this->assertEquals(mod_progcontest_display_options::HIDDEN, $options->generalfeedback);

        $options = mod_progcontest_display_options::make_from_progcontest($progcontest,
            mod_progcontest_display_options::AFTER_CLOSE);

        $this->assertEquals(mod_progcontest_display_options::VISIBLE, $options->overallfeedback);
        $this->assertEquals(mod_progcontest_display_options::HIDDEN, $options->rightanswer);
    }
}
