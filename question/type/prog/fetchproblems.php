<?php
require_once('../../../config.php');
require_login();
header('Content-Type: application/json');

// Ensure the request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

global $DB;

// Fetch all problems from the database, ordered by name
$records = $DB->get_records(
    'local_prog_problem',
    null,
    'name ASC',
    'code, name, description'
);

// Transform the records into a JSON-serializable array
$problems = [];
foreach ($records as $record) {
    $problems[] = [
        'code' => $record->code,
        'name' => $record->name,
        'description' => $record->description
    ];
}

// Return the list of problems as JSON
echo json_encode($problems);
