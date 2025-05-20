<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();
header('Content-Type: application/json');

// Lecture de la requête JSON envoyée par JavaScript
$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';
$problemcode = $input['problemcode'] ?? '';

// Vérification des paramètres requis

// === Configuration de l'API DMOJ ===
$apiurl = 'http://139.59.105.152'; // Remplace si besoin
$username = 'admin';
$password = 'admin';

// === Étape 1 : Authentification pour obtenir le token ===
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

// === Étape 2 : Soumettre le code à l'API ===
$curl = curl_init("$apiurl/api/v2/problem/$problemcode/submit");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "Authorization: Bearer $token"
]);

$languageId = $input['language'] ?? 1;

curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
    'source' => $code ?: "print(123)",  // fallback au cas où vide
    'language' => $languageId,                   // à corriger si invalide
    'judge' => ''                       // vide pour auto
]));


$response = curl_exec($curl);
curl_close($curl);



// === Réponse de l'API ===
$submitResponse = json_decode($response, true);

// === Retour vers le navigateur (frontend JS) ===
echo json_encode([
    'submission_id' => $submitResponse['submission_id'] ?? null,
    'error' => $submitResponse['error'] ?? null,
    'raw_response' => $submitResponse // tableau, pas chaîne JSON
]);

