<?php
require('../../../config.php');

$courseid = required_param('id', PARAM_INT);
$userid = required_param('user', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/dmoj_user_link:viewstats', $context);

if ($userid !== $USER->id && !has_capability('moodle/course:viewparticipants', $context)) {
    throw new required_capability_exception($context, 'moodle/course:viewparticipants', 'nopermissions', '');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/dmoj_user_link/stats/view_submission.php', ['id' => $courseid, 'user' => $userid]));
$PAGE->set_title("Submissions");
$PAGE->set_heading("Submissions for course");
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo $OUTPUT->heading("Submissions in this course");

global $DB;

$page = optional_param('page', 0, PARAM_INT);
$perpage = 15;
$start = $page * $perpage;

$statusfilter = optional_param('status', '', PARAM_ALPHANUM);
$languagefilter = optional_param('language', '', PARAM_TEXT);

$allsubmissions = $DB->get_records('qtype_prog_submission', ['user_id' => $userid, 'course_id' => $courseid], 'submission_id DESC');

$filteredsubmissions = [];
$statuscounts = [];

foreach ($allsubmissions as $sub) {
    $submissionid = $sub->submission_id;
    $result = $sub->result ?? '---';
    $questionid = $sub->question_id;
    $point = $sub->point ?? 0;
    $total = $sub->total_point ?? 0;

    $option = $DB->get_record('qtype_prog_options', ['id' => $questionid]);
    if (!$option) continue;

    $problemid = $option->problem_id;
    $problem = $DB->get_record('local_dmoj_user_linkproblem', ['id' => $problemid]);
    $problemname = $problem->name ?? 'Unknown problem';

    // ✅ Nouveau code pour récupérer le langage directement
    $languageid = $sub->language_id;
    $language = $DB->get_field('local_dmoj_user_linklanguage', 'name', ['id' => $languageid]) ?? '---';

    if ($statusfilter && strtolower($result) !== strtolower($statusfilter)) continue;
    if ($languagefilter && stripos($language, $languagefilter) === false) continue;

    $timestamp = $sub->timecreated ?? time();
    $dateStr = format_time(time() - $timestamp);

    $statuscounts[$result] = ($statuscounts[$result] ?? 0) + 1;

    $filteredsubmissions[] = (object)[
        'submission_id' => $submissionid,
        'result' => $result,
        'language' => $language,
        'problem' => $problemname,
        'dateStr' => $dateStr,
        'points' => "$point / $total",
        'time' => $sub->time ?? '---',
        'memory' => $sub->memory ?? '---',
    ];
}


$total = count($filteredsubmissions);
$submissions = array_slice($filteredsubmissions, $start, $perpage);

// Préparation des données pour le template Mustache
$templatecontext = [
    'pagingbar' => $OUTPUT->paging_bar($total, $page, $perpage,
        new moodle_url('/local/dmoj_user_link/stats/view_submission.php', [
            'id' => $courseid,
            'user' => $userid,
            'status' => $statusfilter,
            'language' => $languagefilter,
        ])
    ),
    'submissions' => [],
    'total' => array_sum($statuscounts),
    'backurl' => (new moodle_url('/local/dmoj_user_link/stats/report.php', ['id' => $courseid, 'user' => $userid]))->out(false),
];

// Submissions
foreach ($submissions as $obj) {
    $color = match ($obj->result) {
        'AC' => '#4CAF50',
        'WA', 'CE', 'TLE', 'RE' => '#F44336',
        'IE', 'RTE' => '#FF9800',
        default => '#607D8B'
    };

    $templatecontext['submissions'][] = [
        'submission_id' => $obj->submission_id,
        'result' => $obj->result,
        'language' => $obj->language,
        'problem' => $obj->problem,
        'dateStr' => $obj->dateStr,
        'points' => $obj->points,
        'time' => $obj->time,
        'memory' => $obj->memory,
        'color' => $color,
        'viewlink' => (new moodle_url('/local/dmoj_user_link/stats/submission_viewer.php', ['sid' => $obj->submission_id]))->out(false)
    ];
}

// Capture du formulaire de filtre en HTML
ob_start();
echo html_writer::start_tag('form', ['method' => 'get']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'user', 'value' => $userid]);

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
$templatecontext['filterform'] = ob_get_clean();

// Affichage via template
echo $OUTPUT->render_from_template('local_dmoj_user_link/view_submission', $templatecontext);

// Chart.js
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

echo $OUTPUT->footer();
