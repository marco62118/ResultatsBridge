<?php
class CalculClassementManager {

    // =========================================================
    // 📊 Calcul Simplifiés standard (tous types sauf par4equ2t21d)
    // =========================================================
	public static function calculerClassementTournoi(array $resultats): array {

    // 1️⃣ Regrouper les résultats par donne
    $resultatsParDonne = [];
    foreach ($resultats as $ligne) {
        $resultatsParDonne[$ligne['numero_donne']][] = $ligne;
    }

    // 2️⃣ N = référence Neuberg = max fois où une donne est jouée
    $N = 0;
    foreach ($resultatsParDonne as $lignes) {
        $N = max($N, count($lignes));
    }
    $Ntop = ($N - 1) * 2;  // TOP de référence commun à toutes les donnes

    $pointsParEquipe = [];
    $dealsParEquipe  = [];  // nb de donnes jouées par équipe (NS + EO)
    $majDonnes = [];

    // 3️⃣ Calcul donne par donne
    foreach ($resultatsParDonne as $numeroDonne => $lignes) {

        $nFois = count($lignes);
        $top   = ($nFois - 1) * 2;  // TOP brut de cette donne

        // Score NS pour chaque ligne (positif si NS gagne, négatif si EO gagne)
        $scoresNSParLigne = array_map(function($ligne) {
            return ($ligne['pointsNS'] !== null)
                ? (int)$ligne['pointsNS']
                : -(int)$ligne['pointsEO'];
        }, $lignes);

        $scoresNSTries = $scoresNSParLigne;
        rsort($scoresNSTries);

        // Calcul matchpoints bruts puis formule de Neuberg
        $pointsAttribues = [];
        foreach ($scoresNSTries as $score) {
            if (isset($pointsAttribues[$score])) continue;
            $indicesEgaux = array_keys(array_filter($scoresNSTries, fn($v) => $v == $score));
            $moyennePlace = array_sum($indicesEgaux) / count($indicesEgaux);
            $pas          = ($nFois > 1) ? $top / ($nFois - 1) : 2.0;
            $scoreReel    = $top - $moyennePlace * $pas;

            // Formule de Neuberg : ajuste si cette donne a été jouée moins de N fois
            $scoreAjuste = ($nFois < $N)
                ? ($N * ($scoreReel + 1.0)) / $nFois - 1.0
                : $scoreReel;

            $pointsAttribues[$score] = $scoreAjuste;
        }

        // Attribution des points à chaque équipe
        foreach ($lignes as $index => $ligne) {
            $scoreNS = $scoresNSParLigne[$index];
            $ptsNS   = $pointsAttribues[$scoreNS];
            $ptsEO   = $Ntop - $ptsNS;  // complément Neuberg
            $pointsParEquipe[$ligne['equipeNS']] = ($pointsParEquipe[$ligne['equipeNS']] ?? 0.0) + $ptsNS;
            $pointsParEquipe[$ligne['equipeEO']] = ($pointsParEquipe[$ligne['equipeEO']] ?? 0.0) + $ptsEO;
            $dealsParEquipe[$ligne['equipeNS']]  = ($dealsParEquipe[$ligne['equipeNS']]  ?? 0) + 1;
            $dealsParEquipe[$ligne['equipeEO']]  = ($dealsParEquipe[$ligne['equipeEO']]  ?? 0) + 1;
            $majDonnes[] = [
                'numero_donne' => $ligne['numero_donne'],
                'numero_table' => $ligne['numero_table'],
                'ptsNS'        => $ptsNS,
                'ptsEO'        => $ptsEO
            ];
        }
    }

    // 4️⃣ Trier et attribuer les rangs + pourcentage Neuberg
    arsort($pointsParEquipe);
    $classement = [];
    $precedentScore = null;
    foreach ($pointsParEquipe as $equipeNumero => $totalPts) {
        if ($totalPts !== $precedentScore) $rang = count($classement) + 1;
        $nDeals   = $dealsParEquipe[$equipeNumero] ?? 0;
        $scorePct = ($nDeals > 0 && $Ntop > 0) ? ($totalPts / ($nDeals * $Ntop)) * 100.0 : 0.0;
        $classement[] = ['numeroEquipe' => $equipeNumero, 'totalPts' => $totalPts, 'rang' => $rang, 'scorePct' => $scorePct];
        $precedentScore = $totalPts;
    }

    return ['classement' => $classement, 'majDonnes' => $majDonnes];
}
    /* public static function calculerClassementTournoi(array $resultats): array {
        $resultatsParDonne = [];
        foreach ($resultats as $ligne) {
            $resultatsParDonne[$ligne['numero_donne']][] = $ligne;
        }
        $pointsParEquipe = [];
        $nTables = count(array_unique(array_column($resultats, 'numero_table')));
        $top = ($nTables - 1) * 2;
        $majDonnes = [];
        foreach ($resultatsParDonne as $numeroDonne => $lignes) {
            // $scoresNS = array_column($lignes, 'pointsNS');
			// Calcul du scoreNS : positif si NS gagne, négatif si EO gagne
			$scoresNS = array_map(function($ligne) {
				if ($ligne['pointsNS'] !== null) {
					return (int)$ligne['pointsNS'];   // NS a gagné
				} else {
					return -(int)$ligne['pointsEO'];  // EO a gagné → négatif pour NS
				}
			}, $lignes);
            rsort($scoresNS);
            $pointsAttribues = [];
            foreach ($scoresNS as $score) {
                $nbEgaux = count(array_filter($scoresNS, fn($v) => $v == $score));
                $indicesEgaux = array_keys(array_filter($scoresNS, fn($v) => $v == $score));
                $moyennePlace = array_sum($indicesEgaux) / $nbEgaux;
                $pointsAttribues[$score] = $top - $moyennePlace * ($top / ($nTables - 1));
            }
            foreach ($lignes as $ligne) {
                $ptsNS = $pointsAttribues[$ligne['pointsNS']] ?? 0;
                $ptsEO = $top - $ptsNS;
                $pointsParEquipe[$ligne['equipeNS']] = ($pointsParEquipe[$ligne['equipeNS']] ?? 0) + $ptsNS;
                $pointsParEquipe[$ligne['equipeEO']] = ($pointsParEquipe[$ligne['equipeEO']] ?? 0) + $ptsEO;
                $majDonnes[] = ['numero_donne' => $ligne['numero_donne'], 'numero_table' => $ligne['numero_table'], 'ptsNS' => $ptsNS, 'ptsEO' => $ptsEO];
            }
        }
        arsort($pointsParEquipe);
        $classement = [];
        $precedentScore = null;
        foreach ($pointsParEquipe as $equipeNumero => $totalPts) {
            if ($totalPts !== $precedentScore) $rang = count($classement) + 1;
            $classement[] = ['numeroEquipe' => $equipeNumero, 'totalPts' => $totalPts, 'rang' => $rang];
            $precedentScore = $totalPts;
        }
        return ['classement' => $classement, 'majDonnes' => $majDonnes];
    } */

