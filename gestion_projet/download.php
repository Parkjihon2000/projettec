
<?php
if (!isset($_GET['file']) || empty($_GET['file'])) {
    die("Fichier non spécifié.");
}

// Le chemin du fichier dans la base de données
$filePath = $_GET['file'];

// Vérifier si le fichier existe dans le répertoire
$fullPath = __DIR__ . '/../uploads/livrables/' . basename($filePath);

if (!file_exists($fullPath)) {
    die("Fichier introuvable.");
}

// Définir les headers pour le téléchargement du fichier
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($fullPath));

// Lire le fichier et l'envoyer au navigateur
readfile($fullPath);
exit;
?>
