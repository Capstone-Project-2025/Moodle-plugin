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
 * Readme file for local customisations
 *
 * @package    local_programming
 * @copyright  Dinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\context\user;

require_once("$CFG->libdir/formslib.php");
require_once(__DIR__ . '/get_unlinked.php');

class UserDataForm extends moodleform {

    function definition() {
        $mform = $this->_form;
        // comments
        $mform->addElement('advcheckbox', 'comment_download', 'download comments?', ' ');
        $mform->addHelpButton('comment_download', 'downloadcommentshelp', 'local_programming');

        // submissions
        $mform->addElement('advcheckbox', 'submission_download', 'download submissions?',' ');
        $mform->addHelpButton('submission_download', 'downloadsubmissionshelp', 'local_programming');

        // Appearing when submission is checked
        // i found mform->hideIf() which also works so i use that instead
        // $mform->addElement('html', '<div id="submission-options" style="display:none;">');

        // Filter by problem code glob
        $mform->addElement('text', 'submission_problem_glob', 'Filter by problem code glob');
        $mform->setType('submission_problem_glob', PARAM_TEXT);
        $mform->setDefault('submission_problem_glob', '*'); // Default to all problems
        $mform->hideIf('submission_problem_glob', 'submission_download', 'notchecked'); // Disable if submission_download is not checked

        // Filter by result
        $choices = [
            'AC' => 'Accepted',
            'WA' => 'Wrong Answer',
            'TLE' => 'Time Limit Exceeded',
            'MLE' => 'Memory Limit Exceeded',
            'OLE' => 'Output Limit Exceeded',
            'IR' => 'Invalid Return',
            'RTE' => 'Runtime Error',
            'CE' => 'Compile Error',
            'IE' => 'Internal Error',
            'SC' => 'Short Circuited',
            'AB' => 'Aborted',
        ];                                                                                                                                         
        $options = array('multiple' => true,);         
        $mform->addElement('autocomplete', 'submission_results', get_string('searcharea', 'search'), $choices, $options);
        $mform->setType('submission_results', PARAM_RAW);
        $mform->hideIf('submission_results', 'submission_download', 'notchecked'); // Disable if submission_download is not checked

        // i found another method from moodleformslib.php so i will use that instead
        // if you want to use raw html for submission options instead, uncomment the lines below
        /*
        $mform->addElement('html', '</div>');

        // Script to toggle the visibility of submission options
        $mform->addElement('html', '
            <script>
            function toggleSubmissionOptions() {
                const checkbox = document.getElementById("id_submission");
                const target = document.getElementById("submission-options");
                if (!checkbox || !target) return;

                target.style.display = checkbox.checked ? "block" : "none";
            }

            document.addEventListener("DOMContentLoaded", function () {
                const checkbox = document.getElementById("id_submission");
                if (checkbox) {
                    checkbox.addEventListener("change", toggleSubmissionOptions);
                    toggleSubmissionOptions(); // Run once on load
                }
            });
            </script>
        ');
        */

        $this->add_action_buttons(true, 'Prepare new User Data');
    }

    function validation($data, $files) {
        return array();
    }
}