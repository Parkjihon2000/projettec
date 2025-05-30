
<?php
session_start();
include('includes/db.php');

$project_id = $_GET['project_id'] ?? null;
if ($project_id && is_numeric($project_id)) {
    $stmt = $pdo->prepare("UPDATE projects SET status = 'validated' WHERE project_id = :project_id");
    $stmt->execute([':project_id' => $project_id]);
}

header("Location: index.php?message=rejected");
exit;
