<?php
define('AJAX_SCRIPT', true); // Important pour Moodle AJAX
require_once('../../../config.php');
require_login();


header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

if (!isset($_FILES['zipfile']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No zip file uploaded']);
    exit;
}

function extract_zip_file_list(string $zipPath): array {
    $zip = new ZipArchive();
    $input_files = [];
    $output_files = [];

    if ($zip->open($zipPath) === true) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (substr($entry, -3) === '.in') {
                $input_files[] = $entry;
            } elseif (substr($entry, -4) === '.out') {
                $output_files[] = $entry;
            }
        }
        $zip->close();
    }

    return [
        'input_files' => $input_files,
        'output_files' => $output_files
    ];
}

$response = extract_zip_file_list($_FILES['zipfile']['tmp_name']);
echo json_encode($response);
exit;
