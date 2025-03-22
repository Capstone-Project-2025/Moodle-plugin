<?php
require_once('../../config.php');

$PAGE->set_url(new moodle_url('/local/problems/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Problem Management');
$PAGE->set_heading('Problem details');

echo $OUTPUT->header();
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="style.css">

<div class="container mt-5 text-center">
    <h2>Dilhan&#39;s Computing Contest 1 P1 - The Cathedral of Learning</h2>
</div>

<h4>Info</h4>
<li>Points: 5 (partial)</li>
<li>Time limit: 2.0s</li>
<li>Memory limit: 1G</li>
<button>Submit solution</button>
<br>
<p>Alice and Bob have scheduled a meeting inside of the Cathedral of Learning. Unfortunately, they didn't decide what floor to meet on!</p>
<p>The Cathedral of Learning has <span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/b51a60734da64be0e618bacbea2865a8a7dcd669/svg" style="vertical-align: -0.338ex; width:2.064ex; height:2.176ex;" alt="N"><span class="tex-text" style="display:none;">~N~</span></span> floors. Alice will start on floor <span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/86f7e437faa5a7fce15d1ddcb9eaeaea377667b8/svg" style="vertical-align: -0.338ex; width:1.23ex; height:1.676ex;" alt="a"><span class="tex-text" style="display:none;">~a~</span></span> and Bob will start on floor <span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98/svg" style="vertical-align: -0.338ex; width:0.998ex; height:2.176ex;" alt="b"><span class="tex-text" style="display:none;">~b~</span></span>. The two will then employ opposing strategies to try and find the other: Every minute Alice and Bob will each explore the floor they are on, and will find each other if they are on the same floor. At the end of the minute Alice will take an elevator one floor up, and simultaneously Bob will take an elevator one floor down. They will repeat this process until either they meet up or one of the two can no longer continue the process (i.e. Alice finishes exploring floor <span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/b51a60734da64be0e618bacbea2865a8a7dcd669/svg" style="vertical-align: -0.338ex; width:2.064ex; height:2.176ex;" alt="N"><span class="tex-text" style="display:none;">~N~</span></span> or Bob finishes exploring floor <span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/356a192b7913b04c54574d18c28d46e6395428ab/svg" style="vertical-align: -0.338ex; width:1.162ex; height:2.176ex;" alt="1"><span class="tex-text" style="display:none;">~1~</span></span>) in which case they will leave the building and agree to meet up at a later time.</p>
<p>Given <span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/8f6a2d52ba95bacc3247d81f7d64f983448335b1/svg" style="vertical-align: -0.671ex; width:4.974ex; height:2.509ex;" alt="N, a,"><span class="tex-text" style="display:none;">~N, a,~</span></span> and <span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98/svg" style="vertical-align: -0.338ex; width:0.998ex; height:2.176ex;" alt="b"><span class="tex-text" style="display:none;">~b~</span></span> determine if Alice and Bob will successfully meet up within the Cathedral of Learning.</p>
<br>
<h4>Constraints</h4>
<p><span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/5ae4bddaed66c39a6ca8300d1462530f89822715/svg" style="vertical-align: -0.505ex; width:13.624ex; height:2.843ex;" alt="1 \leq N \leq 10^{12}"><span class="tex-text" style="display:none;">~1 \leq N \leq 10^{12}~</span></span></p>
<p><span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/01608fdb6fdcf6fb899a443856b958ec316f7277/svg" style="vertical-align: -0.671ex; width:12.685ex; height:2.509ex;" alt="1 \leq a, b \leq N"><span class="tex-text" style="display:none;">~1 \leq a, b \leq N~</span></span></p>
<h5>Subtask 1 [60%]</h5>
<p><span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/a0a7539390ef2e92e6afc5f4925efdde5c1b0821/svg" style="vertical-align: -0.505ex; width:12.911ex; height:2.343ex;" alt="1 \leq N \leq 100"><span class="tex-text" style="display:none;">~1 \leq N \leq 100~</span></span></p>
<h5>Subtask 2 [40%]</h5>
<p>No additional constraints.</p>
<br>
<h4>Input Specification</h4>
<p>The first line contains the integer <span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/b51a60734da64be0e618bacbea2865a8a7dcd669/svg" style="vertical-align: -0.338ex; width:2.064ex; height:2.176ex;" alt="N"><span class="tex-text" style="display:none;">~N~</span></span>.</p>
<p>The second line contains the integer <span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/86f7e437faa5a7fce15d1ddcb9eaeaea377667b8/svg" style="vertical-align: -0.338ex; width:1.23ex; height:1.676ex;" alt="a"><span class="tex-text" style="display:none;">~a~</span></span>.</p>
<p>The third line contains the integer <span class="inline-math"><img class="tex-image" src="//static.dmoj.ca/mathoid/e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98/svg" style="vertical-align: -0.338ex; width:0.998ex; height:2.176ex;" alt="b"><span class="tex-text" style="display:none;">~b~</span></span>.</p>
<br>
<h4>Output Specification</h4>
<p>On a single line output the string <code>YES</code> or the string <code>NO</code> - corresponding to whether Alice and Bob will meet in the given scenario.</p>
<br>
<h4>Sample Input 1</h4>

<pre><code>5
3
4</code></pre>
<h4>Sample Output 1</h4>

<pre><code>NO</code></pre>
<h4>Explanation for Sample Output 1</h4>
<p>In the first minute Alice will explore floor 3 and Bob will explore floor 4 - they will not meet.<br>
Alice will then take the elevator to floor 4 while simultaneously Bob takes the elevator to floor 3.<br>
In the second minute Alice will explore floor 4 and Bob will explore floor 3 - they will not meet.<br>
Alice will then take the elevator to floor 5 while simultaneously Bob takes the elevator to floor 2.<br>
In the third minute Alice will explore floor 5 and Bob will explore floor 2 - they will not meet.<br>
Alice has now explored the top floor and will then leave the cathedral - Alice and Bob will not meet in the cathedral.</p>
<h4>Sample Input 2</h4>

<pre><code>10
4
4</code></pre>
<h4>Sample Output 2</h4>

<pre><code>YES</code></pre>
<h4>Sample Input 3</h4>

<pre><code>12345
1
12345</code></pre>
<h4>Sample Output 3</h4>

<pre><code>YES</code></pre>
<h4>Sample Input 4</h4>

<pre><code>12345
12345
1</code></pre>
<h4>Sample Output 4</h4>

<pre><code>NO</code></pre>
</div>