<?php
require_once('../../config.php');
require_login();

$PAGE->set_url(new moodle_url('/mod/programmingassign/apiproblems.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('External Problems');
$PAGE->set_heading('List of Problems from API');

// Configuration
$apiurl = 'http://139.59.105.152';
$username = 'basicuser1';
$password = 'RoadRageWarrior1!';

// === Auth ===
function get_token($apiurl, $username, $password) {
    $curl = curl_init("$apiurl/api/token/");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'username' => $username,
        'password' => $password
    ]));
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    return $data['access'] ?? null;
}

// === Get problems from API ===
function get_problems($apiurl, $token) {
    $curl = curl_init("$apiurl/api/v2/problems");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token"
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}

// === Get token and problems ===
$token = get_token($apiurl, $username, $password);
$problems = [];
if ($token) {
    $apiresponse = get_problems($apiurl, $token);
    $problems = $apiresponse['data']['objects'] ?? [];
}

// === Get filter params ===
$searchname = optional_param('searchname', '', PARAM_TEXT);
$searchdifficulty = optional_param('searchdifficulty', '', PARAM_TEXT);
$minpoints = optional_param('minpoints', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 15;

// === Filter problems ===
if (!empty($searchname)) {
    $problems = array_filter($problems, function($p) use ($searchname) {
        return stripos($p['name'], $searchname) !== false;
    });
}
if (!empty($searchdifficulty)) {
    $problems = array_filter($problems, function($p) use ($searchdifficulty) {
        return stripos($p['difficulty'], $searchdifficulty) !== false;
    });
}
$problems = array_filter($problems, function($p) use ($minpoints) {
    return $p['points'] >= $minpoints;
});

// === Pagination logic ===
$total = count($problems);
$offset = $page * $perpage;
$problems_to_display = array_slice($problems, $offset, $perpage);

echo $OUTPUT->header();
echo $OUTPUT->heading('External Problems');

// === WRAPPER DIV ===
echo html_writer::start_div('dmoj-wrapper', ['style' => 'display: flex; gap: 30px;']);

// === LEFT COLUMN: PROBLEMS TABLE ===
echo html_writer::start_div('dmoj-left', ['style' => 'flex: 3;']);

if (!empty($problems_to_display)) {
    echo html_writer::start_tag('table', ['class' => 'generaltable', 'style' => 'width: 100%;']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Code');
    echo html_writer::tag('th', 'Name');
    echo html_writer::tag('th', 'Points');
    echo html_writer::tag('th', 'Public');
    echo html_writer::tag('th', 'Difficulty');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');
    foreach ($problems_to_display as $problem) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($problem['code']));
        echo html_writer::tag('td', s($problem['name']));
        echo html_writer::tag('td', s($problem['points']));
        echo html_writer::tag('td', $problem['is_public'] ? 'Yes' : 'No');
        echo html_writer::tag('td', s($problem['difficulty']));
        $addtestcaseurl = new moodle_url('/mod/programmingassign/addtestcase.php', ['code' => $problem['code']]);
        echo html_writer::tag('td', html_writer::link($addtestcaseurl, '➕ Add Test Case', ['class' => 'btn btn-secondary']));

        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    // Pagination bar
    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/mod/programmingassign/apiproblems.php', [
        'searchname' => $searchname,
        'searchdifficulty' => $searchdifficulty,
        'minpoints' => $minpoints
    ]));
}else {
    if (!$token) {
        echo $OUTPUT->notification('No problems found or failed to authenticate.', 'notifyproblem');
    } elseif (!empty($searchdifficulty)) {
        echo $OUTPUT->notification('No problems found for the selected difficulty: "' . s($searchdifficulty) . '"', 'notifyproblem');
    } elseif (!empty($searchname)) {
        echo $OUTPUT->notification('No problems found for the search: "' . s($searchname) . '"', 'notifyproblem');
    } else {
        echo $OUTPUT->notification('No problems match the selected filters.', 'notifyproblem');
    }
}


echo html_writer::end_div(); // END LEFT COLUMN

// === RIGHT COLUMN: SEARCH PANEL ===
$difficulties = ['' => 'All', 'easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'];

echo html_writer::start_div('dmoj-right', [
    'style' => 'flex: 1; background: #f9f9f9; padding: 15px; border: 1px solid #ccc; border-radius: 6px; height: fit-content;'
]);

echo html_writer::tag('h4', 'Problem search');

echo html_writer::start_tag('form', ['method' => 'get', 'action' => '']);

// Input nom
echo html_writer::tag('label', 'Search by name');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'searchname',
    'value' => $searchname,
    'placeholder' => 'e.g. Fibonacci',
    'style' => 'width: 100%; margin-bottom: 10px; padding: 5px;'
]);

// Dropdown difficulté colorée
echo html_writer::tag('label', 'Difficulty');
echo '<select name="searchdifficulty" style="width: 100%; margin-bottom: 10px; padding: 5px;">';
foreach ($difficulties as $value => $label) {
    $color = match ($value) {
        'easy' => 'green',
        'medium' => 'orange',
        'hard' => 'red',
        default => 'black'
    };
    $selected = ($value === $searchdifficulty) ? 'selected' : '';
    echo "<option value=\"$value\" style=\"color: $color;\" $selected>$label</option>";
}
echo '</select>';

// Slider de points
echo html_writer::tag('label', 'Minimum points:');
echo html_writer::start_div('', ['style' => 'margin-bottom: 10px;']);
echo '<input type="range" name="minpoints" id="minpoints" min="0" max="100" step="10" value="' . $minpoints . '" style="width: 100%; height: 25px;" oninput="document.getElementById(\'pointsValue\').textContent = this.value">';
echo '<div style="margin-top: 5px;">Points ≥ <span id="pointsValue">' . $minpoints . '</span></div>';
echo html_writer::end_div();

// Submit button
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => 'Filter',
    'style' => 'width: 100%; padding: 6px; background-color: #0073aa; color: white; border: none; border-radius: 4px;'
]);

echo html_writer::link(
    new moodle_url('/mod/programmingassign/uploadproblem.php'),
    'Upload new problem',
    ['class' => 'btn', 'style' => 'display: block; text-align: center; margin-top: 10px; padding: 8px; background-color: #28a745; color: white; border-radius: 5px; text-decoration: none;']
);

echo html_writer::end_tag('form');
echo html_writer::end_div(); // END RIGHT COLUMN

echo html_writer::end_div(); // END WRAPPER

echo $OUTPUT->footer();
