<?php
include('includes/db.php'); // $pdo doit être défini ici
include('includes/header.php');

// Vérification et sécurisation de l'ID du projet
$projectId = $_GET['project_id'] ?? null;
if (!$projectId || !is_numeric($projectId)) {
    echo "<p style='color:red;'>ID de projet invalide ou manquant.</p>";
    include('includes/footer.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM project_remarks WHERE project_id = :id ORDER BY created_at DESC");
    $stmt->execute([':id' => $projectId]);
    $remarques = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Erreur lors de la récupération des remarques : " . htmlspecialchars($e->getMessage()) . "</p>";
    include('includes/footer.php');
    exit;
}
?>

<h1>Remarques pour le projet</h1>

<?php if (empty($remarques)): ?>
    <p>Aucune remarque pour ce projet.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Remarque</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($remarques as $remarque): ?>
            <tr>
                <td><?= htmlspecialchars($remarque['remarque']); ?></td>
                <td><?= htmlspecialchars($remarque['created_at']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include('includes/footer.php'); ?>
