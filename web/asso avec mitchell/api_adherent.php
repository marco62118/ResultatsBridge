<?php
require_once __DIR__ . '/config.php';
require_once 'fonctions.php';
require_once 'DatabaseManager.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$token  = $_GET['token']  ?? '';

logServer("▶️ api_adherent action=$action token=" . substr($token, 0, 8) . "...");

// =========================================================
// CONNEXION ADHÉRENT
// =========================================================
if ($action === 'connexion') {
    $data  = json_decode(file_get_contents("php://input"), true);
    $email = trim($data['email'] ?? '');
    $mdp   = trim($data['mdp']   ?? '');

    if (empty($email) || empty($mdp)) {
        echo json_encode(["etat" => "ERREUR", "message" => "Paramètres manquants"]);
        exit;
    }

    $result = DatabaseManager::connexionAdherent($email, $mdp);
    if ($result === 'COMPTE_INACTIF') {
        logServer("⚠️ Connexion adhérent refusée (compte inactif) : $email");
        echo json_encode(["etat" => "COMPTE_INACTIF"]);
    } elseif ($result) {
        logServer("✅ Connexion adhérent OK : $email → token=" . substr($result['token'], 0, 8));
        echo json_encode([
            "etat"          => "OK",
            "token"         => $result['token'],
            "idAssociation" => $result['idAssociation'],
            "nomClub"       => $result['nomClub'],
            "nom"           => $result['nom'],
            "prenom"        => $result['prenom']
        ]);
    } else {
        logServer("❌ Connexion adhérent refusée : $email");
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
        // On cherche dans joueurs (les adhérents qui ont un compte = email non null)
        $stmt = $pdo->prepare("
            SELECT ID, prenom FROM joueurs
            WHERE email = ? AND actif = 1 AND mdp_hash IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $joueur = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$joueur) {
            logServer("❌ mdpOublie adhérent : email inconnu ou sans compte $email");
            echo json_encode(["etat" => "EMAIL_INCONNU"]);
            exit;
        }

        $idCible = (int)$joueur['ID'];
        $prenom  = $joueur['prenom'];

        // Invalider les anciens codes non utilisés
        $pdo->prepare("UPDATE reset_tokens SET utilise = 1 WHERE type = 'adherent' AND id_cible = ? AND utilise = 0")
            ->execute([$idCible]);

        // Générer le code à 6 chiffres
        $code     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expireAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $pdo->prepare("INSERT INTO reset_tokens (type, id_cible, code, expire_at) VALUES ('adherent', ?, ?, ?)")
            ->execute([$idCible, $code, $expireAt]);

        $sujet = "🃏 Réinitialisation de votre mot de passe";
        $corps = "
            <p>Bonjour {$prenom},</p>
            <p>Votre code de vérification pour réinitialiser votre mot de passe :</p>
            <h2 style='letter-spacing:0.3em;color:#1a472a;font-size:2rem;font-family:monospace'>{$code}</h2>
            <p>Ce code est valable <strong>15 minutes</strong>.</p>
            <p>Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</p>
            <p style='color:#6b6b6b;font-size:0.85em'>— Tournoi Bridge Online</p>
        ";

        $ok = envoyerEmail($email, $sujet, $corps);
        logServer($ok ? "✅ mdpOublie adhérent : code envoyé à $email" : "❌ mdpOublie adhérent : échec envoi $email");
        echo json_encode(["etat" => $ok ? "OK" : "ERREUR"]);

    } catch (Exception $e) {
        logServer("❌ mdpOublie adhérent exception : " . $e->getMessage());
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
        $stmt = $pdo->prepare("SELECT ID FROM joueurs WHERE email = ? AND actif = 1 LIMIT 1");
        $stmt->execute([$email]);
        $joueur = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$joueur) {
            echo json_encode(["etat" => "CODE_INVALIDE"]);
            exit;
        }

        $idCible = (int)$joueur['ID'];

        $stmt = $pdo->prepare("
            SELECT id, expire_at FROM reset_tokens
            WHERE type = 'adherent' AND id_cible = ? AND code = ? AND utilise = 0
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$idCible, $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            logServer("❌ verifierCodeMdp adhérent : code invalide pour $email");
            echo json_encode(["etat" => "CODE_INVALIDE"]);
            exit;
        }

        if (strtotime($row['expire_at']) < time()) {
            $pdo->prepare("UPDATE reset_tokens SET utilise = 1 WHERE id = ?")
                ->execute([$row['id']]);
            logServer("❌ verifierCodeMdp adhérent : code expiré pour $email");
            echo json_encode(["etat" => "CODE_EXPIRE"]);
            exit;
        }

        // Code valide → token de session reset (10 min)
        $resetToken = bin2hex(random_bytes(32));
        $expireAt   = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $pdo->prepare("UPDATE reset_tokens SET token = ?, expire_at = ? WHERE id = ?")
            ->execute([$resetToken, $expireAt, $row['id']]);

        logServer("✅ verifierCodeMdp adhérent OK pour $email");
        echo json_encode(["etat" => "OK", "token" => $resetToken]);

    } catch (Exception $e) {
        logServer("❌ verifierCodeMdp adhérent exception : " . $e->getMessage());
        echo json_encode(["etat" => "ERREUR"]);
    }
    exit;
}

