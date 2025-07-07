<?php
require('../../../config.php');
require_login();

use local_programming\api\ProblemList;

$PAGE->set_url(new moodle_url('/local/programming/problems/apiproblems.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('My Problems');
$PAGE->set_heading('List of Problems');

$searchname = optional_param('searchname', '', PARAM_TEXT);
$searchdifficulty = optional_param('searchdifficulty', '', PARAM_TEXT);
$minpoints = optional_param('minpoints', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 15;

global $USER, $DB;

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

$total = $DB->count_records_select('local_programming_problem', $sqlwhere, $params);
$offset = $page * $perpage;

$problems = $DB->get_records_select(
    'local_programming_problem',
    $sqlwhere,
    $params,
    'id ASC',
    '*',
    $offset,
    $perpage
);

// Prepare data for template
$difficulties = [
    ['value' => '', 'label' => 'All'],
    ['value' => 'easy', 'label' => 'Easy'],
    ['value' => 'medium', 'label' => 'Medium'],
    ['value' => 'hard', 'label' => 'Hard'],
];

$problem_list = [];
foreach ($problems as $problem) {
    $problem_list[] = [
        'code' => $problem->code,
        'name' => $problem->name,
        'points' => $problem->points,
        'ispublic' => $problem->ispublic ? 'Yes' : 'No',
        'difficulty' => $problem->difficulty ?? '-',
        'addtestcaseurl' => (new moodle_url('/local/programming/problems/addtestcase.php', ['code' => $problem->code]))->out()
    ];
}

$templatecontext = [
    'problems' => $problem_list,
    'hasproblems' => !empty($problem_list),
    'difficulties' => $difficulties,
    'selected_difficulty' => $searchdifficulty,
    'searchname' => $searchname,
    'minpoints' => $minpoints,
    'pagination' => $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/local/programming/problems/apiproblems.php', [
        'searchname' => $searchname,
        'searchdifficulty' => $searchdifficulty,
        'minpoints' => $minpoints
    ]), 'page'),
    'uploadurl' => (new moodle_url('/local/programming/problems/uploadproblem.php'))->out()
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_programming/apiproblems', $templatecontext);
echo $OUTPUT->footer();
