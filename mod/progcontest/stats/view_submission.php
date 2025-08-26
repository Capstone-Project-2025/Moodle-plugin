<?php
require('../../../config.php');

global $DB, $USER, $PAGE, $OUTPUT;

// Paramètres requis.
$cmid = required_param('id', PARAM_INT); // Course module ID.
$userid = required_param('user', PARAM_INT); // User ID.

// Récupérer cm, cours et contexte.
$cm = get_coursemodule_from_id('progcontest', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$context = context_module::instance($cm->id);

// Authentification et capacités.
require_login($course, false, $cm);
require_capability('mod/progcontest:viewreports', $context);

if ($userid !== $USER->id && !has_capability('moodle/course:viewparticipants', $context)) {
    throw new required_capability_exception($context, 'moodle/course:viewparticipants', 'nopermissions', '');
}

// Initialisation de la page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/progcontest/stats/view_submission.php', ['id' => $cmid, 'user' => $userid]));
$PAGE->set_title("Submissions");
$PAGE->set_heading("Submissions for course");
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
echo $OUTPUT->heading("Submissions in this course");

// Pagination.
$page = optional_param('page', 0, PARAM_INT);
$perpage = 15;
$start = $page * $perpage;

// Filtres.
$statusfilter = optional_param('status', '', PARAM_ALPHANUM);
$languagefilter = optional_param('language', '', PARAM_TEXT);

// Récupération des soumissions.
$allsubmissions = $DB->get_records('qtype_programming_submission', [
    'user_id' => $userid,
    'course_id' => $course->id
], 'submission_id DESC');

// Langages.
$languagerecords = $DB->get_records_menu('programming_language', null, 'id ASC', 'id,name');

// Traitement des soumissions.
$filteredsubmissions = [];
$statuscounts = [];

foreach ($allsubmissions as $sub) {
    $submissionid = $sub->submission_id;
    $result = $sub->result ?? '---';
    $questionid = $sub->question_id;
    $point = $sub->point ?? 0;
    $total = $sub->total_point ?? 0;

    $option = $DB->get_record('qtype_programming_options', ['id' => $questionid]);
    if (!$option) continue;

    $problem = $DB->get_record('programming_problem', ['id' => $option->problem_id]);
    $problemname = $problem->name ?? 'Unknown problem';

    $languageid = $sub->language_id;
    $language = $DB->get_field('programming_language', 'name', ['id' => $languageid]) ?? '---';

    // Filtres actifs
    //if (!empty($statusfilter) && strcasecmp($result, $statusfilter) !== 0) continue;
    //if (!empty($languagefilter) && strcasecmp($language, $languagefilter) !== 0) continue;

    $timestamp = $sub->timecreated ?? time();
    $dateStr = format_time(time() - $timestamp);

    $statuscounts[$result] = ($statuscounts[$result] ?? 0) + 1;

    $filteredsubmissions[] = (object)[
        'submission_id' => $submissionid,
        'result' => $result,
        'language' => $language,
        'problem' => $problemname,
        'dateStr' => $dateStr,
        'points' => "{$point} / {$total}",
        'time' => $sub->time ?? '---',
        'memory' => $sub->memory ?? '---',
    ];
}

// Pagination.
$total = count($filteredsubmissions);
$submissions = array_slice($filteredsubmissions, $start, $perpage);

// Contexte pour le template.
$templatecontext = [
    'pagingbar' => $OUTPUT->paging_bar($total, $page, $perpage,
        new moodle_url('/mod/progcontest/stats/view_submission.php', [
            'id' => $cmid,
            'user' => $userid,
            'status' => $statusfilter,
            'language' => $languagefilter,
        ])
    ),
    'submissions' => [],
    'total' => array_sum($statuscounts),
    'backurl' => (new moodle_url('/mod/progcontest/stats/index.php', ['id' => $cmid]))->out(false),
];

// Couleurs (sans match)
function get_status_color($status) {
    switch ($status) {
        case 'AC': return '#4CAF50';
        case 'WA':
        case 'CE':
        case 'TLE':
        case 'RE': return '#F44336';
        case 'IE':
        case 'RTE':
        case 'IRE':
        case 'AB': return '#FF9800';
        default: return '#607D8B';
    }
}

foreach ($submissions as $obj) {
    $templatecontext['submissions'][] = [
        'submission_id' => $obj->submission_id,
        'result' => $obj->result,
        'language' => $obj->language,
        'problem' => $obj->problem,
        'dateStr' => $obj->dateStr,
        'points' => $obj->points,
        'time' => $obj->time,
        'memory' => $obj->memory,
        'color' => get_status_color($obj->result),
        'viewlink' => (new moodle_url('/mod/progcontest/stats/submission_viewer.php', ['sid' => $obj->submission_id]))->out(false)
    ];
}

// Formulaire de filtres.
ob_start();
echo html_writer::start_tag('form', ['method' => 'get']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'user', 'value' => $userid]);

// Status filter
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

// Language filter
echo html_writer::tag('label', 'Language');
echo html_writer::start_tag('select', ['name' => 'language', 'class' => 'form-select mb-2']);
echo html_writer::tag('option', 'All', ['value' => '']);
foreach ($languagerecords as $id => $name) {
    $selected = ($languagefilter === $name) ? ['selected' => 'selected'] : [];
    echo html_writer::tag('option', $name, ['value' => $name] + $selected);
}
echo html_writer::end_tag('select');

echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Apply', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
$templatecontext['filterform'] = ob_get_clean();


// Rendu Mustache
echo $OUTPUT->render_from_template('mod_progcontest/view_submission', $templatecontext);

// Graphique Chart.js
$labels = array_keys($statuscounts);
$data = array_values($statuscounts);
$colors = array_map('get_status_color', $labels);

echo "<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('submissionChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
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
                    position: 'right'
                }
            }
        }
    });
});
</script>";

echo $OUTPUT->footer();