// =========================================================
// MOT DE PASSE OUBLIÉ — ÉTAPE 3 : enregistrement nouveau mdp
// Reçoit : { email, token, mdp }
// Renvoie : { etat: "OK" | "TOKEN_INVALIDE" | "ERREUR" }
// =========================================================
if ($action === 'resetMdp') {
    $data  = json_decode(file_get_contents("php://input"), true);
    $email = trim($data['email'] ?? '');
    $token = trim($data['token'] ?? '');
    $mdp   = $data['mdp'] ?? '';

    if (!$email || !$token || strlen($mdp) < 6) {
        echo json_encode(["etat" => "ERREUR"]);
        exit;
    }

    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare("SELECT ID FROM joueurs WHERE email = ? AND actif = 1 LIMIT 1");
        $stmt->execute([$email]);
        $joueur = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$joueur) {
            echo json_encode(["etat" => "TOKEN_INVALIDE"]);
            exit;
        }

        $idCible = (int)$joueur['ID'];

        $stmt = $pdo->prepare("
            SELECT id, expire_at FROM reset_tokens
            WHERE type = 'adherent' AND id_cible = ? AND token = ? AND utilise = 0
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$idCible, $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || strtotime($row['expire_at']) < time()) {
            logServer("❌ resetMdp adhérent : token invalide/expiré pour $email");
            echo json_encode(["etat" => "TOKEN_INVALIDE"]);
            exit;
        }

        // Mettre à jour le mot de passe (SHA2-256 cohérent avec le projet)
        $pdo->prepare("UPDATE joueurs SET mdp_hash = SHA2(?, 256) WHERE ID = ?")
            ->execute([$mdp, $idCible]);

        // Invalider tous les tokens de cet adhérent
        $pdo->prepare("UPDATE reset_tokens SET utilise = 1 WHERE type = 'adherent' AND id_cible = ?")
            ->execute([$idCible]);

        logServer("✅ resetMdp adhérent OK pour $email");
        echo json_encode(["etat" => "OK"]);

    } catch (Exception $e) {
        logServer("❌ resetMdp adhérent exception : " . $e->getMessage());
        echo json_encode(["etat" => "ERREUR"]);
    }
    exit;
}

// =========================================================
// VÉRIFICATION TOKEN — appelé au démarrage de l'app Android
// Vérifie que le token de session est toujours valide (non expiré, joueur actif).
// Si valide, renouvelle l'expiration de 24h.
// =========================================================
if ($action === 'verifierToken') {
    $joueur = DatabaseManager::getAdherentParToken($token);
    if ($joueur) {
        logServer("✅ verifierToken OK → idJoueur=" . $joueur['idJoueur']);
        echo json_encode(["etat" => "OK"]);
    } else {
        logServer("❌ verifierToken : token invalide ou expiré");
        echo json_encode(["etat" => "TOKEN_INVALIDE"]);
    }
    exit;
}

// ── Vérification token pour les actions suivantes ───────────
if (empty($token)) {
    logServer("❌ Token vide");
    echo json_encode(["etat" => "NON_CONNECTE"]);
    exit;
}

$adherent = DatabaseManager::getAdherentParToken($token);
if (!$adherent) {
    logServer("❌ Token invalide ou expiré : " . substr($token, 0, 8));
    echo json_encode(["etat" => "TOKEN_INVALIDE"]);
    exit;
}

$idAssociation = $adherent['idAssociation'];
logServer("✅ Token OK → idAssociation=$idAssociation");

// =========================================================
// LISTE DES JOUEURS DE L'ASSOCIATION
// =========================================================
if ($action === 'joueurs') {
    $joueurs = DatabaseManager::getTousLesJoueurs($idAssociation);
    logServer("✅ joueurs → " . count($joueurs) . " trouvés pour assoc=$idAssociation");
    echo json_encode(["etat" => "OK", "joueurs" => $joueurs]);
    exit;
}

// =========================================================
// LISTE DES TOURNOIS TERMINÉS
// =========================================================
if ($action === 'tournois') {
    $tournois = DatabaseManager::getTournoisTermines($idAssociation, $adherent['idJoueur']);
    logServer("✅ tournois → " . count($tournois) . " trouvés pour joueur=" . $adherent['idJoueur']);
    echo json_encode(["etat" => "OK", "tournois" => $tournois]);
    exit;
}

// =========================================================
// DÉTAIL D'UN TOURNOI (classement + donnes + équipes)
// =========================================================
if ($action === 'tournoi') {
    $idTournoi = intval($_GET['id'] ?? 0);
    logServer("▶️ tournoi id=$idTournoi idAssociation=$idAssociation");

    if ($idTournoi <= 0) {
        echo json_encode(["etat" => "ERREUR_PARAM"]);
        exit;
    }

    $detail = DatabaseManager::getDetailTournoi($idAssociation, $idTournoi);
    if (!$detail) {
        logServer("❌ getDetailTournoi retourne null pour id=$idTournoi assoc=$idAssociation");
        echo json_encode(["etat" => "INTROUVABLE"]);
        exit;
    }

    logServer("✅ tournoi OK → donnes=" . count($detail['donnes']) . " classement=" . count($detail['classement']));
    echo json_encode(array_merge(["etat" => "OK"], $detail));
    exit;
}

// =========================================================
// DÉTAIL D'UNE DONNE (mains + enchères)
// =========================================================
if ($action === 'donne') {
    $idTournoi   = intval($_GET['id']          ?? 0);
    $numeroDonne = intval($_GET['numeroDonne'] ?? 0);
    $equipeNS    = intval($_GET['equipeNS']    ?? 0);

    logServer("▶️ donne id=$idTournoi numeroDonne=$numeroDonne equipeNS=$equipeNS");

    if ($idTournoi <= 0 || $numeroDonne <= 0 || $equipeNS <= 0) {
        echo json_encode(["etat" => "ERREUR_PARAM"]);
        exit;
    }

    $donne = DatabaseManager::getDonneComplete($idTournoi, $numeroDonne, $equipeNS);
    if (!$donne) {
        logServer("❌ getDonneComplete retourne null");
        echo json_encode(["etat" => "INTROUVABLE"]);
        exit;
    }

    logServer("✅ donne OK");
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
