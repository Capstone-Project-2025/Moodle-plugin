<?php
require('../../../config.php'); // ✅ Chemin correct depuis local/
require_login();

use local_programming\api\ProblemList;

$PAGE->set_url(new moodle_url('/local/programming/problems/apiproblems.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('External Problems');
$PAGE->set_heading('List of Problems from API');

// === Paramètres de filtre ===
$searchname = optional_param('searchname', '', PARAM_TEXT);
$searchdifficulty = optional_param('searchdifficulty', '', PARAM_TEXT);
$minpoints = optional_param('minpoints', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 15;

// === Récupération depuis l'API
$response = ProblemList::get_all(); // ou get_by_type($type) si besoin
$data = json_decode($response['body'], true);
$allproblems = $data['data']['objects'] ?? [];

$problems = array_filter($allproblems, function ($p) use ($searchname, $searchdifficulty, $minpoints) {
    $match = true;
    if (!empty($searchname)) {
        $match = $match && stripos($p['name'], $searchname) !== false;
    }
    if (!empty($searchdifficulty) && isset($p['difficulty'])) {
        $match = $match && stripos($p['difficulty'], $searchdifficulty) !== false;
    }
    if (isset($p['points'])) {
        $match = $match && $p['points'] >= $minpoints;
    }
    return $match;
});

// === Pagination
$total = count($problems);
$offset = $page * $perpage;
$problems_to_display = array_slice($problems, $offset, $perpage);

// === Affichage
echo $OUTPUT->header();
echo $OUTPUT->heading('External Problems');

// === WRAPPER DIV
echo html_writer::start_div('dmoj-wrapper', ['style' => 'display: flex; gap: 30px;']);

// === GAUCHE : TABLEAU
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
    echo html_writer::tag('th', '');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');
    foreach ($problems_to_display as $problem) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($problem['code']));
        echo html_writer::tag('td', s($problem['name']));
        echo html_writer::tag('td', s($problem['points']));
        echo html_writer::tag('td', !empty($problem['is_public']) ? 'Yes' : 'No');
        echo html_writer::tag('td', s($problem['difficulty'] ?? '-'));

        $addtestcaseurl = new moodle_url('/mod/programmingassign/addtestcase.php', ['code' => $problem['code']]);
        echo html_writer::tag('td', html_writer::link($addtestcaseurl, '➕ Add Test Case', ['class' => 'btn btn-secondary']));

        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/local/programming/problems/apiproblems.php', [
        'searchname' => $searchname,
        'searchdifficulty' => $searchdifficulty,
        'minpoints' => $minpoints
    ]));
} else {
    echo $OUTPUT->notification('No problems match the selected filters.', 'notifyproblem');
}

echo html_writer::end_div(); // LEFT

// === DROITE : FILTRES
$difficulties = ['' => 'All', 'easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'];

echo html_writer::start_div('dmoj-right', [
    'style' => 'flex: 1; background: #f9f9f9; padding: 15px; border: 1px solid #ccc; border-radius: 6px; height: fit-content;'
]);

echo html_writer::tag('h4', 'Problem search');

echo html_writer::start_tag('form', ['method' => 'get']);

// Nom
echo html_writer::tag('label', 'Search by name');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'searchname',
    'value' => $searchname,
    'placeholder' => 'e.g. Fibonacci',
    'style' => 'width: 100%; margin-bottom: 10px; padding: 5px;'
]);

// Difficulté
echo html_writer::tag('label', 'Difficulty');
echo '<select name="searchdifficulty" style="width: 100%; margin-bottom: 10px; padding: 5px;">';
foreach ($difficulties as $value => $label) {
    $color = match ($value) {
        'easy' => 'green', 'medium' => 'orange', 'hard' => 'red', default => 'black'
    };
    $selected = ($value === $searchdifficulty) ? 'selected' : '';
    echo "<option value=\"$value\" style=\"color: $color;\" $selected>$label</option>";
}
echo '</select>';

// Points
echo html_writer::tag('label', 'Minimum points:');
echo html_writer::start_div('', ['style' => 'margin-bottom: 10px;']);
echo '<input type="range" name="minpoints" id="minpoints" min="0" max="100" step="10" value="' . $minpoints . '" style="width: 100%; height: 25px;" oninput="document.getElementById(\'pointsValue\').textContent = this.value">';
echo '<div style="margin-top: 5px;">Points ≥ <span id="pointsValue">' . $minpoints . '</span></div>';
echo html_writer::end_div();

// Bouton
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => 'Filter',
    'class' => 'btn btn-primary',
    'style' => 'width: 100%;'
]);

// Lien vers upload
echo html_writer::link(
    new moodle_url('/mod/programmingassign/uploadproblem.php'),
    'Upload new problem',
    ['class' => 'btn', 'style' => 'display: block; text-align: center; margin-top: 10px; padding: 8px; background-color: #28a745; color: white; border-radius: 5px; text-decoration: none;']
);

echo html_writer::end_tag('form');
echo html_writer::end_div(); // RIGHT

echo html_writer::end_div(); // WRAPPER

echo $OUTPUT->footer();
