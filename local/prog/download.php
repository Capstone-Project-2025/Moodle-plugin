<?php
require_once('../../config.php');
require_once(__DIR__ . '/classes/api/requests_to_dmoj.php');

$request = new FetchZipFile(optional_param('download_url', '', PARAM_URL));
$fileResponse = $request->run();

if ($fileResponse && isset($fileResponse['body']) && $fileResponse['body']) {

    // Save ZIP to a temporary file
    $tempfilename = tempnam(sys_get_temp_dir(), 'myplugin_') . '.zip';
    file_put_contents($tempfilename, $fileResponse['body']);

    // Check if file saved correctly
    if (file_exists($tempfilename)) {
        // Send the ZIP file to the browser
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="data.zip"');
        header('Content-Length: ' . filesize($tempfilename));
        readfile($tempfilename);
        unlink($tempfilename); // Clean up the temp file after sending
        exit;
    } else {
        echo $OUTPUT->notification('Failed to write temporary ZIP file.', 'error');
    }
} else {
    echo $OUTPUT->notification('No ZIP data available.', 'error');
}