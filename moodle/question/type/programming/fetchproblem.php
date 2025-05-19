<?php
require_once('../../../config.php');
require_login();
header('Content-Type: application/json');

$problemcode = required_param('code', PARAM_TEXT);

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

// Appel API de détail du problème
$curl = curl_init("$apiurl/api/v2/problem/$problemcode");
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]
]);
$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);
$object = $data['data']['object'] ?? [];

echo json_encode([
    'name' => $object['name'] ?? '',
    'code' => $object['code'] ?? '',
    'description' => $object['description'] ?? ''
]);

