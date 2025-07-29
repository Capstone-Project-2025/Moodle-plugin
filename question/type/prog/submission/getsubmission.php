<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/local/prog/classes/api/ProblemSubmission.php');

use local_prog\api\ProblemSubmission;

require_login();
header('Content-Type: application/json');

global $DB, $USER;

// 📥 Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
$submission_id = $input['submission_id'] ?? null;

if (!$submission_id) {
    echo json_encode(['error' => 'Missing submission ID']);
    exit;
}

// 📡 Call the external API using your class
try {
    $response = ProblemSubmission::get_by_id($submission_id);
    $submissiondata = $response['body'] ?? [];
} catch (Exception $e) {
    echo json_encode(['error' => 'API error: ' . $e->getMessage()]);
    exit;
}

// 🔎 Check API response structure
if (!is_array($submissiondata) || empty($submissiondata['data']['object'])) {
    echo json_encode([
        'error' => 'Invalid response from API',
        'raw_response' => $submissiondata
    ]);
    exit;
}

$object = $submissiondata['data']['object'] ?? [];

// 🧠 Extract submission values
$result        = $object['result'] ?? 'unknown';
$point         = $object['case_points'] ?? 0.0;
$total_point   = $object['case_total'] ?? 0.0;
$source_code   = $object['source_code'] ?? '';
$language_name = $object['language'] ?? '';

// 🧩 (Optional) Convert language name to language_id
$languageId = null;
if (!empty($language_name)) {
    $sql = "SELECT language_id FROM {local_prog_language} 
            WHERE " . $DB->sql_compare_text('name') . " = " . $DB->sql_compare_text(':name');
    $params = ['name' => $language_name];
    $languageId = $DB->get_field_sql($sql, $params);
}

// 💾 Update database record if it exists for this user and submission
$existing = $DB->get_record('qtype_prog_submission', [
    'submission_id' => $submission_id,
    'user_id' => $USER->id
]);

if ($existing) {
    $DB->set_field('qtype_prog_submission', 'result', $result, ['submission_id' => $submission_id]);
    $DB->set_field('qtype_prog_submission', 'point', $point, ['submission_id' => $submission_id]);
    $DB->set_field('qtype_prog_submission', 'total_point', $total_point, ['submission_id' => $submission_id]);
    $DB->set_field('qtype_prog_submission', 'code', $source_code, ['submission_id' => $submission_id]);

    // ✅ Only update language_id if a valid ID was found
    if ($languageId) {
        $DB->set_field('qtype_prog_submission', 'language_id', $languageId, ['submission_id' => $submission_id]);
    }
}

// ✅ Send final JSON response to the client
echo json_encode([
    'status' => $object['status'] ?? 'unknown',
    'result' => $result,
    'language' => $language_name,
    'time' => $object['time'] ?? null,
    'memory' => $object['memory'] ?? null,
    'source_code' => $source_code,

    'case_points' => $point,
    'case_total' => $total_point,
    'cases' => $object['cases'] ?? [],

    'raw' => $submissiondata
]);
