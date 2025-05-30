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
        SELECT c.certificate_number, c.issue_date, p.title AS project_title, 
               CONCAT(u.first_name, ' ', u.last_name) AS verifier_name
        FROM certificates c
        JOIN projects p ON c.project_id = p.project_id
        JOIN users u ON c.verified_by = u.user_id
        WHERE c.user_id = ?
        ORDER BY c.issue_date DESC
    ");
    $stmt->execute([$user_id]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>🎓 Mes certificats</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="text-primary mb-4">🎓 Mes Certificats de Projets</h2>

    <?php if (count($certificates) === 0): ?>
        <div class="alert alert-info">Aucun certificat disponible pour le moment.</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php foreach ($certificates as $cert): ?>
                <div class="col">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-body">
                            <h5 class="card-title text-success">Projet : <?= htmlspecialchars($cert['project_title']) ?></h5>
                            <p class="card-text">
                                <strong>📄 Numéro :</strong> <?= htmlspecialchars($cert['certificate_number']) ?><br>
                                <strong>📅 Émis le :</strong> <?= date('d/m/Y', strtotime($cert['issue_date'])) ?><br>
                                <strong>✅ Validé par :</strong> <?= htmlspecialchars($cert['verifier_name']) ?>
                            </p>
                        </div>
                        <div class="card-footer text-end bg-white border-0">
                            <a href="#" class="btn btn-outline-primary btn-sm disabled">📥 Télécharger</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
