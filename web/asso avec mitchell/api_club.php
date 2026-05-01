<?php
require_once __DIR__ . '/config.php';
require_once 'fonctions.php';
require_once 'DatabaseManager.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$token  = $_GET['token']  ?? '';

logServer("▶️ api_club action=$action");

// =========================================================
// CONNEXION CLUB
// =========================================================
if ($action === 'connexion') {
    $data  = json_decode(file_get_contents("php://input"), true);
    $email = trim($data['email'] ?? '');
    $mdp   = trim($data['mdp']   ?? '');

    if (empty($email) || empty($mdp)) {
        echo json_encode(["etat" => "ERREUR"]);
        exit;
    }

    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare("
            SELECT ID, nom, token_api, nbre_tournois_joues, nbre_tournois_max, code_adherent, code_externe
            FROM associations
            WHERE email = ? AND mdp_hash = SHA2(?, 256) AND actif = 1
            LIMIT 1
        ");
        $stmt->execute([$email, $mdp]);
        $assoc = $stmt->fetch(PDO::FETCH_ASSOC);
        logServer("🔍 Résultat : " . ($assoc ? "trouvé ID=" . $assoc['ID'] : "AUCUN pour email=$email"));

        if (!$assoc) {
            logServer("❌ Connexion club refusée : $email");
            echo json_encode(["etat" => "ERREUR"]);
            exit;
        }

        logServer("✅ Connexion club OK : $email → token=" . substr($assoc['token_api'], 0, 8));
        echo json_encode([
            "etat"                => "OK",
            "token"               => $assoc['token_api'],
            "idAssociation"       => (int)$assoc['ID'],
            "nom"                 => $assoc['nom'],
            "nbre_tournois_joues" => (int)$assoc['nbre_tournois_joues'],
            "nbre_tournois_max"   => (int)$assoc['nbre_tournois_max'],
            "code_adherent"       => $assoc['code_adherent'] ?? '',
            "code_externe"        => $assoc['code_externe']  ?? ''
        ]);
    } catch (Exception $e) {
        logServer("❌ Erreur connexion club : " . $e->getMessage());
        echo json_encode(["etat" => "ERREUR"]);
    }
    exit;
}

// =========================================================
// MOT DE PASSE OUBLIÉ — ÉTAPE 1 : envoi du code
// Reçoit : { email }
// Renvoie : { etat: "OK" | "EMAIL_INCONNU" | "ERREUR" }
// =========================================================
if ($action === 'mdpOublie') {
    $data  = json_decode(file_get_contents("php://input"), true);
    $email = trim($data['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["etat" => "ERREUR"]);
        exit;
    }

    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare("SELECT ID FROM associations WHERE email = ? AND actif = 1 LIMIT 1");
        $stmt->execute([$email]);
        $assoc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assoc) {
            logServer("❌ mdpOublie club : email inconnu $email");
            echo json_encode(["etat" => "EMAIL_INCONNU"]);
            exit;
        }

        $idCible = (int)$assoc['ID'];

        // Invalider les anciens codes non utilisés
        $pdo->prepare("UPDATE reset_tokens SET utilise = 1 WHERE type = 'club' AND id_cible = ? AND utilise = 0")
            ->execute([$idCible]);

        // Générer le code à 6 chiffres
        $code     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expireAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $pdo->prepare("INSERT INTO reset_tokens (type, id_cible, code, expire_at) VALUES ('club', ?, ?, ?)")
            ->execute([$idCible, $code, $expireAt]);

        $sujet = "🃏 Réinitialisation de votre mot de passe club";
        $corps = "
            <p>Bonjour,</p>
            <p>Votre code de vérification pour réinitialiser le mot de passe de votre espace club :</p>
            <h2 style='letter-spacing:0.3em;color:#1a472a;font-size:2rem;font-family:monospace'>{$code}</h2>
            <p>Ce code est valable <strong>15 minutes</strong>.</p>
            <p>Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</p>
            <p style='color:#6b6b6b;font-size:0.85em'>— Tournoi Bridge Online</p>
        ";

        $ok = envoyerEmail($email, $sujet, $corps);
        logServer($ok ? "✅ mdpOublie club : code envoyé à $email" : "❌ mdpOublie club : échec envoi $email");
        echo json_encode(["etat" => $ok ? "OK" : "ERREUR"]);

    } catch (Exception $e) {
        logServer("❌ mdpOublie club exception : " . $e->getMessage());
        echo json_encode(["etat" => "ERREUR"]);
    }
    exit;
}

