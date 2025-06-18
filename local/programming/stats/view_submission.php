<?php
require('../../../config.php');

use local_programming\api\ProblemSubmission;

require_login();

$userid = $USER->id;
$context = context_user::instance($userid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/programming/stats/view_submission.php', ['id' => $userid]));
$PAGE->set_title("My submissions");
$PAGE->set_heading("All my submissions");
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo $OUTPUT->heading("All my submissions");

global $DB;

$page = optional_param('page', 0, PARAM_INT);
$perpage = 15;
$start = $page * $perpage;

$statusfilter = optional_param('status', '', PARAM_ALPHANUM);
$languagefilter = optional_param('language', '', PARAM_TEXT);

$allsubmissions = $DB->get_records('qtype_programming_submission', ['user_id' => $userid], 'submission_id DESC');

$filteredsubmissions = [];
$statuscounts = [];

foreach ($allsubmissions as $sub) {
    $submissionid = $sub->submission_id;

    try {
        $response = ProblemSubmission::get_by_id($submissionid);
        $data = json_decode($response['body'], true);
        $obj = $data['data']['object'] ?? null;
    } catch (Exception $e) {
        continue;
    }

    if (!is_array($obj)) continue;

    $obj['submission_id'] = $submissionid;

    if ($statusfilter && strtolower($obj['result']) !== strtolower($statusfilter)) continue;
    if ($languagefilter && strtolower($obj['language']) !== strtolower($languagefilter)) continue;

    $result = $obj['result'] ?? '---';
    $statuscounts[$result] = ($statuscounts[$result] ?? 0) + 1;

    $filteredsubmissions[] = $obj;
}

$total = count($filteredsubmissions);
$submissions = array_slice($filteredsubmissions, $start, $perpage);

echo html_writer::start_div('d-flex', ['style' => 'gap:30px; align-items:flex-start;']);

// 📋 Colonne principale
echo html_writer::start_div('flex-fill');

$url = new moodle_url('/local/programming/stats/view_submission.php', ['id' => $userid]);
if ($statusfilter !== '') $url->param('status', $statusfilter);
if ($languagefilter !== '') $url->param('language', $languagefilter);
echo $OUTPUT->paging_bar($total, $page, $perpage, $url);

if (empty($submissions)) {
    echo $OUTPUT->notification("No submissions found.");
} else {
    foreach ($submissions as $obj) {
        $submissionid = $obj['submission_id'];
        $results = $obj['result'] ?? '---';
        $status = $obj['status'] ?? '---';
        $language = $obj['language'] ?? '---';
        $problem = $obj['problem'] ?? '---';
        $date = strtotime($obj['date'] ?? '');
        $dateStr = $date ? format_time(time() - $date) : '---';
        $points = ($obj['case_points'] ?? 0.0) . ' / ' . ($obj['case_total'] ?? 0.0);
        $time = $obj['time'] ? round($obj['time'], 2) . 's' : '---';
        $memory = $obj['memory'] ? format_float($obj['memory'] / 1024 / 1024, 2) . ' Mio' : '---';

        $color = match ($results) {
            'AC' => '#4CAF50',
            'WA', 'CE', 'TLE', 'RE' => '#F44336',
            'IE', 'RTE' => '#FF9800',
            default => '#607D8B'
        };

        $badge = html_writer::div(
            html_writer::tag('strong', $points) . '<br>' .
            html_writer::tag('span', "$results | $language"),
            '',
            ['style' => "width: 80px; text-align:center; padding:5px; background:$color; color:white; border-radius:4px; font-size: 0.8em;"]
        );

        $viewlink = new moodle_url('/local/programming/stats/submission_viewer.php', ['sid' => $submissionid]);
        $viewbtn = html_writer::link($viewlink, 'view', ['class' => 'btn btn-link p-0 m-0']);

        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body d-flex align-items-center');
        echo $badge;
        echo html_writer::start_div('flex-fill pl-3');
        echo html_writer::tag('div', "<strong>$problem</strong>");
        echo html_writer::tag('div', "admin il y a $dateStr", ['class' => 'text-muted', 'style' => 'font-size: 0.8em;']);
        echo html_writer::end_div();
        echo html_writer::div("$viewbtn<br><small>$time<br>$memory</small>", 'text-right text-muted', ['style' => 'min-width: 100px; font-size: 0.8em;']);
        echo html_writer::end_div(); // card-body
        echo html_writer::end_div(); // card
    }

    echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
}
echo html_writer::end_div(); // flex-fill

// 📊 Colonne latérale (filtres + stats)
echo html_writer::start_div('', ['style' => 'width:300px;']);

// === Filtres
echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', 'Filter submissions', ['class' => 'card-title']);
echo html_writer::start_tag('form', ['method' => 'get']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $userid]);

