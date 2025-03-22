<?php
require_once('../../config.php');

$userid = required_param('userid', PARAM_INT);

$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/problems/user_statistic.php', array('userid' => $userid)));
$PAGE->set_context(context_user::instance($userid));
$PAGE->set_title('Statistiques de ' . fullname($user));
$PAGE->set_heading('Statistiques de ' . fullname($user));

echo $OUTPUT->header();
?>

<div class="container mt-5 text-center">
    <h1 class="fw-bold">Statistics of <?php echo fullname($user); ?></h1>
    <p class="lead">Analysis of the user's performance.</p>
</div>

<!-- Bootstrap & Chart.js -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container mt-5">
    <div class="row mt-4">
        <!-- Statistics -->
        <div class="col-md-6">
            <div class="card shadow p-4">
                <h4 class="fw-bold text-center">General Statistics</h4>
                <div class="row text-center mt-3">
                    <div class="col-6">
                        <h5><i class="bi bi-upload"></i> Total Submissions</h5>
                        <p class="display-6 fw-bold text-primary" id="totalSub">150</p>
                    </div>
                    <div class="col-6">
                        <h5><i class="bi bi-check-circle-fill text-success"></i> Accepted</h5>
                        <p class="display-6 fw-bold text-success" id="accepted">90</p>
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-6">
                        <h5><i class="bi bi-x-circle-fill text-danger"></i> Wrong Answers</h5>
                        <p class="display-6 fw-bold text-danger" id="wrong">30</p>
                    </div>
                    <div class="col-6">
                        <h5><i class="bi bi-hourglass-split text-warning"></i> Time Limit Exceeded</h5>
                        <p class="display-6 fw-bold text-warning" id="tle">20</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Circle Diagramme -->
        <div class="col-md-6">
            <div class="card shadow p-4">
                <h4 class="fw-bold text-center">Strengths & Weaknesses</h4>
                <canvas id="skillsChart" class="mt-3"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Script pour Chart.js -->
<script>
    const ctx = document.getElementById('skillsChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Dynamic Programming', 'Graphs', 'Math', 'Greedy', 'Recursion'],
            datasets: [{
                label: 'Skills Mastery',
                data: [30, 15, 20, 25, 10],
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4CAF50', '#9966FF'],
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    function animateValue(id, start, end, duration) {
        let obj = document.getElementById(id);
        let range = end - start;
        let current = start;
        let increment = range / (duration / 50);
        let stepTime = 50;
        let timer = setInterval(function () {
            current += increment;
            obj.innerText = Math.floor(current);
            if (current >= end) {
                obj.innerText = end;
                clearInterval(timer);
            }
        }, stepTime);
    }

    window.onload = function () {
        animateValue("totalSub", 0, 150, 1000);
        animateValue("accepted", 0, 90, 1000);
        animateValue("wrong", 0, 30, 1000);
        animateValue("tle", 0, 20, 1000);
    };
</script>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">

<?php
echo $OUTPUT->footer();
?>
