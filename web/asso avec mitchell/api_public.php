<?php
require_once __DIR__ . '/config.php';
require_once 'fonctions.php';
require_once 'DatabaseManager.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$action      = $_GET['action']      ?? '';
$tokenPublic = trim($_GET['tokenPublic'] ?? '');
$idTournoi   = intval($_GET['id']   ?? 0);

logServer("▶️ api_public action=$action tokenPublic=" . substr($tokenPublic, 0, 8) . "...");

if (empty($tokenPublic) || $idTournoi <= 0) {
    echo json_encode(["etat" => "TOKEN_INVALIDE"]);
    exit;
}

// =========================================================
// DÉTAIL D'UN TOURNOI PAR TOKEN PUBLIC
// =========================================================
if ($action === 'tournoi') {
    $detail = DatabaseManager::getDetailTournoiParTokenPublic($tokenPublic, $idTournoi);
    if (!$detail) {
        logServer("❌ api_public tournoi : token invalide id=$idTournoi");
        echo json_encode(["etat" => "TOKEN_INVALIDE"]);
        exit;
    }
    logServer("✅ api_public tournoi OK id=$idTournoi");
    echo json_encode(array_merge(["etat" => "OK"], $detail), JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================
// DÉTAIL D'UNE DONNE PAR TOKEN PUBLIC
// =========================================================
if ($action === 'donne') {
    $numeroDonne = intval($_GET['numeroDonne'] ?? 0);
    $equipeNS    = intval($_GET['equipeNS']    ?? 0);

    if ($numeroDonne <= 0 || $equipeNS <= 0) {
        echo json_encode(["etat" => "ERREUR_PARAM"]);
        exit;
    }

    $donne = DatabaseManager::getDonneCompleteParTokenPublic($tokenPublic, $idTournoi, $numeroDonne, $equipeNS);
    if (!$donne) {
        logServer("❌ api_public donne : token invalide ou donne introuvable");
        echo json_encode(["etat" => "TOKEN_INVALIDE"]);
        exit;
    }

    logServer("✅ api_public donne OK id=$idTournoi donne=$numeroDonne");
    echo json_encode([
        "etat"       => "OK",
        "mains"      => $donne['mains'],
        "encheres"   => $donne['encheres'],
        "vulnerable" => $donne['vulnerable'],
        "donneur"    => $donne['donneur'],
        "contrat"    => $donne['contrat'],
        "declarant"  => $donne['declarant']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

logServer("❌ api_public action inconnue : $action");
echo json_encode(["etat" => "ACTION_INCONNUE"]);
