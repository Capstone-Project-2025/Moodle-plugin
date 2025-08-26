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
 * Page to edit progcontests
 *
 * @package    mod_progcontest
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/progcontest/locallib.php');
require_once($CFG->dirroot . '/mod/progcontest/addrandomform.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/category_class.php');

// These params are only passed from page request to request while we stay on
// this page otherwise they would go in question_edit_setup.
$scrollpos = optional_param('scrollpos', '', PARAM_INT);



list($thispageurl, $contexts, $cmid, $cm, $progcontest, $pagevars) =
    question_edit_setup('editq', '/mod/progcontest/edit.php', true);

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$progcontesthasattempts = progcontest_has_attempts($progcontest->id);

$PAGE->set_url($thispageurl);

$course = $DB->get_record('course', array('id' => $progcontest->course), '*', MUST_EXIST);
$progcontestobj = new progcontest($progcontest, $cm, $course);
$structure = $progcontestobj->get_structure();

// You need mod/progcontest:manage in addition to question capabilities to access this page.
require_capability('mod/progcontest:manage', $contexts->lowest());

// Log this visit.
$params = array(
    'courseid' => $course->id,
    'context' => $contexts->lowest(),
    'other' => array('progcontestid' => $progcontest->id)
);
$event = \mod_progcontest\event\edit_page_viewed::create($params);
$event->trigger();

// Process commands ============================================================.

// Get the list of question ids had their check-boxes ticked.
$selectedslots = array();
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedslots[] = $matches[1];
    }
}

$afteractionurl = new moodle_url($thispageurl);
if ($scrollpos) {
    $afteractionurl->param('scrollpos', $scrollpos);
}

if (optional_param('repaginate', false, PARAM_BOOL) && confirm_sesskey()) {
    $structure->check_can_be_edited();
    $questionsperpage = optional_param('questionsperpage', $progcontest->questionsperpage, PARAM_INT);
    progcontest_repaginate_questions($progcontest->id, $questionsperpage);
    progcontest_delete_previews($progcontest);
    redirect($afteractionurl);
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    $structure->check_can_be_edited();

    // 🛡️ Sécurité : autoriser uniquement certains types.
    $allowedqtypes = ['truefalse', 'numerical', 'programming'];
    $question = $DB->get_record('question', ['id' => $addquestion], '*', MUST_EXIST);

    if (!in_array($question->qtype, $allowedqtypes)) {
        print_error('unsupportedqtype', 'progcontest', '', $question->qtype);
    }

    // ✅ Si tout va bien, continuer normalement.
    progcontest_require_question_use($addquestion);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    progcontest_add_progcontest_question($addquestion, $progcontest, $addonpage);
    progcontest_delete_previews($progcontest);
    progcontest_update_sumgrades($progcontest);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}


if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $structure->check_can_be_edited();
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) {
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            progcontest_require_question_use($key);
            add_progcontest_question($key, $progcontest, $addonpage);
        }
    }
    progcontest_delete_previews($progcontest);
    progcontest_update_sumgrades($progcontest);
    redirect($afteractionurl);
}

if ($addsectionatpage = optional_param('addsectionatpage', false, PARAM_INT)) {
    $structure->check_can_be_edited();
    $structure->add_section_heading($addsectionatpage);
    progcontest_delete_previews($progcontest);
    redirect($afteractionurl);
}

if ((optional_param('addrandom', false, PARAM_BOOL)) && confirm_sesskey()) {
    $structure->check_can_be_edited();
    $recurse = optional_param('recurse', 0, PARAM_BOOL);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $randomcount = required_param('randomcount', PARAM_INT);
    add_progcontest_random_questions($progcontest, $addonpage, $categoryid, $randomcount, $recurse);

    progcontest_delete_previews($progcontest);
    progcontest_update_sumgrades($progcontest);
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {
    $maxgrade = unformat_float(optional_param('maxgrade', '', PARAM_RAW_TRIMMED), true);
    if (is_float($maxgrade) && $maxgrade >= 0) {
        progcontest_set_grade($maxgrade, $progcontest);
        progcontest_update_all_final_grades($progcontest);
        progcontest_update_grades($progcontest, 0, true);
    }

    redirect($afteractionurl);
}

// Get the question bank view.
$questionbank = new mod_progcontest\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $progcontest);
$questionbank->set_progcontest_has_attempts($progcontesthasattempts);
$questionbank->process_actions($thispageurl, $cm);

// End of process commands =====================================================.

$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('mod-progcontest-edit');

$output = $PAGE->get_renderer('mod_progcontest', 'edit');

$PAGE->set_title(get_string('editingprogcontestx', 'progcontest', format_string($progcontest->name)));
$PAGE->set_heading($course->fullname);
$node = $PAGE->settingsnav->find('mod_progcontest_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}

echo $OUTPUT->header();

// Initialise the JavaScript.
$progcontesteditconfig = new stdClass();
$progcontesteditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$progcontesteditconfig->dialoglisteners = array();
$numberoflisteners = $DB->get_field_sql("
    SELECT COALESCE(MAX(page), 1)
      FROM {progcontest_slots}
     WHERE progcontestid = ?", array($progcontest->id));

for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $progcontesteditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}

$PAGE->requires->data_for_js('progcontest_edit_config', $progcontesteditconfig);
$PAGE->requires->js('/question/qengine.js');


// Questions wrapper start.
echo html_writer::start_tag('div', array('class' => 'mod-progcontest-edit-content'));
echo $output->edit_page($progcontestobj, $structure, $contexts, $thispageurl, $pagevars);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
