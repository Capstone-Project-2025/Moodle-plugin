<?php
define('AJAX_SCRIPT', true);

require_once('../../../config.php');
require_login();

header('Content-Type: application/json; charset=utf-8');

global $DB, $USER;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

try {
    $records = $DB->get_records_select(
        'programming_problem',
        'ispublic = ? OR userid = ?',
        [1, $USER->id],
        'name ASC',
        'id, code, name, description, ispublic, userid'
    );

    $seen = [];
    $problems = [];
    foreach ($records as $r) {
        if (isset($seen[$r->code])) {
            continue;
        }
        $seen[$r->code] = true;

        $problems[] = [
            'id'          => (int)$r->id,
            'code'        => $r->code,
            'name'        => $r->name,
            'description' => $r->description,
            'ispublic'    => (int)$r->ispublic,
            'ownerid'     => (int)$r->userid,
        ];
    }

    echo json_encode($problems);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
    exit;
}
