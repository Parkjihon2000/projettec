<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    die("Vous devez être connecté pour voir vos projets.");
}

$user_id = $_SESSION['user_id'];

$sql = "
    SELECT p.project_id, p.title, p.description, pt.type_name, p.status, p.created_at
    FROM projects p
    INNER JOIN project_members pm ON p.project_id = pm.project_id
    INNER JOIN project_types pt ON p.project_type_id = pt.type_id
    WHERE pm.user_id = ?
    ORDER BY p.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Projets - Historique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-primary">Historique de mes projets</h2>
            <a href="chat.php" class="btn btn-outline-secondary">⬅ Retour au tableau de bord</a>
        </div>

        <?php if (count($projects) > 0): ?>
            <div class="table-responsive shadow-sm rounded bg-white">
                <table class="table table-striped table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Titre</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?= htmlspecialchars($project['title']) ?></td>
                                <td><?= htmlspecialchars($project['description']) ?></td>
                                <td><?= htmlspecialchars($project['type_name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $project['status'] === 'Terminé' ? 'success' : 'warning' ?>">
                                        <?= htmlspecialchars($project['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($project['created_at'])) ?></td>
                                <td>
                                    <a href="edit_project.php?project_id=<?= $project['project_id'] ?>" class="btn btn-sm btn-primary">
                                        Modifier
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                Vous n'avez encore aucun projet enregistré.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
