
<?php  
require_once 'fonctions.php';
require_once 'DatabaseManager.php';

function getLocalIP() {  
    $ip = getHostByName(getHostName());  
    return $ip ?: 'Inconnue';  
}  

$tournoi = DatabaseManager::getTournoiOuvert();  
$ipLocale = getLocalIP();  

// ✅ CLIC : FERMER LE TOURNOI
if (isset($_POST['fermer'])) {  
    if (DatabaseManager::fermerTournoiOuvert()) {  
        logServer("Tournoi fermé manuellement via interface PC.");   
        header("Location: index.php");
        exit;
    }
}  
  

// ✅ CLIC : CRÉER LE TOURNOI
if (isset($_POST['creer'])) {  
    $donneesBrutes = $_POST['type_choisi']; 
    list($nomType, $tables, $donnes) = explode('|', $donneesBrutes);
    
    $nbreEquipes = intval($tables) * 2; 
    $nbreDonnes = intval($donnes);
    $nbreEnregistrement = intval($tables) * $nbreDonnes;
  
    $idTournoi = DatabaseManager::creerTournoi($nomType, $nbreEquipes, $nbreDonnes, $nbreEnregistrement);  
  
    if ($idTournoi > 0) {  
        logServer("Nouveau tournoi créé : ID $idTournoi | Type: $nomType | Equipes: $nbreEquipes");
        DatabaseManager::ouvrirTournoi($idTournoi);  
        
        // On redirige avec les paramètres nécessaires pour être sûr
        header("Location: constitutionEquipes.php?idTournoi=$idTournoi&fromCreation=1");  
        exit;  
    }
}

// Récupération dynamique des types depuis la base
$optionsTypes = DatabaseManager::getListeTypesTournoi();
?>  
  
<!DOCTYPE html>  
<html lang="fr">  
<head>  
    <meta charset="UTF-8">  
    <title>Serveur Bridge</title>  
    <style>  
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f0f2f5; text-align: center; padding-top: 40px; }  
        .container { max-width: 500px; margin: auto; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; text-align: left; }
        .ip-header { background: #1b5e20; color: white; padding: 15px; border-radius: 12px 12px 0 0; margin-bottom: 0; }
        .ip-content { background: #e8f5e9; padding: 15px; border-radius: 0 0 12px 12px; margin-top: 0; border: 1px solid #c8e6c9; }
        code { font-size: 24px; color: #2e7d32; font-weight: bold; }
        select, button { width: 100%; padding: 12px; margin-top: 10px; border-radius: 8px; border: 1px solid #ccc; font-size: 16px; }
        button { background: #2e7d32; color: white; border: none; font-weight: bold; cursor: pointer; }
        button:hover { background: #1b5e20; }
        .btn-danger { background: #c62828; }
        .btn-danger:hover { background: #b71c1c; }
        h3 { margin-top: 0; color: #333; }
        label { font-size: 14px; font-weight: bold; color: #666; }
    </style>  
</head>  
<body>  

<div class="container">
    <div class="ip-header">🌐 ADRESSE DU SERVEUR</div>
    <div class="card ip-content">
        <code><?= htmlspecialchars($ipLocale) ?></code>
    </div>

    <?php if ($tournoi): ?>
        <div class="card">
            <h3>🟢 Tournoi en cours</h3>
            <p><strong>ID :</strong> #<?= $tournoi['ID'] ?></p>
            <p><strong>Type :</strong> <?= htmlspecialchars($tournoi['type']) ?></p>
            
            <form action="participationMaitre.php" method="get">
                <input type="hidden" name="idTournoi" value="<?= $tournoi['ID'] ?>">
                <button type="submit">🎮 Participation Maître</button>
            </form>

            <form method="post" onsubmit="return confirm('Voulez-vous vraiment fermer ce tournoi ?');">
                <button type="submit" name="fermer" class="btn-danger">🧹 Fermer le tournoi</button>
            </form>
            
            <p style="text-align:center;"><a href="constitutionEquipes.php?idTournoi=<?= $tournoi['ID'] ?>">📝 Gérer les équipes</a></p>
        </div>

    <?php else: ?>
        <div class="card">
            <h3>🆕 Nouveau Tournoi</h3>
            <form method="post">
                <label for="type_choisi">Sélectionnez le format :</label>
                <select name="type_choisi" id="type_choisi" required>
                    <?php if (empty($optionsTypes)): ?>
                        <option value="">⚠️ Aucune config en base</option>
                    <?php else: ?>
                        <?php foreach ($optionsTypes as $opt): ?>
                            <?php 
                                // On prépare la valeur pour le explode : "nom|tables|donnes"
                                $val = $opt['nom']."|".$opt['tables']."|".$opt['donnes'];
                                // Affichage lisible : "Howell : 3 tables, 6 équipes, 20 donnes"
                                $label = str_replace('_', ' ', $opt['nom']) . " : " . $opt['tables'] . " tables, " . ($opt['tables']*2) . " éq, " . $opt['donnes'] . " donnes";
                            ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <button type="submit" name="creer" style="margin-top:20px;">🚀 Démarrer le tournoi</button>
            </form>
        </div>
    <?php endif; ?>
</div>

</body>  
</html>