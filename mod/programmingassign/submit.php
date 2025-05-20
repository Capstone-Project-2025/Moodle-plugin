<?php

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$course_module = get_coursemodule_from_id('programmingassign', $id, 0, false, MUST_EXIST);
$context = context_module::instance($course_module->id);
$course = $DB->get_record('course', ['id' => $course_module->course], '*', MUST_EXIST);

require_login($course, true, $course_module);
require_capability('mod/programmingassign:submit', $context);

$instance = $DB->get_record('programmingassign', ['id' => $course_module->instance], '*', MUST_EXIST);
$usertext = required_param('usertext', PARAM_RAW);

// === JSON ===
$response = [
    'userId' => $USER->id,
    'id' => $instance->id,
    'title' => $instance->name,
    'response' => $usertext,
    'completed' => true
];

header('Content-Type: application/json');
echo json_encode($response);
exit;
