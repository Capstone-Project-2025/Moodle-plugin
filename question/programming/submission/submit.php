<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/local/dmoj_user_link/classes/api/ProblemSubmission.php');

use local_dmoj_user_link\api\ProblemSubmission;

header('Content-Type: application/json');

// 🔐 User security check
if (!isloggedin() || isguestuser()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied: user not logged in']);
    exit;
}

global $DB, $USER;

// 🔄 Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// 🔑 Validate session key
$sesskey = $input['sesskey'] ?? '';
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['error' => 'Invalid sesskey']);
    exit;
}

// 📦 Extract input values
$code        = $input['code'] ?? '';
$problemcode = $input['problemcode'] ?? '';
$languageId  = $input['language'] ?? 1;
$attemptid   = isset($input['attemptid']) ? (int)$input['attemptid'] : null;
$questionid  = isset($input['questionid']) ? (int)$input['questionid'] : null;
$userid      = (int)$USER->id;

// 📘 Get course ID from quiz attempt (if available)
$courseid = null;
error_log("📥 valeur de attemptid :  $attemptid");
if ($attemptid) {
    $sql = "
        SELECT c.id AS courseid
        FROM {progcontest_attempts} pa
        JOIN {progcontest} pc ON pc.id = pa.progcontest
        JOIN {course} c ON c.id = pc.course
        WHERE pa.id = :attemptid
        LIMIT 1
";
    $record = $DB->get_record_sql($sql, ['attemptid' => $attemptid]);
    $courseid = $record->courseid ?? null;
    error_log('⚠️ valeur de course id : ' . $courseid);

}

// 🚫 Required field validation
if (!$questionid) {
    echo json_encode(['error' => 'Missing question ID']);
    exit;
}
if (!$problemcode) {
    echo json_encode(['error' => 'Missing problem code']);
    exit;
}

// 📨 Submit the code to external API using your class
try {
    $submissionApi = new ProblemSubmission('', '');
    $submitResponse = $submissionApi->submit($problemcode, $code, $languageId);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// 📥 Handle API response
$submission_id = $submitResponse['body']['submission_id'] ?? null;



$result       = $submitResponse['body']['result'] ?? 'pending';
$points       = $submitResponse['body']['case_points'] ?? 0;
$total_point  = $submitResponse['body']['case_total'] ?? 0;
$source_code  = $submitResponse['body']['source_code'] ?? '';

if ($result === null) {
    $result = 'pending';
}

// 💾 Insert submission into Moodle database
$questionrecord = $DB->get_record('qtype_programming_options', ['id' => $questionid]);

if ($questionrecord) {
    error_log('📥 questionrecord existe');
} else {
    error_log('📥 questionrecord est null/false');
}
if ($questionrecord) {
    error_log("📥 present dans le if : question not null");
    error_log("📥 valeur de attemptid numero 2 avant input :  $attemptid");
    try {
        $DB->execute(
            "INSERT INTO {qtype_programming_submission}
            (submission_id, question_id, user_id, result, point, total_point, language_id, course_id, code, attempt_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$submission_id, $questionid, $userid, $result, $points, $total_point, $languageId, $courseid, $source_code, $attemptid]
        );
    } catch (dml_exception $e) {
        error_log('⚠️ INSERT failed: ' . $e->getMessage());
    }
} else {
    echo json_encode(['error' => 'Question configuration not found']);
    exit;
}

// ✅ Final JSON response
echo json_encode([
    'submission_id' => $submission_id,
    'raw_response' => $submitResponse
]);
exit;
