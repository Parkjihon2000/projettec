<?php
// Démarre la session si elle n’est pas encore démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifie si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Étudiant</title>
    <link rel="stylesheet" href="styles.css"> <!-- Ajoute un fichier CSS si nécessaire -->
</head>
<body>
    <header>
        <nav>
            <ul style="list-style:none; display:flex; gap:20px;">
                <li><a href="submit_project.php">Soumettre un Projet</a></li>
                <li><a href="upload_deliverable.php">Livrables</a></li>
                <li><a href="my_projects_Historique.php">Historique</a></li>
                <li><a href="edit_project.php">Modifier un Projet</a></li>
                <li><a href="logout.php">Se Déconnecter</a></li>
            </ul>
        </nav>
    </header>
    <hr>
