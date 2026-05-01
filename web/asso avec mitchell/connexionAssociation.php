<?php

require_once 'fonctions.php';
require_once 'DatabaseManager.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// =========================================================
// 🔐 Connexion d'une association
// POST JSON : { "email": "...", "mdp": "..." }
// Retourne : { "etat": "OK", "token_api": "...", "nom": "...", "idAssociation": N }
//         ou { "etat": "ERREUR", "message": "..." }
// =========================================================

logServer("▶️ connexionAssociation appelé");

$data  = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');
$mdp   = trim($data['mdp']   ?? '');

logServer("📥 email=$email");

// Vérification des paramètres
if (empty($email) || empty($mdp)) {
    logServer("⚠️ Paramètres manquants");
    echo json_encode([
        "etat"    => "ERREUR",
        "message" => "Email et mot de passe obligatoires"
    ]);
    exit;
}

// Vérification en base
$result = DatabaseManager::connexionAssociation($email, $mdp);

if ($result) {
    logServer("✅ Connexion OK pour $email → assoc ID=" . $result['idAssociation']);
    echo json_encode([
        "etat"          => "OK",
        "token_api"     => $result['token_api'],
        "nom"           => $result['nom'],
        "idAssociation" => $result['idAssociation']
    ]);
} else {
    logServer("❌ Connexion refusée pour $email");
    echo json_encode([
        "etat"    => "ERREUR",
        "message" => "Email ou mot de passe incorrect"
    ]);
}
