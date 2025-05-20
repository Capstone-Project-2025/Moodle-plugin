<?php
require_once('../../../config.php');
require_login();
header('Content-Type: application/json');

$apiurl = 'http://139.59.105.152';
$username = 'admin';
$password = 'admin';

// Authentification
$curl = curl_init("$apiurl/api/token/");
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['username' => $username, 'password' => $password])
]);
$response = curl_exec($curl);
$data = json_decode($response, true);
$token = $data['access'] ?? null;
curl_close($curl);

if (!$token) {
    echo json_encode(['error' => 'Token missing']);
    exit;
}

// Récupération des problèmes
$curl = curl_init("$apiurl/api/v2/problems");
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]
]);
$response = curl_exec($curl);
curl_close($curl);

// 🔄 Correction ici :
$data = json_decode($response, true);
$problems = [];

foreach ($data['data']['objects'] ?? [] as $problem) {
    if (isset($problem['name'], $problem['code'])) {
        $problems[] = [
            'name' => $problem['name'],
            'code' => $problem['code'],
            'description' => $problem['description'] ?? ''
        ];
    }
}

echo json_encode($problems);

