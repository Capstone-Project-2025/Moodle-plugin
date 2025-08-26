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
 * This script lists all the instances of progcontest in a particular course
 *
 * @package    mod_progcontest
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once("../../config.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT);
$PAGE->set_url('/mod/progcontest/index.php', array('id'=>$id));
if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}
$coursecontext = context_course::instance($id);
require_login($course);
$PAGE->set_pagelayout('incourse');

$params = array(
    'context' => $coursecontext
);
$event = \mod_progcontest\event\course_module_instance_list_viewed::create($params);
$event->trigger();

// Print the header.
$strprogcontestzes = get_string("modulenameplural", "progcontest");
$PAGE->navbar->add($strprogcontestzes);
$PAGE->set_title($strprogcontestzes);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($strprogcontestzes, 2);

// Get all the appropriate data.
if (!$progcontestzes = get_all_instances_in_course("progcontest", $course)) {
    notice(get_string('thereareno', 'moodle', $strprogcontestzes), "../../course/view.php?id=$course->id");
    die;
}

// Check if we need the feedback header.
$showfeedback = false;
foreach ($progcontestzes as $progcontest) {
    if (progcontest_has_feedback($progcontest)) {
        $showfeedback=true;
    }
    if ($showfeedback) {
        break;
    }
}

// Configure table for displaying the list of instances.
$headings = array(get_string('name'));
$align = array('left');

array_push($headings, get_string('progcontestcloses', 'progcontest'));
array_push($align, 'left');

if (course_format_uses_sections($course->format)) {
    array_unshift($headings, get_string('sectionname', 'format_'.$course->format));
} else {
    array_unshift($headings, '');
}
array_unshift($align, 'center');

$showing = '';

if (has_capability('mod/progcontest:viewreports', $coursecontext)) {
    array_push($headings, get_string('attempts', 'progcontest'));
    array_push($align, 'left');
    $showing = 'stats';

} else if (has_any_capability(array('mod/progcontest:reviewmyattempts', 'mod/progcontest:attempt'),
        $coursecontext)) {
    array_push($headings, get_string('grade', 'progcontest'));
    array_push($align, 'left');
    if ($showfeedback) {
        array_push($headings, get_string('feedback', 'progcontest'));
        array_push($align, 'left');
    }
    $showing = 'grades';

    $grades = $DB->get_records_sql_menu('
            SELECT qg.progcontest, qg.grade
            FROM {progcontest_grades} qg
            JOIN {progcontest} q ON q.id = qg.progcontest
            WHERE q.course = ? AND qg.userid = ?',
            array($course->id, $USER->id));
}

$table = new html_table();
$table->head = $headings;
$table->align = $align;

// Populate the table with the list of instances.
$currentsection = '';
// Get all closing dates.
$timeclosedates = progcontest_get_user_timeclose($course->id);
foreach ($progcontestzes as $progcontest) {
    $cm = get_coursemodule_from_instance('progcontest', $progcontest->id);
    $context = context_module::instance($cm->id);
    $data = array();

    // Section number if necessary.
    $strsection = '';
    if ($progcontest->section != $currentsection) {
        if ($progcontest->section) {
            $strsection = $progcontest->section;
            $strsection = get_section_name($course, $progcontest->section);
        }
        if ($currentsection !== "") {
            $table->data[] = 'hr';
        }
        $currentsection = $progcontest->section;
    }
    $data[] = $strsection;

    // Link to the instance.
    $class = '';
    if (!$progcontest->visible) {
        $class = ' class="dimmed"';
    }
    $data[] = "<a$class href=\"view.php?id=$progcontest->coursemodule\">" .
            format_string($progcontest->name, true) . '</a>';

    // Close date.
    if (($timeclosedates[$progcontest->id]->usertimeclose != 0)) {
        $data[] = userdate($timeclosedates[$progcontest->id]->usertimeclose);
    } else {
        $data[] = get_string('noclose', 'progcontest');
    }

    if ($showing == 'stats') {
        // The $progcontest objects returned by get_all_instances_in_course have the necessary $cm
        // fields set to make the following call work.
        $data[] = progcontest_attempt_summary_link_to_reports($progcontest, $cm, $context);

    } else if ($showing == 'grades') {
        // Grade and feedback.
        $attempts = progcontest_get_user_attempts($progcontest->id, $USER->id, 'all');
        list($someoptions, $alloptions) = progcontest_get_combined_reviewoptions(
                $progcontest, $attempts);

        $grade = '';
        $feedback = '';
        if ($progcontest->grade && array_key_exists($progcontest->id, $grades)) {
            if ($alloptions->marks >= question_display_options::MARK_AND_MAX) {
                $a = new stdClass();
                $a->grade = progcontest_format_grade($progcontest, $grades[$progcontest->id]);
                $a->maxgrade = progcontest_format_grade($progcontest, $progcontest->grade);
                $grade = get_string('outofshort', 'progcontest', $a);
            }
            if ($alloptions->overallfeedback) {
                $feedback = progcontest_feedback_for_grade($grades[$progcontest->id], $progcontest, $context);
            }
        }
        $data[] = $grade;
        if ($showfeedback) {
            $data[] = $feedback;
        }
    }

    $table->data[] = $data;
} // End of loop over progcontest instances.

// Display the table.
echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();
