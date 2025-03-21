<?php
require_once('../../config.php');

$PAGE->set_url(new moodle_url('/local/problems/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Problem Management');
$PAGE->set_heading('Moodle Interface: List of contest problems');

echo $OUTPUT->header();
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="style.css">

<div class="container mt-5 text-center">
<table class="table striped">
  <tr>
    <th>Problem</th>
    <th>Points</th>
    <th>AC rate</th>
    <th>Solvers</th>
  </tr>
  <tr>
    <td><a href="https://dmoj.ca/problem/dcc1p1">Dilhan&#39;s Computing Contest 1 P1 - The Cathedral of Learning</a></td>
    <td>5p</td>
    <td>47.0%</td>
    <td><a href="https://dmoj.ca/problem/dcc1p1/rank/">336</a></td>
  </tr>
  <tr>
    <td><a href="https://dmoj.ca/problem/dcc1p2">Dilhan&#39;s Computing Contest 1 P2 - Square Sum</a></td>
    <td>12p</td>
    <td>18.7%</td>
    <td><a href="https://dmoj.ca/problem/dcc1p2/rank/">183</a></td>
  </tr>
  <tr>
    <td><a href="https://dmoj.ca/problem/dcc1p3">Dilhan&#39;s Computing Contest 1 P3 - Soccer Court</a></td>
    <td>10p</td>
    <td>34.0%</td>
    <td><a href="https://dmoj.ca/problem/dcc1p3/rank/">162</a></td>
  </tr>
  <tr>
    <td><a href="https://dmoj.ca/problem/dcc1p4">Dilhan&#39;s Computing Contest 1 P4 - Increasing Sequence With Gap</a></td>
    <td>17p</td>
    <td>12.4%</td>
    <td><a href="https://dmoj.ca//problem/dcc1p4/rank/">72</a></td>
  </tr>
  <tr>
    <td><a href="https://dmoj.ca/problem/dcc1p5">Dilhan&#39;s Computing Contest 1 P5 - Get It Twisted, They Will Divide Us</a></td>
    <td>17p</td>
    <td>5.3%</td>
    <td><a href="https://dmoj.ca/problem/dcc1p5/rank/">22</a></td>
  </tr>
  <tr>
    <td><a href="https://dmoj.ca/problem/dcc1p6">Dilhan&#39;s Computing Contest 1 P6 - Subsequence Reversal</a></td>
    <td>35p</td>
    <td>1.4%</td>
    <td><a href="https://dmoj.ca/problem/dcc1p6/rank/">2</a></td>
  </tr>
</table>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php
echo $OUTPUT->footer();
?>
