<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT e.comment, e.grade, e.evaluation_date, p.title AS project_title,
               CONCAT(u.first_name, ' ', u.last_name) AS evaluator_name
        FROM evaluations e
        JOIN projects p ON e.project_id = p.project_id
        JOIN project_members pm ON pm.project_id = p.project_id
        JOIN users u ON e.evaluator_id = u.user_id
        WHERE pm.user_id = ?
        ORDER BY e.evaluation_date DESC
    ");
    $stmt->execute([$user_id]);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>📋 Mes Feedbacks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4 text-primary">📋 Commentaires & Évaluations</h2>

    <?php if (count($feedbacks) === 0): ?>
        <div class="alert alert-info">Aucun feedback reçu pour l’instant.</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php foreach ($feedbacks as $fb): ?>
                <div class="col">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-body">
                            <h5 class="card-title text-success"><?= htmlspecialchars($fb['project_title']) ?></h5>
                            <p class="card-text">
                                <strong>🎓 Note :</strong> 
                                <?= is_null($fb['grade']) ? '<span class="text-muted">Non noté</span>' : $fb['grade'] . '/20' ?><br>
                                <strong>💬 Commentaire :</strong><br>
                                <span class="text-dark"><?= nl2br(htmlspecialchars($fb['comment'])) ?></span><br>
                                <strong>👤 Évalué par :</strong> <?= htmlspecialchars($fb['evaluator_name']) ?><br>
                                <strong>📅 Date :</strong> <?= date('d/m/Y', strtotime($fb['evaluation_date'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
