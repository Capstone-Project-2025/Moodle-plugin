<?php
require_once(__DIR__ . '/../../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$confirm = optional_param('confirm', 0, PARAM_BOOL);

$PAGE->set_url(new moodle_url('/question/type/programming/cleandelete.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Delete all programming questions');
$PAGE->set_heading('Delete all programming questions');

echo $OUTPUT->header();

// Step 0: Confirm action
if (!$confirm) {
    $continueurl = new moodle_url('/question/type/programming/cleandelete.php', ['confirm' => 1]);
    $cancelurl = new moodle_url('/admin/settings.php', ['section' => 'qtype_programming_settings']);

    echo $OUTPUT->confirm(
        'Are you sure you want to permanently delete all programming questions and all related data, including quiz and progcontest attempts?',
        new single_button($continueurl, 'Yes, delete all', 'post'),
        new single_button($cancelurl, 'Cancel', 'get')
    );
    echo $OUTPUT->footer();
    exit;
}

require_once($CFG->dirroot . '/question/lib.php');

global $DB;

$questions = $DB->get_records('question', ['qtype' => 'programming']);

if (!empty($questions)) {
    $questionids = array_keys($questions);
    list($insql, $params) = $DB->get_in_or_equal($questionids);

    // Step 1: Delete from plugin tables
    $DB->delete_records_select('qtype_programming_submission', "question_id $insql", $params);
    $DB->delete_records_select('qtype_programming_options', "questionid $insql", $params);

    // Step 2: Delete attempt steps
    $DB->delete_records_select('question_attempt_steps', "questionattemptid IN (
        SELECT id FROM {question_attempts} WHERE questionid $insql
    )", $params);

    // Step 3: Delete question_attempts
    $DB->delete_records_select('question_attempts', "questionid $insql", $params);

    // Step 4: Delete question_usages
    $DB->delete_records_select('question_usages', "id IN (
        SELECT qu.id FROM {question_usages} qu
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {question} q ON q.id = qa.questionid
        WHERE q.qtype = 'programming'
    )");

    // Step 5: Delete from progcontest_attempts
    $DB->delete_records_select('progcontest_attempts', "uniqueid IN (
    SELECT qu.id FROM {question_usages} qu
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {question} q ON q.id = qa.questionid
    WHERE q.qtype = 'programming'
)", []);


    // Step 6: Remove quiz_slots
    $DB->delete_records_select('quiz_slots', "questionid $insql", $params);

    // Step 7: Delete the questions themselves
    $DB->delete_records_select('question', "id $insql", $params);

    // Final cleanup: remove orphaned progcontest attempts
    $DB->delete_records_select('progcontest_attempts', "uniqueid NOT IN (
    SELECT id FROM {question_usages}
)");

// Remove orphaned question_usages
    $DB->delete_records_select('question_usages', "id NOT IN (
    SELECT DISTINCT questionusageid FROM {question_attempts}
)");

// Optionally remove previews
    $DB->delete_records('progcontest_attempts', ['preview' => 1]);


    echo $OUTPUT->notification('✅ All programming questions and related data have been successfully deleted.', 'notifysuccess');
} else {
    echo $OUTPUT->notification('No programming questions found to delete.', 'notifyinfo');
}

echo $OUTPUT->continue_button(new moodle_url('/admin/settings.php', ['section' => 'qtype_programming_settings']));
echo $OUTPUT->footer();
