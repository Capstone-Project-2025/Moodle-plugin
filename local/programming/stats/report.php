<?php

require('../../../config.php');

$courseid = required_param('id', PARAM_INT);
require_login($courseid);

$context = context_course::instance($courseid);
require_capability('local/programming:viewstats', $context);

$PAGE->set_url(new moodle_url('/local/programming/stats/report.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title('Programming Stats');
$PAGE->set_heading('Programming Stats');
$PAGE->set_pagelayout('course');

echo $OUTPUT->header();
echo $OUTPUT->heading('Programming statistics for this course');

global $DB, $USER;

// ✅ Total des submissions pour l’utilisateur
$submissions = $DB->get_records('qtype_programming_submission', ['user_id' => $USER->id]);
$totalsub = count($submissions);
echo $OUTPUT->heading("Total number of submissions : $totalsub");

// 🔗 Lien vers la page de soumissions
$submissionviewurl = new moodle_url('/local/programming/stats/view_submission.php', ['id' => $courseid]);
echo html_writer::link($submissionviewurl, '🔍 View submissions', ['class' => 'btn btn-primary', 'style' => 'margin: 10px 0; display: inline-block;']);

// ✅ Requête SQL regroupée par type
$sql = "
    SELECT 
        t.name AS type_name,
        COUNT(*) AS total_submissions,
        SUM(CASE WHEN s.result = 'AC' THEN 1 ELSE 0 END) AS AC_count,
        SUM(CASE WHEN s.result != 'AC' AND s.result != 'pending' THEN 1 ELSE 0 END) AS Other_count
    FROM {qtype_programming_submission} s
    JOIN {qtype_programming_options} o ON s.question_id = o.id
    JOIN {local_programming_problem_type} pt ON o.problem_id = pt.problem_id
    JOIN {local_programming_type} t ON pt.type_id = t.id
    WHERE s.user_id = :userid
    GROUP BY t.name
    ORDER BY t.name
";
$params = ['userid' => $USER->id];
$typestats = $DB->get_records_sql($sql, $params);

// ✅ Affichage du tableau HTML
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
$labels = [];
$acceptedData = [];
$otherData = [];

foreach ($typestats as $stat) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $stat->type_name);
    echo html_writer::tag('td', $stat->total_submissions);
    echo html_writer::tag('td', $stat->ac_count);
    echo html_writer::tag('td', $stat->other_count);
    echo html_writer::end_tag('tr');

    // Préparation des données pour le graphique
    $typeStatusMap[$stat->type_name] = [
        'AC' => $stat->ac_count,
        'Other' => $stat->other_count
    ];
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// ✅ Construction des labels et datasets pour Chart.js
foreach ($typeStatusMap as $type => $counts) {
    $ac = $counts['AC'];
    $other = $counts['Other'];
    $total = $ac + $other;
    $rate = ($total > 0) ? round(($ac / $total) * 100) : 0;

    $labels[] = $type . " ({$rate}%)";
    $acceptedData[] = $ac;
    $otherData[] = $other;
}

// ✅ Calcul des dates pour heatmap
$submissiondates = [];
foreach ($submissions as $submission) {
    $timestamp = $submission->timecreated ?? time();
    $date = (new DateTime())->setTimestamp($timestamp)->format('Y-m-d');
    $submissiondates[$date] = ($submissiondates[$date] ?? 0) + 1;
}
$heatmapdata = [];
foreach ($submissiondates as $date => $count) {
    $datetime = new DateTime($date, new DateTimeZone('UTC'));
    $datetime->setTime(0, 0, 0);
    $timestamp = $datetime->getTimestamp();
    $heatmapdata[$timestamp] = $count;
}

// ✅ Graphique Chart.js
echo '<canvas id="typeStatusChart" width="800" height="' . (count($labels) * 40) . '"></canvas>';

// ✅ Recommandations basées sur les types les moins réussis
$typeSuccessRates = [];
foreach ($typeStatusMap as $type => $counts) {
    $ac = $counts['AC'];
    $total = $ac + $counts['Other'];
    $rate = ($total > 0) ? ($ac / $total) : 0;
    $typeSuccessRates[$type] = $rate;
}
asort($typeSuccessRates);
$lowestTypes = array_slice(array_keys($typeSuccessRates), 0, 3);

echo $OUTPUT->heading('🧠 Recommendations based on your challenges');

foreach ($lowestTypes as $typename) {
    $sql = "SELECT id FROM {local_programming_type} WHERE " . $DB->sql_compare_text('name') . " = " . $DB->sql_compare_text(':name');
    $typeid = $DB->get_field_sql($sql, ['name' => $typename]);

    if (!$typeid) continue;

    $sql = "SELECT DISTINCT pt.problem_id
            FROM {local_programming_problem_type} pt
            WHERE pt.type_id = :typeid";
    $problemids = $DB->get_records_sql($sql, ['typeid' => $typeid]);
    if (empty($problemids)) continue;
    $problemidlist = array_keys($problemids);

    list($insql, $inparams) = $DB->get_in_or_equal($problemidlist, SQL_PARAMS_NAMED, 'prob');
    $inparams['userid'] = $USER->id;

    $sql = "SELECT DISTINCT qo.problem_id
            FROM {qtype_programming_submission} qs
            JOIN {qtype_programming_options} qo ON qs.question_id = qo.questionid
            WHERE qs.user_id = :userid AND qs.result = 'AC' AND qo.problem_id $insql";
    $solvedproblems = $DB->get_records_sql($sql, $inparams);
    $solvedids = array_keys($solvedproblems);
    $unsolved = array_diff($problemidlist, $solvedids);
    if (empty($unsolved)) continue;

    shuffle($unsolved);
    $problemid = reset($unsolved);

    $problem = $DB->get_record('local_programming_problem', ['id' => $problemid]);
    if (!$problem) continue;

    $code = $problem->code ?? 'N/A';
    $name = $problem->name ?? 'Unnamed Problem';

    $typerecords = $DB->get_records('local_programming_problem_type', ['problem_id' => $problemid]);
    $typenames = [];
    foreach ($typerecords as $trecord) {
        $tname = $DB->get_field('local_programming_type', 'name', ['id' => $trecord->type_id]);
        if ($tname) $typenames[] = $tname;
    }

    $typeString = implode(', ', $typenames);

    echo html_writer::start_div('card', ['style' => 'margin:10px; padding:10px; border:1px solid #ccc;']);
    echo html_writer::tag('h4', $name);
    echo html_writer::tag('p', "<strong>Type :</strong> $typeString<br><strong>Code :</strong> $code");
    echo html_writer::end_div();
}

// ✅ Assets Chart.js et CalHeatMap
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
            plugins: {
                legend: { position: 'top' }
            },
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
