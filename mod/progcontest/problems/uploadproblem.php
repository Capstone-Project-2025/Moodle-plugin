<?php
require('../../../config.php');
require_login();

use local_dmoj_user_link\api\ProblemList;

global $DB, $USER, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT); // cmid
$backurl = new moodle_url('/mod/progcontest/problems/programming_problem.php', ['id' => $id]);

$cm = get_coursemodule_from_id('progcontest', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($cm->course, false, $cm);

$PAGE->set_url(new moodle_url('/mod/progcontest/problems/uploadproblem.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('uploadproblem', 'progcontest'));
$PAGE->set_heading(get_string('uploadproblem', 'progcontest'));

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $code = required_param('code', PARAM_TEXT);
    $name = required_param('name', PARAM_TEXT);
    $description = required_param('description', PARAM_RAW);
    $time_limit = required_param('time_limit', PARAM_FLOAT);
    $memory_limit = required_param('memory_limit', PARAM_INT);
    $types = required_param_array('types', PARAM_INT);
    $group = 1;
    $languages = optional_param_array('allowed_languages', [], PARAM_INT);
    $difficulty = required_param('difficulty', PARAM_TEXT);
    $points = 100;
    $ispublic = optional_param('is_public', 0, PARAM_BOOL);

    if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
        print_error("Invalid difficulty value.");
    }

    $payload = [
        'code' => $code,
        'name' => $name,
        'description' => $description,
        'points' => $points,
        'difficulty' => $difficulty,
        'time_limit' => $time_limit,
        'memory_limit' => $memory_limit,
        'types' => $types,
        'group' => $group,
        'allowed_languages' => $languages,
        'is_public' => (bool)$ispublic
    ];

    try {
        $response = ProblemList::create($payload);
        $status = $response['status'] ?? 0;

        if ($status === 201) {
            // ✅ Enregistrement local
            $problem = new stdClass();
            $problem->code = $code;
            $problem->name = $name;
            $problem->description = $description;
            $problem->userid = $USER->id;
            $problem->ispublic = $ispublic ? 1 : 0;
            $problem->points = 100;
            $problem->difficulty = $difficulty;
            $problem->time_limit = $time_limit;
            $problem->memory_limit = $memory_limit;

            $problemid = $DB->insert_record('programming_problem', $problem);

            foreach (array_unique($types) as $typeid) {
                $type = new stdClass();
                $type->problem_id = $problemid;
                $type->type_id = $typeid;
                $DB->insert_record('programming_problem_type', $type);
            }

            foreach (array_unique($languages) as $langid) {
                $language = new stdClass();
                $language->problem_id = $problemid;
                $language->language_id = $langid;
                $DB->insert_record('programming_problem_language', $language);
            }

            $message = get_string('uploadsuccess', 'progcontest');
        } else {
            $body = $response['body'];
            $errormsg = $body['detail'] ?? json_encode($body);
            $message = "❌ Failed to upload problem (HTTP $status) - $errormsg";
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// ✅ Récupération des types et langages
$typerecords = $DB->get_records('programming_type', null, 'id ASC');
$langrecords = $DB->get_records('programming_language', null, 'id ASC');

$types = array_map(function($t) {
    return ['id' => $t->id, 'name' => $t->name];
}, $typerecords);

$languages = array_map(function($l) {
    return ['id' => $l->id, 'name' => $l->name];
}, $langrecords);

$templatecontext = [
    'message' => $message,
    'sesskey' => sesskey(),
    'default_time_limit' => 2.0,
    'default_memory_limit' => 25000,
    'default_points' => 100,
    'types' => array_values($types),
    'languages' => array_values($languages),
    'formurl' => (new moodle_url('/mod/progcontest/problems/uploadproblem.php', ['id' => $id]))->out(),
    'backurl' => $backurl->out()
];

// ✅ Rendu
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_progcontest/uploadproblem', $templatecontext);
echo $OUTPUT->footer();
