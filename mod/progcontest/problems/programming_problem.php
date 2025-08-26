<?php
require('../../../config.php');
require_login();

use local_dmoj_user_link\api\ProblemList;

$id = required_param('id', PARAM_INT); // cmid

$cm = get_coursemodule_from_id('progcontest', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($cm->course, false, $cm);

$PAGE->set_url(new moodle_url('/mod/progcontest/problems/programming_problem.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('apiproblemslink', 'progcontest'));
$PAGE->set_heading(get_string('apiproblemslink', 'progcontest'));

global $USER, $DB, $OUTPUT;

$searchname = optional_param('searchname', '', PARAM_TEXT);
$searchdifficulty = optional_param('searchdifficulty', '', PARAM_TEXT);
$minpoints = optional_param('minpoints', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 15;

// Préparation de la requête
$sqlwhere = 'userid = :userid';
$params = ['userid' => $USER->id];

if (!empty($searchname)) {
    $sqlwhere .= ' AND name LIKE :searchname';
    $params['searchname'] = '%' . $DB->sql_like_escape($searchname) . '%';
}

if (!empty($searchdifficulty)) {
    $sqlwhere .= ' AND difficulty = :difficulty';
    $params['difficulty'] = $searchdifficulty;
}

if ($minpoints > 0) {
    $sqlwhere .= ' AND points >= :minpoints';
    $params['minpoints'] = $minpoints;
}

// Comptage total pour pagination
$total = $DB->count_records_select('programming_problem', $sqlwhere, $params);
$offset = $page * $perpage;

// Récupération des problèmes
$problems = $DB->get_records_select(
    'programming_problem',
    $sqlwhere,
    $params,
    'id ASC',
    '*',
    $offset,
    $perpage
);

// Données pour le template
$difficulties = [
    ['value' => '', 'label' => get_string('all')],
    ['value' => 'easy', 'label' => get_string('easy', 'progcontest')],
    ['value' => 'medium', 'label' => get_string('medium', 'progcontest')],
    ['value' => 'hard', 'label' => get_string('hard', 'progcontest')],
];

$problem_list = [];
foreach ($problems as $problem) {
    $problem_list[] = [
        'code' => $problem->code,
        'name' => $problem->name,
        'points' => $problem->points,
        'ispublic' => $problem->ispublic ? get_string('yes') : get_string('no'),
        'difficulty' => $problem->difficulty ?? '-',
        'addtestcaseurl' => (new moodle_url('/mod/progcontest/problems/addtestcase.php', [
            'id' => $id,
            'code' => $problem->code
        ]))->out(false)
    ];
}

$templatecontext = [
    'problems' => $problem_list,
    'hasproblems' => !empty($problem_list),
    'difficulties' => $difficulties,
    'selected_difficulty' => $searchdifficulty,
    'searchname' => $searchname,
    'minpoints' => $minpoints,
    'cmid' => $id,
    'pagination' => $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/mod/progcontest/problems/programming_problem.php', [
        'id' => $id,
        'searchname' => $searchname,
        'searchdifficulty' => $searchdifficulty,
        'minpoints' => $minpoints
    ]), 'page'),
    'uploadurl' => (new moodle_url('/mod/progcontest/problems/uploadproblem.php', ['id' => $id]))->out()
];

// Affichage
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_progcontest/apiproblems', $templatecontext);
echo $OUTPUT->footer();
