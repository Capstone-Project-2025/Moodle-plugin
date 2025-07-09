<?php
require('../../config.php');

use local_programming\api\ProblemSubmission;

$courseid = required_param('id', PARAM_INT);
$userid = optional_param('user', $USER->id, PARAM_INT);

require_login($courseid);
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('report/programming_classmanagement:view', $context);

// Check if user is allowed to view other users
if ($userid !== $USER->id && !has_capability('moodle/course:viewparticipants', $context)) {
    throw new required_capability_exception($context, 'moodle/course:viewparticipants', 'nopermissions', '');
}

// Page setup
$PAGE->set_url(new moodle_url('/report/programming_classmanagement/index.php', ['id' => $courseid, 'user' => $userid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'report_programming_classmanagement'));
$PAGE->set_heading($course->fullname);

// Add custom inline CSS styles
echo html_writer::start_tag('style');
echo "
    .success-low { background-color: #f8d7da; color: #721c24; }     /* Red */
    .success-medium { background-color: #fff3cd; color: #856404; }  /* Orange */
    .success-high { background-color: #d4edda; color: #155724; }    /* Green */
";
echo html_writer::end_tag('style');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'report_programming_classmanagement'));

// Step 1: Get all submissions
$allsubmissions = $DB->get_records('qtype_programming_submission');

// Step 2: Group submissions by user
$usersubmissions = [];

foreach ($allsubmissions as $submission) {
    $uid = $submission->user_id;

    if (!isset($usersubmissions[$uid])) {
        $usersubmissions[$uid] = ['AC' => 0, 'total' => 0];
    }

    $subresponse = ProblemSubmission::get_by_id($submission->submission_id);
    $subdata = $subresponse['body'];
    $submissionobj = $subdata['data']['object'] ?? null;

    if (!$submissionobj) continue;

    $status = $submissionobj['result'] ?? 'Unknown';

    $usersubmissions[$uid]['total']++;
    if ($status === 'AC') {
        $usersubmissions[$uid]['AC']++;
    }
}

// Step 3: Global stats
$globalAC = 0;
$globalTotal = 0;

foreach ($usersubmissions as $stats) {
    $globalAC += $stats['AC'];
    $globalTotal += $stats['total'];
}

if ($globalTotal > 0) {
    $globalRate = round(($globalAC / $globalTotal) * 100, 2);
    echo html_writer::tag('p', "✅ Overall class success rate: <strong>$globalRate%</strong> ($globalAC / $globalTotal)");
} else {
    echo html_writer::tag('p', "⚠️ No submissions found.");
}

// Step 4: Show individual user stats
if (isset($usersubmissions[$userid])) {
    $userstats = $usersubmissions[$userid];
    $rate = ($userstats['total'] > 0) ? round(($userstats['AC'] / $userstats['total']) * 100, 2) : 0;
    $username = fullname(core_user::get_user($userid));
} else {
    echo html_writer::tag('p', "ℹ️ No submissions found for this user.");
}

// Step 5: Show user table
echo $OUTPUT->heading("👥 Participants and their success rates", 3);

echo html_writer::start_tag('table', ['class' => 'generaltable', 'style' => 'width: 100%;']);
echo html_writer::start_tag('thead');
echo html_writer::tag('tr',
    html_writer::tag('th', 'Name') .
    html_writer::tag('th', 'Success rate') .
    html_writer::tag('th', 'Submissions') .
    html_writer::tag('th', 'Full report')
);
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($usersubmissions as $uid => $data) {
    $user = core_user::get_user($uid);
    if (!$user) continue;

    $fullname = fullname($user);
    $ac = $data['AC'];
    $total = $data['total'];
    $rate = ($total > 0) ? round(($ac / $total) * 100, 2) : 0;

    // Determine CSS class based on rate
    if ($rate < 50) {
        $class = 'success-low';
    } elseif ($rate < 70) {
        $class = 'success-medium';
    } else {
        $class = 'success-high';
    }

    // Link to full user report
    $statsurl = new moodle_url('/local/programming/stats/report.php', [
        'id' => $courseid,
        'user' => $uid
    ]);

    echo html_writer::tag('tr',
        html_writer::tag('td', $fullname) .
        html_writer::tag('td', "$rate%", ['class' => $class]) .
        html_writer::tag('td', "$ac / $total") .
        html_writer::tag('td', html_writer::link($statsurl, 'View full report'))
    );
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// 📊 Step: Build success rate distribution bins
$bins = [
    '0–10%' => 0,
    '11–20%' => 0,
    '21–30%' => 0,
    '31–40%' => 0,
    '41–50%' => 0,
    '51–60%' => 0,
    '61–70%' => 0,
    '71–80%' => 0,
    '81–90%' => 0,
    '91–100%' => 0
];

foreach ($usersubmissions as $data) {
    $total = $data['total'];
    $ac = $data['AC'];
    if ($total === 0) continue;

    $rate = ($ac / $total) * 100;

    if ($rate <= 10) $bins['0–10%']++;
    elseif ($rate <= 20) $bins['11–20%']++;
    elseif ($rate <= 30) $bins['21–30%']++;
    elseif ($rate <= 40) $bins['31–40%']++;
    elseif ($rate <= 50) $bins['41–50%']++;
    elseif ($rate <= 60) $bins['51–60%']++;
    elseif ($rate <= 70) $bins['61–70%']++;
    elseif ($rate <= 80) $bins['71–80%']++;
    elseif ($rate <= 90) $bins['81–90%']++;
    else $bins['91–100%']++;
}

// 📊 Heading + canvas
// 📊 Heading + canvas
echo $OUTPUT->heading("📊 Success Rate Distribution", 3);
echo '
<div style="width: 100vw; margin-left: calc(-50vw + 50%); padding: 20px 0;">
    <canvas id="successDistributionChart" style="width: 100%; height: 300px;"></canvas>
</div>';


// 📊 Chart.js library and script
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById("successDistributionChart").getContext("2d");
    new Chart(ctx, {
        type: "bar",
        data: {
            labels: ' . json_encode(array_keys($bins)) . ',
            datasets: [{
                label: "Number of students",
                data: ' . json_encode(array_values($bins)) . ',
                backgroundColor: "rgba(75, 192, 192, 0.6)",
                borderColor: "rgba(75, 192, 192, 1)",
                borderWidth: 1
            }]
        },
            options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: "Number of students",
                    font: { size: 16 }
                },
                ticks: {
                    stepSize: 1,
                    font: { size: 14 }
                }
            },
            x: {
                title: {
                    display: true,
                    text: "Success rate range",
                    font: { size: 16 }
                },
                ticks: {
                    maxRotation: 0,
                    minRotation: 0,
                    font: { size: 14 }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }

    });
});
</script>';


echo $OUTPUT->footer();
