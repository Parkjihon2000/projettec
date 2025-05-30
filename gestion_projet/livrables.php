<?php
include('includes/db.php');      // Doit définir l'objet $pdo
include('includes/header.php');

$projectId = $_GET['project_id'] ?? null;

if ($projectId === null || !is_numeric($projectId)) {
    echo "<p style='color:red;'>ID de projet invalide ou manquant.</p>";
    include('includes/footer.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM deliverables WHERE project_id = :project_id");
    $stmt->execute([':project_id' => $projectId]);
    $deliverables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Erreur lors de la récupération des livrables : " . htmlspecialchars($e->getMessage()) . "</p>";
    include('includes/footer.php');
    exit;
}
?>

<h1>Livrables du projet</h1>

<?php if (count($deliverables) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Titre</th>
                <th>Fichier</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deliverables as $livrable): ?>
                <tr>
                    <td><?= htmlspecialchars($livrable['title']); ?></td>
                    <td>
                        <a href="download.php?file=<?= urlencode($livrable['file_path']); ?>" target="_blank">Télécharger</a>
                    </td>
                    <td><?= htmlspecialchars($livrable['status']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Aucun livrable trouvé pour ce projet.</p>
<?php endif; ?>

<?php include('includes/footer.php'); ?>
