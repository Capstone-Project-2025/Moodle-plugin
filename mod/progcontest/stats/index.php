<?php
// File: mod/progcontest/stats/index.php
require('../../../config.php');

$id     = required_param('id', PARAM_INT);           // Peut être cmid ou courseid.
$userid = optional_param('user', 0, PARAM_INT);      // 0 = global, sinon vue centrée sur un user.

global $DB, $USER, $PAGE, $OUTPUT;

// Déterminer si $id est un cmid (module) ou un courseid.
$cm = get_coursemodule_from_id('progcontest', $id, 0, false);
if ($cm) {
    $course   = get_course($cm->course);
    $courseid = $course->id;
    $context  = context_module::instance($cm->id);
    $pageurl  = new moodle_url('/mod/progcontest/stats/index.php', ['id' => $cm->id] + ($userid ? ['user' => $userid] : []));
    require_login($course, false, $cm);
    // Capacité de consultation des rapports du module.
    require_capability('mod/progcontest:viewreports', $context);
    $backparams = ['id' => $cm->id];
} else {
    // Fallback: id est un courseid.
    $course   = get_course($id);
    $courseid = $course->id;
    $context  = context_course::instance($courseid);
    $pageurl  = new moodle_url('/mod/progcontest/stats/index.php', ['id' => $courseid] + ($userid ? ['user' => $userid] : []));
    require_login($course);
    // À défaut, on exige voir les participants au niveau cours.
    require_capability('moodle/course:viewparticipants', $context);
    $backparams = ['id' => $courseid];
}

// Si l’utilisateur essaie de voir un autre utilisateur sans permission, on bloque.
if ($userid && $userid != $USER->id && !has_capability('moodle/course:viewparticipants', $context)) {
    throw new required_capability_exception($context, 'moodle/course:viewparticipants', 'nopermissions', '');
}

// Page setup.
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title('Programming stats');
$PAGE->set_heading($course->fullname);

// CSS simple pour colorer les taux.
echo html_writer::tag('style', "
    .success-low { background-color: #f8d7da; color: #721c24; }     /* Red */
    .success-medium { background-color: #fff3cd; color: #856404; }  /* Orange */
    .success-high { background-color: #d4edda; color: #155724; }    /* Green */
");

echo $OUTPUT->header();
echo $OUTPUT->heading('Programming stats');

// ===============================
// 1) Récupération des submissions (par cours)
// ===============================
$allsubmissions = $DB->get_records('qtype_programming_submission', ['course_id' => $courseid]);

// ===============================
// 2) Regrouper par utilisateur
//  - On utilise directement le champ 'result' de la table (AC, WA, CE, ...)
// ===============================
$usersubmissions = []; // [userid => ['AC' => x, 'total' => y]]

foreach ($allsubmissions as $submission) {
    $uid = (int)$submission->user_id;

    if (!isset($usersubmissions[$uid])) {
        $usersubmissions[$uid] = ['AC' => 0, 'total' => 0];
    }

    $status = isset($submission->result) ? (string)$submission->result : 'Unknown';

    $usersubmissions[$uid]['total']++;
    if ($status === 'AC') {
        $usersubmissions[$uid]['AC']++;
    }
}

// ===============================
// 3) Statistiques globales
// ===============================
$globalAC = 0;
$globalTotal = 0;

foreach ($usersubmissions as $stats) {
    $globalAC   += $stats['AC'];
    $globalTotal += $stats['total'];
}

if ($globalTotal > 0) {
    $globalRate = round(($globalAC / $globalTotal) * 100, 2);
    echo html_writer::tag('p', "✅ Overall class success rate: <strong>{$globalRate}%</strong> ({$globalAC} / {$globalTotal})");
} else {
    echo html_writer::tag('p', "⚠️ No submissions found.");
}

// ===============================
// 4) Stats utilisateur ciblé (si demandé)
// ===============================
if ($userid) {
    if (isset($usersubmissions[$userid])) {
        $userstats = $usersubmissions[$userid];
        $rate = ($userstats['total'] > 0) ? round(($userstats['AC'] / $userstats['total']) * 100, 2) : 0;
        $username = fullname(core_user::get_user($userid));
        echo html_writer::tag('p', "ℹ️ Stats for <strong>{$username}</strong>: {$userstats['AC']} AC / {$userstats['total']} ({$rate}%)");
    } else {
        echo html_writer::tag('p', "ℹ️ No submissions found for this user.");
    }
}

// ===============================
// 5) Tableau par participant
// ===============================
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
    if (!$user) {
        continue;
    }
    $fullname = fullname($user);
    $ac    = (int)$data['AC'];
    $total = (int)$data['total'];
    $rate  = ($total > 0) ? round(($ac / $total) * 100, 2) : 0;

    // Couleur selon le taux de réussite.
    if ($rate < 50) {
        $class = 'success-low';
    } elseif ($rate < 70) {
        $class = 'success-medium';
    } else {
        $class = 'success-high';
    }

    // Lien vers le report détaillé par user.
    $statsurl = new moodle_url('/mod/progcontest/stats/report.php', [
        'id'   => $cm ? $cm->id : $courseid,
        'user' => $uid
    ]);

    echo html_writer::tag('tr',
        html_writer::tag('td', $fullname) .
        html_writer::tag('td', "{$rate}%", ['class' => $class]) .
        html_writer::tag('td', "{$ac} / {$total}") .
        html_writer::tag('td', html_writer::link($statsurl, 'View full report'))
    );
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// ===============================
// 6) Distribution des taux (bins)
// ===============================
$bins = [
    '0–10%'   => 0,
    '11–20%'  => 0,
    '21–30%'  => 0,
    '31–40%'  => 0,
    '41–50%'  => 0,
    '51–60%'  => 0,
    '61–70%'  => 0,
    '71–80%'  => 0,
    '81–90%'  => 0,
    '91–100%' => 0
];

foreach ($usersubmissions as $data) {
    $total = (int)$data['total'];
    $ac    = (int)$data['AC'];
    if ($total === 0) {
        continue;
    }
    $rate = ($ac / $total) * 100;

    if ($rate <= 10)       $bins['0–10%']++;
    elseif ($rate <= 20)   $bins['11–20%']++;
    elseif ($rate <= 30)   $bins['21–30%']++;
    elseif ($rate <= 40)   $bins['31–40%']++;
    elseif ($rate <= 50)   $bins['41–50%']++;
    elseif ($rate <= 60)   $bins['51–60%']++;
    elseif ($rate <= 70)   $bins['61–70%']++;
    elseif ($rate <= 80)   $bins['71–80%']++;
    elseif ($rate <= 90)   $bins['81–90%']++;
    else                   $bins['91–100%']++;
}

echo $OUTPUT->heading("📊 Success Rate Distribution", 3);
echo '<div style="width: 100vw; margin-left: calc(-50vw + 50%); padding: 20px 0;">
    <canvas id="successDistributionChart" style="width: 100%; height: 300px;"></canvas>
</div>';

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
                    ticks: { stepSize: 1 }
                }
            },
            plugins: { legend: { display: false } }
        }
    });
});
</script>';

echo $OUTPUT->footer();
