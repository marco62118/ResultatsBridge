<?php

	//permet de recevoir les appels des Clients (sur leur smartphone) et traiter leur requêtes
	//Chaque Client qui va se connecter va ouvrir une session_cache_expire
	// Démarrer la session (au début du fichier)  
	require_once 'fonctions.php';
	// require_once 'TournoiConfig.php';

	require_once 'DatabaseManager.php';
	require_once 'MouvementResult.php';  
	require_once 'DonneDetail.php';  
	require_once 'Mouvement.php';  
	require_once 'CalculClassementManager.php';

	header('Access-Control-Allow-Origin: *');
	header('Content-Type: application/json; charset=utf-8');

	// =========================================================
	// 🔐 Récupération du token_api et de l'id_association
	// Le token peut venir du GET, du POST, ou du header HTTP
	// =========================================================
	$tokenApi = $_GET['token_api']
		?? $_POST['token_api']
		?? ($_SERVER['HTTP_X_TOKEN_API'] ?? '');

	$idAssociation = 0;
	if (!empty($tokenApi)) {
		$idAssociation = DatabaseManager::getIdAssociation($tokenApi);
	}
	logServer("🔑 token_api=$tokenApi → id_association=$idAssociation");

	// =========================================================
	// 🔍 Routeur principal du serveur HTTP Bridge
	// =========================================================

	$uri = $_SERVER['REQUEST_URI'];
	$path = $_SERVER['PATH_INFO'] ?? parse_url($uri, PHP_URL_PATH);
	$query = $_GET;

	logServer("📥 Nouvelle requête : $uri");
	logServer("🔹 PATH = $path");
	logServer("🔹 GET = " . json_encode($query, JSON_UNESCAPED_UNICODE));



	switch (true) {

	//=====================================
	// 🌍 Vérifie si un tournoi est ouvert
	//=====================================

	case str_contains($path, '/verifierTournoiOuvert'):
		logServer("▶️ Action reçue : verifierTournoiOuvert");
		
		$tournoi = DatabaseManager::getTournoiOuvert($idAssociation);
		header('Content-Type: text/plain');
		if ($tournoi) {
			$nbreEquipes        = (int)$tournoi['nbre_equipe'];
			$nbreDonnes         = (int)$tournoi['nbre_donne'];
			$nbreMouvements     = $nbreEquipes - 1;
			$nbreDonnesParTable = ($nbreEquipes > 1) ? intdiv($nbreDonnes, $nbreEquipes - 1) : 0;
			$equipeRelais       = DatabaseManager::getEquipeRelais((int)$tournoi['ID']);

			$response = $tournoi['ID'] . "|" . $tournoi['type'] . "|" . $nbreMouvements . "|" . $nbreDonnesParTable . "|" . $equipeRelais;
			logServer("✅ Tournoi ouvert : $response");
			echo $response;
		} else {
			echo "0|";
			logServer("⚠️ Aucun tournoi ouvert");
		}
		break;

	case str_contains($path, '/fermerTournoiOuvert'):
		$result = DatabaseManager::fermerTournoiOuvert();
		echo json_encode(["etat" => $result ? "OK" : "ERREUR"]);
		break;
		
	
// =========================================================
// 📋 Récupération des types de tournoi
// =========================================================
case str_contains($path, '/getListeTypesTournoi'):
    logServer("▶️ Action reçue : getListeTypesTournoi");
    $liste = DatabaseManager::getListeTypesTournoi();
    echo json_encode($liste, JSON_UNESCAPED_UNICODE);
    break;

// =========================================================
// 🏆 Création d'un tournoi
// =========================================================
case str_contains($path, '/creerTournoi'):
    logServer("▶️ Action reçue : creerTournoi");
    $data = json_decode(file_get_contents("php://input"), true);
    $type               = $data['type']               ?? "";
    $nbreEquipes        = intval($data['nbreEquipes']        ?? 0);
    $nbreDonnes         = intval($data['nbreDonnes']         ?? 0);
    $nbreEnregistrement = intval($data['nbreEnregistrement'] ?? 0);

    if (empty($type) || $nbreEquipes <= 0) {
        echo json_encode(["etat" => "ERREUR_PARAM"], JSON_UNESCAPED_UNICODE);
        break;
    }

    $resultat = DatabaseManager::creerTournoi($type, $nbreEquipes, $nbreDonnes, $nbreEnregistrement, $idAssociation);

    if ($resultat['etat'] === 'BLOQUE') {
        logServer("🚫 Création bloquée : limite atteinte pour assoc=$idAssociation");
        echo json_encode([
            "etat"    => "BLOQUE",
            "message" => $resultat['message']
        ], JSON_UNESCAPED_UNICODE);
        break;
    }

    if ($resultat['etat'] === 'OK') {
        logServer("✅ Tournoi créé ID=" . $resultat['idTournoi']);
        $response = [
            "etat"      => "OK",
            "idTournoi" => $resultat['idTournoi']
        ];
        if ($resultat['avertissement'] !== null) {
            $response['avertissement'] = $resultat['avertissement'];
            logServer("⚠️ Avertissement limite : " . $resultat['avertissement']);
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        logServer("❌ Échec création tournoi");
        echo json_encode(["etat" => "ERREUR"], JSON_UNESCAPED_UNICODE);
    }
    break;	
	
	
	
case str_contains($path, '/getTousLesJoueurs'):
    logServer("▶️ Action reçue : getTousLesJoueurs");
    $joueurs = DatabaseManager::getTousLesJoueurs($idAssociation);
    echo json_encode($joueurs, JSON_UNESCAPED_UNICODE);
    break;

case str_contains($path, '/ajouterNouveauJoueur'):
    $data = json_decode(file_get_contents("php://input"), true);
    $nom = $data['nom'] ?? "";
    $prenom = $data['prenom'] ?? "";
    $idJoueur = DatabaseManager::ajouterNouveauJoueur($nom, $prenom, $idAssociation);
    if ($idJoueur > 0) {
        echo json_encode(["etat" => "OK", "idJoueur" => $idJoueur], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(["etat" => "ERREUR"], JSON_UNESCAPED_UNICODE);
    }
    break;

case str_contains($path, '/enregistrerEquipes'):
    $data = json_decode(file_get_contents("php://input"), true);
    $idTournoi = intval($data['idTournoi'] ?? 0);
    $equipes = $data['equipes'] ?? [];
    $success = DatabaseManager::enregistrerEquipes($idTournoi, $equipes, $idAssociation);
    echo json_encode(["etat" => $success ? "OK" : "ERREUR"], JSON_UNESCAPED_UNICODE);
    break;
	
// =========================================================
// 🏆 verifierFinMouvement
// =========================================================
	case str_contains($path, '/verifierFinMouvement'):
		$idTournoi  = (int)($_GET['idTournoi'] ?? 0);
		$mvntNumero = (int)($_GET['mvntNumero'] ?? 0);
		$termine = DatabaseManager::toutesEquipesOntTermineMouvement($idTournoi, $mvntNumero);
		logServer("🏁 verifierFinMouvement idTournoi=$idTournoi mvnt=$mvntNumero → " . ($termine ? "true" : "false"));
		header('Content-Type: application/json');
		echo json_encode(['termine' => $termine]);
		break;
		
	// =========================================================
	// 📊 Récupération des équipes ayant joué une donne
	// =========================================================

	case str_contains($path, '/getEquipesAyantJoueDonne'):
		logServer("▶️ Action reçue : getEquipesAyantJoueDonne");
		
		$idTournoi = intval($query['idTournoi'] ?? 0);
		$numeroDonne = intval($query['numeroDonne'] ?? 0);
		
		if ($idTournoi <= 0 || $numeroDonne <= 0) {
			echo json_encode([], JSON_UNESCAPED_UNICODE);
			logServer("⚠️ Paramètres invalides");
			break;
		}
		
		try {
			// ✅ UTILISER LA MÉTHODE DE DatabaseManager
			$liste = DatabaseManager::getEquipesAyantJoueDonne($idTournoi, $numeroDonne);
			
			echo json_encode($liste, JSON_UNESCAPED_UNICODE);
			logServer("✅ getEquipesAyantJoueDonne → " . count($liste) . " équipes");
			
		} catch (Exception $e) {
			logServer("❌ Erreur getEquipesAyantJoueDonne : " . $e->getMessage());
			echo json_encode([], JSON_UNESCAPED_UNICODE);
		}
		break;
		
		
	//===============================================
	// 📦 Récupère la liste des équipes d’un tournoi
	//===============================================

    case str_contains($path, '/getEquipes'):
        logServer("▶️ Action reçue : getEquipes");

        $idTournoi = intval($query['idTournoi'] ?? 0);
        if ($idTournoi <= 0) {
            $json = ['error' => 'idTournoi manquant ou invalide'];
            echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            logServer("⚠️ idTournoi manquant ou invalide");
            break;
        }

        $equipes = DatabaseManager::getEquipesPourTournoi($idTournoi);
        logServer("   ↳ " . count($equipes) . " équipes trouvées pour tournoi $idTournoi");

        $json = ['listeEquipes' => []];
        foreach ($equipes as $eq) {
            $json['listeEquipes'][] = [
                'idTournoi' => $idTournoi,
                'equipeNumero' => $eq['equipe_numero'],
                'joueur1' => [
                    'idJoueur' => $eq['id_joueur1'],
                    'nom' => $eq['joueur1_nom'],
                    'prenom' => $eq['joueur1_prenom'],
                ],
                'joueur2' => [
                    'idJoueur' => $eq['id_joueur2'],
                    'nom' => $eq['joueur2_nom'],
                    'prenom' => $eq['joueur2_prenom'],
                ]
            ];
        }

        echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        logServer("✅ JSON équipes envoyé au client (total " . count($equipes) . ")");
        break;
	


// =========================================================
// 🃏 GET getMainsRelais?idTournoi=X&numeroDonne=Y
// Retourne les mains d'une donne relais si elles existent
// =========================================================
// À AJOUTER dans serverBridge.php, avant le case passerTableRelais

case str_contains($path, '/getMainsRelais'):
    logServer("▶️ Action reçue : getMainsRelais");

    $idTournoi   = intval($query['idTournoi']  ?? 0);
    $numeroDonne = intval($query['numeroDonne'] ?? 0);

    if ($idTournoi <= 0 || $numeroDonne <= 0) {
        echo json_encode(["etat" => "ERREUR_PARAM"], JSON_UNESCAPED_UNICODE);
        break;
    }

    $mains = DatabaseManager::getMainsRelais($idTournoi, $numeroDonne);

    if ($mains !== null) {
        logServer("✅ getMainsRelais → mains trouvées pour donne $numeroDonne");
        echo json_encode([
            "etat"  => "OK",
            "mains" => $mains   // array de 4 arrays de 13 codes carte
        ], JSON_UNESCAPED_UNICODE);
    } else {
        logServer("ℹ️ getMainsRelais → pas de mains pour donne $numeroDonne");
        echo json_encode(["etat" => "NOT_FOUND"], JSON_UNESCAPED_UNICODE);
    }
    break;


// =========================================================
// 🃏 POST enregistrerMainsRelais
// Body JSON : { idTournoi, numeroDonne, mains }
// Insère dans donnes + mains sans toucher à resultats
// =========================================================
case str_contains($path, '/enregistrerMainsRelais'):
    logServer("▶️ Action reçue : enregistrerMainsRelais");

    $data        = json_decode(file_get_contents("php://input"), true);
    $idTournoi   = intval($data['idTournoi']  ?? 0);
    $numeroDonne = intval($data['numeroDonne'] ?? 0);
    $mains       = $data['mains'] ?? null;

    logServer("📦 idTournoi=$idTournoi numeroDonne=$numeroDonne mains=" . ($mains ? count($mains) . " mains" : "null"));

    if ($idTournoi <= 0 || $numeroDonne <= 0 || !is_array($mains) || count($mains) !== 4) {
        logServer("⚠️ Paramètres invalides");
        echo json_encode(["etat" => "ERREUR_PARAM"], JSON_UNESCAPED_UNICODE);
        break;
    }

    $ok = DatabaseManager::enregistrerMainsRelais($idTournoi, $numeroDonne, $mains, $idAssociation);

    if ($ok) {
        logServer("✅ enregistrerMainsRelais OK donne=$numeroDonne");
        echo json_encode(["etat" => "OK"], JSON_UNESCAPED_UNICODE);
    } else {
        logServer("❌ enregistrerMainsRelais ERREUR donne=$numeroDonne");
        echo json_encode(["etat" => "ERREUR"], JSON_UNESCAPED_UNICODE);
    }
    break;

	
//===============================================
// 🎯 passerTableRelais  
//=============================================
// Dans serverBridge.php, ajouter un nouveau case
case str_contains($path, '/passerTableRelais'):
    logServer("▶️ Action reçue : passerTableRelais");
    $idTournoi = intval($query['idTournoi'] ?? 0);
    $numeroEquipe = intval($query['equipe'] ?? 0);
    
    if ($idTournoi <= 0 || $numeroEquipe <= 0) {
        echo json_encode(["etat" => "ERREUR_PARAM"], JSON_UNESCAPED_UNICODE);
        break;
    }
    
    DatabaseManager::incrementerMouvementEquipe($idTournoi, $numeroEquipe);
    
    echo json_encode(["etat" => "OK"], JSON_UNESCAPED_UNICODE);
    break;



	
//===============================================
// 🎯 Récupère le mouvement pour une équipe  
//=============================================

case (str_contains($path, '/getMouvement') && isset($query['idTournoi']) && isset($query['equipeNumero'])):
    logServer("▶️ Action reçue : getMouvement");

    $idTournoi = (int)$query['idTournoi'];
    $equipeNumero = (int)$query['equipeNumero'];
    logServer("▶️ /getMouvement appelé : idTournoi=$idTournoi, equipeNumero=$equipeNumero");

    $resultat = DatabaseManager::getMouvementPourEquipe($idTournoi, $equipeNumero);

    // Log brut
  //  logServer("✅ getMouvementPourEquipe (raw) : " . substr(print_r($resultat, true), 0, 300) . "...");
logServer("✅ getMouvementPourEquipe (raw) : " . print_r($resultat, true));
	 // Construction JSON pour le client Android
	if ($resultat->status === "Complet") {

		$mvnt = $resultat->mouvement;

		$response = json_encode([
			"etat"             => "MOUVEMENT",
			"tousTermines"     => $resultat->tousTermines,
			"mvntNumero"       => $mvnt->mvntNumero,
			"tableNumero"      => $mvnt->tableNumero,
			"indexDonneAJouer" => $mvnt->indexDonneAJouer,
			"equipeNS"         => $mvnt->equipeNS,
			"joueur1NSNom"     => $mvnt->joueur1NSNom,
			"joueur1NSPrenom"  => $mvnt->joueur1NSPrenom,
			"joueur2NSNom"     => $mvnt->joueur2NSNom,
			"joueur2NSPrenom"  => $mvnt->joueur2NSPrenom,
			"equipeEO"         => $mvnt->equipeEO,
			"joueur1EONom"     => $mvnt->joueur1EONom,
			"joueur1EOPrenom"  => $mvnt->joueur1EOPrenom,
			"joueur2EONom"     => $mvnt->joueur2EONom,
			"joueur2EOPrenom"  => $mvnt->joueur2EOPrenom,

			// 🔥 mains incluses dans chaque donne
			"donnes"           => $mvnt->donnes

		], JSON_UNESCAPED_UNICODE);

	} elseif ($resultat->status === "ClassementEnAttente") {

		$response = json_encode([
			"etat" => "ATTENTE_CLASSEMENT",
			"nbreEnregistrement" => $resultat->nbreEnregistrement
		], JSON_UNESCAPED_UNICODE);

	} elseif ($resultat->status === "Terminé") {

		$response = json_encode([
			"etat" => "TOURNOI_TERMINE"
		], JSON_UNESCAPED_UNICODE);

	} elseif ($resultat->status === "Erreur") {

		$response = json_encode([
			"etat" => "ERREUR",
			"message" => $resultat->message
		], JSON_UNESCAPED_UNICODE);

	} else {

		$response = json_encode([
			"etat" => "ERREUR",
			"message" => "Status inconnu"
		], JSON_UNESCAPED_UNICODE);
	}

	echo $response;
	// logServer("✅ getMouvementPourEquipe → " . substr($response, 0, 300) . "...");
logServer("✅ getMouvementPourEquipe → " . $response);
	break;

//=============================		
// 💾 Enregistrement d'une donne
//=============================
case str_contains($path, '/enregistreDonne'):
    logServer("📥 POST /enregistreDonne reçu");

    $data = json_decode(file_get_contents("php://input"), true);

    logServer("📦 Données reçues : " . json_encode($data, JSON_UNESCAPED_UNICODE));

    // Extraction
    $idTournoi       = intval($data['idTournoi']);
    $mvntNumero      = intval($data['mvntNumero']);
    $equipeNS        = intval($data['equipeNS']);
    $equipeEO        = intval($data['equipeEO']);
    $numeroTable     = intval($data['numeroTable']);
    $numeroDonne     = intval($data['numeroDonne']);
    $indexDonneJouee = intval($data['indexDonneJouee']);
    $contrat         = $data['contrat'] ?? "";
    $declarant       = $data['declarant'] ?? "";
    $resultatContrat = $data['resultatContrat'] ?? "";
    $points          = intval($data['points'] ?? 0);
    $nombrePlis      = intval($data['nombrePlis'] ?? 0);
    $carteEntame     = $data['carteEntame'] ?? "";
    $historiqueJson  = json_encode($data['historique'] ?? []);
    $mainsJson       = json_encode($data['mains'] ?? null);

    $indexDonneAJouer = DatabaseManager::enregistreDonne(
        $idTournoi, $mvntNumero, $equipeNS, $equipeEO, $numeroTable,
        $numeroDonne, $indexDonneJouee, $contrat, $declarant,
        $resultatContrat, $points, $nombrePlis, $carteEntame,
        $historiqueJson, $mainsJson, $idAssociation
    );

    header('Content-Type: application/json');
    echo json_encode(['indexDonneAJouer' => $indexDonneAJouer], JSON_UNESCAPED_UNICODE);
break;

//=============================================
// 🔄 Récupère le futur mouvement pour information et affichage
//==========================================

case str_contains($path, '/getFuturMouvement'):
    logServer("▶️ Action reçue : getFuturMouvement");
    $idTournoi = intval($query['idTournoi'] ?? 0);
    $mvntActuel = intval($query['mvntActuel'] ?? 0);
    $equipeNS = intval($query['equipeNS'] ?? 0);
    $equipeEO = intval($query['equipeEO'] ?? 0);
    
    logServer("📥 /getFuturMouvement appelé idTournoi=$idTournoi mvntActuel=$mvntActuel equipeNS=$equipeNS equipeEO=$equipeEO");
    
    $changement = DatabaseManager::getFuturMouvement($idTournoi, $mvntActuel, $equipeNS, $equipeEO);
    
    if ($changement) {
        $response = [
            'etat' => 'CHANGEMENT_DE_MOUVEMENT',
            'mvntSuivant' => $changement['mvntSuivant'],
            'entries' => $changement['entries']
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        logServer("📤 JSON envoyé : " . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        echo json_encode(['etat' => 'ERREUR'], JSON_UNESCAPED_UNICODE);
    }
    break;

// =========================================================
// 📊 Vérifierl'état du tournoi
// =========================================================

case str_contains($path, '/getEtatTournoi'):
    // Extraction du paramètre ID
    $idTournoi = isset($_GET['idTournoi']) ? intval($_GET['idTournoi']) : 0;

    if ($idTournoi <= 0) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(["etat" => "ERREUR_PARAM"]);
        break;
    }

    // 🔑 APPEL AU DATABASE MANAGER (PC)
    // On délègue la responsabilité de la vérification à la fonction dédiée
    $etatTournoi = DatabaseManager::verifierEtatTournoi($idTournoi);

    header('Content-Type: application/json');
    echo json_encode(["etat" => $etatTournoi]);
    break;
	
// =========================================================
// 📊 Récupération des résultats du tournoi
// =========================================================
/* sans jeu tournoi par 4
case str_contains($path, '/getResultatsTournoi'):
    logServer("▶️ Action reçue : getResultatsTournoi");

    $idTournoi = intval($query['idTournoi'] ?? 0);
    if ($idTournoi <= 0) {
        echo json_encode(["etat" => "ERREUR_PARAM"], JSON_UNESCAPED_UNICODE);
        break;
    }

    $etatTournoi = DatabaseManager::verifierEtatTournoi($idTournoi);
    if ($etatTournoi === "NON_TERMINE") {
        logServer("⚠️ Tournoi $idTournoi non terminé");
        echo json_encode(["etat" => "TOURNOI_NON_TERMINE"], JSON_UNESCAPED_UNICODE);
        break;
    }

    // 🔹 Étape 1 : lecture brute
    $resultats = DatabaseManager::getResultatsTournoi($idTournoi);
    if (empty($resultats)) {
        echo json_encode(["etat" => "AUCUN_RESULTAT"], JSON_UNESCAPED_UNICODE);
        break;
    }

    // 🔹 Étape 2 : calcul du classement (pts Simplifies + rang)
    $calcul = CalculClassementManager::calculerClassementTournoi($resultats);
    $classement = $calcul['classement'];
    $majDonnes = $calcul['majDonnes'];

    // 🔹 Étape 3 : mise à jour en base
    foreach ($majDonnes as $maj) {
        DatabaseManager::majPointsDonne(
            $idTournoi,
            $maj['numero_donne'],
            $maj['numero_table'],
            $maj['ptsNS'],
            $maj['ptsEO']
        );
    }

    foreach ($classement as $c) {
        DatabaseManager::majClassementEquipe(
            $idTournoi,
            $c['numeroEquipe'],
            $c['totalPts'],
            $c['rang']
        );
    }

    logServer("✅ Classement calculé et enregistré pour le tournoi $idTournoi");

    // 🔹 Étape 4 : retour au client
    echo json_encode([
        "etat" => "RESULTATS",
        "classement" => $classement
    ], JSON_UNESCAPED_UNICODE);
    break;
 */
 
 //avec jeu tournoi par 4
 case str_contains($path, '/getResultatsTournoi'):
    logServer("▶️ Action reçue : getResultatsTournoi");

    $idTournoi = intval($query['idTournoi'] ?? 0);
    if ($idTournoi <= 0) {
        echo json_encode(["etat" => "ERREUR_PARAM"], JSON_UNESCAPED_UNICODE);
        break;
    }

    $etatTournoi = DatabaseManager::verifierEtatTournoi($idTournoi);
    if ($etatTournoi === "NON_TERMINE") {
        logServer("⚠️ Tournoi $idTournoi non terminé");
        echo json_encode(["etat" => "TOURNOI_NON_TERMINE"], JSON_UNESCAPED_UNICODE);
        break;
    }

    // 🔹 Étape 1 : lecture brute des résultats
    $resultats = DatabaseManager::getResultatsTournoi($idTournoi);
    if (empty($resultats)) {
        echo json_encode(["etat" => "AUCUN_RESULTAT"], JSON_UNESCAPED_UNICODE);
        break;
    }

    // 🔹 Étape 2 : récupérer le type du tournoi
    // (fonctionne même si le tournoi est déjà fermé car on lit par ID)
    $typeTournoi = DatabaseManager::getTypeTournoi($idTournoi);
    logServer("📋 Type de tournoi : '$typeTournoi'");

    // 🔹 Étape 3 : calcul du classement selon le type
    if ($typeTournoi === 'par4equ2t21d') {
        // ── Calcul croisé + barème EBL ────────────────────────────────────
        logServer("🔀 Algorithme EBL croisé (par4equ2t21d)");
        $calcul = CalculClassementManager::calculerClassementPar4Equ2T21D($resultats);
    } else {
        // ── Calcul Simplifiés standard ────────────────────────────────────
        logServer("📊 Algorithme Simplifiés standard");
        $calcul = CalculClassementManager::calculerClassementTournoi($resultats);
    }

    $classement = $calcul['classement'];
    $majDonnes  = $calcul['majDonnes'];

    // 🔹 Étape 4 : mise à jour en base (ptsNS/ptsEO + pts/rang)
    foreach ($majDonnes as $maj) {
        DatabaseManager::majPointsDonne(
            $idTournoi,
            $maj['numero_donne'],
            $maj['numero_table'],
            $maj['ptsNS'],
            $maj['ptsEO']
        );
    }
    foreach ($classement as $c) {
        DatabaseManager::majClassementEquipe(
            $idTournoi,
            $c['numeroEquipe'],
            $c['totalPts'],
            $c['rang']
        );
    }

    logServer("✅ Classement calculé et enregistré pour le tournoi $idTournoi (type=$typeTournoi)");

    // 🔹 Étape 5 : retour au client
    echo json_encode([
        "etat" => "RESULTATS",
        "classement" => $classement
    ], JSON_UNESCAPED_UNICODE);
    break;
// =========================================================
// 📊 Récupération des résultats détail par donne
// =========================================================
case str_contains($path, '/getDetailsDonnes'):
    // Log de début
    logServer("--- DEBUT getDetailsDonnes ---");
    
    $idTournoi = isset($_GET['idTournoi']) ? intval($_GET['idTournoi']) : 0;
    logServer("ID Tournoi reçu : " . $idTournoi);
    
    if ($idTournoi > 0) {
        try {
            // Appel de la fonction
            $resultats = DatabaseManager::getDonneResultatDetails($idTournoi);
            
            logServer("Nombre de lignes récupérées : " . count($resultats));
            
            // Nettoyage pour éviter les <br> de Warnings précédents
            if (ob_get_length()) ob_clean(); 
            
            header('Content-Type: application/json; charset=utf-8');
            $json = json_encode($resultats, JSON_UNESCAPED_UNICODE);
            
            if ($json === false) {
                logServer("Erreur JSON encode : " . json_last_error_msg());
            }
            
            echo $json;
            
        } catch (Exception $e) {
            logServer("EXCEPTION dans serverBridge : " . $e->getMessage());
            echo json_encode(["error" => "Erreur interne"]);
        }
    } else {
        logServer("ERREUR : ID Tournoi invalide");
        echo json_encode(["error" => "ID Tournoi invalide"]);
    }
    logServer("--- FIN getDetailsDonnes ---");
    break;

// =========================================================
// 🎴 Détails d'une donne (Mains + Enchères)
// =========================================================
case str_contains($path, '/getDonneComplete'):

	logServer("--- DEBUT getDonneComplete ---");
    // On s'assure qu'aucune erreur PHP précédente ne pollue le flux
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // Récupération et log des paramètres
    $idT   = intval($query['idTournoi'] ?? 0);
    $numD  = intval($query['numeroDonne'] ?? 0);
    $eqNS  = intval($query['equipeNS'] ?? 0);

    logServer("📊 REQUÊTE getDonneComplete : idTournoi=$idT, numDonne=$numD, equipeNS=$eqNS");

    if ($idT <= 0 || $numD <= 0 || $eqNS <= 0) {
        logServer("⚠️ ERREUR : Paramètres invalides ou manquants.");
        echo json_encode(["etat" => "ERREUR_PARAM"]);
        exit;
    }

    // Appel au DatabaseManager
    $data = DatabaseManager::getDonneComplete($idT, $numD, $eqNS);

    if ($data) {
        $nbEncheres = count($data['encheres']);
        logServer("✅ SUCCÈS : Donne trouvée. Mains extraites, $nbEncheres enchères récupérées.");
        
        // Encodage JSON
        $jsonResponse = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        // Log du début de la réponse pour vérification visuelle dans les logs serveur
        logServer("📤 RÉPONSE JSON (tronquée) : " . substr($jsonResponse, 0, 100) . "...");
        
        echo $jsonResponse;
    } else {
        logServer("❌ ÉCHEC : Aucune donnée trouvée en base pour ces critères.");
        echo json_encode(["etat" => "INTROUVABLE"]);
    }
    exit;
	



// =========================================================
// 🔑 Récupère le code adhérent de l'association
// =========================================================
case str_contains($path, '/getCodeAdherent'):
    logServer("▶️ Action reçue : getCodeAdherent");
    $code = DatabaseManager::getCodeAdherent($idAssociation);
    echo json_encode([
        "etat" => "OK",
        "code_adherent" => $code ?? ""
    ], JSON_UNESCAPED_UNICODE);
    break;

// =========================================================
// 🔑 Met à jour le code adhérent de l'association
// =========================================================
case str_contains($path, '/majCodeAdherent'):
    logServer("▶️ Action reçue : majCodeAdherent");
    $data = json_decode(file_get_contents("php://input"), true);
    $nouveauCode = strtoupper(trim($data['code_adherent'] ?? ''));

    if (empty($nouveauCode) || strlen($nouveauCode) < 4) {
        echo json_encode(["etat" => "ERREUR", "message" => "Code trop court (4 caractères minimum)"]);
        break;
    }

    $result = DatabaseManager::majCodeAdherent($idAssociation, $nouveauCode);
    if ($result === 'CODE_EXISTE') {
        echo json_encode(["etat" => "CODE_EXISTE", "message" => "Ce code est déjà utilisé par un autre club"]);
    } elseif ($result) {
        logServer("✅ Code adhérent mis à jour : $nouveauCode pour assoc=$idAssociation");
        echo json_encode(["etat" => "OK", "code_adherent" => $nouveauCode]);
    } else {
        echo json_encode(["etat" => "ERREUR"]);
    }
    break;

// =========================================================
// 📤 Import d'un tournoi local vers le cloud
// =========================================================
case str_contains($path, '/importerTournoi'):
    logServer("▶️ Action reçue : importerTournoi");

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        echo json_encode(["etat" => "ERREUR_PARAM", "message" => "JSON invalide"]);
        break;
    }

    $result = DatabaseManager::importerTournoi($idAssociation, $data);

    if ($result['etat'] === 'OK') {
        logServer("✅ Tournoi importé → idTournoiCloud=" . $result['idTournoi']);
        echo json_encode(["etat" => "OK", "idTournoi" => $result['idTournoi']], JSON_UNESCAPED_UNICODE);
    } else {
        logServer("❌ Erreur import tournoi : " . ($result['message'] ?? '?'));
        echo json_encode(["etat" => "ERREUR", "message" => $result['message'] ?? 'Erreur inconnue'], JSON_UNESCAPED_UNICODE);
    }
    break;

// =========================================================
// 📊 Test de connexion simple (ping) pas utilisé
// =========================================================
    case $path === '/' || str_contains($path, 'test'):
        logServer("▶️ Action : test de connexion (ping)");
        header('Content-Type: text/plain');
        echo "OK";
        logServer("✅ Réponse envoyée : OK");
        break;

// ❌ Route inconnue
    default:
        http_response_code(404);
        $msg = ['error' => 'Route inconnue', 'path' => $path];
        echo json_encode($msg);
        logServer("❌ Route inconnue appelée : $path");
        break;
		
		
}

logServer("📤 Fin de traitement\n");
