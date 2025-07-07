<?php

require('../../../config.php');
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

// === Paramètres de pagination et filtres
$page = optional_param('page', 0, PARAM_INT);
$perpage = 15;
$start = $page * $perpage;

$statusfilter = optional_param('status', '', PARAM_ALPHANUM);
$languagefilter = optional_param('language', '', PARAM_TEXT);

// === Récupérer toutes les soumissions de l’utilisateur
$allsubmissions = $DB->get_records('qtype_programming_submission', ['user_id' => $userid], 'submission_id DESC');

$filteredsubmissions = [];
$statuscounts = [];

foreach ($allsubmissions as $sub) {
    $submissionid = $sub->submission_id;
    $result = $sub->result ?? '---';
    $questionid = $sub->question_id;
    $point = $sub->point ?? 0;
    $total = $sub->total_point ?? 0;

    // Aller chercher le problem_id via la question
    $option = $DB->get_record('qtype_programming_options', ['id' => $questionid]);
    if (!$option) continue;
    $problemid = $option->problem_id;

    // Nom du problème
    $problem = $DB->get_record('local_programming_problem', ['id' => $problemid]);
    $problemname = $problem->name ?? 'Unknown problem';

    // Langages associés au problème
    $langids = $DB->get_records('local_programming_problem_language', ['problem_id' => $problemid]);
    $languages = [];
    foreach ($langids as $lang) {
        $lname = $DB->get_field('local_programming_language', 'name', ['id' => $lang->language_id]);
        if ($lname) $languages[] = strtoupper($lname);
    }
    $language = !empty($languages) ? implode(', ', $languages) : '---';

    // Filtres
    if ($statusfilter && strtolower($result) !== strtolower($statusfilter)) continue;
    if ($languagefilter && stripos($language, $languagefilter) === false) continue;

    // Date
    $timestamp = $sub->timecreated ?? time();
    $dateStr = format_time(time() - $timestamp);

    // Stats
    $statuscounts[$result] = ($statuscounts[$result] ?? 0) + 1;

    $filteredsubmissions[] = (object)[
        'submission_id' => $submissionid,
        'result' => $result,
        'language' => $language,
        'problem' => $problemname,
        'dateStr' => $dateStr,
        'points' => "$point / $total",
        'time' => $sub->time ?? null,
        'memory' => $sub->memory ?? null,
    ];
}

// Pagination
$total = count($filteredsubmissions);
$submissions = array_slice($filteredsubmissions, $start, $perpage);

// === Mise en page
echo html_writer::start_div('d-flex', ['style' => 'gap:30px; align-items:flex-start;']);

// === Colonne principale
echo html_writer::start_div('flex-fill');

$url = new moodle_url('/local/programming/stats/view_submission.php', ['id' => $userid]);
if ($statusfilter !== '') $url->param('status', $statusfilter);
if ($languagefilter !== '') $url->param('language', $languagefilter);
echo $OUTPUT->paging_bar($total, $page, $perpage, $url);

if (empty($submissions)) {
    echo $OUTPUT->notification("No submissions found.");
} else {
    foreach ($submissions as $obj) {
        $submissionid = $obj->submission_id;
        $results = $obj->result;
        $language = $obj->language;
        $problem = $obj->problem;
        $dateStr = $obj->dateStr;
        $points = $obj->points;
        $time = $obj->time ? round($obj->time, 2) . 's' : '---';
        $memory = $obj->memory ? format_float($obj->memory / 1024 / 1024, 2) . ' Mio' : '---';

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

// === Colonne latérale
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

// Langages (static, à personnaliser si besoin)
echo html_writer::tag('label', 'Language');
echo html_writer::start_tag('select', ['name' => 'language', 'class' => 'form-select mb-2']);
echo html_writer::tag('option', 'All', ['value' => '']);
foreach (['PYTHON 2', 'PYTHON 3', 'C', 'C++', 'JAVA 8', 'CPP14', 'CPP17', 'CPP20'] as $code) {
    $selected = ($languagefilter === $code) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', $code, ['value' => $code] + $selected);
}
echo html_writer::end_tag('select');

echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Apply', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

// === Statistiques (chart.js)
echo html_writer::start_div('card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', 'Statistics', ['class' => 'card-title']);
echo '<canvas id="submissionChart" width="260" height="260"></canvas>';
echo html_writer::tag('p', 'Total: ' . array_sum($statuscounts), ['style' => 'text-align:center;']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div(); // sidebar
echo html_writer::end_div(); // layout container

// === Chart.js rendering
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
