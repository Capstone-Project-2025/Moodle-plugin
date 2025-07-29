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
 * This page is the entry page into the progcontest UI. Displays information about the
 * progcontest to students and teachers, and lets students see their previous attempts.
 *
 * @package   mod_progcontest
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/mod/progcontest/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/format/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or ...
$q = optional_param('q',  0, PARAM_INT);  // Programmingcontest ID.

if ($id) {
    if (!$cm = get_coursemodule_from_id('progcontest', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
} else {
    if (!$progcontest = $DB->get_record('progcontest', array('id' => $q))) {
        print_error('invalidprogcontestid', 'progcontest');
    }
    if (!$course = $DB->get_record('course', array('id' => $progcontest->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("progcontest", $progcontest->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

// Check login and get context.
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/progcontest:view', $context);

// Cache some other capabilities we use several times.
$canattempt = has_capability('mod/progcontest:attempt', $context);
$canreviewmine = has_capability('mod/progcontest:reviewmyattempts', $context);
$canpreview = has_capability('mod/progcontest:preview', $context);

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();
$progcontestobj = progcontest::create($cm->instance, $USER->id);
$accessmanager = new progcontest_access_manager($progcontestobj, $timenow,
        has_capability('mod/progcontest:ignoretimelimits', $context, null, false));
$progcontest = $progcontestobj->get_progcontest();

// Trigger course_module_viewed event and completion.
progcontest_view($progcontest, $course, $cm, $context);

// Initialize $PAGE, compute blocks.
$PAGE->set_url('/mod/progcontest/view.php', array('id' => $cm->id));

// Create view object which collects all the information the renderer will need.
$viewobj = new mod_progcontest_view_object();
$viewobj->accessmanager = $accessmanager;
$viewobj->canreviewmine = $canreviewmine || $canpreview;

// Get this user's attempts.
$attempts = progcontest_get_user_attempts($progcontest->id, $USER->id, 'finished', true);
$lastfinishedattempt = end($attempts);
$unfinished = false;
$unfinishedattemptid = null;
if ($unfinishedattempt = progcontest_get_user_attempt_unfinished($progcontest->id, $USER->id)) {
    $attempts[] = $unfinishedattempt;

    // If the attempt is now overdue, deal with that - and pass isonline = false.
    // We want the student notified in this case.
    $progcontestobj->create_attempt_object($unfinishedattempt)->handle_if_time_expired(time(), false);

    $unfinished = $unfinishedattempt->state == progcontest_attempt::IN_PROGRESS ||
            $unfinishedattempt->state == progcontest_attempt::OVERDUE;
    if (!$unfinished) {
        $lastfinishedattempt = $unfinishedattempt;
    }
    $unfinishedattemptid = $unfinishedattempt->id;
    $unfinishedattempt = null; // To make it clear we do not use this again.
}
$numattempts = count($attempts);

$viewobj->attempts = $attempts;
$viewobj->attemptobjs = array();
foreach ($attempts as $attempt) {
    $viewobj->attemptobjs[] = new progcontest_attempt($attempt, $progcontest, $cm, $course, false);
}

// Work out the final grade, checking whether it was overridden in the gradebook.
if (!$canpreview) {
    $mygrade = progcontest_get_best_grade($progcontest, $USER->id);
} else if ($lastfinishedattempt) {
    // Users who can preview the progcontest don't get a proper grade, so work out a
    // plausible value to display instead, so the page looks right.
    $mygrade = progcontest_rescale_grade($lastfinishedattempt->sumgrades, $progcontest, false);
} else {
    $mygrade = null;
}

$mygradeoverridden = false;
$gradebookfeedback = '';

$item = null;

$grading_info = grade_get_grades($course->id, 'mod', 'progcontest', $progcontest->id, $USER->id);
if (!empty($grading_info->items)) {
    $item = $grading_info->items[0];
    if (isset($item->grades[$USER->id])) {
        $grade = $item->grades[$USER->id];

        if ($grade->overridden) {
            $mygrade = $grade->grade + 0; // Convert to number.
            $mygradeoverridden = true;
        }
        if (!empty($grade->str_feedback)) {
            $gradebookfeedback = $grade->str_feedback;
        }
    }
}

$title = $course->shortname . ': ' . format_string($progcontest->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
$output = $PAGE->get_renderer('mod_progcontest');

// Print table with existing attempts.
if ($attempts) {
    // Work out which columns we need, taking account what data is available in each attempt.
    list($someoptions, $alloptions) = progcontest_get_combined_reviewoptions($progcontest, $attempts);

    $viewobj->attemptcolumn  = $progcontest->attempts != 1;

    $viewobj->gradecolumn    = $someoptions->marks >= question_display_options::MARK_AND_MAX &&
            progcontest_has_grades($progcontest);
    $viewobj->markcolumn     = $viewobj->gradecolumn && ($progcontest->grade != $progcontest->sumgrades);
    $viewobj->overallstats   = $lastfinishedattempt && $alloptions->marks >= question_display_options::MARK_AND_MAX;

    $viewobj->feedbackcolumn = progcontest_has_feedback($progcontest) && $alloptions->overallfeedback;
}

$viewobj->timenow = $timenow;
$viewobj->numattempts = $numattempts;
$viewobj->mygrade = $mygrade;
$viewobj->moreattempts = $unfinished ||
        !$accessmanager->is_finished($numattempts, $lastfinishedattempt);
$viewobj->mygradeoverridden = $mygradeoverridden;
$viewobj->gradebookfeedback = $gradebookfeedback;
$viewobj->lastfinishedattempt = $lastfinishedattempt;
$viewobj->canedit = has_capability('mod/progcontest:manage', $context);
$viewobj->editurl = new moodle_url('/mod/progcontest/edit.php', array('cmid' => $cm->id));
$viewobj->backtocourseurl = new moodle_url('/course/view.php', array('id' => $course->id));
$viewobj->startattempturl = $progcontestobj->start_attempt_url();

if ($accessmanager->is_preflight_check_required($unfinishedattemptid)) {
    $viewobj->preflightcheckform = $accessmanager->get_preflight_check_form(
            $viewobj->startattempturl, $unfinishedattemptid);
}
$viewobj->popuprequired = $accessmanager->attempt_must_be_in_popup();
$viewobj->popupoptions = $accessmanager->get_popup_options();

// Display information about this progcontest.
$viewobj->infomessages = $viewobj->accessmanager->describe_rules();
if ($progcontest->attempts != 1) {
    $viewobj->infomessages[] = get_string('gradingmethod', 'progcontest',
            progcontest_get_grading_option_name($progcontest->grademethod));
}

// Inform user of the grade to pass if non-zero.
if ($item && grade_floats_different($item->gradepass, 0)) {
    $a = new stdClass();
    $a->grade = progcontest_format_grade($progcontest, $item->gradepass);
    $a->maxgrade = progcontest_format_grade($progcontest, $progcontest->grade);
    $viewobj->infomessages[] = get_string('gradetopassoutof', 'progcontest', $a);
}

// Determine wheter a start attempt button should be displayed.
$viewobj->progcontesthasquestions = $progcontestobj->has_questions();
$viewobj->preventmessages = array();
if (!$viewobj->progcontesthasquestions) {
    $viewobj->buttontext = '';

} else {
    if ($unfinished) {
        if ($canattempt) {
            $viewobj->buttontext = get_string('continueattemptprogcontest', 'progcontest');
        } else if ($canpreview) {
            $viewobj->buttontext = get_string('continuepreview', 'progcontest');
        }

    } else {
        if ($canattempt) {
            $viewobj->preventmessages = $viewobj->accessmanager->prevent_new_attempt(
                    $viewobj->numattempts, $viewobj->lastfinishedattempt);
            if ($viewobj->preventmessages) {
                $viewobj->buttontext = '';
            } else if ($viewobj->numattempts == 0) {
                $viewobj->buttontext = get_string('attemptprogcontestnow', 'progcontest');
            } else {
                $viewobj->buttontext = get_string('reattemptprogcontest', 'progcontest');
            }

        } else if ($canpreview) {
            $viewobj->buttontext = get_string('previewprogcontestnow', 'progcontest');
        }
    }

    // If, so far, we think a button should be printed, so check if they will be
    // allowed to access it.
    if ($viewobj->buttontext) {
        if (!$viewobj->moreattempts) {
            $viewobj->buttontext = '';
        } else if ($canattempt
                && $viewobj->preventmessages = $viewobj->accessmanager->prevent_access()) {
            $viewobj->buttontext = '';
        }
    }
}

$viewobj->showbacktocourse = ($viewobj->buttontext === '' &&
        course_get_format($course)->has_view_page());

echo $OUTPUT->header();

if (isguestuser()) {
    // Guests can't do a progcontest, so offer them a choice of logging in or going back.
    echo $output->view_page_guest($course, $progcontest, $cm, $context, $viewobj->infomessages);
} else if (!isguestuser() && !($canattempt || $canpreview
          || $viewobj->canreviewmine)) {
    // If they are not enrolled in this course in a good enough role, tell them to enrol.
    echo $output->view_page_notenrolled($course, $progcontest, $cm, $context, $viewobj->infomessages);
} else {
    echo $output->view_page($course, $progcontest, $cm, $context, $viewobj);
}

echo $OUTPUT->footer();
