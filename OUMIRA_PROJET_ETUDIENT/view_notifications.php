<?php
session_start();
require 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>📬 Mes Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4 text-primary">📬 Mes Notifications</h2>

    <?php if (count($notifications) === 0): ?>
        <div class="alert alert-info">Aucune notification pour le moment.</div>
    <?php else: ?>
        <div class="list-group shadow-sm">
            <?php foreach ($notifications as $notif): ?>
                <div class="list-group-item list-group-item-action <?= !$notif['is_read'] ? 'list-group-item-warning' : '' ?>">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1"><?= htmlspecialchars($notif['title']) ?></h5>
                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?></small>
                    </div>
                    <p class="mb-1"><?= nl2br(htmlspecialchars($notif['message'])) ?></p>
                    <?php if (!$notif['is_read']): ?>
                        <span class="badge bg-danger">Non lu</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Lu</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>

