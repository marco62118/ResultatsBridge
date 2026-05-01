<?php
require_once 'fonctions.php';
require_once 'DatabaseManager.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

logServer("▶️ traitement_inscription appelé");

$data         = json_decode(file_get_contents("php://input"), true);
$code         = strtoupper(trim($data['code']          ?? ''));
$nom          = trim($data['nom']           ?? '');
$email        = trim($data['email']         ?? '');
$mdp          = trim($data['mdp']           ?? '');
$codeAdherent = strtoupper(trim($data['code_adherent'] ?? ''));

if (empty($code) || empty($nom) || empty($email) || empty($mdp) || empty($codeAdherent)) {
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

if (strlen($codeAdherent) < 4) {
    echo json_encode(["etat" => "ERREUR", "message" => "Code adhérent trop court (4 caractères minimum)"]);
    exit;
}

$result = DatabaseManager::inscrireAssociation($nom, $email, $mdp, $code, $codeAdherent);

if ($result === 'CODE_INVALIDE') {
    logServer("⚠️ Code invitation invalide : $code");
    echo json_encode(["etat" => "CODE_INVALIDE"]);
    exit;
}

if ($result === 'EXISTE_DEJA') {
    logServer("⚠️ Email déjà utilisé : $email");
    echo json_encode(["etat" => "EXISTE_DEJA"]);
    exit;
}

if ($result === 'CODE_ADHERENT_EXISTE') {
    logServer("⚠️ Code adhérent déjà pris : $codeAdherent");
    echo json_encode(["etat" => "CODE_ADHERENT_EXISTE"]);
    exit;
}

if ($result === false) {
    logServer("❌ Erreur insertion : $email");
    echo json_encode(["etat" => "ERREUR", "message" => "Erreur serveur"]);
    exit;
}

logServer("✅ Inscription OK : $nom ($email) code_adherent=$codeAdherent");
echo json_encode(["etat" => "OK"]);
