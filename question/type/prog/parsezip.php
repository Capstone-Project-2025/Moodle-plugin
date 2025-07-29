<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_login(null, false);
require_sesskey();

header('Content-Type: application/json');

if (!isset($_FILES['zipfile']) || !is_uploaded_file($_FILES['zipfile']['tmp_name'])) {
    echo json_encode(['error' => 'No valid zip file uploaded']);
    exit;
}

$tmpfile = $_FILES['zipfile']['tmp_name'];
$zip = new ZipArchive();

if ($zip->open($tmpfile) !== true) {
    echo json_encode(['error' => 'Failed to open ZIP']);
    exit;
}

$input_files = [];
$output_files = [];

for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (preg_match('/\.in$/', $name)) {
        $input_files[] = $name;
    } elseif (preg_match('/\.out$/', $name)) {
        $output_files[] = $name;
    }
}
$zip->close();

echo json_encode([
    'input_files' => $input_files,
    'output_files' => $output_files
]);
exit;
