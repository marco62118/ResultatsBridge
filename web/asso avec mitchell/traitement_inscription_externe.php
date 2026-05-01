<?php
require_once 'fonctions.php';
require_once 'DatabaseManager.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

logServer("▶️ traitement_inscription_externe");

$data   = json_decode(file_get_contents("php://input"), true);
$code   = strtoupper(trim($data['code']   ?? ''));
$nom    = strtoupper(trim($data['nom']    ?? ''));
$prenom = trim($data['prenom'] ?? '');
$email  = trim($data['email']  ?? '');
$mdp    = trim($data['mdp']    ?? '');

if (empty($code) || empty($nom) || empty($prenom) || empty($email) || empty($mdp)) {
    echo json_encode(["etat" => "ERREUR", "message" => "Tous les champs sont obligatoires"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["etat" => "ERREUR", "message" => "Email invalide"]);
    exit;
}

if (strlen($mdp) < 6) {
    echo json_encode(["etat" => "ERREUR", "message" => "Mot de passe trop court (6 caractères minimum)"]);
    exit;
}

$result = DatabaseManager::inscrireExterne($nom, $prenom, $email, $mdp, $code);

if ($result === 'CODE_INVALIDE') {
    logServer("⚠️ Code externe invalide : $code");
    echo json_encode(["etat" => "CODE_INVALIDE", "message" => "Code club invalide ou expiré"]);
    exit;
}

if ($result === 'EMAIL_EXISTE') {
    logServer("⚠️ Email déjà utilisé : $email");
    echo json_encode(["etat" => "EMAIL_EXISTE", "message" => "Cet email est déjà utilisé"]);
    exit;
}

if ($result === false) {
    logServer("❌ Erreur inscription externe");
    echo json_encode(["etat" => "ERREUR", "message" => "Erreur serveur"]);
    exit;
}

logServer("✅ Inscription externe OK : $prenom $nom ($email) → $result");
echo json_encode(["etat" => "OK", "message" => "✅ " . $result]);
