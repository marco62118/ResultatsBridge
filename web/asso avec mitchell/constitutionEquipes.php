<?php
require_once 'DatabaseManager.php';
require_once 'fonctions.php'; 

$tournoi = DatabaseManager::getTournoiOuvert();
if (!$tournoi) {
    header("Location: index.php");
    exit;
}

$idTournoi   = $tournoi['ID'];
$typeTournoi = $tournoi['type'];
$nbreEquipes = intval($tournoi['nbre_equipe']); 
$joueurs = DatabaseManager::getTousLesJoueurs();

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrerEquipes'])) {
    $equipesData = [];
    for ($i = 1; $i <= $nbreEquipes; $i++) {
        $idJ1 = intval($_POST["equipe{$i}_joueur1"] ?? 0);
        $idJ2 = intval($_POST["equipe{$i}_joueur2"] ?? 0);
        if ($idJ1 > 0 && $idJ2 > 0) {
            $equipesData[] = ['equipeNumero' => $i, 'joueur1_id' => $idJ1, 'joueur2_id' => $idJ2];
        }
    }
if (count($equipesData) === $nbreEquipes) {
    if (DatabaseManager::enregistrerEquipes($idTournoi, $equipesData)) {
        header("Location: participationMaitre.php?idTournoi=$idTournoi");
        exit;
    }
} else {
    $message = "❌ Veuillez sélectionner tous les joueurs.";
}
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Constitution des Équipes</title>
    <style>
        /* --- LE STYLE QUI MANQUAIT --- */
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 850px; margin: auto; }
        h1 { color: #0066cc; text-align: center; }
        .info { text-align: center; margin-bottom: 20px; font-weight: bold; padding: 10px; background: #eef; border-radius: 5px; }
        
        .equipes-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .equipe-card { border: 1px solid #ccc; padding: 15px; border-radius: 5px; background: #fafafa; }
        
        .select-row { display: flex; gap: 10px; margin-top: 10px; }
        select { flex: 1; padding: 10px; border: 1px solid #999; border-radius: 4px; }
        
        /* C'est cette règle qui force la disparition visuelle des joueurs déjà pris */
        option[hidden] { display: none !important; }

        .btn { width: 100%; padding: 15px; background: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; margin-top: 20px; font-weight: bold; }
        .btn:hover { background: #218838; }
        .error { color: red; text-align: center; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h1>Constitution des Équipes</h1>
    
    <div class="info">
        Tournoi #<?= $idTournoi ?> | Format: <?= htmlspecialchars($typeTournoi) ?> | <?= $nbreEquipes ?> Équipes
    </div>

    <?php if ($message) echo "<p class='error'>$message</p>"; ?>

    <form method="post">
        <div class="equipes-grid">
            <?php for ($i = 1; $i <= $nbreEquipes; $i++): ?>
                <div class="equipe-card">
                    <strong>ÉQUIPE <?= $i ?></strong>
                    <div class="select-row">
                        <select name="equipe<?= $i ?>_joueur1" class="jSelect" required>
                            <option value="">-- Joueur 1 --</option>
                            <?php foreach ($joueurs as $j): ?>
                                <option value="<?= $j['ID'] ?>"><?= htmlspecialchars($j['prenom'] . ' ' . $j['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="equipe<?= $i ?>_joueur2" class="jSelect" required>
                            <option value="">-- Joueur 2 --</option>
                            <?php foreach ($joueurs as $j): ?>
                                <option value="<?= $j['ID'] ?>"><?= htmlspecialchars($j['prenom'] . ' ' . $j['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <button type="submit" name="enregistrerEquipes" class="btn">💾 Enregistrer et Continuer</button>
    </form>
</div>

<script>
    const selects = document.querySelectorAll('.jSelect');

    function filtrerJoueurs() {
        // Liste de tous les joueurs sélectionnés dans TOUTES les listes
        const choisis = Array.from(selects).map(s => s.value).filter(v => v !== "");

        selects.forEach(s => {
            const maValeur = s.value;

            Array.from(s.options).forEach(opt => {
                if (opt.value === "") return; // Toujours laisser l'option vide

                // Si le joueur est pris ailleurs, on le cache ET on le désactive
                if (choisis.includes(opt.value) && opt.value !== maValeur) {
                    opt.hidden = true;
                    opt.disabled = true;
                } else {
                    opt.hidden = false;
                    opt.disabled = false;
                }
            });
        });
    }

    selects.forEach(s => s.addEventListener('change', filtrerJoueurs));
</script>

</body>
</html>