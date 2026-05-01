<?php
require_once 'fonctions.php';
require_once 'DatabaseManager.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

logServer("▶️ traitement_inscription_adherent");

$data   = json_decode(file_get_contents("php://input"), true);
$code   = strtoupper(trim($data['code']   ?? ''));
$nom    = strtoupper(trim($data['nom']    ?? ''));
$prenom = trim($data['prenom'] ?? '');
$email  = trim($data['email']  ?? '');
$mdp    = trim($data['mdp']    ?? '');

// Vérification paramètres
if (empty($code) || empty($nom) || empty($prenom) || empty($email) || empty($mdp)) {
    echo json_encode(["etat" => "ERREUR", "message" => "Tous les champs sont obligatoires"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["etat" => "ERREUR", "message" => "Email invalide"]);
    exit;
}

if (strlen($mdp) < 6) {
    echo json_encode(["etat" => "ERREUR", "message" => "Mot de passe trop court"]);
    exit;
}

$result = DatabaseManager::inscrireAdherent($nom, $prenom, $email, $mdp, $code);

if ($result === 'CODE_INVALIDE') {
    logServer("⚠️ Code invalide : $code");
    echo json_encode(["etat" => "CODE_INVALIDE"]);
    exit;
}

if ($result === 'EMAIL_EXISTE') {
    logServer("⚠️ Email déjà utilisé : $email");
    echo json_encode(["etat" => "EMAIL_EXISTE"]);
    exit;
}

if ($result === false) {
    logServer("❌ Erreur inscription adhérent");
    echo json_encode(["etat" => "ERREUR", "message" => "Erreur serveur"]);
    exit;
}

// $result contient un message (joueur lié ou nouveau créé)
logServer("✅ Inscription adhérent OK : $prenom $nom ($email) → $result");
echo json_encode(["etat" => "OK", "message" => "✅ " . $result]);
