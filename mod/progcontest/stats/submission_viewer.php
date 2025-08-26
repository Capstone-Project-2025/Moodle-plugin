<?php
require('../../../config.php');

$sid = required_param('sid', PARAM_INT); // ID de la soumission

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

// 🔍 Récupération de la soumission (depuis la bonne table)
$submission = $DB->get_record('qtype_programming_submission', ['submission_id' => $sid], '*', MUST_EXIST);

// 🔒 Contexte = celui du cours (pour les capacités + navigation)
$course = $DB->get_record('course', ['id' => $submission->course_id], '*', MUST_EXIST);
$context = context_course::instance($course->id);

// 🔐 Vérification des droits
if ($USER->id !== $submission->user_id && !has_capability('moodle/course:viewparticipants', $context)) {
    throw new required_capability_exception($context, 'moodle/course:viewparticipants', 'nopermissions', '');
}

// 📄 Définition de la page
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/progcontest/stats/submission_viewer.php', ['sid' => $sid]));
$PAGE->set_title("Submission #$sid");
$PAGE->set_heading("Submission details");

echo $OUTPUT->header();
echo $OUTPUT->heading("Submission #$sid");

// 🧩 Récupération des données associées
$option = $DB->get_record('qtype_programming_options', ['questionid' => $submission->question_id]);

if ($option) {
    $problem = $DB->get_record('programming_problem', ['id' => $option->problem_id]);
    $problemname = $problem->name ?? 'Unknown problem';
} else {
    $problemname = 'Unknown problem';
}

// 🖥️ Nom du langage
$language = $DB->get_field('programming_language', 'name', ['id' => $submission->language_id]) ?? '---';

// 🏁 Traduction du statut
$statuslabels = [
    'AC' => 'Accepted',
    'WA' => 'Wrong Answer',
    'TLE' => 'Time Limit Exceeded',
    'MLE' => 'Memory Limit Exceeded',
    'OLE' => 'Output Limit Exceeded',
    'IRE' => 'Invalid Return',
    'RE' => 'Runtime Error',
    'CE' => 'Compilation Error',
    'IE' => 'Internal Error',
    'AB' => 'Abandoned',
    '---' => 'Unknown'
];
$status = $statuslabels[$submission->result] ?? $submission->result;

// 🔢 Données à envoyer au template Mustache
$templatecontext = [
    'problemname' => $problemname,
    'language' => $language,
    'status' => $status,
    'points' => $submission->point,
    'totalpoints' => $submission->total_point,
    'sourcecode' => $submission->code
];

// 🎨 Affichage via Mustache
echo $OUTPUT->render_from_template('mod_progcontest/submission_viewer', $templatecontext);

echo $OUTPUT->footer();
