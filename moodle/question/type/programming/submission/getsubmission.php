<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$submission_id = $input['submission_id'] ?? null;

if (!$submission_id) {
    echo json_encode(['error' => 'Missing submission ID']);
    exit;
}

// === Authentification API ===
$apiurl = 'http://139.59.105.152';
$username = 'admin';
$password = 'admin';

$curl = curl_init("$apiurl/api/token/");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
    'username' => $username,
    'password' => $password
]));
$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);
$token = $data['access'] ?? null;

if (!$token) {
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

// === Appel GET /submission/{id} ===
$curl = curl_init("$apiurl/api/v2/submission/$submission_id");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "Authorization: Bearer $token"
]);

$response = curl_exec($curl);
curl_close($curl);

$submissiondata = json_decode($response, true);

echo json_encode([
    'status' => $submissiondata['data']['object']['status'] ?? 'unknown',
    'result' => $submissiondata['data']['object']['result'] ?? null,
    'language' => $submissiondata['data']['object']['language'] ?? null,
    'time' => $submissiondata['data']['object']['time'] ?? null,
    'memory' => $submissiondata['data']['object']['memory'] ?? null,
    'raw' => $submissiondata
]);
