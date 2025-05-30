<?php
session_start();  // Démarre la session si ce n'est pas déjà fait

// Vérifie que l'utilisateur est bien connecté
if (!isset($_SESSION['user_id'])) {
    echo "Erreur : utilisateur non connecté.";
    exit;  // Arrête le script si l'utilisateur n'est pas connecté
}

$evaluator_id = $_SESSION['user_id'];  // Récupère l'ID de l'utilisateur

include('../includes/db.php');  // Assure-toi que ce chemin est correct

// Vérifier que la méthode de requête est bien POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les valeurs du formulaire
    $project_id = $_POST['project_id'] ?? null;
    $comment = $_POST['comment'] ?? null;
    $grade = $_POST['grade'] ?? null;

    // Validation de base
    if (!$project_id || !is_numeric($grade) || $grade < 0 || $grade > 20) {
        echo "Erreur : données invalides.";
        exit;
    }

    // Échapper les caractères spéciaux dans le commentaire
    $comment = htmlspecialchars($comment);

    try {
        // Vérifier si une évaluation existe déjà
        $checkStmt = $pdo->prepare("SELECT * FROM evaluations WHERE project_id = :project_id");
        $checkStmt->execute([':project_id' => $project_id]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Mettre à jour l'évaluation existante
            $stmt = $pdo->prepare("
                UPDATE evaluations 
                SET comment = :comment, grade = :grade, evaluator_id = :evaluator_id 
                WHERE project_id = :project_id
            ");
        } else {
            // Insérer une nouvelle évaluation
            $stmt = $pdo->prepare("
                INSERT INTO evaluations (project_id, comment, grade, evaluator_id) 
                VALUES (:project_id, :comment, :grade, :evaluator_id)
            ");
        }

        // Exécuter la requête
        $stmt->execute([
            ':project_id' => $project_id,
            ':comment' => $comment,
            ':grade' => $grade,
            ':evaluator_id' => $evaluator_id
        ]);

        // Redirection après enregistrement réussi
        header("Location: ../projet.php?project_id=$project_id&message=success");
        exit;  // Important pour stopper l'exécution du script

    } catch (PDOException $e) {
        echo "Erreur lors de l'enregistrement : " . htmlspecialchars($e->getMessage());
    }
} else {
    echo "Requête non autorisée.";
}
?>