// Statuts
echo html_writer::tag('label', 'Status');
echo html_writer::start_tag('select', ['name' => 'status', 'class' => 'form-select mb-2']);
echo html_writer::tag('option', 'All', ['value' => '']);
foreach ([
    'AC' => 'Accepted', 'WA' => 'Wrong Answer', 'TLE' => 'Time Limit Exceeded',
    'MLE' => 'Memory Limit Exceeded', 'OLE' => 'Output Limit Exceeded',
    'IRE' => 'Invalid Return', 'RE' => 'Runtime Error', 'CE' => 'Compilation Error',
    'IE' => 'Internal Error', 'AB' => 'Abandoned'
] as $value => $label) {
    $selected = ($statusfilter === $value) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', $label, ['value' => $value] + $selected);
}
echo html_writer::end_tag('select');

// Langages
echo html_writer::tag('label', 'Language');
echo html_writer::start_tag('select', ['name' => 'language', 'class' => 'form-select mb-2']);
echo html_writer::tag('option', 'All', ['value' => '']);
foreach ([
    'AWK', 'BRAIN****', 'C', 'C11', 'CPP03', 'CPP11', 'CPP14', 'CPP17', 'CPP20',
    'GAS', 'GAS64', 'JAVA8', 'PASCAL', 'PERL', 'PY2', 'PY3', 'SED', 'TEXT'
] as $code) {
    $selected = ($languagefilter === $code) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', $code, ['value' => $code] + $selected);
}
echo html_writer::end_tag('select');

echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Apply', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

// === Statistiques
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', 'Statistics', ['class' => 'card-title']);
echo '<canvas id="submissionChart" width="260" height="260"></canvas>';
echo html_writer::tag('p', 'Total: ' . array_sum($statuscounts), ['style' => 'text-align:center;']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // colonne latérale
echo html_writer::end_div(); // row container

// CHART.JS
$labels = array_keys($statuscounts);
$data = array_values($statuscounts);
$colors = array_map(function ($status) {
    return match ($status) {
        'AC' => '#4CAF50',
        'WA', 'CE', 'TLE', 'RE' => '#F44336',
        'IE', 'RTE' => '#FF9800',
        default => '#607D8B'
    };
}, $labels);

echo "<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
<script>
const ctx = document.getElementById('submissionChart').getContext('2d');
new Chart(ctx, {
    type: 'pie',
    data: {
        labels: " . json_encode($labels) . ",
        datasets: [{
            data: " . json_encode($data) . ",
            backgroundColor: " . json_encode($colors) . "
        }]
    },
    options: {
        responsive: false,
        plugins: {
            legend: {
                position: 'right',
                labels: { color: '#000' }
            }
        }
    }
});
</script>";

echo html_writer::start_div('mt-4', ['style' => 'text-align: center;']);
$backurl = new moodle_url('/local/programming/stats/report.php', ['id' => $userid]);
echo html_writer::link($backurl, '⬅️ Back to the statistics page', ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

echo $OUTPUT->footer();
