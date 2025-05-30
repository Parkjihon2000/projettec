<?php
include('../includes/db.php'); // Ce fichier doit créer l'objet $pdo

header('Content-Type: application/json');

$project_id = $_GET['project_id'] ?? null;

if ($project_id !== null && is_numeric($project_id)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM deliverables WHERE project_id = :project_id");
        $stmt->execute([':project_id' => $project_id]);

        $livrables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($livrables);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Paramètre project_id manquant ou invalide.']);
}
?>

