<?php
require('../../../config.php');

use local_programming\api\ProblemSubmission;
use local_programming\api\ProblemTestData;

require_login();

$sid = required_param('sid', PARAM_INT);

$PAGE->set_url(new moodle_url('/local/programming/stats/submission_viewer.php', ['sid' => $sid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title("Submission #$sid");
$PAGE->set_heading("Submission details");

echo $OUTPUT->header();
echo $OUTPUT->heading("Submission #$sid");

try {
    // 🔍 1. Appel API pour les infos de soumission
    $res = ProblemSubmission::get_by_id($sid);
    $data = json_decode($res['body'], true);
    $submission = $data['data']['object'] ?? null;

    if (!$submission) {
        throw new moodle_exception("Submission not found in API");
    }

    $problemcode = $submission['problem'];
    $status = $submission['status'];
    $language = $submission['language'];
    $points = $submission['points'];
    $casepoints = $submission['case_points'];
    $casetotal = $submission['case_total'];
    $source = $submission['source_code'];
    $cases = $submission['cases'] ?? [];

    // 🧾 Détails simples
    echo html_writer::start_tag('div');
    echo html_writer::tag('p', "<strong>Problème :</strong> $problemcode");
    echo html_writer::tag('p', "<strong>Langage :</strong> $language");
    echo html_writer::tag('p', "<strong>Statut :</strong> $status");
    echo html_writer::tag('p', "<strong>Score final :</strong> $casepoints / $casetotal ($points points)");
    echo html_writer::end_tag('div');

    // 🔍 2. Récupérer les cas de test attendus via l'API
    $res2 = ProblemTestData::get_by_problemcode($problemcode);
    $testcases = json_decode($res2['body'], true)['data']['test_cases'] ?? [];

    echo html_writer::tag('h3', "Test results");

    $test_points = [];
    foreach ($testcases as $idx => $tc) {
        $test_points[$idx] = $tc['points'] ?? '?';
    }

    if (empty($cases)) {
        echo html_writer::div("No test cases executed or not available.", 'alert alert-warning');
    } else {
        echo html_writer::start_tag('ul');
        foreach ($cases as $index => $c) {
            $res = $c['status'] ?? '???';
            $t = round($c['time'] ?? 0, 3);
            $m = round($c['memory'] ?? 0, 2);
            $earned = $c['points'] ?? 0;
            $total = $test_points[$index] ?? '?';

            $label = "<strong>$res</strong>";
            echo html_writer::tag('li', "Test case #" . ($index + 1) . ": $label [{$t}s, {$m} MB] ($earned/$total)");
        }
        echo html_writer::end_tag('ul');
    }

    // 💻 Code source
    echo html_writer::tag('h3', "Source code");
    echo html_writer::tag('pre', htmlentities($source), ['style' => 'background:#f4f4f4;padding:10px;']);

} catch (Exception $e) {
    echo $OUTPUT->notification("Erreur : " . $e->getMessage(), 'notifyproblem');
}

echo $OUTPUT->footer();
