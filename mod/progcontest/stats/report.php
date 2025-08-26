<?php
// File: mod/progcontest/stats/report.php
require('../../../config.php');

$cmid   = required_param('id',   PARAM_INT); // cmid (module instance)
$userid = required_param('user', PARAM_INT);

$cm = get_coursemodule_from_id('progcontest', $cmid, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$course  = get_course($cm->course);

require_login($course, false, $cm);
require_capability('mod/progcontest:viewreports', $context);

$targetuser = core_user::get_user($userid, '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/mod/progcontest/stats/report.php', ['id' => $cmid, 'user' => $userid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('progconteststats', 'progcontest'));
$PAGE->set_heading(fullname($targetuser));
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('progconteststats', 'progcontest'));

global $DB;

// =====================================
// 1) Récupération des submissions user
// =====================================

// La table qtype_programming_submission contient course_id, on filtre par cours.
$submissions = $DB->get_records('qtype_programming_submission', [
    'user_id'   => $userid,
    'course_id' => $course->id,
]);

$totalsub = count($submissions);
echo $OUTPUT->heading("Total number of submissions : $totalsub", 3);

// (Optionnel) Lien vers une page de liste de soumissions si tu l’as.
$submissionviewurl = new moodle_url('/mod/progcontest/stats/view_submission.php', [
    'id'   => $cmid,
    'user' => $userid
]);
echo html_writer::link($submissionviewurl, '🔍 View submissions', [
    'class' => 'btn btn-primary',
    'style' => 'margin: 10px 0; display: inline-block;'
]);

// ===================================================
// 2) Stats par type (JOIN via options -> problem -> type)
// ===================================================
//
// Schéma (nouveaux noms) :
//   submissions s (qtype_programming_submission)
//   options     o (qtype_programming_options)  (s.question_id -> o.id)
//   mapping     ppt (programming_problem_type) (o.problem_id -> ppt.problem_id)
//   type        t (programming_type)           (ppt.type_id -> t.id)
//
$sql = "
    SELECT
        t.name AS type_name,
        COUNT(*) AS total_submissions,
        SUM(CASE WHEN s.result = 'AC' THEN 1 ELSE 0 END) AS ac_count,
        SUM(CASE WHEN s.result <> 'AC' AND s.result <> 'pending' THEN 1 ELSE 0 END) AS other_count
    FROM {qtype_programming_submission} s
    JOIN {qtype_programming_options}   o   ON o.id = s.question_id
    JOIN {programming_problem_type}    ppt ON ppt.problem_id = o.problem_id
    JOIN {programming_type}            t   ON t.id = ppt.type_id
    WHERE s.user_id = :userid
      AND s.course_id = :courseid
    GROUP BY t.name
    ORDER BY t.name
";
$params = ['userid' => $userid, 'courseid' => $course->id];
$typestats = $DB->get_records_sql($sql, $params);

// ======================
// 3) Tableau HTML
// ======================
echo html_writer::start_tag('table', ['class' => 'generaltable', 'style' => 'margin-top: 20px;']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Type');
echo html_writer::tag('th', 'Total Submissions');
echo html_writer::tag('th', 'Accepted (AC)');
echo html_writer::tag('th', 'Other');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

$typeStatusMap = [];
foreach ($typestats as $stat) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', format_string($stat->type_name));
    echo html_writer::tag('td', (int)$stat->total_submissions);
    echo html_writer::tag('td', (int)$stat->ac_count);
    echo html_writer::tag('td', (int)$stat->other_count);
    echo html_writer::end_tag('tr');

    $typeStatusMap[$stat->type_name] = [
        'AC'    => (int)$stat->ac_count,
        'Other' => (int)$stat->other_count
    ];
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// ======================
// 4) Données pour chart
// ======================
$labels       = [];
$acceptedData = [];
$otherData    = [];

foreach ($typeStatusMap as $type => $counts) {
    $ac    = $counts['AC'];
    $other = $counts['Other'];
    $total = $ac + $other;
    $rate  = ($total > 0) ? round(($ac / $total) * 100) : 0;

    $labels[]       = $type . " ({$rate}%)";
    $acceptedData[] = $ac;
    $otherData[]    = $other;
}

// =============================
// 5) Heatmap par date (simple)
// =============================
$submissiondates = [];
foreach ($submissions as $submission) {
    // timecreated n'existe peut-être pas dans ta table => fallback time()
    $ts   = property_exists($submission, 'timecreated') && !empty($submission->timecreated)
        ? (int)$submission->timecreated
        : time();
    $date = (new DateTime())->setTimestamp($ts)->format('Y-m-d');
    $submissiondates[$date] = ($submissiondates[$date] ?? 0) + 1;
}
// convertir en map timestamp => count pour Cal-Heatmap
$heatmapdata = [];
foreach ($submissiondates as $date => $count) {
    $dt = new DateTime($date, new DateTimeZone('UTC'));
    $dt->setTime(0, 0, 0);
    $heatmapdata[$dt->getTimestamp()] = $count;
}

// ======================
// 6) Graphiques & assets
// ======================
echo html_writer::empty_tag('br');
echo html_writer::tag('canvas', '', [
    'id'     => 'typeStatusChart',
    'width'  => '800',
    'height' => max(200, count($labels) * 40)
]);

echo html_writer::tag('div', '', ['id' => 'calendar', 'style' => 'margin-top:20px;']);

// JS libs
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
echo '<link href="https://cdn.jsdelivr.net/npm/cal-heatmap@3.6.3/cal-heatmap.css" rel="stylesheet">';
echo '<script src="https://cdn.jsdelivr.net/npm/cal-heatmap@3.6.3/cal-heatmap.min.js"></script>';

echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('typeStatusChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: " . json_encode($labels) . ",
            datasets: [
                {
                    label: 'Accepted (AC)',
                    data: " . json_encode($acceptedData) . ",
                    backgroundColor: 'rgba(0, 200, 0, 0.7)'
                },
                {
                    label: 'Other (WA, CE, etc.)',
                    data: " . json_encode($otherData) . ",
                    backgroundColor: 'rgba(255, 0, 0, 0.7)'
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { stacked: true, beginAtZero: true },
                y: { stacked: true }
            }
        }
    });

    const cal = new CalHeatMap();
    cal.init({
        itemSelector: '#calendar',
        domain: 'month',
        subDomain: 'day',
        data: " . json_encode((object)$heatmapdata) . ",
        start: new Date(new Date().setFullYear(new Date().getFullYear() - 1)),
        range: 13,
        cellSize: 15,
        domainGutter: 10,
        legend: [1, 3, 5, 10],
        tooltip: true,
        legendColors: {
            min: '#eeeeee',
            max: '#006400'
        }
    });
});
</script>";

echo $OUTPUT->footer();
