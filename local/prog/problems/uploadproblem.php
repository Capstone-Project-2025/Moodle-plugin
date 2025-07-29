<?php
require_once('../../../config.php');
require_login();

use local_prog\api\ProblemList;

global $DB, $USER;

$PAGE->set_url(new moodle_url('/local/prog/problems/uploadproblem.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Upload New Problem');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    // Récupération des paramètres
    $code = required_param('code', PARAM_TEXT);
    $name = required_param('name', PARAM_TEXT);
    $description = required_param('description', PARAM_RAW);
    $time_limit = required_param('time_limit', PARAM_FLOAT);
    $memory_limit = required_param('memory_limit', PARAM_INT);
    $types = required_param_array('types', PARAM_INT);
    $group = required_param('group', PARAM_INT);
    $languages = optional_param_array('allowed_languages', [], PARAM_INT);
    $difficulty = required_param('difficulty', PARAM_TEXT);
    $points = max(10, min(required_param('points', PARAM_INT), 100));
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
        error_log("=== PAYLOAD SENT TO API ===");
        error_log(print_r($payload, true));

        $response = ProblemList::create($payload);
        $status = $response['status'] ?? 0;

        if ($status === 201) {
            $problem = new stdClass();
            $problem->code = $code;
            $problem->name = $name;
            $problem->description = $description;
            $problem->userid = $USER->id;
            $problem->ispublic = $ispublic ? 1 : 0;
            $problem->points = $points;
            $problem->difficulty = $difficulty;

            $problemid = $DB->insert_record('local_prog_problem', $problem);

            foreach (array_unique($types) as $typeid) {
                $type = new stdClass();
                $type->problem_id = $problemid;
                $type->type_id = $typeid;
                $DB->insert_record('local_prog_type', $type);
            }

            foreach (array_unique($languages) as $langid) {
                $language = new stdClass();
                $language->problem_id = $problemid;
                $language->language_id = $langid;
                $DB->insert_record('local_prog_problem_language', $language);
            }

            $message = '✅ Problem successfully uploaded!';
        } else {
            $body = $response['body'];
            $errormsg = $body['detail'] ?? json_encode($body);
            $message = "❌ Failed to upload problem (HTTP $status) - $errormsg";
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Récupérer types et langages avec réindexation pour Mustache
$typerecords = $DB->get_records('local_prog_type', null, 'id ASC');
$langrecords = $DB->get_records('local_prog_language', null, 'id ASC');

$types = array_values(array_map(function($t) {
    return ['id' => $t->type_id, 'name' => $t->name];
}, $typerecords));

$languages = array_values(array_map(function($l) {
    return ['id' => $l->language_id, 'name' => $l->name];
}, $langrecords));

$templatecontext = [
    'message' => $message,
    'sesskey' => sesskey(),
    'default_time_limit' => 2.0,
    'default_memory_limit' => 262144,
    'default_points' => 100,
    'types' => $types,
    'languages' => $languages
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_prog/uploadproblem', $templatecontext);
echo $OUTPUT->footer();
