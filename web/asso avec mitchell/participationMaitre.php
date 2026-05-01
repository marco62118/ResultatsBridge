<?php
require_once 'DatabaseManager.php';

// 🔹 Récupérer le tournoi ouvert
$tournoi = DatabaseManager::getTournoiOuvert();
if (!$tournoi) {
    echo "<p>❌ Aucun tournoi ouvert. Veuillez créer un tournoi d'abord.</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['jouer'])) {
        // 🔸 Le maître veut jouer → redirection avec paramètre
        header("Location: constitutionEquipes.php?maitre_joue=1");
        exit;
    } elseif (isset($_POST['organiser'])) {
        // 🔸 Le maître reste organisateur
        header("Location: constitutionEquipes.php?maitre_joue=0");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Participation du Maître</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}
.container {
    background: white;
    padding: 40px;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    text-align: center;
    max-width: 500px;
}
h1 {
    color: #1565c0;
    margin-bottom: 10px;
}
p {
    font-size: 18px;
    margin-bottom: 20px;
}
button {
    background-color: #1976d2;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 12px 24px;
    margin: 10px;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.3s ease;
}
button:hover { background-color: #0d47a1; }
.no {
    background-color: #e53935;
}
.no:hover {
    background-color: #b71c1c;
}
</style>
</head>
<body>

<div class="container">
    <h1>🎉 Tournoi ouvert !</h1>
    <p>Souhaitez-vous participer en tant que joueur ?</p>

    <form method="post">
        <button type="submit" name="jouer">✅ Oui, je veux jouer</button>
        <button type="submit" name="organiser" class="no">🚫 Non, rester organisateur</button>
    </form>
</div>

</body>
</html>
