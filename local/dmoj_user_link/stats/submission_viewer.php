<?php
require('../../../config.php');

$sid = required_param('sid', PARAM_INT);
global $DB;

require_login();

// 🔒 Récupération de la soumission
$submission = $DB->get_record('qtype_prog_submission', ['submission_id' => $sid], '*', MUST_EXIST);

// 🔐 Vérification des droits
$context = context_user::instance($submission->user_id);
if ($USER->id !== $submission->user_id && !has_capability('moodle/user:viewdetails', $context)) {
    throw new required_capability_exception($context, 'moodle/user:viewdetails', 'nopermissions', '');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/dmoj_user_link/stats/submission_viewer.php', ['sid' => $sid]));
$PAGE->set_title("Submission #$sid");
$PAGE->set_heading("Submission details");

echo $OUTPUT->header();
echo $OUTPUT->heading("Submission #$sid");

// 📚 Données associées à la soumission
$question = $DB->get_record('qtype_prog_options', ['id' => $submission->question_id], '*', MUST_EXIST);
$problem = $DB->get_record('local_dmoj_user_linkproblem', ['id' => $question->problem_id], '*', MUST_EXIST);

// ✅ Nouveau : récupérer le nom du langage via language_id
$language = $DB->get_field('local_dmoj_user_linklanguage', 'name', ['id' => $submission->language_id]) ?? '---';

// ✅ Conversion du code de résultat (AC, WA...) en texte lisible
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

$templatecontext = [
    'problemname' => $problem->name,
    'language' => $language,
    'status' => $status,
    'points' => $submission->point,
    'totalpoints' => $submission->total_point,
    'sourcecode' => $submission->code
];

// 🎨 Affichage via Mustache
echo $OUTPUT->render_from_template('local_dmoj_user_link/submission_viewer', $templatecontext);

echo $OUTPUT->footer();