    // =========================================================
    // 🆕 Calcul croisé + barème EBL — tournoi par4equ2t21d
    // =========================================================
    // Pour chaque donne (2 lignes T1 + T2) :
    //   recapEq1 = ptsNS(eq1 NS à T1) + ptsEO(eq2 EO à T2)
    //   recapEq2 = ptsNS(eq3 NS à T2) + ptsEO(eq4 EO à T1)
    //   diff = recapEq1 - recapEq2
    //   → EBL(|diff|) au gagnant, 0 au perdant
    //
    // Groupes par mouvement (numéro de donne) :
    //   donnes  1- 7 → mvnt 1 : groupe1={1,4} groupe2={2,3}
    //   donnes  8-14 → mvnt 2 : groupe1={1,2} groupe2={3,4}
    //   donnes 15-21 → mvnt 3 : groupe1={1,3} groupe2={2,4}
    // =========================================================
    public static function calculerClassementPar4Equ2T21D(array $resultats): array {

        // ── Barème EBL ────────────────────────────────────────────────────────
        $bareme = function(int $diff): int {
            if ($diff <=   10) return 0;
            if ($diff <=   40) return 1;
            if ($diff <=   80) return 2;
            if ($diff <=  120) return 3;
            if ($diff <=  160) return 4;
            if ($diff <=  210) return 5;
            if ($diff <=  260) return 6;
            if ($diff <=  310) return 7;
            if ($diff <=  360) return 8;
            if ($diff <=  420) return 9;
            if ($diff <=  490) return 10;
            if ($diff <=  590) return 11;
            if ($diff <=  740) return 12;
            if ($diff <=  890) return 13;
            if ($diff <= 1090) return 14;
            if ($diff <= 1290) return 15;
            if ($diff <= 1490) return 16;
            if ($diff <= 1740) return 17;
            if ($diff <= 1990) return 18;
            if ($diff <= 2240) return 19;
            if ($diff <= 2490) return 20;
            if ($diff <= 2740) return 21;
            if ($diff <= 2990) return 22;
            if ($diff <= 3240) return 23;
            if ($diff <= 3490) return 24;
            return 25;
        };

        // ── Configuration des groupes par mouvement ───────────────────────────
        // nsEq1/eoEq1 = équipes du groupe 1 (NS à T1, EO à T2)
        // nsEq2/eoEq2 = équipes du groupe 2 (NS à T2, EO à T1)
        $groupes = [
            1 => ['nsEq1' => 1, 'eoEq1' => 4, 'nsEq2' => 3, 'eoEq2' => 2],
            2 => ['nsEq1' => 1, 'eoEq1' => 2, 'nsEq2' => 4, 'eoEq2' => 3],
            3 => ['nsEq1' => 1, 'eoEq1' => 3, 'nsEq2' => 2, 'eoEq2' => 4],
        ];

        // ── Équipes qui reçoivent les points EBL selon le mouvement ───────────
        $equipesGroupe = [
            1 => ['g1' => [1, 4], 'g2' => [2, 3]],
            2 => ['g1' => [1, 2], 'g2' => [3, 4]],
            3 => ['g1' => [1, 3], 'g2' => [2, 4]],
        ];

        // ── Numéro de donne → mouvement ───────────────────────────────────────
        $mouvementPourDonne = function(int $num): int {
            if ($num <=  7) return 1;
            if ($num <= 14) return 2;
            return 3;
        };

        // ── Accumulateur EBL par équipe ───────────────────────────────────────
        $totalEblParEquipe = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

        // ── Regrouper résultats par donne ─────────────────────────────────────
        $parDonne = [];
        foreach ($resultats as $ligne) {
            $parDonne[(int)$ligne['numero_donne']][] = $ligne;
        }
        ksort($parDonne);

        $majDonnes = [];

        foreach ($parDonne as $numeroDonne => $lignes) {
            $mouvement = $mouvementPourDonne($numeroDonne);
            $g = $groupes[$mouvement];

            // Lire les points bruts par numéro d'équipe
            $ptsNS_eq = [];
            $ptsEO_eq = [];
            foreach ($lignes as $ligne) {
                $ptsNS_eq[(int)$ligne['equipeNS']] = (int)($ligne['pointsNS'] ?? 0);
                $ptsEO_eq[(int)$ligne['equipeEO']] = (int)($ligne['pointsEO'] ?? 0);
            }

            // recap groupe 1 = ptsNS(nsEq1 joue NS à T1) + ptsEO(eoEq1 joue EO à T2)
            $recapEq1 = ($ptsNS_eq[$g['nsEq1']] ?? 0) + ($ptsEO_eq[$g['eoEq1']] ?? 0);
            // recap groupe 2 = ptsNS(nsEq2 joue NS à T2) + ptsEO(eoEq2 joue EO à T1)
            $recapEq2 = ($ptsNS_eq[$g['nsEq2']] ?? 0) + ($ptsEO_eq[$g['eoEq2']] ?? 0);

            $diff = $recapEq1 - $recapEq2;
            if ($diff > 0)      { $ebl1 = $bareme($diff);  $ebl2 = 0; }
            elseif ($diff < 0)  { $ebl1 = 0; $ebl2 = $bareme(-$diff); }
            else                { $ebl1 = 0; $ebl2 = 0; }

            // Attribution aux équipes des deux groupes
            $eg = $equipesGroupe[$mouvement];
            foreach ($eg['g1'] as $eq) { $totalEblParEquipe[$eq] += $ebl1; }
            foreach ($eg['g2'] as $eq) { $totalEblParEquipe[$eq] += $ebl2; }

            // Stockage pour mise à jour en base
            // Convention : T1 → ptsNS=ebl1, ptsEO=ebl2 | T2 → ptsNS=ebl2, ptsEO=ebl1
            foreach ($lignes as $ligne) {
                $table = (int)$ligne['numero_table'];
                if ($table === 1) {
                    $majDonnes[] = ['numero_donne' => $numeroDonne, 'numero_table' => 1, 'ptsNS' => $ebl1, 'ptsEO' => $ebl2];
                } else {
                    $majDonnes[] = ['numero_donne' => $numeroDonne, 'numero_table' => 2, 'ptsNS' => $ebl2, 'ptsEO' => $ebl1];
                }
            }
        }

        // ── Classement trié par EBL décroissant ───────────────────────────────
        arsort($totalEblParEquipe);
        $classement = [];
        $precedent  = null;
        foreach ($totalEblParEquipe as $equipe => $total) {
            if ($total !== $precedent) $rang = count($classement) + 1;
            $classement[] = ['numeroEquipe' => $equipe, 'totalPts' => $total, 'rang' => $rang];
            $precedent = $total;
        }

        return ['classement' => $classement, 'majDonnes' => $majDonnes];
    }
}
?>