// =========================================================
// MOT DE PASSE OUBLIÉ — ÉTAPE 2 : vérification du code
// Reçoit : { email, code }
// Renvoie : { etat: "OK", token } | { etat: "CODE_INVALIDE" | "CODE_EXPIRE" }
// =========================================================
if ($action === 'verifierCodeMdp') {
    $data  = json_decode(file_get_contents("php://input"), true);
    $email = trim($data['email'] ?? '');
    $code  = trim($data['code']  ?? '');

    if (!$email || strlen($code) !== 6) {
        echo json_encode(["etat" => "ERREUR"]);
        exit;
    }

    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare("SELECT ID FROM associations WHERE email = ? AND actif = 1 LIMIT 1");
        $stmt->execute([$email]);
        $assoc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assoc) {
            echo json_encode(["etat" => "CODE_INVALIDE"]);
            exit;
        }

        $idCible = (int)$assoc['ID'];

        $stmt = $pdo->prepare("
            SELECT id, expire_at FROM reset_tokens
            WHERE type = 'club' AND id_cible = ? AND code = ? AND utilise = 0
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$idCible, $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            logServer("❌ verifierCodeMdp club : code invalide pour $email");
            echo json_encode(["etat" => "CODE_INVALIDE"]);
            exit;
        }

        if (strtotime($row['expire_at']) < time()) {
            $pdo->prepare("UPDATE reset_tokens SET utilise = 1 WHERE id = ?")
                ->execute([$row['id']]);
            logServer("❌ verifierCodeMdp club : code expiré pour $email");
            echo json_encode(["etat" => "CODE_EXPIRE"]);
            exit;
        }

        // Code valide → token de session reset (10 min)
        $resetToken = bin2hex(random_bytes(32));
        $expireAt   = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $pdo->prepare("UPDATE reset_tokens SET token = ?, expire_at = ? WHERE id = ?")
            ->execute([$resetToken, $expireAt, $row['id']]);

        logServer("✅ verifierCodeMdp club OK pour $email");
        echo json_encode(["etat" => "OK", "token" => $resetToken]);

    } catch (Exception $e) {
        logServer("❌ verifierCodeMdp club exception : " . $e->getMessage());
        echo json_encode(["etat" => "ERREUR"]);
    }
    exit;
}

// =========================================================
// MOT DE PASSE OUBLIÉ — ÉTAPE 3 : enregistrement nouveau mdp
// Reçoit : { email, token, mdp }
// Renvoie : { etat: "OK" | "TOKEN_INVALIDE" | "ERREUR" }
// =========================================================
if ($action === 'resetMdpClub') {
    $data  = json_decode(file_get_contents("php://input"), true);
    $email = trim($data['email'] ?? '');
    $token = trim($data['token'] ?? '');
    $mdp   = $data['mdp'] ?? '';

    if (!$email || !$token || strlen($mdp) < 8) {
        echo json_encode(["etat" => "ERREUR"]);
        exit;
    }

    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare("SELECT ID FROM associations WHERE email = ? AND actif = 1 LIMIT 1");
        $stmt->execute([$email]);
        $assoc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assoc) {
            echo json_encode(["etat" => "TOKEN_INVALIDE"]);
            exit;
        }

        $idCible = (int)$assoc['ID'];

        $stmt = $pdo->prepare("
            SELECT id, expire_at FROM reset_tokens
            WHERE type = 'club' AND id_cible = ? AND token = ? AND utilise = 0
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$idCible, $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || strtotime($row['expire_at']) < time()) {
            logServer("❌ resetMdpClub : token invalide/expiré pour $email");
            echo json_encode(["etat" => "TOKEN_INVALIDE"]);
            exit;
        }

        // Mettre à jour le mot de passe (SHA2-256 cohérent avec le projet)
        $pdo->prepare("UPDATE associations SET mdp_hash = SHA2(?, 256) WHERE ID = ?")
            ->execute([$mdp, $idCible]);

        // Invalider tous les tokens de ce club
        $pdo->prepare("UPDATE reset_tokens SET utilise = 1 WHERE type = 'club' AND id_cible = ?")
            ->execute([$idCible]);

        logServer("✅ resetMdpClub OK pour $email");
        echo json_encode(["etat" => "OK"]);

    } catch (Exception $e) {
        logServer("❌ resetMdpClub exception : " . $e->getMessage());
        echo json_encode(["etat" => "ERREUR"]);
    }
    exit;
}

