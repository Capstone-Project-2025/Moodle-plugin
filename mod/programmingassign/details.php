<?php
require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$assignment = $DB->get_record('programmingassign', ['id' => $id], '*', MUST_EXIST);
$course = get_course($assignment->course);
$cm = get_coursemodule_from_instance('programmingassign', $assignment->id, $assignment->course, false, MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/programmingassign:view', $context);

$PAGE->set_url('/mod/programmingassign/details.php', ['id' => $id]);
$PAGE->set_title($assignment->name);
$PAGE->set_heading($course->fullname);

// Affichage
echo $OUTPUT->header();

// En-tête
echo html_writer::start_div('box generalbox', ['style' => 'max-width: 800px; margin: 0 auto; padding: 30px; background-color: #f9f9f9; border-radius: 10px;']);

echo html_writer::tag('h2', format_string($assignment->name), ['style' => 'margin-bottom: 20px; text-align: center; color: #004085;']);

// Métadonnées
echo html_writer::start_div('', ['style' => 'margin-bottom: 20px;']);
echo html_writer::tag('p', '<strong>Language:</strong> ' . s($assignment->language));
echo html_writer::tag('p', '<strong>Start date:</strong> ' . (!empty($assignment->allowsubmissionsfromdate) ? userdate($assignment->allowsubmissionsfromdate) : '-'));
echo html_writer::tag('p', '<strong>Due date:</strong> ' . (!empty($assignment->duedate) ? userdate($assignment->duedate) : '-'));
echo html_writer::end_div();

// Description
if (!empty($assignment->description)) {
    echo html_writer::tag('h4', 'Description', ['style' => 'margin-top: 30px;']);
    echo html_writer::div(format_text($assignment->description, $assignment->descriptionformat), 'content', ['style' => 'margin-bottom: 20px;']);
}

// Test cases
if (!empty($assignment->testcases)) {
    echo html_writer::tag('h4', 'Test Cases');
    echo html_writer::tag('pre', s($assignment->testcases), ['style' => 'background: #f0f0f0; padding: 15px; border-radius: 5px;']);
}

// Bouton retour
$backurl = new moodle_url('/mod/programmingassign/list.php', ['id' => $assignment->course]);
echo html_writer::div(
    html_writer::link($backurl, '← Back to list', ['class' => 'btn btn-secondary', 'style' => 'text-decoration: none; padding: 8px 12px; border-radius: 5px; background-color: #ccc; color: #000;']),
    '', ['style' => 'text-align: center; margin-top: 30px;']
);

echo html_writer::end_div(); // Fin container
echo $OUTPUT->footer();

