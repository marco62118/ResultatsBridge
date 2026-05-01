<?php
require_once __DIR__ . '/config.php';

class DatabaseManager {

    // 🔹 Écriture dans le log
    public static function logDB($message) {
        $logFile = __DIR__ . '/logs/database_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL . PHP_EOL, FILE_APPEND);
    }

    // 🔹 Connexion MySQL — credentials lus depuis config.php
    private static function connect() {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    // 🔐 Récupère l'id_association depuis le token_api
    public static function getIdAssociation(string $tokenApi): int {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT ID FROM associations WHERE token_api = ? AND actif = 1 LIMIT 1");
            $stmt->execute([$tokenApi]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { self::logDB("❌ getIdAssociation : token invalide"); return 0; }
            return (int)$row['ID'];
        } catch (Exception $e) { self::logDB("❌ getIdAssociation erreur : " . $e->getMessage()); return 0; }
    }

    // 🔐 Vérifie email + mdp et retourne token_api si OK
    public static function connexionAssociation(string $email, string $mdp): ?array {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT ID, nom, token_api FROM associations WHERE email = ? AND mdp_hash = SHA2(?, 256) AND actif = 1 LIMIT 1");
            $stmt->execute([$email, $mdp]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { self::logDB("❌ connexionAssociation : échec pour email=$email"); return null; }
            self::logDB("✅ connexionAssociation OK → ID=" . $row['ID'] . " nom=" . $row['nom']);
            return ['idAssociation' => (int)$row['ID'], 'nom' => $row['nom'], 'token_api' => $row['token_api']];
        } catch (Exception $e) { self::logDB("❌ connexionAssociation erreur : " . $e->getMessage()); return null; }
    }

    // 📝 Inscrit une nouvelle association
    public static function inscrireAssociation(string $nom, string $email, string $mdp, string $code = '', string $codeAdherent = ''): mixed {
        try {
            $pdo = self::connect();
            $nbreTournoisMax = 5;
            if (!empty($code)) {
                $stmt = $pdo->prepare("SELECT ID, nbre_tournois_max FROM codes_invitation WHERE code = ? AND actif = 1 LIMIT 1");
                $stmt->execute([$code]);
                $rowCode = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$rowCode) { self::logDB("⚠️ inscrireAssociation : code invalide ($code)"); return 'CODE_INVALIDE'; }
                $nbreTournoisMax = (int)$rowCode['nbre_tournois_max'];
            }
            $stmt = $pdo->prepare("SELECT ID FROM associations WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) return 'EXISTE_DEJA';
            if (!empty($codeAdherent)) {
                $stmt = $pdo->prepare("SELECT ID FROM associations WHERE code_adherent = ? LIMIT 1");
                $stmt->execute([$codeAdherent]);
                if ($stmt->fetch()) return 'CODE_ADHERENT_EXISTE';
            }
            $actif = empty($code) ? 0 : 1;
            $stmt = $pdo->prepare("INSERT INTO associations (nom, email, mdp_hash, token_api, actif, plan, code_adherent, nbre_tournois_max) VALUES (?, ?, SHA2(?, 256), UUID(), ?, 'gratuit', ?, ?)");
            $stmt->execute([$nom, $email, $mdp, $actif, $codeAdherent ?: null, $nbreTournoisMax]);
            return true;
        } catch (Exception $e) { self::logDB("❌ inscrireAssociation erreur : " . $e->getMessage()); return false; }
    }

    public static function connectPublic(): PDO { return self::connect(); }

    public static function getIdAssociationParTokenClub(string $token): ?int {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT id_association FROM sessions_club WHERE token = ? AND expire > NOW() LIMIT 1");
            $stmt->execute([$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            $pdo->prepare("UPDATE sessions_club SET expire = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE token = ?")->execute([$token]);
            return (int)$row['id_association'];
        } catch (Exception $e) { self::logDB("❌ getIdAssociationParTokenClub erreur : " . $e->getMessage()); return null; }
    }

    // =========================================================
    // 📤 Import complet d'un tournoi local vers le cloud
    // =========================================================
    public static function importerTournoi(int $idAssociation, array $data): array {
        try {
            $pdo = self::connect();
            $pdo->beginTransaction();
            $type        = $data['type'] ?? '';
            $nbreEquipes = (int)($data['nbre_equipe'] ?? 0);
            $nbreDonnes  = (int)($data['nbre_donne_total']  ?? 0); // total dans tous les cas
            if ($type === 'Mitchell' || $type === 'MitchellGueridon') {
                $nbreMouvements     = (int)($data['nbre_mouvements'] ?? intdiv($nbreEquipes, 2));
                $nbreTables         = intdiv($nbreEquipes, 2);
                $nbreDonnesParTable = ($nbreMouvements > 0) ? intdiv($nbreDonnes, $nbreMouvements) : 0;
            } else {
                $nbreMouvements     = max($nbreEquipes - 1, 0);
                $nbreTables         = intdiv($nbreEquipes, 2);
                $nbreDonnesParTable = ($nbreMouvements > 0) ? intdiv($nbreDonnes, $nbreMouvements) : 0;
            }
            // nbre_donne_total = total uniforme (même formule Mitchell et Howell)
            $tokenPublic = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("
                INSERT INTO tournois
                    (id_association, date, type, nbre_equipe, nbre_donne_total, nbre_enregistrement, ouvert,
                     nbre_mouvements, nbre_donnes_par_table, nbre_tables, token_public)
                VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idAssociation, $data['date'] ?? date('Y-m-d'), $type, $nbreEquipes, $nbreDonnes,
                $nbreMouvements, $nbreDonnesParTable, $nbreTables, $tokenPublic
            ]);
            $idTournoi = (int)$pdo->lastInsertId();
            self::logDB("✅ importerTournoi → tournoi créé ID=$idTournoi type=$type mvnts=$nbreMouvements tables=$nbreTables donnes/table=$nbreDonnesParTable total=$nbreDonnes");
            $mapJoueurs = [];
            foreach ($data['joueurs'] ?? [] as $j) {
                $stmt = $pdo->prepare("SELECT ID FROM joueurs WHERE id_association = ? AND UPPER(nom) = ? AND UPPER(prenom) = ? LIMIT 1");
                $stmt->execute([$idAssociation, strtoupper($j['nom']), strtoupper($j['prenom'])]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing) { $mapJoueurs[$j['id']] = (int)$existing['ID']; }
                else {
                    $pdo->prepare("INSERT INTO joueurs (id_association, nom, prenom, actif) VALUES (?, ?, ?, 1)")->execute([$idAssociation, $j['nom'], $j['prenom']]);
                    $mapJoueurs[$j['id']] = (int)$pdo->lastInsertId();
                }
            }
            foreach ($data['equipes'] ?? [] as $e) {
                $idJ1 = $mapJoueurs[$e['id_joueur1']] ?? 0;
                $idJ2 = $mapJoueurs[$e['id_joueur2']] ?? 0;
                $pdo->prepare("INSERT INTO equipes (id_association, id_tournoi, equipe_numero, id_joueur1, id_joueur2, mvnt_numero, numero_donne, index_donne_jouee, pts, rang) VALUES (?, ?, ?, ?, ?, 0, 0, -1, ?, ?)")->execute([$idAssociation, $idTournoi, $e['equipe_numero'], $idJ1, $idJ2, $e['pts'] ?? 0, $e['rang'] ?? 0]);
            }
            $mapDonnes = [];
            foreach ($data['donnes'] ?? [] as $d) {
                $idsMains = [];
                foreach (['N', 'E', 'S', 'O'] as $pos) {
                    $cartes = $d['mains'][$pos] ?? [];
                    if (count($cartes) === 13) {
                        $cols = implode(',', array_map(fn($i) => "carte$i", range(1, 13)));
                        $ph   = implode(',', array_fill(0, 13, '?'));
                        $pdo->prepare("INSERT INTO mains ($cols) VALUES ($ph)")->execute($cartes);
                        $idsMains[$pos] = (int)$pdo->lastInsertId();
                    } else { $idsMains[$pos] = null; }
                }
                $pdo->prepare("INSERT INTO donnes (id_tournoi, numero_donne, donneur, vulnerable, main_N, main_E, main_S, main_O) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([$idTournoi, $d['numero_donne'], $d['donneur'] ?? 'N', $d['vulnerable'] ?? 'P', $idsMains['N'], $idsMains['E'], $idsMains['S'], $idsMains['O']]);
                $mapDonnes[$d['numero_donne']] = (int)$pdo->lastInsertId();
            }
            foreach ($data['resultats'] ?? [] as $r) {
                $idDonne = $mapDonnes[$r['numero_donne']] ?? null;
                $pdo->prepare("INSERT INTO resultats (id_association, id_tournoi, mvnt_numero, equipeNS, equipeEO, numero_table, numero_donne, contrat, declarant, resultat_contrat, points, pointsNS, pointsEO, nombre_pli, carteEntame, ptsNS, ptsEO, id_donne) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([$idAssociation, $idTournoi, $r['mvnt_numero'] ?? 0, $r['equipeNS'], $r['equipeEO'], $r['numero_table'] ?? 0, $r['numero_donne'], $r['contrat'] ?? '', $r['declarant'] ?? '', $r['resultat_contrat'] ?? '', $r['points'] ?? 0, $r['pointsNS'] ?? null, $r['pointsEO'] ?? null, $r['nombre_pli'] ?? 0, $r['carteEntame'] ?? '', $r['ptsNS'] ?? 0, $r['ptsEO'] ?? 0, $idDonne]);
            }
            foreach ($data['encheres'] ?? [] as $enc) {
                $pdo->prepare("INSERT INTO encheres (id_tournoi, numero_donne, equipeNS, equipeEO, ordre, joueur, annonce) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$idTournoi, $enc['numero_donne'], $enc['equipeNS'], $enc['equipeEO'], $enc['ordre'] ?? 0, $enc['joueur'] ?? '', $enc['annonce'] ?? '']);
            }
            $pdo->commit();
            self::logDB("🎉 importerTournoi TERMINÉ → idTournoi=$idTournoi");
            return ['etat' => 'OK', 'idTournoi' => $idTournoi, 'tokenPublic' => $tokenPublic];
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            self::logDB("❌ importerTournoi erreur : " . $e->getMessage());
            return ['etat' => 'ERREUR', 'message' => 'Erreur serveur'];
        }
    }

    public static function getCodeAdherent(int $idAssociation): ?string {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT code_adherent FROM associations WHERE ID = ? LIMIT 1");
            $stmt->execute([$idAssociation]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['code_adherent'] : null;
        } catch (Exception $e) { self::logDB("❌ getCodeAdherent erreur : " . $e->getMessage()); return null; }
    }

    public static function majCodeAdherent(int $idAssociation, string $code): mixed {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT ID FROM associations WHERE code_adherent = ? AND ID != ? LIMIT 1");
            $stmt->execute([$code, $idAssociation]);
            if ($stmt->fetch()) return 'CODE_EXISTE';
            $pdo->prepare("UPDATE associations SET code_adherent = ? WHERE ID = ?")->execute([$code, $idAssociation]);
            return true;
        } catch (Exception $e) { self::logDB("❌ majCodeAdherent erreur : " . $e->getMessage()); return false; }
    }

    // Met à jour le code_externe de l'association.
    // Ce code est donné par l'organisateur aux joueurs externes pour qu'ils puissent s'inscrire.
    public static function majCodeExterne(int $idAssociation, string $code): mixed {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT ID FROM associations WHERE code_externe = ? AND ID != ? LIMIT 1");
            $stmt->execute([$code, $idAssociation]);
            if ($stmt->fetch()) return 'CODE_EXISTE';
            $pdo->prepare("UPDATE associations SET code_externe = ? WHERE ID = ?")->execute([$code, $idAssociation]);
            return true;
        } catch (Exception $e) { self::logDB("❌ majCodeExterne erreur : " . $e->getMessage()); return false; }
    }

    public static function inscrireAdherent(string $nom, string $prenom, string $email, string $mdp, string $code): mixed {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT ID FROM associations WHERE code_adherent = ? AND actif = 1 LIMIT 1");
            $stmt->execute([$code]);
            $assocRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$assocRow) return 'CODE_INVALIDE';
            $idAssociation = (int)$assocRow['ID'];
            $stmt = $pdo->prepare("SELECT ID FROM joueurs WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) return 'EMAIL_EXISTE';
            $stmt = $pdo->prepare("SELECT ID FROM joueurs WHERE UPPER(nom) = ? AND UPPER(prenom) = ? AND id_association = ? LIMIT 1");
            $stmt->execute([strtoupper($nom), strtoupper($prenom), $idAssociation]);
            $joueurExistant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($joueurExistant) {
                $pdo->prepare("UPDATE joueurs SET email = ?, mdp_hash = SHA2(?, 256), actif = 1, type = 'membre', date_inscription = CURDATE() WHERE ID = ?")->execute([$email, $mdp, $joueurExistant['ID']]);
                return "Compte lié à votre fiche joueur. Vous pouvez maintenant vous connecter.";
            } else {
                $pdo->prepare("INSERT INTO joueurs (id_association, nom, prenom, email, mdp_hash, actif, type, date_inscription) VALUES (?, ?, ?, ?, SHA2(?, 256), 1, 'membre', CURDATE())")->execute([$idAssociation, $nom, $prenom, $email, $mdp]);
                return "Compte créé avec succès. Vous pouvez maintenant vous connecter.";
            }
        } catch (Exception $e) { self::logDB("❌ inscrireAdherent erreur : " . $e->getMessage()); return false; }
    }

    // Inscrit un joueur externe en vérifiant le code_externe de l'association.
    // Crée un compte type='externe' — pas de liaison à une fiche joueur existante (contrairement aux membres).
    public static function inscrireExterne(string $nom, string $prenom, string $email, string $mdp, string $code): mixed {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT ID FROM associations WHERE code_externe = ? AND actif = 1 LIMIT 1");
            $stmt->execute([$code]);
            $assocRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$assocRow) return 'CODE_INVALIDE';
            $idAssociation = (int)$assocRow['ID'];
            $stmt = $pdo->prepare("SELECT ID FROM joueurs WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) return 'EMAIL_EXISTE';
            $pdo->prepare("INSERT INTO joueurs (id_association, nom, prenom, email, mdp_hash, actif, type, date_inscription) VALUES (?, ?, ?, ?, SHA2(?, 256), 1, 'externe', CURDATE())")->execute([$idAssociation, $nom, $prenom, $email, $mdp]);
            self::logDB("✅ inscrireExterne OK → $nom $prenom ($email) assoc=$idAssociation");
            return "Compte externe créé. Vous pouvez maintenant vous connecter.";
        } catch (Exception $e) { self::logDB("❌ inscrireExterne erreur : " . $e->getMessage()); return false; }
    }

