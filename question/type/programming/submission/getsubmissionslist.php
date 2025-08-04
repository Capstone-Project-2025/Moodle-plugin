<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/local/prog/classes/api/ProblemSubmission.php');

use local_prog\api\ProblemSubmission;

require_login();
require_sesskey();
header('Content-Type: application/json');

global $DB, $USER;

// 🔎 Get the question ID from the request
$questionid = required_param('questionid', PARAM_INT);

// 📥 Get all submissions made by the current user for the given question
$submissions = $DB->get_records('qtype_programming_submission', [
    'user_id' => $USER->id,
    'question_id' => $questionid
]);

$result = [];

// 🔁 Fetch submission details for each submission using the API class
foreach ($submissions as $submission) {
    $submission_id = $submission->submission_id;

    try {
        $response = ProblemSubmission::get_by_id($submission_id);
        $data = $response['body'] ?? [];
    } catch (Exception $e) {
        $result[] = [
            'submission_id' => $submission_id,
            'status' => 'error',
            'result' => 'API error: ' . $e->getMessage(),
            'language' => null,
            'time' => null,
            'memory' => null,
            'source' => null,
            'source_code' => null
        ];
        continue;
    }

    // Check if data is valid
    if (!is_array($data) || empty($data['data']['object'])) {
        $result[] = [
            'submission_id' => $submission_id,
            'status' => 'error',
            'result' => 'Invalid or empty response from API',
            'language' => null,
            'time' => null,
            'memory' => null,
            'source' => null,
            'source_code' => null
        ];
        continue;
    }

    // 🧠 Extract fields from the API object
    $object = $data['data']['object'];

    $result[] = [
        'submission_id' => $submission_id,
        'status'       => $object['status'] ?? 'unknown',
        'result'       => $object['result'] ?? null,
        'language'     => $object['language'] ?? null,
        'time'         => $object['time'] ?? null,
        'memory'       => $object['memory'] ?? null,
        'source'       => $object['source'] ?? null,
        'source_code'  => $object['source_code'] ?? null
    ];
}

// ✅ Return submissions list as JSON
echo json_encode(['submissions' => $result]);
exit;
