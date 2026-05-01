<?php
// Endpoint unifié pour l'inscription depuis l'application Android.
// Essaie le code comme code_adherent (membre) puis comme code_externe (invité).
// Le joueur n'a pas à savoir quel type de code il possède.
require_once 'fonctions.php';
require_once 'DatabaseManager.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

logServer("▶️ traitement_inscription_joueur");

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

// Essai 1 : code membre
$result = DatabaseManager::inscrireAdherent($nom, $prenom, $email, $mdp, $code);

// Essai 2 : si le code ne correspond pas à un membre, tenter comme externe
if ($result === 'CODE_INVALIDE') {
    $result = DatabaseManager::inscrireExterne($nom, $prenom, $email, $mdp, $code);
}

if ($result === 'CODE_INVALIDE') {
    logServer("⚠️ Code invalide (ni membre ni externe) : $code");
    echo json_encode(["etat" => "CODE_INVALIDE", "message" => "Code club invalide. Vérifiez avec votre organisateur."]);
    exit;
}

if ($result === 'EMAIL_EXISTE') {
    logServer("⚠️ Email déjà utilisé : $email");
    echo json_encode(["etat" => "EMAIL_EXISTE", "message" => "Cet email est déjà utilisé."]);
    exit;
}

if ($result === false) {
    logServer("❌ Erreur inscription joueur");
    echo json_encode(["etat" => "ERREUR", "message" => "Erreur serveur"]);
    exit;
}

logServer("✅ Inscription joueur OK : $prenom $nom ($email)");
echo json_encode(["etat" => "OK", "message" => "✅ " . $result]);
