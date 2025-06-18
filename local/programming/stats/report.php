<?php

require('../../../config.php');

use local_programming\api\ProblemDetail;
use local_programming\api\ProblemSubmission;
use local_programming\api\ProblemList;

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

$submissions = $DB->get_records('qtype_programming_submission', ['user_id' => $USER->id]);
$totalsub = count($submissions);
echo $OUTPUT->heading("Total number of submissions : $totalsub");

$submissionviewurl = new moodle_url('/local/programming/stats/view_submission.php', ['id' => $courseid]);
echo html_writer::link(
    $submissionviewurl,
    '🔍 View submissions',
    ['class' => 'btn btn-primary', 'style' => 'margin: 10px 0; display: inline-block;']
);

$typeStatusMap = [];
$submissiondates = [];



foreach ($submissions as $submission) {
    $submissionid = $submission->submission_id;

    $subresponse = ProblemSubmission::get_by_id($submissionid);
    $subdata = json_decode($subresponse['body'], true);
    $submissionobj = $subdata['data']['object'] ?? null;
    if (!$submissionobj) continue;

    $status = $submissionobj['result'] ?? 'Unknown';
    $problemcode = $submissionobj['problem'] ?? null;

    // Date de soumission
    $dateStr = $submissionobj['date'] ?? null;
    if ($dateStr) {
        try {
            $date = DateTime::createFromFormat("Y-m-d\TH:i:s.uP", $dateStr) ?: DateTime::createFromFormat(DateTime::ATOM, $dateStr);
            if ($date) {
                $day = $date->format('Y-m-d');
                $submissiondates[$day] = ($submissiondates[$day] ?? 0) + 1;
            }
        } catch (Exception $e) {
            debugging("Erreur de date : " . $e->getMessage());
        }
    }

    if (!$problemcode) continue;

    $probresponse = ProblemDetail::get_by_code($problemcode);
    $probdata = json_decode($probresponse['body'], true);
    $type = $probdata['data']['object']['types'][0] ?? '(No type)';

    $category = ($status === 'AC') ? 'AC' : 'Other';

    if (!isset($typeStatusMap[$type])) {
        $typeStatusMap[$type] = ['AC' => 0, 'Other' => 0];
    }
    $typeStatusMap[$type][$category]++;
}

// Préparation des données de graphique
$labels = [];
$acceptedData = [];
$otherData = [];

foreach ($typeStatusMap as $type => $counts) {
    $ac = $counts['AC'];
    $other = $counts['Other'];
    $total = $ac + $other;
    $rate = ($total > 0) ? round(($ac / $total) * 100) : 0;

    $labels[] = $type . " ({$rate}%)";
    $acceptedData[] = $ac;
    $otherData[] = $other;
}

// Identifier les types avec les taux les plus faibles
$typeSuccessRates = [];
foreach ($typeStatusMap as $type => $counts) {
    $ac = $counts['AC'];
    $total = $ac + $counts['Other'];
    $rate = ($total > 0) ? ($ac / $total) : 0;
    $typeSuccessRates[$type] = $rate;
}

asort($typeSuccessRates);
$lowestTypes = array_slice(array_keys($typeSuccessRates), 0, 3);

$problemsToRecommend = [];

foreach ($lowestTypes as $type) {
    $cache = cache::make('local_programming', 'problemtype');
    $safe_type_key = preg_replace('/[^a-zA-Z0-9_]/', '_', $type);
    $cached = $cache->get($safe_type_key);

    if ($cached !== false) {
        $problems = $cached;
    } else {
        $response = ProblemList::get_by_type($type);
        $data = json_decode($response['body'], true);
        $problems = $data['data']['objects'] ?? [];
        $cache->set($safe_type_key, $problems);
    }

    if (!empty($problems)) {
        $randomIndex = array_rand($problems);
        $problemsToRecommend[] = $problems[$randomIndex];
    }
}

// Affichage du graphique
echo '<canvas id="typeStatusChart" width="800" height="' . (count($labels) * 40) . '"></canvas>';

// Données pour Cal-Heatmap
$heatmapdata = [];
foreach ($submissiondates as $date => $count) {
    $datetime = new DateTime($date, new DateTimeZone('UTC'));
    $datetime->setTime(0, 0, 0);
    $timestamp = $datetime->getTimestamp();
    $heatmapdata[$timestamp] = $count;
}

// Affichage des recommandations
echo $OUTPUT->heading('🧠 Recommendations based on your challenges');

foreach ($problemsToRecommend as $problem) {
    $code = $problem['code'] ?? 'N/A';
    $name = $problem['name'] ?? 'Unnamed Problem';
    $description = $problem['description'] ?? '';
    $type = implode(', ', $problem['types'] ?? []);

    echo html_writer::start_div('card', ['style' => 'margin:10px; padding:10px; border:1px solid #ccc;']);
    echo html_writer::tag('h4', $name);
    echo html_writer::tag('p', "<strong>Type :</strong> $type<br><strong>Code :</strong> $code");
    echo html_writer::tag('p', $description);
    echo html_writer::end_div();
}

// JS/CSS pour les graphiques
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
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw;
                        }
                    }
                }
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