    public static function connexionAdherent(string $email, string $mdp): mixed {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT j.ID, j.nom, j.prenom, j.id_association, j.actif AS joueurActif, a.nom AS nomClub, a.actif AS assocActif FROM joueurs j JOIN associations a ON a.ID = j.id_association WHERE j.email = ? AND j.mdp_hash = SHA2(?, 256) LIMIT 1");
            $stmt->execute([$email, $mdp]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            if (!$row['joueurActif'] || !$row['assocActif']) return 'COMPTE_INACTIF';
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO sessions_adherents (token, id_joueur, id_association, expire) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR)) ON DUPLICATE KEY UPDATE token = VALUES(token), expire = VALUES(expire)")->execute([$token, $row['ID'], $row['id_association']]);
            return ['token' => $token, 'idAssociation' => (int)$row['id_association'], 'nomClub' => $row['nomClub'], 'nom' => $row['nom'], 'prenom' => $row['prenom']];
        } catch (Exception $e) { self::logDB("❌ connexionAdherent erreur : " . $e->getMessage()); return null; }
    }

    public static function getAdherentParToken(string $token): ?array {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT id_joueur, id_association FROM sessions_adherents WHERE token = ? AND expire > NOW() LIMIT 1");
            $stmt->execute([$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            $pdo->prepare("UPDATE sessions_adherents SET expire = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE token = ?")->execute([$token]);
            return ['idJoueur' => (int)$row['id_joueur'], 'idAssociation' => (int)$row['id_association']];
        } catch (Exception $e) { self::logDB("❌ getAdherentParToken erreur : " . $e->getMessage()); return null; }
    }

    public static function getTournoisTermines(int $idAssociation, int $idJoueur = 0): array {
        try {
            $pdo = self::connect();
            if ($idJoueur > 0) {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT t.ID, t.date, t.type, t.nbre_equipe, t.token_public
                    FROM tournois t
                    JOIN equipes e ON e.id_tournoi = t.ID AND (e.id_joueur1 = ? OR e.id_joueur2 = ?)
                    WHERE t.id_association = ? AND t.ouvert = 0 AND t.nbre_enregistrement = 0
                    ORDER BY t.date DESC
                ");
                $stmt->execute([$idJoueur, $idJoueur, $idAssociation]);
            } else {
                $stmt = $pdo->prepare("SELECT ID, date, type, nbre_equipe, token_public FROM tournois WHERE id_association = ? AND ouvert = 0 AND nbre_enregistrement = 0 ORDER BY date DESC");
                $stmt->execute([$idAssociation]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { self::logDB("❌ getTournoisTermines erreur : " . $e->getMessage()); return []; }
    }

    public static function getDetailTournoi(int $idAssociation, int $idTournoi): ?array {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT ID, date, type, nbre_equipe FROM tournois WHERE ID = ? AND (id_association = ? OR id_association = 0) LIMIT 1");
            $stmt->execute([$idTournoi, $idAssociation]);
            $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tournoi) return null;
            $stmt = $pdo->prepare("SELECT e.equipe_numero AS equipeNumero, j1.ID AS id_joueur1, j1.nom AS joueur1_nom, j1.prenom AS joueur1_prenom, j2.ID AS id_joueur2, j2.nom AS joueur2_nom, j2.prenom AS joueur2_prenom FROM equipes e JOIN joueurs j1 ON j1.ID = e.id_joueur1 JOIN joueurs j2 ON j2.ID = e.id_joueur2 WHERE e.id_tournoi = ?");
            $stmt->execute([$idTournoi]);
            $equipesRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $mapEquipes = [];
            foreach ($equipesRows as $eq) { $mapEquipes[(int)$eq['equipeNumero']] = $eq; }
            $stmt = $pdo->prepare("SELECT equipe_numero AS numeroEquipe, rang FROM equipes WHERE id_tournoi = ? ORDER BY rang ASC");
            $stmt->execute([$idTournoi]);
            $classement = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcul totalPts et scorePct depuis les ptsNS/ptsEO stockés dans resultats
            $stmtPts = $pdo->prepare("
                SELECT equipe, SUM(pts) AS totalPts, COUNT(*) AS nDeals
                FROM (
                    SELECT equipeNS AS equipe, ptsNS AS pts FROM resultats WHERE id_tournoi = ?
                    UNION ALL
                    SELECT equipeEO AS equipe, ptsEO AS pts FROM resultats WHERE id_tournoi = ?
                ) sub GROUP BY equipe");
            $stmtPts->execute([$idTournoi, $idTournoi]);
            $ptsMap = [];
            while ($row = $stmtPts->fetch(PDO::FETCH_ASSOC)) {
                $ptsMap[(int)$row['equipe']] = ['totalPts' => floatval($row['totalPts']), 'nDeals' => (int)$row['nDeals']];
            }
            $stmtN = $pdo->prepare("SELECT MAX(cnt) FROM (SELECT COUNT(*) AS cnt FROM resultats WHERE id_tournoi = ? GROUP BY numero_donne) sub");
            $stmtN->execute([$idTournoi]);
            $N    = max(1, (int)$stmtN->fetchColumn());
            $Ntop = ($N - 1) * 2;
            // Déterminer la position NS/EO de chaque équipe (NS en priorité si les deux)
            $stmtPos = $pdo->prepare("
                SELECT equipe, 'NS' AS position FROM (SELECT DISTINCT equipeNS AS equipe FROM resultats WHERE id_tournoi = ?) ns
                UNION ALL
                SELECT equipe, 'EO' AS position FROM (SELECT DISTINCT equipeEO AS equipe FROM resultats WHERE id_tournoi = ?) eo
            ");
            $stmtPos->execute([$idTournoi, $idTournoi]);
            $posMap = [];
            while ($rowPos = $stmtPos->fetch(PDO::FETCH_ASSOC)) {
                $eqNum = (int)$rowPos['equipe'];
                if (!isset($posMap[$eqNum])) { $posMap[$eqNum] = $rowPos['position']; }
            }
            foreach ($classement as &$c) {
                $eq   = (int)$c['numeroEquipe'];
                $data = $ptsMap[$eq] ?? ['totalPts' => 0.0, 'nDeals' => 0];
                $c['totalPts'] = $data['totalPts'];
                $nDeals = $data['nDeals'];
                $c['scorePct']  = ($nDeals > 0 && $Ntop > 0) ? round(($data['totalPts'] / ($nDeals * $Ntop)) * 100.0, 2) : 0.0;
                $c['position']  = $posMap[$eq] ?? 'NS';
            }
            unset($c);
            $stmt = $pdo->prepare("SELECT r.numero_donne, r.equipeNS, r.equipeEO, r.contrat, r.declarant, r.resultat_contrat, r.nombre_pli, r.carteEntame, r.pointsNS, r.pointsEO, r.ptsNS, r.ptsEO, dv.vulnerable FROM resultats r JOIN donnes_d_v dv ON dv.ID_donnes_d_v = r.numero_donne WHERE r.id_tournoi = ? ORDER BY r.numero_donne ASC");
            $stmt->execute([$idTournoi]);
            $donnesRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $donnes = [];
            foreach ($donnesRows as $d) {
                $ns = $mapEquipes[(int)$d['equipeNS']] ?? null;
                $eo = $mapEquipes[(int)$d['equipeEO']] ?? null;
                $d['ns_joueurs'] = $ns ? $ns['joueur1_prenom'] . ' ' . $ns['joueur1_nom'] . "\n" . $ns['joueur2_prenom'] . ' ' . $ns['joueur2_nom'] : 'Éq.' . $d['equipeNS'];
                $d['eo_joueurs'] = $eo ? $eo['joueur1_prenom'] . ' ' . $eo['joueur1_nom'] . "\n" . $eo['joueur2_prenom'] . ' ' . $eo['joueur2_nom'] : 'Éq.' . $d['equipeEO'];
                $donnes[] = $d;
            }
            return ['id' => (int)$tournoi['ID'], 'date' => $tournoi['date'], 'type' => $tournoi['type'], 'nbre_equipe' => (int)$tournoi['nbre_equipe'], 'classement' => $classement, 'equipes' => $equipesRows, 'donnes' => $donnes];
        } catch (Exception $e) { self::logDB("❌ getDetailTournoi erreur : " . $e->getMessage()); return null; }
    }

    public static function getDetailTournoiParTokenPublic(string $tokenPublic, int $idTournoi): ?array {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT ID, id_association FROM tournois WHERE ID = ? AND token_public = ? LIMIT 1");
            $stmt->execute([$idTournoi, $tokenPublic]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            return self::getDetailTournoi((int)$row['id_association'], $idTournoi);
        } catch (Exception $e) { self::logDB("❌ getDetailTournoiParTokenPublic erreur : " . $e->getMessage()); return null; }
    }

    public static function getDonneCompleteParTokenPublic(string $tokenPublic, int $idTournoi, int $numeroDonne, int $equipeNS): ?array {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT ID FROM tournois WHERE ID = ? AND token_public = ? LIMIT 1");
            $stmt->execute([$idTournoi, $tokenPublic]);
            if (!$stmt->fetch()) return null;
            return self::getDonneComplete($idTournoi, $numeroDonne, $equipeNS);
        } catch (Exception $e) { self::logDB("❌ getDonneCompleteParTokenPublic erreur : " . $e->getMessage()); return null; }
    }

    public static function genererOuGetTokenPublic(int $idTournoi): string {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT token_public FROM tournois WHERE ID = ? LIMIT 1");
            $stmt->execute([$idTournoi]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['token_public'])) return $row['token_public'];
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE tournois SET token_public = ? WHERE ID = ?")->execute([$token, $idTournoi]);
            return $token;
        } catch (Exception $e) { self::logDB("❌ genererOuGetTokenPublic erreur : " . $e->getMessage()); return ''; }
    }

    public static function donneesTournoi(): ?array {
        $pdo = self::connect();
        $stmt = $pdo->query("SELECT nbre_equipe, nbre_donne_total, nbre_mouvements, nbre_donnes_par_table, nbre_tables FROM tournois WHERE ouvert = 1 LIMIT 1");
        $tournoi = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tournoi) {
            return [
                'nbreEquipes'       => (int)$tournoi['nbre_equipe'],
                'nbreDonnes'        => (int)$tournoi['nbre_donne_total'],
                'nbreMouvements'    => (int)$tournoi['nbre_mouvements'],
                'nbreDonnesParTable'=> (int)$tournoi['nbre_donnes_par_table'],
                'nbreTables'        => (int)$tournoi['nbre_tables'],
            ];
        }
        return null;
    }

    public static function getTournoiOuvert(int $idAssociation = 0) {
        $db = self::connect();
        if ($idAssociation > 0) {
            $stmt = $db->prepare("SELECT ID, type, nbre_equipe, nbre_donne_total, nbre_mouvements, nbre_donnes_par_table, nbre_tables FROM tournois WHERE ouvert = 1 AND id_association = ? LIMIT 1");
            $stmt->execute([$idAssociation]);
        } else {
            $stmt = $db->query("SELECT ID, type, nbre_equipe, nbre_donne_total, nbre_mouvements, nbre_donnes_par_table, nbre_tables FROM tournois WHERE ouvert = 1 LIMIT 1");
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // =========================================================
    // 🆕 Récupère le type d'un tournoi (ouvert ou fermé)
    // =========================================================
    public static function getTypeTournoi(int $idTournoi): string {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("SELECT type FROM tournois WHERE ID = ? LIMIT 1");
            $stmt->execute([$idTournoi]);
            $type = $stmt->fetchColumn();
            self::logDB("🔍 getTypeTournoi($idTournoi) → '$type'");
            return $type ?: '';
        } catch (Exception $e) {
            self::logDB("❌ getTypeTournoi erreur : " . $e->getMessage());
            return '';
        }
    }

    public static function getEquipeRelais(int $idTournoi): int {
        $db = self::connect();
        $stmt = $db->prepare("SELECT e.equipe_numero FROM equipes e JOIN joueurs j1 ON j1.ID = e.id_joueur1 JOIN joueurs j2 ON j2.ID = e.id_joueur2 WHERE e.id_tournoi = ? AND (LOWER(j1.nom) = 'relais' OR LOWER(j2.nom) = 'relais') LIMIT 1");
        $stmt->execute([$idTournoi]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['equipe_numero'] : 0;
    }

    public static function toutesEquipesOntTermineMouvement(int $idTournoi, int $mvntNumero): bool {
        $db = self::connect();
        $stmt = $db->prepare("SELECT nbre_donnes_par_table FROM tournois WHERE ID = ?");
        $stmt->execute([$idTournoi]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $nbreDonnesParTable = (int)$row['nbre_donnes_par_table'];

        $equipeRelais = self::getEquipeRelais($idTournoi);
        $sql = "SELECT COUNT(*) FROM equipes WHERE id_tournoi = ? AND (mvnt_numero < ? OR (mvnt_numero = ? AND index_donne_jouee < ?))";
        if ($equipeRelais > 0) $sql .= " AND equipe_numero != $equipeRelais";
        $stmt = $db->prepare($sql);
        $stmt->execute([$idTournoi, $mvntNumero, $mvntNumero, $nbreDonnesParTable - 1]);
        $nombreEquipesNonTerminees = (int)$stmt->fetchColumn();
        self::logDB("⏳ toutesEquipesOntTermineMouvement mvnt=$mvntNumero → nonTerminees=$nombreEquipesNonTerminees");
        return $nombreEquipesNonTerminees === 0;
    }

    public static function fermerTournoiOuvert() {
        $pdo = self::connect();
        $stmt = $pdo->prepare("UPDATE tournois SET ouvert = 0 WHERE ouvert = 1");
        $res = $stmt->execute();
        self::logDB("🔒 fermerTournoiOuvert → $res");
        return $res;
    }

    public static function getListeTypesTournoi() {
        $db = self::connect();
        $liste = [];
        try {
            $stmt = $db->query("SELECT type, nombre_table, nombre_donne FROM type ORDER BY nombre_table ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $liste[] = ["type" => $row['type'], "nombre_table" => intval($row['nombre_table']), "nombre_donne" => intval($row['nombre_donne'])];
            }
        } catch (Exception $e) { self::logDB("Erreur getListeTypesTournoi : " . $e->getMessage()); }
        return $liste;
    }

    public static function ajouterNouveauJoueur(string $nom, string $prenom, int $idAssociation = 0): int {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("INSERT INTO joueurs (id_association, nom, prenom) VALUES (?, ?, ?)");
            $stmt->execute([$idAssociation, $nom, $prenom]);
            $id = (int)$pdo->lastInsertId();
            self::logDB("✅ ajouterNouveauJoueur → ID=$id nom=$nom prenom=$prenom");
            return $id;
        } catch (Exception $e) { self::logDB("❌ ajouterNouveauJoueur : " . $e->getMessage()); return -1; }
    }

    // =========================================================
    // 🆕 creerTournoi — avec insertion des lignes par4equ2t21d
    // =========================================================
    public static function creerTournoi($type, $nbreEquipes, $nbreDonnes, $nbreEnregistrement, int $idAssociation = 0) {
        try {
            $pdo = self::connect();

            // ── Vérification limite tournois ──────────────────────────────────
            $avertissement = null;
            if ($idAssociation > 0) {
                $stmt = $pdo->prepare("SELECT nbre_tournois_max, nbre_tournois_joues FROM associations WHERE ID = ?");
                $stmt->execute([$idAssociation]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $max   = (int)$row['nbre_tournois_max'];
                    $joues = (int)$row['nbre_tournois_joues'];
                    if ($joues >= $max + 1) {
                        self::logDB("🚫 creerTournoi bloqué : assoc=$idAssociation joues=$joues max=$max");
                        return ['etat' => 'BLOQUE', 'message' => "Vous avez atteint la limite de tournois."];
                    }
                    if ($joues == $max - 1) $avertissement = "⚠️ Attention : c'est votre dernier tournoi gratuit.";
                    if ($joues == $max)     $avertissement = "⚠️ Vous avez dépassé votre limite de tournois gratuits.";
                }
            }

            $date = date("Y-m-d");

            // Calcul des dimensions selon le type
            // Pour Mitchell/Guéridon : nbreEquipes=0 à ce stade, sera mis à jour par miseAJourTournoiMitchell
            if ($type === 'Mitchell' || $type === 'MitchellGueridon' || $nbreEquipes <= 0) {
                $nbreMouvements     = 0;
                $nbreTables         = 0;
                $nbreDonnesParTable = 0;
            } else {
                $nbreMouvements     = $nbreEquipes - 1;
                $nbreTables         = intdiv($nbreEquipes, 2);
                $nbreDonnesParTable = ($nbreMouvements > 0) ? intdiv($nbreDonnes, $nbreMouvements) : 0;
            }
            // nbre_donne_total = total donnes (uniforme pour tous les types)

            $sql = "INSERT INTO tournois
                        (id_association, date, ouvert, nbre_enregistrement, nbre_donne_total, nbre_equipe, type,
                         nbre_mouvements, nbre_donnes_par_table, nbre_tables)
                    VALUES
                        (:idAssociation, :date, 0, :nbreEnregistrement, :nbreDonnes, :nbreEquipes, :type,
                         :nbreMouvements, :nbreDonnesParTable, :nbreTables)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':idAssociation',     $idAssociation,     PDO::PARAM_INT);
            $stmt->bindValue(':date',              $date);
            $stmt->bindValue(':nbreEnregistrement',$nbreEnregistrement,PDO::PARAM_INT);
            $stmt->bindValue(':nbreDonnes',        $nbreDonnes,        PDO::PARAM_INT);
            $stmt->bindValue(':nbreEquipes',       $nbreEquipes,       PDO::PARAM_INT);
            $stmt->bindValue(':type',              $type);
            $stmt->bindValue(':nbreMouvements',    $nbreMouvements,    PDO::PARAM_INT);
            $stmt->bindValue(':nbreDonnesParTable',$nbreDonnesParTable,PDO::PARAM_INT);
            $stmt->bindValue(':nbreTables',        $nbreTables,        PDO::PARAM_INT);
            $stmt->execute();
            $idTournoi = $pdo->lastInsertId();

            if ($idAssociation > 0) {
                $pdo->prepare("UPDATE associations SET nbre_tournois_joues = nbre_tournois_joues + 1 WHERE ID = ?")->execute([$idAssociation]);
            }

            self::logDB("🆕 creerTournoi → ID=$idTournoi type=$type mvnts=$nbreMouvements tables=$nbreTables donnes/table=$nbreDonnesParTable total=$nbreDonnes");
            self::ouvrirTournoi($idTournoi);

            // ── ✅ NOUVEAU : insertion des lignes de mouvement selon le type ──
            if ($type === 'par4equ2t21d') {
                self::insererLignesMouvementPar4Equ2T21D($pdo, (int)$idTournoi);
            }
            // Ajouter ici d'autres types si nécessaire :
            // if ($type === 'autre_type') { self::insererLignesMouvementAutreType($pdo, (int)$idTournoi); }

            return ['etat' => 'OK', 'idTournoi' => (int)$idTournoi, 'avertissement' => $avertissement];

        } catch (Exception $e) {
            self::logDB("❌ Erreur creerTournoi : " . $e->getMessage());
            return ['etat' => 'ERREUR', 'message' => $e->getMessage()];
        }
    }


	// ─────────────────────────────────────────────────────────────────────
	// MITCHELL : mise à jour du tournoi après constitution des équipes.
	// Appelée depuis miseAJourTournoiMitchell dans serverBridge.php.
	// nbreEquipes et nbreEnregistrement étaient à 0 à la création,
	// on les met à jour maintenant qu'on connaît le vrai nombre d'équipes.
	// ─────────────────────────────────────────────────────────────────────
	public static function miseAJourTournoiMitchell(
		int $idTournoi,
		int $nbreEquipes,
		int $nbreDonnesParTable,
		int $nbreEnregistrement,
		int $nbreMouvements
	): bool {
		try {
			$pdo = self::connect();
			$nbreTables = intdiv($nbreEquipes, 2);
			// Guéridon : nbreMouvements = nbreTables (pas de skip). Mitchell standard : nbreTables-1 si pair.
			$stmtType = $pdo->prepare("SELECT type FROM tournois WHERE ID = ?");
			$stmtType->execute([$idTournoi]);
			$typeActuel = $stmtType->fetchColumn();
			$nbreMouvements = ($typeActuel === 'MitchellGueridon')
				? $nbreTables
				: (($nbreTables % 2 === 0) ? $nbreTables - 1 : $nbreTables);
			$nbreTotal           = $nbreDonnesParTable * $nbreTables;
			$nbreEnregistrement  = $nbreDonnesParTable * $nbreTables * $nbreMouvements;
			$stmt = $pdo->prepare("
				UPDATE tournois
				SET nbre_equipe           = ?,
				    nbre_enregistrement   = ?,
				    nbre_donne_total       = ?,
				    nbre_mouvements       = ?,
				    nbre_donnes_par_table = ?,
				    nbre_tables           = ?
				WHERE ID = ?
			");
			$stmt->execute([$nbreEquipes, $nbreEnregistrement, $nbreTotal,
			                $nbreMouvements, $nbreDonnesParTable, $nbreTables,
			                $idTournoi]);
			self::logDB("✅ miseAJourTournoiMitchell : tournoi=$idTournoi équipes=$nbreEquipes " .
						"tables=$nbreTables mvnts=$nbreMouvements " .
						"donnes/table=$nbreDonnesParTable total=$nbreTotal nbreEnreg=$nbreEnregistrement");
			return true;
		} catch (Exception $e) {
			self::logDB("❌ miseAJourTournoiMitchell erreur : " . $e->getMessage());
			return false;
		}
	}
	
    // =========================================================
    // 🆕 insererLignesMouvementPar4Equ2T21D
    // =========================================================
    private static function insererLignesMouvementPar4Equ2T21D(PDO $pdo, int $idTournoi): void {
        // ─────────────────────────────────────────────────────────────────────
        // 6 lignes : 3 mouvements × 2 tables
        //   Mvnt 1 : T1 → Éq1(NS) vs Éq2(EO) | donnes 1-7
        //            T2 → Éq3(NS) vs Éq4(EO) | donnes 1-7
        //   Mvnt 2 : T1 → Éq1(NS) vs Éq3(EO) | donnes 8-14
        //            T2 → Éq4(NS) vs Éq2(EO) | donnes 8-14
        //   Mvnt 3 : T1 → Éq1(NS) vs Éq4(EO) | donnes 15-21
        //            T2 → Éq2(NS) vs Éq3(EO) | donnes 15-21
        // ─────────────────────────────────────────────────────────────────────
        try {
            $sql = "INSERT INTO par4equ2t21d
                        (id_tournoi, mvnt_numero, table_numero, equipe_NS, equipe_EO,
                         numero_d1, numero_d2, numero_d3, numero_d4, numero_d5, numero_d6, numero_d7)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            $lignes = [
                // Mouvement 1 — donnes 1 à 7
                [$idTournoi, 1, 1, 1, 2,  1,  2,  3,  4,  5,  6,  7],
                [$idTournoi, 1, 2, 3, 4,  1,  2,  3,  4,  5,  6,  7],
                // Mouvement 2 — donnes 8 à 14
                [$idTournoi, 2, 1, 1, 3,  8,  9, 10, 11, 12, 13, 14],
                [$idTournoi, 2, 2, 4, 2,  8,  9, 10, 11, 12, 13, 14],
                // Mouvement 3 — donnes 15 à 21
                [$idTournoi, 3, 1, 1, 4, 15, 16, 17, 18, 19, 20, 21],
                [$idTournoi, 3, 2, 2, 3, 15, 16, 17, 18, 19, 20, 21],
            ];

            foreach ($lignes as $ligne) { $stmt->execute($ligne); }
            self::logDB("✅ insererLignesMouvementPar4Equ2T21D → 6 lignes insérées pour tournoi $idTournoi");

        } catch (Exception $e) {
            self::logDB("❌ insererLignesMouvementPar4Equ2T21D erreur : " . $e->getMessage());
        }
    }

    public static function ouvrirTournoi($idTournoi) {
        try {
            $pdo = self::connect();
            $stmt = $pdo->prepare("UPDATE tournois SET ouvert = 1 WHERE ID = :id");
            $stmt->bindValue(':id', $idTournoi, PDO::PARAM_INT);
            $stmt->execute();
            $res = $stmt->rowCount() > 0;
            self::logDB("🔓 ouvrirTournoi ID=$idTournoi → $res");
            return $res;
        } catch (Exception $e) { self::logDB("❌ Erreur ouvrirTournoi : " . $e->getMessage()); return false; }
    }

    public static function getTousLesJoueurs(int $idAssociation = 0): array {
        $pdo = self::connect();
        if ($idAssociation > 0) {
            $stmt = $pdo->prepare("SELECT ID, nom, prenom FROM joueurs WHERE id_association = ? ORDER BY ID ASC");
            $stmt->execute([$idAssociation]);
        } else {
            $stmt = $pdo->query("SELECT ID, nom, prenom FROM joueurs ORDER BY ID ASC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getEquipesPourTournoi(int $idTournoi): array {
        try {
            $pdo = self::connect();
            $sql = "SELECT e.equipe_numero, e.id_joueur1, j1.nom AS joueur1_nom, j1.prenom AS joueur1_prenom, e.id_joueur2, j2.nom AS joueur2_nom, j2.prenom AS joueur2_prenom FROM equipes e JOIN joueurs j1 ON j1.ID = e.id_joueur1 JOIN joueurs j2 ON j2.ID = e.id_joueur2 WHERE e.id_tournoi = :idTournoi";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':idTournoi' => $idTournoi]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { self::logDB("❌ Erreur getEquipesPourTournoi : " . $e->getMessage()); return []; }
    }

    public static function enregistrerEquipes(int $idTournoi, array $equipes, int $idAssociation = 0): bool {
        try {
            $pdo = self::connect();
            $pdo->beginTransaction();
            $sql = "INSERT INTO equipes (id_association, id_tournoi, equipe_numero, id_joueur1, id_joueur2, mvnt_numero, numero_donne, index_donne_jouee, pts, rang) VALUES (:idAssociation, :idTournoi, :equipeNumero, :joueur1, :joueur2, 0, 0, -1, 0, 0)";
            $stmt = $pdo->prepare($sql);
            foreach ($equipes as $e) {
                $stmt->execute([':idAssociation' => $idAssociation, ':idTournoi' => $idTournoi, ':equipeNumero' => $e['equipeNumero'], ':joueur1' => $e['joueur1_id'], ':joueur2' => $e['joueur2_id']]);
            }
            $pdo->commit();
            self::logDB("✅ enregistrerEquipes ID=$idTournoi → " . count($equipes) . " équipes");
            return true;
        } catch (PDOException $e) { $pdo->rollBack(); self::logDB("❌ Erreur enregistrerEquipes : " . $e->getMessage()); return false; }
    }

    public static function incrementerMouvementEquipe(int $idTournoi, int $numeroEquipe): void {

        $db = self::connect();
        $stmt = $db->prepare("SELECT nbre_mouvements, nbre_donnes_par_table FROM tournois WHERE ID = ?");
        $stmt->execute([$idTournoi]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $nbreMouvements     = (int)$row['nbre_mouvements'];
        $nbreDonnesParTable = (int)$row['nbre_donnes_par_table'];

        $equipeRelais = self::getEquipeRelais($idTournoi);
        $stmt = $db->prepare("SELECT mvnt_numero FROM equipes WHERE id_tournoi = ? AND equipe_numero = ?");
        $stmt->execute([$idTournoi, $numeroEquipe]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $mvntActuel  = $row ? (int)$row['mvnt_numero'] : 0;
        $nouveauMvnt = ($mvntActuel === 0) ? 1 : $mvntActuel + 1;
        $nouvelIndex = $nbreDonnesParTable - 1;

        $stmt = $db->prepare("UPDATE equipes SET index_donne_jouee = ?, mvnt_numero = ? WHERE id_tournoi = ? AND equipe_numero = ?");
        $stmt->execute([$nouvelIndex, $nouveauMvnt, $idTournoi, $numeroEquipe]);
        if ($equipeRelais > 0 && $equipeRelais !== $numeroEquipe) {
            $stmt->execute([$nouvelIndex, $nouveauMvnt, $idTournoi, $equipeRelais]);
        }
        $stmt = $db->prepare("UPDATE tournois SET nbre_enregistrement = nbre_enregistrement - ? WHERE ID = ? AND ouvert = 1");
        $stmt->execute([$nbreDonnesParTable, $idTournoi]);
        self::logDB("🏖️ incrementerMouvementEquipe equipe=$numeroEquipe → mvntActuel=$mvntActuel nouveauMvnt=$nouveauMvnt nbreMouvements=$nbreMouvements nbreDonnesParTable=$nbreDonnesParTable");
    }

/*  //version sans Mitchell
  public static function getMouvementPourEquipe(int $idTournoi, int $equipeNumero): MouvementResult {
        self::logDB("▶️ APPEL getMouvementPourEquipe idTournoi=$idTournoi equipe=$equipeNumero");
        $pdo = self::connect();
        try {
            $config = self::donneesTournoi();
            if (!$config) return MouvementResult::Erreur("Aucun tournoi ouvert");
            $nbDonnesParTable = $config['nbreDonnesParTable'];
            $nbMouvements     = $config['nbreMouvements'];
            $stmtEtat = $pdo->prepare("SELECT mvnt_numero, index_donne_jouee FROM equipes WHERE id_tournoi=? AND equipe_numero=?");
            $stmtEtat->execute([$idTournoi, $equipeNumero]);
            $rowEtat = $stmtEtat->fetch(PDO::FETCH_ASSOC);
            if (!$rowEtat) return MouvementResult::Erreur("Aucune ligne d'état pour équipe");
            $mvntNumero      = (int)$rowEtat['mvnt_numero'];
            $indexDonneJouee = (int)$rowEtat['index_donne_jouee'];
            if ($mvntNumero === 0) $mvntNumero = 1;
            $indexDonneAJouer = $indexDonneJouee + 1;
            $tousTermines = true;
            if ($indexDonneAJouer == $nbDonnesParTable) {
                if ($mvntNumero < $nbMouvements) {
                    $tousTermines = self::toutesEquipesOntTermineMouvement($idTournoi, $mvntNumero);
                    if (!$tousTermines) { $indexDonneAJouer = $nbDonnesParTable - 1; }
                    else { $mvntNumero++; $indexDonneAJouer = 0; }
                } else {
                    $reste = self::getNbreEnregistrement($idTournoi);
                    return $reste == 0 ? MouvementResult::Termine() : MouvementResult::ClassementEnAttente($reste);
                }
            }
            $stmtType = $pdo->prepare("SELECT type FROM tournois WHERE ID = ?");
            $stmtType->execute([$idTournoi]);
            $typeDeTournoi = $stmtType->fetchColumn();
            if (!$typeDeTournoi) return MouvementResult::Erreur("Tournoi introuvable");
            $sql = "SELECT m.*, ns.id_joueur1 AS ns_j1, ns.id_joueur2 AS ns_j2, eo.id_joueur1 AS eo_j1, eo.id_joueur2 AS eo_j2, jns1.nom AS ns_j1_nom, jns1.prenom AS ns_j1_prenom, jns2.nom AS ns_j2_nom, jns2.prenom AS ns_j2_prenom, jeo1.nom AS eo_j1_nom, jeo1.prenom AS eo_j1_prenom, jeo2.nom AS eo_j2_nom, jeo2.prenom AS eo_j2_prenom FROM $typeDeTournoi m JOIN equipes ns ON ns.equipe_numero = m.equipe_NS AND ns.id_tournoi=? JOIN equipes eo ON eo.equipe_numero = m.equipe_EO AND eo.id_tournoi=? JOIN joueurs jns1 ON jns1.ID = ns.id_joueur1 JOIN joueurs jns2 ON jns2.ID = ns.id_joueur2 JOIN joueurs jeo1 ON jeo1.ID = eo.id_joueur1 JOIN joueurs jeo2 ON jeo2.ID = eo.id_joueur2 WHERE (m.equipe_NS=? OR m.equipe_EO=?) AND m.mvnt_numero=? LIMIT 1";
            $stmtMv = $pdo->prepare($sql);
            $stmtMv->execute([$idTournoi, $idTournoi, $equipeNumero, $equipeNumero, $mvntNumero]);
            $rowMv = $stmtMv->fetch(PDO::FETCH_ASSOC);
            if (!$rowMv) return MouvementResult::Erreur("Aucun mouvement trouvé");
            $donnes = [];
            $ordre  = ["N","E","S","O"];
            for ($i = 1; $i <= $nbDonnesParTable; $i++) {
                $col = "numero_d$i";
                if (!isset($rowMv[$col])) continue;
                $num = (int)$rowMv[$col];
                $stmtDon = $pdo->prepare("SELECT donneur, vulnerable FROM donnes_d_v WHERE ID_donnes_d_v=? LIMIT 1");
                $stmtDon->execute([$num]);
                $dMeta = $stmtDon->fetch(PDO::FETCH_ASSOC) ?: ["donneur" => null, "vulnerable" => null];
                $stmtM = $pdo->prepare("SELECT * FROM donnes WHERE id_tournoi=? AND numero_donne=? LIMIT 1");
                $stmtM->execute([$idTournoi, $num]);
                $rowD  = $stmtM->fetch(PDO::FETCH_ASSOC);
                $mains = null;
                if ($rowD) {
                    $mains = [];
                    foreach ($ordre as $dir) {
                        $idMain = $rowD["main_$dir"];
                        if ($idMain) {
                            $stmtCards = $pdo->prepare("SELECT * FROM mains WHERE ID=?");
                            $stmtCards->execute([$idMain]);
                            $rowCards = $stmtCards->fetch(PDO::FETCH_ASSOC);
                            $cards = [];
                            for ($c = 1; $c <= 13; $c++) { if (!empty($rowCards["carte$c"])) $cards[] = $rowCards["carte$c"]; }
                            $mains[] = $cards;
                        } else { $mains[] = null; }
                    }
                }
                $donnes[] = new DonneDetail($num, $dMeta['donneur'], $dMeta['vulnerable'], $mains);
            }
            $mouvement = new Mouvement((int)$rowMv['mvnt_numero'], (int)$rowMv['table_numero'], (int)$rowMv['equipe_NS'], $rowMv['ns_j1_nom'], $rowMv['ns_j1_prenom'], $rowMv['ns_j2_nom'], $rowMv['ns_j2_prenom'], (int)$rowMv['equipe_EO'], $rowMv['eo_j1_nom'], $rowMv['eo_j1_prenom'], $rowMv['eo_j2_nom'], $rowMv['eo_j2_prenom'], $donnes, $indexDonneAJouer);
            return MouvementResult::Complet($mouvement, $tousTermines);
        } catch(Exception $e) { self::logDB("❌ Exception getMouvementPourEquipe : " . $e->getMessage()); return MouvementResult::Erreur("Erreur serveur"); }
    }
  */

//version avec mitchell corrigée
public static function getMouvementPourEquipe(int $idTournoi, int $equipeNumero): MouvementResult {
    self::logDB("▶️ APPEL getMouvementPourEquipe idTournoi=$idTournoi equipe=$equipeNumero");
    $pdo = self::connect();
    try {
        // 1️⃣ Type et config du tournoi (lecture directe des colonnes pré-calculées)
        $stmt = $pdo->prepare("SELECT type, nbre_equipe, nbre_mouvements, nbre_donnes_par_table, nbre_tables FROM tournois WHERE ID = ?");
        $stmt->execute([$idTournoi]);
        $rowTournoi = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rowTournoi) return MouvementResult::Erreur("Tournoi introuvable");

        $typeDeTournoi    = $rowTournoi['type'];
        $nbreEquipes      = (int)$rowTournoi['nbre_equipe'];
        $nbMouvements     = (int)$rowTournoi['nbre_mouvements'];
        $nbDonnesParTable = (int)$rowTournoi['nbre_donnes_par_table'];
        $nbreTables       = (int)$rowTournoi['nbre_tables'];

        // 2️⃣ Progression de l'équipe
        $stmtEtat = $pdo->prepare("SELECT mvnt_numero, index_donne_jouee FROM equipes WHERE id_tournoi=? AND equipe_numero=?");
        $stmtEtat->execute([$idTournoi, $equipeNumero]);
        $rowEtat = $stmtEtat->fetch(PDO::FETCH_ASSOC);
        if (!$rowEtat) return MouvementResult::Erreur("Aucune ligne d'état pour équipe");

        $mvntNumero      = (int)$rowEtat['mvnt_numero'];
        $indexDonneJouee = (int)$rowEtat['index_donne_jouee'];
        if ($mvntNumero === 0) $mvntNumero = 1;
        $indexDonneAJouer = $indexDonneJouee + 1;

        // 3️⃣ L'équipe a fini toutes les donnes de ce mouvement ?
        $aFiniLeMouvement = ($indexDonneAJouer == $nbDonnesParTable);
        $tousTermines = true;
        if ($aFiniLeMouvement) {
            $estDernierMouvement = ($mvntNumero == $nbMouvements);
            if ($estDernierMouvement) {
                $reste = self::getNbreEnregistrement($idTournoi);
                return MouvementResult::ClassementEnAttente($reste);
            }
            $tousTermines = self::toutesEquipesOntTermineMouvement($idTournoi, $mvntNumero);
            $mvntNumero++;
            $indexDonneAJouer = 0;
            // Pas d'UPDATE ici — la BD est modifiée uniquement par enregistreDonne et passerTableRelais
        }

        // 4️⃣ Branchement selon le type de tournoi
        if ($typeDeTournoi === 'Mitchell') {
            self::logDB("🎯 Branchement Mitchell → getMouvementMitchell()");
            return self::getMouvementMitchell(
                $pdo, $idTournoi, $equipeNumero, $mvntNumero,
                $indexDonneAJouer, $nbDonnesParTable, $nbreEquipes,
                $tousTermines
            );
        } elseif ($typeDeTournoi === 'MitchellGueridon') {
            self::logDB("🎯 Branchement MitchellGuéridon → getMouvementMitchellGueridon()");
            return self::getMouvementMitchellGueridon(
                $pdo, $idTournoi, $equipeNumero, $mvntNumero,
                $indexDonneAJouer, $nbDonnesParTable, $nbreEquipes,
                $tousTermines
            );
        } else {
            self::logDB("🎯 Branchement Howell → requête SQL sur $typeDeTournoi");
            $sql = "SELECT m.*,
                           jns1.nom AS ns_j1_nom, jns1.prenom AS ns_j1_prenom,
                           jns2.nom AS ns_j2_nom, jns2.prenom AS ns_j2_prenom,
                           jeo1.nom AS eo_j1_nom, jeo1.prenom AS eo_j1_prenom,
                           jeo2.nom AS eo_j2_nom, jeo2.prenom AS eo_j2_prenom
                    FROM $typeDeTournoi m
                    JOIN equipes ns  ON ns.equipe_numero = m.equipe_NS AND ns.id_tournoi = ?
                    JOIN equipes eo  ON eo.equipe_numero = m.equipe_EO AND eo.id_tournoi = ?
                    JOIN joueurs jns1 ON jns1.ID = ns.id_joueur1
                    JOIN joueurs jns2 ON jns2.ID = ns.id_joueur2
                    JOIN joueurs jeo1 ON jeo1.ID = eo.id_joueur1
                    JOIN joueurs jeo2 ON jeo2.ID = eo.id_joueur2
                    WHERE (m.equipe_NS=? OR m.equipe_EO=?) AND m.mvnt_numero=? LIMIT 1";
            $stmtMv = $pdo->prepare($sql);
            $stmtMv->execute([$idTournoi, $idTournoi, $equipeNumero, $equipeNumero, $mvntNumero]);
            $rowMv = $stmtMv->fetch(PDO::FETCH_ASSOC);
            if (!$rowMv) return MouvementResult::Erreur("Aucun mouvement trouvé");

            $donnes = [];
            for ($i = 1; $i <= $nbDonnesParTable; $i++) {
                $col = "numero_d$i";
                if (!isset($rowMv[$col])) continue;
                $num = (int)$rowMv[$col];
                $stmtDon = $pdo->prepare("SELECT donneur, vulnerable FROM donnes_d_v WHERE ID_donnes_d_v=? LIMIT 1");
                $stmtDon->execute([$num]);
                $dMeta = $stmtDon->fetch(PDO::FETCH_ASSOC) ?: ["donneur" => "N", "vulnerable" => "Aucune"];
                $mains = self::getMainsPourDonne($idTournoi, $num);
                $donnes[] = new DonneDetail($num, $dMeta['donneur'], $dMeta['vulnerable'], $mains);
            }

            $mouvement = new Mouvement(
                (int)$rowMv['mvnt_numero'], (int)$rowMv['table_numero'],
                (int)$rowMv['equipe_NS'],
                $rowMv['ns_j1_nom'], $rowMv['ns_j1_prenom'],
                $rowMv['ns_j2_nom'], $rowMv['ns_j2_prenom'],
                (int)$rowMv['equipe_EO'],
                $rowMv['eo_j1_nom'], $rowMv['eo_j1_prenom'],
                $rowMv['eo_j2_nom'], $rowMv['eo_j2_prenom'],
                $donnes, $indexDonneAJouer
            );
            return MouvementResult::MouvementComplet($mouvement, $tousTermines);
        }
    } catch(Exception $e) {
        self::logDB("❌ Exception getMouvementPourEquipe : " . $e->getMessage());
        return MouvementResult::Erreur("Erreur serveur");
    }
}
/*
	// version avec mitchell
	
	public static function getMouvementPourEquipe(int $idTournoi, int $equipeNumero): MouvementResult {
    self::logDB("▶️ APPEL getMouvementPourEquipe idTournoi=$idTournoi equipe=$equipeNumero");
    $pdo = self::connect();
    try {
        // 1️⃣ Récupérer le type et les infos du tournoi
        $stmt = $pdo->prepare("SELECT type, nbre_equipe, nbre_donne FROM tournois WHERE ID = ?");
        $stmt->execute([$idTournoi]);
        $rowTournoi = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rowTournoi) return MouvementResult::Erreur("Tournoi introuvable");

        $typeDeTournoi = $rowTournoi['type'];
        $nbreEquipes   = (int)$rowTournoi['nbre_equipe'];
        $nbreDonnesBD  = (int)$rowTournoi['nbre_donne'];

        // Mitchell : nbre_donne contient les donnes PAR TABLE
        // Howell   : nbre_donne contient le total, on divise par nbreEquipes-1
        if ($typeDeTournoi === 'Mitchell') {
            $nbDonnesParTable = $nbreDonnesBD;
            $nbreTables       = intdiv($nbreEquipes, 2);
            $nbMouvements     = ($nbreTables % 2 === 0) ? $nbreTables - 1 : $nbreTables;
        } else {
            $nbDonnesParTable = ($nbreEquipes > 1) ? intdiv($nbreDonnesBD, $nbreEquipes - 1) : 0;
            $nbMouvements     = $nbreEquipes - 1;
        }

        // 2️⃣ Récupérer la progression de l'équipe
        $stmtEtat = $pdo->prepare("SELECT mvnt_numero, index_donne_jouee FROM equipes WHERE id_tournoi=? AND equipe_numero=?");
        $stmtEtat->execute([$idTournoi, $equipeNumero]);
        $rowEtat = $stmtEtat->fetch(PDO::FETCH_ASSOC);
        if (!$rowEtat) return MouvementResult::Erreur("Aucune ligne d'état pour équipe");

        $mvntNumero      = (int)$rowEtat['mvnt_numero'];
        $indexDonneJouee = (int)$rowEtat['index_donne_jouee'];
        if ($mvntNumero === 0) $mvntNumero = 1;
        $indexDonneAJouer = $indexDonneJouee + 1;
        $tousTermines = true;

        if ($indexDonneAJouer == $nbDonnesParTable) {
            if ($mvntNumero < $nbMouvements) {
                $tousTermines = self::toutesEquipesOntTermineMouvement($idTournoi, $mvntNumero);
                if ($tousTermines) {
                    $mvntNumero++;
                    $indexDonneAJouer = 0;
                }
                // Si !tousTermines : mvntNumero et indexDonneAJouer restent inchangés
                // Le serveur retourne le mouvement actuel avec tousTermines=false
                // Le client affiche CHANGEMENT_DE_MOUVEMENT et attend
            } else {
                $reste = self::getNbreEnregistrement($idTournoi);
                return $reste == 0 ? MouvementResult::Termine() : MouvementResult::ClassementEnAttente($reste);
            }
        }

        // 3️⃣ Branchement Mitchell ou Howell
        if ($typeDeTournoi === 'Mitchell') {
            // ── Mitchell : calcul dynamique sans table SQL ────────────────
            self::logDB("🎯 Branchement Mitchell → getMouvementMitchell()");
            return self::getMouvementMitchell(
                $pdo, $idTournoi, $equipeNumero, $mvntNumero,
                $indexDonneAJouer, $nbDonnesParTable, $nbreEquipes, $tousTermines
            );
        } else {
            // ── Howell : requête SQL sur la table de mouvements ───────────
            self::logDB("🎯 Branchement Howell → requête SQL sur $typeDeTournoi");
            $sql = "SELECT m.*, ns.id_joueur1 AS ns_j1, ns.id_joueur2 AS ns_j2,
                           eo.id_joueur1 AS eo_j1, eo.id_joueur2 AS eo_j2,
                           jns1.nom AS ns_j1_nom, jns1.prenom AS ns_j1_prenom,
                           jns2.nom AS ns_j2_nom, jns2.prenom AS ns_j2_prenom,
                           jeo1.nom AS eo_j1_nom, jeo1.prenom AS eo_j1_prenom,
                           jeo2.nom AS eo_j2_nom, jeo2.prenom AS eo_j2_prenom
                    FROM $typeDeTournoi m
                    JOIN equipes ns  ON ns.equipe_numero  = m.equipe_NS AND ns.id_tournoi = ?
                    JOIN equipes eo  ON eo.equipe_numero  = m.equipe_EO AND eo.id_tournoi = ?
                    JOIN joueurs jns1 ON jns1.ID = ns.id_joueur1
                    JOIN joueurs jns2 ON jns2.ID = ns.id_joueur2
                    JOIN joueurs jeo1 ON jeo1.ID = eo.id_joueur1
                    JOIN joueurs jeo2 ON jeo2.ID = eo.id_joueur2
                    WHERE (m.equipe_NS=? OR m.equipe_EO=?) AND m.mvnt_numero=? LIMIT 1";
            $stmtMv = $pdo->prepare($sql);
            $stmtMv->execute([$idTournoi, $idTournoi, $equipeNumero, $equipeNumero, $mvntNumero]);
            $rowMv = $stmtMv->fetch(PDO::FETCH_ASSOC);
            if (!$rowMv) return MouvementResult::Erreur("Aucun mouvement trouvé");

            $donnes = [];
            for ($i = 1; $i <= $nbDonnesParTable; $i++) {
                $col = "numero_d$i";
                if (!isset($rowMv[$col])) continue;
                $num = (int)$rowMv[$col];
                $stmtDon = $pdo->prepare("SELECT donneur, vulnerable FROM donnes_d_v WHERE ID_donnes_d_v=? LIMIT 1");
                $stmtDon->execute([$num]);
                $dMeta = $stmtDon->fetch(PDO::FETCH_ASSOC) ?: ["donneur" => null, "vulnerable" => null];
                $mains = self::getMainsPourDonne($idTournoi, $num);
                $donnes[] = new DonneDetail($num, $dMeta['donneur'], $dMeta['vulnerable'], $mains);
            }

            $mouvement = new Mouvement(
                (int)$rowMv['mvnt_numero'], (int)$rowMv['table_numero'],
                (int)$rowMv['equipe_NS'],
                $rowMv['ns_j1_nom'], $rowMv['ns_j1_prenom'],
                $rowMv['ns_j2_nom'], $rowMv['ns_j2_prenom'],
                (int)$rowMv['equipe_EO'],
                $rowMv['eo_j1_nom'], $rowMv['eo_j1_prenom'],
                $rowMv['eo_j2_nom'], $rowMv['eo_j2_prenom'],
                $donnes, $indexDonneAJouer
            );
            return MouvementResult::Complet($mouvement, $tousTermines);
        }
    } catch(Exception $e) {
        self::logDB("❌ Exception getMouvementPourEquipe : " . $e->getMessage());
        return MouvementResult::Erreur("Erreur serveur");
    }
}
  */
 
 
// ─────────────────────────────────────────────────────────────────────
// MITCHELL : calcul dynamique du mouvement à la volée.
// Pas de table SQL : on calcule selon les règles de rotation Mitchell.
//
// Convention de numérotation des équipes :
//   Équipes 1..T     → EO, départ à la table correspondant à leur numéro
//   Équipes T+1..N   → NS, fixes à leur table (eq T+1 = table 1, etc.)
//
// Formule table au mouvement M :
//   t = equipeNumero + mvntNumero - 1 + offset
//   si t > nbreTables → t = t - nbreTables
//
// Skip Mitchell (nbreTables pair) :
//   Entre R(N/2) et R(N/2+1) les EO sautent une table supplémentaire
//   offset = 1 si mvntNumero > nbreTables/2, sinon 0
//   nbreMouvements = nbreTables - 1 (pas nbreTables)
//
// Équipe NS à la table T : equipeNumero = T + nbreTables
// Équipe EO à la table T au mouvement M :
//   equipeEO = tableNumero - mvntNumero + 1 - offset
//   si equipeEO <= 0 → equipeEO += nbreTables
//
// Set de donnes (sans saut, régulier) :
//   setDonnes = tableNumero + mvntNumero - 1
//   si setDonnes > nbreTables → setDonnes -= nbreTables
//   donnes = (setDonnes-1)*nbreDonnesParTable+1 .. setDonnes*nbreDonnesParTable
// ─────────────────────────────────────────────────────────────────────
private static function getMouvementMitchell(
    PDO $pdo,
    int $idTournoi,
    int $equipeNumero,
    int $mvntNumero,
    int $indexDonneAJouer,
    int $nbreDonnesParTable,
    int $nbreEquipes,
    bool $tousTermines = true
): MouvementResult {
    self::logDB("🎯 getMouvementMitchell : équipe=$equipeNumero mvnt=$mvntNumero");
    $nbreTables = intdiv($nbreEquipes, 2);

    // 1️⃣ Skip Mitchell : offset +1 après la moitié des mouvements si nbreTables pair
    // Évite les collisions de sets sans matériel supplémentaire
    $offset = ($nbreTables % 2 === 0 && $mvntNumero > intdiv($nbreTables, 2)) ? 1 : 0;
    self::logDB("   → nbreTables=$nbreTables offset=$offset");

    // 2️⃣ Table de l'équipe au mouvement M
    $tableNumero = $equipeNumero + $mvntNumero - 1 + $offset;
    if ($tableNumero > $nbreTables) $tableNumero -= $nbreTables;
    self::logDB("   → équipe=$equipeNumero mvnt=$mvntNumero table=$tableNumero");

    // 3️⃣ Équipe NS à cette table (NS fixe : eq = table + nbreTables)
    $equipeNS = $tableNumero + $nbreTables;

    // 4️⃣ Équipe EO à cette table au mouvement M
    $equipeEO = $tableNumero - $mvntNumero + 1 - $offset;
    if ($equipeEO <= 0) $equipeEO += $nbreTables;
    self::logDB("   → equipeNS=$equipeNS | equipeEO=$equipeEO");

    // 5️⃣ Set de donnes (régulier, sans saut)
    $setDonnes = $tableNumero + $mvntNumero - 1;
    if ($setDonnes > $nbreTables) $setDonnes -= $nbreTables;
    $premiereDonne = ($setDonnes - 1) * $nbreDonnesParTable + 1;
    $derniereDonne = $setDonnes * $nbreDonnesParTable;
    self::logDB("   → setDonnes=$setDonnes | donnes=$premiereDonne..$derniereDonne");

    // 6️⃣ Récupérer les infos joueurs des deux équipes
    $fnJoueurs = function(int $numEquipe) use ($pdo, $idTournoi): array {
        $stmt = $pdo->prepare("SELECT j1.nom, j1.prenom, j2.nom, j2.prenom
            FROM equipes e
            JOIN joueurs j1 ON j1.ID = e.id_joueur1
            JOIN joueurs j2 ON j2.ID = e.id_joueur2
            WHERE e.id_tournoi = ? AND e.equipe_numero = ?");
        $stmt->execute([$idTournoi, $numEquipe]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row ?: ['', '', '', ''];
    };
    $joueursNS = $fnJoueurs($equipeNS);
    $joueursEO = $fnJoueurs($equipeEO);

    // 7️⃣ Construire la liste des donnes avec métadonnées
    $donnes = [];
    for ($numDonne = $premiereDonne; $numDonne <= $derniereDonne; $numDonne++) {
        $stmtDon = $pdo->prepare("SELECT donneur, vulnerable FROM donnes_d_v WHERE ID_donnes_d_v = ? LIMIT 1");
        $stmtDon->execute([$numDonne]);
        $dMeta = $stmtDon->fetch(PDO::FETCH_ASSOC) ?: ["donneur" => "N", "vulnerable" => "Aucune"];
        $mains = self::getMainsPourDonne($idTournoi, $numDonne);
        $donnes[] = new DonneDetail($numDonne, $dMeta['donneur'], $dMeta['vulnerable'], $mains);
    }

    // 8️⃣ Construire et retourner le Mouvement
    $mouvement = new Mouvement(
        $mvntNumero, $tableNumero,
        $equipeNS,
        $joueursNS[0], $joueursNS[1],
        $joueursNS[2], $joueursNS[3],
        $equipeEO,
        $joueursEO[0], $joueursEO[1],
        $joueursEO[2], $joueursEO[3],
        $donnes, $indexDonneAJouer
    );
	self::logDB("✅ Mitchell → mvnt=$mvntNumero table=$tableNumero NS=$equipeNS EO=$equipeEO set=$setDonnes donnes=$premiereDonne..$derniereDonne");
    return MouvementResult::MouvementComplet($mouvement, $tousTermines);

}


// ─────────────────────────────────────────────────────────────────────
// MITCHELL GUÉRIDON : calcul dynamique du mouvement à la volée.
//
// Convention de numérotation identique à Mitchell :
//   Équipes 1..T     → EO (se déplacent +1 table par mouvement, sans skip)
//   Équipes T+1..N   → NS (fixes)
//
// Disposition physique : T1, TN, T(N-1), …, [Guéridon], …, T2
// Chaque table possède un offset fixe qui détermine le set de départ :
//   offsetGueridon(1)   = 0
//   offsetGueridon(N)   = 0
//   offsetGueridon(t>N/2) = N - t
//   offsetGueridon(t≤N/2) = N - t + 1
//
// Set de donnes au mouvement M pour la table t :
//   setDonnes = ((offset - M + 1) mod N + N) mod N + 1
// ─────────────────────────────────────────────────────────────────────
private static function offsetGueridon(int $tableNumero, int $n): int {
    if ($tableNumero === 1) return 0;
    if ($tableNumero === $n) return 0;
    if ($tableNumero > intdiv($n, 2)) return $n - $tableNumero;
    return $n - $tableNumero + 1;
}

private static function getMouvementMitchellGueridon(
    PDO $pdo,
    int $idTournoi,
    int $equipeNumero,
    int $mvntNumero,
    int $indexDonneAJouer,
    int $nbreDonnesParTable,
    int $nbreEquipes,
    bool $tousTermines = true
): MouvementResult {
    self::logDB("🎯 getMouvementMitchellGueridon : équipe=$equipeNumero mvnt=$mvntNumero");
    $nbreTables = intdiv($nbreEquipes, 2);

    // 1️⃣ Pas de skip en Guéridon : offset de déplacement EO = 0
    $tableNumero = $equipeNumero + $mvntNumero - 1;
    if ($tableNumero > $nbreTables) $tableNumero -= $nbreTables;
    self::logDB("   → nbreTables=$nbreTables équipe=$equipeNumero mvnt=$mvntNumero table=$tableNumero");

    // 2️⃣ Équipe NS à cette table (fixe)
    $equipeNS = $tableNumero + $nbreTables;

    // 3️⃣ Équipe EO à cette table au mouvement M
    $equipeEO = $tableNumero - $mvntNumero + 1;
    if ($equipeEO <= 0) $equipeEO += $nbreTables;
    self::logDB("   → equipeNS=$equipeNS | equipeEO=$equipeEO");

    // 4️⃣ Set de donnes via formule guéridon
    $offset    = self::offsetGueridon($tableNumero, $nbreTables);
    $setDonnes = (($offset - $mvntNumero + 1) % $nbreTables + $nbreTables) % $nbreTables + 1;
    $premiereDonne = ($setDonnes - 1) * $nbreDonnesParTable + 1;
    $derniereDonne = $setDonnes * $nbreDonnesParTable;
    self::logDB("   → offsetGueridon=$offset setDonnes=$setDonnes | donnes=$premiereDonne..$derniereDonne");

    // 5️⃣ Récupérer les infos joueurs des deux équipes
    $fnJoueurs = function(int $numEquipe) use ($pdo, $idTournoi): array {
        $stmt = $pdo->prepare("SELECT j1.nom, j1.prenom, j2.nom, j2.prenom
            FROM equipes e
            JOIN joueurs j1 ON j1.ID = e.id_joueur1
            JOIN joueurs j2 ON j2.ID = e.id_joueur2
            WHERE e.id_tournoi = ? AND e.equipe_numero = ?");
        $stmt->execute([$idTournoi, $numEquipe]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row ?: ['', '', '', ''];
    };
    $joueursNS = $fnJoueurs($equipeNS);
    $joueursEO = $fnJoueurs($equipeEO);

    // Table relais : NS ou EO nommé "Relais" → aucune donne à saisir
    $estRelais = strtolower(trim($joueursNS[0])) === 'relais' || strtolower(trim($joueursNS[2])) === 'relais'
              || strtolower(trim($joueursEO[0])) === 'relais' || strtolower(trim($joueursEO[2])) === 'relais';

    // 6️⃣ Construire la liste des donnes avec métadonnées (vide si table relais)
    $donnes = [];
    if (!$estRelais) {
        for ($numDonne = $premiereDonne; $numDonne <= $derniereDonne; $numDonne++) {
            $stmtDon = $pdo->prepare("SELECT donneur, vulnerable FROM donnes_d_v WHERE ID_donnes_d_v = ? LIMIT 1");
            $stmtDon->execute([$numDonne]);
            $dMeta = $stmtDon->fetch(PDO::FETCH_ASSOC) ?: ["donneur" => "N", "vulnerable" => "Aucune"];
            $mains = self::getMainsPourDonne($idTournoi, $numDonne);
            $donnes[] = new DonneDetail($numDonne, $dMeta['donneur'], $dMeta['vulnerable'], $mains);
        }
        // TN sans relais : joue la 2ème moitié des donnes d'abord
        if ($tableNumero === $nbreTables && $nbreEquipes % 2 === 0 && count($donnes) >= 2) {
            $mid = intdiv(count($donnes), 2);
            $donnes = array_merge(array_slice($donnes, $mid), array_slice($donnes, 0, $mid));
        }
    }

    // 7️⃣ Construire et retourner le Mouvement
    $mouvement = new Mouvement(
        $mvntNumero, $tableNumero,
        $equipeNS,
        $joueursNS[0], $joueursNS[1],
        $joueursNS[2], $joueursNS[3],
        $equipeEO,
        $joueursEO[0], $joueursEO[1],
        $joueursEO[2], $joueursEO[3],
        $donnes, $indexDonneAJouer
    );
    self::logDB("✅ MitchellGuéridon → mvnt=$mvntNumero table=$tableNumero NS=$equipeNS EO=$equipeEO set=$setDonnes donnes=$premiereDonne..$derniereDonne");
    return MouvementResult::MouvementComplet($mouvement, $tousTermines);
}


    public static function getMainsRelais(int $idTournoi, int $numeroDonne): ?array {
        $db = self::connect();
        try {
            $stmt = $db->prepare("SELECT d.main_N, d.main_E, d.main_S, d.main_O FROM donnes d WHERE d.id_tournoi = ? AND d.numero_donne = ? LIMIT 1");
            $stmt->execute([$idTournoi, $numeroDonne]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            $result = [];
            foreach (['main_N', 'main_E', 'main_S', 'main_O'] as $col) {
                $idMain = $row[$col];
                $stmtM = $db->prepare("SELECT * FROM mains WHERE ID = ?");
                $stmtM->execute([$idMain]);
                $mainRow = $stmtM->fetch(PDO::FETCH_ASSOC);
                if (!$mainRow) return null;
                $cartes = [];
                for ($i = 1; $i <= 13; $i++) { $cartes[] = $mainRow["carte$i"]; }
                $result[] = $cartes;
            }
            return $result;
        } catch (Exception $e) { self::logDB("❌ getMainsRelais erreur : " . $e->getMessage()); return null; }
    }

    public static function enregistrerMainsRelais(int $idTournoi, int $numeroDonne, array $mains, int $idAssociation = 0): bool {
        $db = self::connect();
        try {
            $stmt = $db->prepare("SELECT ID FROM donnes WHERE id_tournoi = ? AND numero_donne = ? LIMIT 1");
            $stmt->execute([$idTournoi, $numeroDonne]);
            if ($stmt->fetch()) { self::logDB("🔁 enregistrerMainsRelais donne=$numeroDonne déjà en BD"); return true; }
            if (count($mains) !== 4) return false;
            foreach ($mains as $main) { if (count($main) !== 13) return false; }
            $db->beginTransaction();
            $idsMains = [];
            foreach ($mains as $main) {
                $colonnes = implode(',', array_map(fn($i) => "carte$i", range(1, 13)));
                $placeholders = implode(',', array_fill(0, 13, '?'));
                $stmtM = $db->prepare("INSERT INTO mains ($colonnes) VALUES ($placeholders)");
                $stmtM->execute($main);
                $idsMains[] = $db->lastInsertId();
            }
            $stmtDV = $db->prepare("SELECT donneur, vulnerable FROM donnes_d_v WHERE ID_donnes_d_v = ? LIMIT 1");
            $stmtDV->execute([$numeroDonne]);
            $dv = $stmtDV->fetch(PDO::FETCH_ASSOC);
            $donneur    = $dv['donneur']    ?? 'N';
            $vulnerable = $dv['vulnerable'] ?? 'P';
            $stmtD = $db->prepare("INSERT INTO donnes (id_association, id_tournoi, numero_donne, donneur, vulnerable, main_N, main_E, main_S, main_O) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtD->execute([$idAssociation, $idTournoi, $numeroDonne, $donneur, $vulnerable, $idsMains[0], $idsMains[1], $idsMains[2], $idsMains[3]]);
            $db->commit();
            self::logDB("✅ enregistrerMainsRelais donne=$numeroDonne insérée");
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            self::logDB("❌ enregistrerMainsRelais erreur : " . $e->getMessage());
            return false;
        }
    }

    private static function getNbreEnregistrement(int $idTournoi): int {
        $pdo = self::connect();
        try {
            $stmt = $pdo->prepare("SELECT nbre_enregistrement FROM tournois WHERE ID = ?");
            $stmt->execute([$idTournoi]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['nbre_enregistrement'] : -1;
        } catch (PDOException $e) { self::logDB("❌ getNbreEnregistrement erreur : " . $e->getMessage()); return -1; }
    }

    // =========================================================
    // 💾 enregistreDonne — appelle finaliserClassementTournoi
    // =========================================================
    public static function enregistreDonne($idTournoi, $mvntNumero, $equipeNS, $equipeEO, $numeroTable, $numeroDonne, $indexDonneJouee, $contrat, $declarant, $resultatContrat, $points, $nombrePlis, $carteEntame, $historique, $mainsJson, int $idAssociation = 0) {
        self::logDB("💾 enregistreDonne : tournoi=$idTournoi donne=$numeroDonne equipeNS=$equipeNS equipeEO=$equipeEO");
        $shouldFinalizeClassement = false;
        try {
            $pdo = self::connect();
            $pdo->beginTransaction();
            $signe    = ($resultatContrat === "-") ? -1 : 1;
            $estNS    = ($declarant === "Nord" || $declarant === "Sud");
            $pointsNS = $estNS  ? ($points * $signe) : null;
            $pointsEO = !$estNS ? ($points * $signe) : null;
            $pdo->prepare("INSERT INTO resultats (id_association, id_tournoi, mvnt_numero, equipeNS, equipeEO, numero_table, numero_donne, contrat, declarant, resultat_contrat, points, pointsNS, pointsEO, nombre_pli, carteEntame) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([$idAssociation, $idTournoi, $mvntNumero, $equipeNS, $equipeEO, $numeroTable, $numeroDonne, $contrat, $declarant, $resultatContrat, $points, $pointsNS, $pointsEO, $nombrePlis, $carteEntame]);
            if (is_string($historique)) $historiqueArray = json_decode($historique, true) ?? [];
            elseif (is_array($historique)) $historiqueArray = $historique;
            else $historiqueArray = [];
            if (count($historiqueArray) > 0) {
                $stmt = $pdo->prepare("INSERT INTO encheres (id_association, id_tournoi, numero_donne, equipeNS, equipeEO, ordre, joueur, annonce) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($historiqueArray as $index => $tour) {
                    $joueur  = trim(urldecode($tour['joueur']  ?? ''));
                    $annonce = trim(urldecode($tour['annonce'] ?? ''));
                    if (empty($joueur) || empty($annonce)) continue;
                    $stmt->execute([$idAssociation, $idTournoi, $numeroDonne, $equipeNS, $equipeEO, $index, $joueur, $annonce]);
                }
            }
            $stmtCheck = $pdo->prepare("SELECT ID FROM donnes WHERE id_tournoi = ? AND numero_donne = ? LIMIT 1");
            $stmtCheck->execute([$idTournoi, $numeroDonne]);
            $donneDejaExiste = (bool)$stmtCheck->fetch();
            if (!$donneDejaExiste && !empty($mainsJson) && $mainsJson !== "null") {
                $decoded = is_array($mainsJson) ? $mainsJson : (json_decode($mainsJson, true) ?? []);
                if (is_array($decoded) && count($decoded) === 4 && array_sum(array_map('count', $decoded)) === 52) {
                    $insertMain = $pdo->prepare("INSERT INTO mains (carte1,carte2,carte3,carte4,carte5,carte6,carte7,carte8,carte9,carte10,carte11,carte12,carte13) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $idsMains = [];
                    foreach ($decoded as $cartes) { $cartes = array_pad($cartes, 13, null); $insertMain->execute($cartes); $idsMains[] = $pdo->lastInsertId(); }
                    $stmtMeta = $pdo->prepare("SELECT donneur, vulnerable FROM donnes_d_v WHERE ID_donnes_d_v = ? LIMIT 1");
                    $stmtMeta->execute([$numeroDonne]);
                    $meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);
                    $pdo->prepare("INSERT INTO donnes (id_association, id_tournoi, numero_donne, donneur, vulnerable, main_N, main_E, main_S, main_O) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([$idAssociation, $idTournoi, $numeroDonne, $meta['donneur'] ?? 'N', $meta['vulnerable'] ?? 'P', $idsMains[0] ?? null, $idsMains[1] ?? null, $idsMains[2] ?? null, $idsMains[3] ?? null]);
                }
            }
            $sqlUpdate = "UPDATE equipes SET mvnt_numero=?, index_donne_jouee=?, numero_donne=? WHERE id_tournoi=? AND equipe_numero=?";
            $stmt = $pdo->prepare($sqlUpdate);
            $stmt->execute([$mvntNumero, $indexDonneJouee, $numeroDonne, $idTournoi, $equipeNS]);
            $stmt->execute([$mvntNumero, $indexDonneJouee, $numeroDonne, $idTournoi, $equipeEO]);
            $pdo->exec("UPDATE tournois SET nbre_enregistrement = nbre_enregistrement - 1 WHERE ID = $idTournoi");
            $reste = (int)$pdo->query("SELECT nbre_enregistrement FROM tournois WHERE ID = $idTournoi")->fetchColumn();
            self::logDB("💾 nbre_enregistrement restants = $reste");
            if ($reste === 0) { self::logDB("🏁 Tournoi $idTournoi TERMINÉ"); $shouldFinalizeClassement = true; }
            $pdo->commit();
            $indexDonneAJouer = $indexDonneJouee + 1;
            // ✅ Finalisation selon le type (EBL ou Simplifiés)
            if ($shouldFinalizeClassement) { self::finaliserClassementTournoi($idTournoi); }
            return $indexDonneAJouer;
        } catch (Exception $e) {
            self::logDB("❌ enregistreDonne erreur : " . $e->getMessage());
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            return -1;
        }
    }

    // =========================================================
    // 🆕 finaliserClassementTournoi — branche selon le type
    // =========================================================
    public static function finaliserClassementTournoi(int $idTournoi): void {
        self::logDB("⚙️ finaliserClassementTournoi → tournoi $idTournoi");
        $typeTournoi = self::getTypeTournoi($idTournoi);
        self::logDB("📋 Type : '$typeTournoi'");

        $resultats = self::getResultatsTournoi($idTournoi);
        if (empty($resultats)) { self::logDB("⚠️ Aucun résultat pour finaliser"); return; }

        if ($typeTournoi === 'par4equ2t21d') {
            // ── Calcul croisé + barème EBL ────────────────────────────────
            self::logDB("🔀 Calcul EBL croisé (par4equ2t21d)");
            $calcul = CalculClassementManager::calculerClassementPar4Equ2T21D($resultats);
            foreach ($calcul['majDonnes'] as $maj) {
                self::majPointsDonne($idTournoi, $maj['numero_donne'], $maj['numero_table'], $maj['ptsNS'], $maj['ptsEO']);
            }
        } else {
            // ── Calcul Simplifiés standard ────────────────────────────────
            self::logDB("📊 Calcul Simplifiés standard");
            $calcul = CalculClassementManager::calculerClassementTournoi($resultats);
            foreach ($calcul['majDonnes'] as $maj) {
                self::majPointsDonne($idTournoi, $maj['numero_donne'], $maj['numero_table'], $maj['ptsNS'], $maj['ptsEO']);
            }
        }

        foreach ($calcul['classement'] as $c) {
            self::majClassementEquipe($idTournoi, $c['numeroEquipe'], $c['totalPts'], $c['rang']);
        }
        self::logDB("✅ finaliserClassementTournoi terminé pour tournoi $idTournoi");
    }

	public static function getFuturMouvement(int $idTournoi, int $mvntActuel, int $equipeNS, int $equipeEO): ?array {
		$pdo = self::connect();
		$mvntSuivant = $mvntActuel + 1;
		self::logDB("📞 getFuturMouvement - mvntActuel=$mvntActuel, mvntSuivant=$mvntSuivant");

		// 1️⃣ Récupérer le type et le nombre d'équipes
		$stmt = $pdo->prepare("SELECT type, nbre_equipe FROM tournois WHERE ID = ?");
		$stmt->execute([$idTournoi]);
		$rowTournoi = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$rowTournoi) { self::logDB("❌ Tournoi $idTournoi introuvable"); return null; }
		$typeDeTournoi = $rowTournoi['type'];
		$nbreEquipes   = (int)$rowTournoi['nbre_equipe'];

		// 2️⃣ Branchement Mitchell / Guéridon ou Howell
		if ($typeDeTournoi === 'Mitchell' || $typeDeTournoi === 'MitchellGueridon') {
			// ── Mitchell / Guéridon : calcul dynamique sans table SQL ────────
			$nbreTables = intdiv($nbreEquipes, 2);
			$entries    = [];

			for ($tableNumero = 1; $tableNumero <= $nbreTables; $tableNumero++) {
				// NS à la table T
				$nsNum = $tableNumero + $nbreTables;

				// EO au mouvement suivant.
				// Mitchell standard (tables paires) : skip après la moitié des mouvements.
				// Guéridon : jamais de skip.
				$offset = ($typeDeTournoi === 'Mitchell' && $nbreTables % 2 === 0 && $mvntSuivant > intdiv($nbreTables, 2)) ? 1 : 0;
				$eoNum  = $tableNumero - $mvntSuivant + 1 - $offset;
				if ($eoNum <= 0) $eoNum += $nbreTables;

				// Récupérer les joueurs NS
				$stmtNS = $pdo->prepare("SELECT j1.ID, j1.nom, j1.prenom, j2.ID, j2.nom, j2.prenom
					FROM equipes e
					JOIN joueurs j1 ON j1.ID = e.id_joueur1
					JOIN joueurs j2 ON j2.ID = e.id_joueur2
					WHERE e.id_tournoi = ? AND e.equipe_numero = ?");
				$stmtNS->execute([$idTournoi, $nsNum]);
				$rowNS = $stmtNS->fetch(PDO::FETCH_NUM) ?: [0,'','',0,'',''];

				// Récupérer les joueurs EO
				$stmtEO = $pdo->prepare("SELECT j1.ID, j1.nom, j1.prenom, j2.ID, j2.nom, j2.prenom
					FROM equipes e
					JOIN joueurs j1 ON j1.ID = e.id_joueur1
					JOIN joueurs j2 ON j2.ID = e.id_joueur2
					WHERE e.id_tournoi = ? AND e.equipe_numero = ?");
				$stmtEO->execute([$idTournoi, $eoNum]);
				$rowEO = $stmtEO->fetch(PDO::FETCH_NUM) ?: [0,'','',0,'',''];

				$entries[] = [
					'tableNumero' => $tableNumero,
					'equipe' => [
						'equipeNumero' => $nsNum,
						'joueur1' => ['id' => (int)$rowNS[0], 'nom' => $rowNS[1], 'prenom' => $rowNS[2]],
						'joueur2' => ['id' => (int)$rowNS[3], 'nom' => $rowNS[4], 'prenom' => $rowNS[5]],
						'idTournoi' => $idTournoi
					],
					'adversaire' => [
						'equipeNumero' => $eoNum,
						'joueur1' => ['id' => (int)$rowEO[0], 'nom' => $rowEO[1], 'prenom' => $rowEO[2]],
						'joueur2' => ['id' => (int)$rowEO[3], 'nom' => $rowEO[4], 'prenom' => $rowEO[5]],
						'idTournoi' => $idTournoi
					]
				];
				self::logDB("   → Table $tableNumero : NS=$nsNum EO=$eoNum");
			}

			self::logDB("✅ getFuturMouvement $typeDeTournoi - " . count($entries) . " table(s) pour mouvement $mvntSuivant");
			return count($entries) > 0 ? ['mvntSuivant' => $mvntSuivant, 'entries' => $entries] : null;

		} else {
			// ── Howell : requête SQL sur la table de mouvements (inchangé) ────
			$sql = "SELECT t.table_numero AS table_numero, t.equipe_NS AS equipe_NS, t.equipe_EO AS equipe_EO,
						jNS1.ID AS jns1_id, jNS1.nom AS jns1_nom, jNS1.prenom AS jns1_prenom,
						jNS2.ID AS jns2_id, jNS2.nom AS jns2_nom, jNS2.prenom AS jns2_prenom,
						jEO1.ID AS jeo1_id, jEO1.nom AS jeo1_nom, jEO1.prenom AS jeo1_prenom,
						jEO2.ID AS jeo2_id, jEO2.nom AS jeo2_nom, jEO2.prenom AS jeo2_prenom
					FROM $typeDeTournoi t
					INNER JOIN equipes eNS ON eNS.equipe_numero = t.equipe_NS
					INNER JOIN equipes eEO ON eEO.equipe_numero = t.equipe_EO
					INNER JOIN joueurs jNS1 ON jNS1.ID = eNS.id_joueur1
					INNER JOIN joueurs jNS2 ON jNS2.ID = eNS.id_joueur2
					INNER JOIN joueurs jEO1 ON jEO1.ID = eEO.id_joueur1
					INNER JOIN joueurs jEO2 ON jEO2.ID = eEO.id_joueur2
					WHERE eNS.id_tournoi = :idTournoi AND eEO.id_tournoi = :idTournoi
					  AND t.mvnt_numero = :mvntSuivant
					  AND ((t.equipe_NS IN (:equipeNS, :equipeEO)) OR (t.equipe_EO IN (:equipeNS, :equipeEO)))";
			$stmt = $pdo->prepare($sql);
			$stmt->execute([
				'idTournoi'   => $idTournoi,
				'mvntSuivant' => $mvntSuivant,
				'equipeNS'    => $equipeNS,
				'equipeEO'    => $equipeEO
			]);
			$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (!$results) { self::logDB("⚠️ Aucun mouvement futur pour mvnt $mvntSuivant"); return null; }

			$entries = [];
			foreach ($results as $row) {
				$entries[] = [
					'tableNumero' => (int)$row['table_numero'],
					'equipe' => [
						'equipeNumero' => (int)$row['equipe_NS'],
						'joueur1' => ['id' => (int)$row['jns1_id'], 'nom' => $row['jns1_nom'], 'prenom' => $row['jns1_prenom']],
						'joueur2' => ['id' => (int)$row['jns2_id'], 'nom' => $row['jns2_nom'], 'prenom' => $row['jns2_prenom']],
						'idTournoi' => $idTournoi
					],
					'adversaire' => [
						'equipeNumero' => (int)$row['equipe_EO'],
						'joueur1' => ['id' => (int)$row['jeo1_id'], 'nom' => $row['jeo1_nom'], 'prenom' => $row['jeo1_prenom']],
						'joueur2' => ['id' => (int)$row['jeo2_id'], 'nom' => $row['jeo2_nom'], 'prenom' => $row['jeo2_prenom']],
						'idTournoi' => $idTournoi
					]
				];
			}
			self::logDB("✅ getFuturMouvement Howell - " . count($entries) . " table(s) pour mouvement $mvntSuivant");
			return ['mvntSuivant' => $mvntSuivant, 'entries' => $entries];
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// Retourne pour chaque équipe sa table et orientation au mouvement 1
	// Utilisé dans ConstitutionEquipesScreen pour afficher NS/EO + table
	// Mitchell : calcul algorithmique local
	// Howell   : requête SQL sur la table de mouvements au mouvement 1
	// ─────────────────────────────────────────────────────────────────────
	/**
	 * Retourne pour chaque équipe sa table et orientation de départ au mouvement 1.
	 * Appelée depuis ConstitutionEquipesScreen (via serverBridge) après la constitution des équipes.
	 *
	 * Le tournoi est déjà créé en BD avant d'arriver ici : le type est donc lu depuis
	 * la table tournois (jamais accepté du client) pour éviter toute injection SQL.
	 *
	 * Mitchell / MitchellGuéridon : positions calculées algorithmiquement.
	 * Howell : positions lues dans la table de mouvements propre au type.
	 *
	 * @return array<int, array{orientation: string, table: int}>
	 */
	public static function getPositionsMouvement1(int $idTournoi): array {
		$positions = [];
		try {
			$pdo = self::connect();

			// Lecture du type depuis la BD — jamais depuis le client
			$stmtType = $pdo->prepare("SELECT type, nbre_equipe FROM tournois WHERE ID = ?");
			$stmtType->execute([$idTournoi]);
			$rowTournoi = $stmtType->fetch(PDO::FETCH_ASSOC);
			if (!$rowTournoi) {
				self::logDB("❌ getPositionsMouvement1 : tournoi $idTournoi introuvable");
				return [];
			}
			$typeTournoi = $rowTournoi['type'];

			if ($typeTournoi === 'Mitchell' || $typeTournoi === 'MitchellGueridon') {
				// Mitchell / Guéridon : calcul algorithmique, pas de table SQL de mouvements
				$nbreEquipes = (int)$rowTournoi['nbre_equipe'];
				$nbreTables  = intdiv($nbreEquipes, 2);

				for ($equipeNumero = 1; $equipeNumero <= $nbreEquipes; $equipeNumero++) {
					if ($equipeNumero > $nbreTables) {
						// Équipes > nbreTables sont NS, table fixe = nbreEquipes - equipeNumero + 1
						$positions[$equipeNumero] = ['orientation' => 'NS', 'table' => $nbreEquipes - $equipeNumero + 1];
					} else {
						// Équipes <= nbreTables sont EO, table de départ = abs(equipeNumero - nbreTables - 1)
						$positions[$equipeNumero] = ['orientation' => 'EO', 'table' => abs($equipeNumero - $nbreTables - 1)];
					}
				}
			} else {
				// Howell : les positions initiales sont stockées dans la table nommée d'après le type
				// Le type vient de la BD (ligne ci-dessus), jamais du client — pas d'injection possible
				$stmt = $pdo->prepare("SELECT equipe_NS, equipe_EO, table_numero FROM $typeTournoi WHERE mvnt_numero = 1");
				$stmt->execute();
				while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
					$positions[(int)$row['equipe_NS']] = ['orientation' => 'NS', 'table' => (int)$row['table_numero']];
					$positions[(int)$row['equipe_EO']] = ['orientation' => 'EO', 'table' => (int)$row['table_numero']];
				}
			}

			self::logDB("✅ getPositionsMouvement1 : " . count($positions) . " équipes pour tournoi $idTournoi type=$typeTournoi");
		} catch (Exception $e) {
			self::logDB("❌ getPositionsMouvement1 erreur : " . $e->getMessage());
		}
		return $positions;
	}

    public static function getMainsPourDonne(int $idTournoi, int $numeroDonne) {
        $pdo = self::connect();
        try {
            $stmt = $pdo->prepare("SELECT main_N, main_E, main_S, main_O FROM donnes WHERE id_tournoi = ? AND numero_donne = ? LIMIT 1");
            $stmt->execute([$idTournoi, $numeroDonne]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            $idsMains = [$row['main_N'] ?? null, $row['main_E'] ?? null, $row['main_S'] ?? null, $row['main_O'] ?? null];
            if (in_array(null, $idsMains, true) || in_array(0, $idsMains, true)) return null;
            $mains = [];
            $stmtMain = $pdo->prepare("SELECT carte1,carte2,carte3,carte4,carte5,carte6,carte7,carte8,carte9,carte10,carte11,carte12,carte13 FROM mains WHERE ID = ?");
            foreach ($idsMains as $idMain) { $stmtMain->execute([$idMain]); $rowMain = $stmtMain->fetch(PDO::FETCH_NUM); if (!$rowMain) return null; $mains[] = $rowMain; }
            return $mains;
        } catch (Exception $e) { self::logDB("❌ getMainsPourDonne erreur : " . $e->getMessage()); return null; }
    }

    public static function verifierEtatTournoi($idTournoi): string {
        $pdo = self::connect();
        $stmt = $pdo->prepare("SELECT nbre_enregistrement FROM tournois WHERE ID = :id");
        $stmt->execute([':id' => $idTournoi]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return "ERREUR";
        return intval($row['nbre_enregistrement']) === 0 ? "TERMINE" : "NON_TERMINE";
    }

    public static function getResultatsTournoi(int $idTournoi): array {
        $pdo = self::connect();
        try {
            $stmt = $pdo->prepare("SELECT numero_donne, numero_table, equipeNS, equipeEO, pointsNS, pointsEO FROM resultats WHERE id_tournoi = :id ORDER BY numero_donne, numero_table");
            $stmt->bindValue(':id', $idTournoi, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { self::logDB("❌ Erreur getResultatsTournoi : " . $e->getMessage()); return []; }
    }

    public static function getDonneResultatDetails($idTournoi) {
        $liste = [];
        $sql = "SELECT r.numero_donne, r.equipeNS, r.equipeEO, r.contrat, r.declarant, r.resultat_contrat, r.nombre_pli, r.carteEntame, r.pointsNS, r.pointsEO, r.ptsNS, r.ptsEO, dv.vulnerable FROM resultats r JOIN donnes_d_v dv ON dv.ID_donnes_d_v = r.numero_donne WHERE r.id_tournoi = ? ORDER BY r.numero_donne ASC";
        try {
            $db = self::connect();
            $stmt = $db->prepare($sql);
            $stmt->execute([$idTournoi]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $liste[] = ["numero_donne" => intval($row['numero_donne'] ?? 0), "equipeNS" => intval($row['equipeNS'] ?? 0), "equipeEO" => intval($row['equipeEO'] ?? 0), "contrat" => $row['contrat'] ?? '', "declarant" => $row['declarant'] ?? '', "resultat_contrat" => $row['resultat_contrat'] ?? '', "nombre_pli" => intval($row['nombre_pli'] ?? 0), "carteEntame" => $row['carteEntame'] ?? '', "pointsNS" => intval($row['pointsNS'] ?? 0), "pointsEO" => intval($row['pointsEO'] ?? 0), "ptsNS" => floatval($row['ptsNS'] ?? 0), "ptsEO" => floatval($row['ptsEO'] ?? 0), "vulnerable" => $row['vulnerable'] ?? 'P'];
            }
        } catch (Exception $e) { self::logDB("❌ getDonneResultatDetails erreur : " . $e->getMessage()); }
        return $liste;
    }

    public static function getDonneComplete(int $idTournoi, int $numeroDonne, int $equipeNS): ?array {
        $db = self::connect();
        try {
            $stmt = $db->prepare("SELECT r.contrat, r.declarant, dv.vulnerable, dv.donneur FROM resultats r JOIN donnes_d_v dv ON dv.ID_donnes_d_v = r.numero_donne WHERE r.id_tournoi = ? AND r.numero_donne = ? AND r.equipeNS = ? LIMIT 1");
            $stmt->execute([$idTournoi, $numeroDonne, $equipeNS]);
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$resultat) return null;
            $mains = [];
            $stmtM = $db->prepare("SELECT mN.carte1,mN.carte2,mN.carte3,mN.carte4,mN.carte5,mN.carte6,mN.carte7,mN.carte8,mN.carte9,mN.carte10,mN.carte11,mN.carte12,mN.carte13,mS.carte1,mS.carte2,mS.carte3,mS.carte4,mS.carte5,mS.carte6,mS.carte7,mS.carte8,mS.carte9,mS.carte10,mS.carte11,mS.carte12,mS.carte13,mE.carte1,mE.carte2,mE.carte3,mE.carte4,mE.carte5,mE.carte6,mE.carte7,mE.carte8,mE.carte9,mE.carte10,mE.carte11,mE.carte12,mE.carte13,mO.carte1,mO.carte2,mO.carte3,mO.carte4,mO.carte5,mO.carte6,mO.carte7,mO.carte8,mO.carte9,mO.carte10,mO.carte11,mO.carte12,mO.carte13 FROM donnes d JOIN mains mN ON d.main_N = mN.ID JOIN mains mS ON d.main_S = mS.ID JOIN mains mE ON d.main_E = mE.ID JOIN mains mO ON d.main_O = mO.ID WHERE d.id_tournoi = ? AND d.numero_donne = ? LIMIT 1");
            $stmtM->execute([$idTournoi, $numeroDonne]);
            $rowMains = $stmtM->fetch(PDO::FETCH_NUM);
            if ($rowMains) {
                foreach (['N', 'S', 'E', 'O'] as $idx => $pos) { $cartes = []; for ($i = 0; $i < 13; $i++) { $cartes[] = $rowMains[$idx * 13 + $i]; } $mains[$pos] = $cartes; }
            }
            $stmtE = $db->prepare("SELECT joueur, annonce FROM encheres WHERE id_tournoi = ? AND numero_donne = ? AND equipeNS = ? ORDER BY ordre ASC");
            $stmtE->execute([$idTournoi, $numeroDonne, $equipeNS]);
            $encheres = [];
            while ($row = $stmtE->fetch(PDO::FETCH_ASSOC)) { $encheres[] = ['joueur' => $row['joueur'], 'annonce' => $row['annonce']]; }
            return ['mains' => $mains, 'encheres' => $encheres, 'vulnerable' => $resultat['vulnerable'] ?? 'P', 'donneur' => $resultat['donneur'] ?? 'N', 'contrat' => $resultat['contrat'] ?? '', 'declarant' => $resultat['declarant'] ?? ''];
        } catch (Exception $e) { self::logDB("❌ getDonneComplete exception : " . $e->getMessage()); return null; }
    }

    public static function getEquipesAyantJoueDonne($idTournoi, $numeroDonne) {
        $pdo = self::connect();
        $liste = [];
        try {
            $stmt = $pdo->prepare("SELECT equipeNS, equipeEO, contrat, declarant FROM resultats WHERE id_tournoi = ? AND numero_donne = ? ORDER BY equipeNS ASC");
            $stmt->execute([$idTournoi, $numeroDonne]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $liste[] = ["equipeNS" => intval($row['equipeNS']), "equipeEO" => intval($row['equipeEO']), "contrat" => $row['contrat'] ?? "", "declarant" => $row['declarant'] ?? ""]; }
        } catch (PDOException $e) { self::logDB("❌ Erreur getEquipesAyantJoueDonne : " . $e->getMessage()); }
        return $liste;
    }

    public static function majPointsDonne($idTournoi, $numeroDonne, $numeroTable, $ptsNS, $ptsEO) {
        try {
            $pdo = self::connect();
            $pdo->prepare("UPDATE resultats SET ptsNS = ?, ptsEO = ? WHERE id_tournoi = ? AND numero_donne = ? AND numero_table = ?")->execute([$ptsNS, $ptsEO, $idTournoi, $numeroDonne, $numeroTable]);
            self::logDB("🟢 majPointsDonne donne=$numeroDonne table=$numeroTable ptsNS=$ptsNS ptsEO=$ptsEO");
        } catch (Exception $e) { self::logDB("❌ majPointsDonne erreur : " . $e->getMessage()); }
    }

    public static function majClassementEquipe($idTournoi, $numeroEquipe, $totalPts, $rang) {
        try {
            $pdo = self::connect();
            $pdo->prepare("UPDATE equipes SET pts = ?, rang = ? WHERE id_tournoi = ? AND equipe_numero = ?")->execute([$totalPts, $rang, $idTournoi, $numeroEquipe]);
            self::logDB("🏆 majClassementEquipe équipe $numeroEquipe = $totalPts pts, rang $rang");
        } catch (Exception $e) { self::logDB("❌ majClassementEquipe erreur : " . $e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Journal des erreurs joueurs
    // ─────────────────────────────────────────────────────────────────────────

    private static bool $erreurLogTableChecked = false;

    private static function ensureErreurLogTable(PDO $pdo): void {
        if (self::$erreurLogTableChecked) return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS erreurs_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp VARCHAR(20) NOT NULL,
            id_tournoi INT DEFAULT -1,
            equipe_numero INT DEFAULT -1,
            etape VARCHAR(100) DEFAULT '',
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        self::$erreurLogTableChecked = true;
    }

    public static function enregistrerErreurLog(int $idTournoi, int $equipeNumero, string $etape, string $message): void {
        try {
            $pdo = self::connect();
            self::ensureErreurLogTable($pdo);
            $timestamp = (new DateTime())->format('d/m H:i');
            $pdo->prepare("INSERT INTO erreurs_log (timestamp, id_tournoi, equipe_numero, etape, message) VALUES (?, ?, ?, ?, ?)")
                ->execute([$timestamp, $idTournoi, $equipeNumero, $etape, $message]);
            self::logDB("📋 Erreur loggée : [$etape] équipe=$equipeNumero tournoi=$idTournoi");
        } catch (Exception $e) {
            self::logDB("❌ enregistrerErreurLog : " . $e->getMessage());
        }
    }

    public static function getErreursLog(int $idTournoi): array {
        try {
            $pdo = self::connect();
            self::ensureErreurLogTable($pdo);
            $stmt = $pdo->prepare(
                "SELECT id, timestamp, id_tournoi, equipe_numero, etape, message
                 FROM erreurs_log
                 WHERE id_tournoi = ?
                 ORDER BY id DESC LIMIT 100"
            );
            $stmt->execute([$idTournoi]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            self::logDB("❌ getErreursLog : " . $e->getMessage());
            return [];
        }
    }
}
