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
    <th>Rank</th>
    <th>Username</th>
    <th>1 (15)</th>
    <th>2 (15)</th>
    <th>3 (15)</th>
    <th>4 (15)</th>
    <th>5 (15)</th>
    <th>Total (75)</th>
  </tr>
  <tr>
    <td>1</td>
    <td>LintahlolSon</td>
    <td>15<br>00:54:19</td>
    <td>15<br>00:09:27</td>
    <td>15<br>00:44:30</td>
    <td>15<br>00:36:16</td>
    <td>15<br>01:10:06</td>
    <td>75<br>01:10:06</td>
  </tr>
  <tr>
    <td>2</td>
    <td>Egor</td>
    <td>15<br>00:04:09</td>
    <td>15<br>00:26:06</td>
    <td>15<br>00:19:40</td>
    <td>15<br>01:17:16</td>
    <td>15<br>01:12:13</td>
    <td>75<br>01:17:16</td>
  </tr>
</table>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