// ── Vérification token pour les actions suivantes ──────────
if (empty($token)) {
    echo json_encode(["etat" => "NON_CONNECTE"]);
    exit;
}

try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT ID FROM associations WHERE token_api = ? AND actif = 1 LIMIT 1");
    $stmt->execute([$token]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(["etat" => "TOKEN_INVALIDE"]);
        exit;
    }
    $idAssociation = (int)$row['ID'];
} catch (Exception $e) {
    echo json_encode(["etat" => "ERREUR"]);
    exit;
}

// =========================================================
// LISTE DES ADHÉRENTS
// =========================================================
if ($action === 'adherents') {
    $stmt = $pdo->prepare("
        SELECT ID as id, nom, prenom, email, actif, type, date_inscription
        FROM joueurs
        WHERE id_association = ?
        ORDER BY type ASC, nom ASC, prenom ASC
    ");
    $stmt->execute([$idAssociation]);
    $adherents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logServer("✅ adherents → " . count($adherents) . " trouvés");
    echo json_encode(["etat" => "OK", "adherents" => $adherents]);
    exit;
}

// =========================================================
// CHANGER LE CODE ADHÉRENT
// =========================================================
if ($action === 'majCode') {
    $data = json_decode(file_get_contents("php://input"), true);
    $code = strtoupper(trim($data['code_adherent'] ?? ''));

    if (strlen($code) < 4) {
        echo json_encode(["etat" => "ERREUR_PARAM"]);
        exit;
    }

    $result = DatabaseManager::majCodeAdherent($idAssociation, $code);
    if ($result === 'CODE_EXISTE') {
        echo json_encode(["etat" => "CODE_EXISTE"]);
    } elseif ($result) {
        logServer("✅ Code adhérent mis à jour : $code pour assoc=$idAssociation");
        echo json_encode(["etat" => "OK", "code_adherent" => $code]);
    } else {
        echo json_encode(["etat" => "ERREUR"]);
    }
    exit;
}

// =========================================================
// CHANGER LE CODE EXTERNE
// =========================================================
if ($action === 'majCodeExterne') {
    $data = json_decode(file_get_contents("php://input"), true);
    $code = strtoupper(trim($data['code_externe'] ?? ''));

    if (strlen($code) < 4) {
        echo json_encode(["etat" => "ERREUR_PARAM"]);
        exit;
    }

    $result = DatabaseManager::majCodeExterne($idAssociation, $code);
    if ($result === 'CODE_EXISTE') {
        echo json_encode(["etat" => "CODE_EXISTE"]);
    } elseif ($result) {
        logServer("✅ Code externe mis à jour : $code pour assoc=$idAssociation");
        echo json_encode(["etat" => "OK", "code_externe" => $code]);
    } else {
        echo json_encode(["etat" => "ERREUR"]);
    }
    exit;
}

// =========================================================
// ACTIVER / DÉSACTIVER UN ADHÉRENT
// =========================================================
if ($action === 'toggleActif') {
    $data  = json_decode(file_get_contents("php://input"), true);
    $id    = intval($data['id']    ?? 0);
    $actif = intval($data['actif'] ?? 0);

    if ($id <= 0) { echo json_encode(["etat" => "ERREUR_PARAM"]); exit; }

    $stmt = $pdo->prepare("UPDATE joueurs SET actif = ? WHERE ID = ? AND id_association = ?");
    $stmt->execute([$actif, $id, $idAssociation]);

    if ($stmt->rowCount() > 0) {
        logServer("✅ toggleActif id=$id actif=$actif");
        echo json_encode(["etat" => "OK"]);
    } else {
        echo json_encode(["etat" => "ERREUR"]);
    }
    exit;
}

// =========================================================
// AJOUTER UN JOUEUR
// =========================================================
if ($action === 'ajouterJoueur') {
    $data   = json_decode(file_get_contents("php://input"), true);
    $nom    = strtoupper(trim($data['nom']    ?? ''));
    $prenom = trim($data['prenom'] ?? '');

    if (empty($nom) || empty($prenom)) {
        echo json_encode(["etat" => "ERREUR_PARAM"]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO joueurs (id_association, nom, prenom, actif) VALUES (?, ?, ?, 1)");
    $stmt->execute([$idAssociation, $nom, $prenom]);
    $newId = $pdo->lastInsertId();
    logServer("✅ Joueur ajouté ID=$newId $prenom $nom assoc=$idAssociation");
    echo json_encode(["etat" => "OK", "id" => (int)$newId]);
    exit;
}

// =========================================================
// SUPPRIMER UN JOUEUR
// =========================================================
if ($action === 'supprimer') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id   = intval($data['id'] ?? 0);

    if ($id <= 0) { echo json_encode(["etat" => "ERREUR_PARAM"]); exit; }

    $stmt = $pdo->prepare("SELECT ID FROM joueurs WHERE ID = ? AND id_association = ?");
    $stmt->execute([$id, $idAssociation]);
    if (!$stmt->fetch()) { echo json_encode(["etat" => "ERREUR"]); exit; }

    $stmt = $pdo->prepare("DELETE FROM joueurs WHERE ID = ? AND id_association = ?");
    $stmt->execute([$id, $idAssociation]);

    if ($stmt->rowCount() > 0) {
        logServer("✅ Joueur ID=$id supprimé");
        echo json_encode(["etat" => "OK"]);
    } else {
        echo json_encode(["etat" => "ERREUR"]);
    }
    exit;
}

// =========================================================
// RÉINITIALISER MOT DE PASSE D'UN ADHÉRENT (par le club)
// =========================================================
if ($action === 'resetMdp') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id   = intval($data['id']  ?? 0);
    $mdp  = trim($data['mdp']   ?? '');

    if ($id <= 0 || strlen($mdp) < 6) {
        echo json_encode(["etat" => "ERREUR_PARAM"]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE joueurs SET mdp_hash = SHA2(?, 256) WHERE ID = ? AND id_association = ?");
    $stmt->execute([$mdp, $id, $idAssociation]);

    if ($stmt->rowCount() > 0) {
        logServer("✅ resetMdp adhérent ID=$id");
        echo json_encode(["etat" => "OK"]);
    } else {
        echo json_encode(["etat" => "ERREUR"]);
    }
    exit;
}

// =========================================================
// LISTE DES TOURNOIS TERMINÉS (avec lien public)
// =========================================================
if ($action === 'tournois') {
    $tournois = DatabaseManager::getTournoisTermines($idAssociation);
    echo json_encode(["etat" => "OK", "tournois" => $tournois]);
    exit;
}

logServer("❌ Action inconnue : $action");
echo json_encode(["etat" => "ACTION_INCONNUE"]);

// =========================================================
// CONNEXION PDO — credentials lus depuis config.php
// =========================================================
function getPDO(): PDO {
    return new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}
