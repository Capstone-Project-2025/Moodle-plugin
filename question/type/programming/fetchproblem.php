<?php
require_once('../../../config.php');
require_login();
header('Content-Type: application/json');

// Make sure the request is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

// Retrieve the problem code from the URL parameters
$code = required_param('code', PARAM_TEXT);

global $DB;

// Try to fetch the corresponding problem from the database
$record = $DB->get_record(
    'programming_problem',
    ['code' => $code],
    'id, code, name, description',
    IGNORE_MISSING
);

// If not found, return an error message
if (!$record) {
    http_response_code(404);
    echo json_encode(['error' => 'Problem not found']);
    exit;
}

// Return the problem data as JSON
echo json_encode([
    'id' => $record->id,
    'code' => $record->code,
    'name' => $record->name,
    'description' => $record->description
]);
