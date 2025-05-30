<?php
include('includes/db.php');


// Récupération de l'ID du projet via GET ou POST
$projectId = $_POST['project_id'] ?? $_GET['project_id'] ?? null;

if ($projectId === null || !is_numeric($projectId)) {
    echo "<div class='alert alert-danger m-3'>ID de projet invalide ou manquant.</div>";
    include('includes/footer.php');
    exit;
}

// Récupérer l'évaluation du projet
try {
    $stmt = $pdo->prepare("SELECT * FROM evaluations WHERE project_id = :id");
    $stmt->execute([':id' => $projectId]);
    $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evaluation) {
        $evaluation = [
            'comment' => '',
            'grade' => ''
        ];
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger m-3'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    include('includes/footer.php');
    exit;
}
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Évaluation du Projet</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary mb-4 border-bottom">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="#"><i class="bi bi-clipboard-check"></i> Évaluation</a>
    <ul class="navbar-nav ms-auto">
      <li class="nav-item">
        <a class="nav-link" href="index.php"><i class="bi bi-arrow-bar-right me-2"></i>Déconnexion</a>
      </li>
    </ul>
  </div>
</nav>

<div class="container">
  <h2 class="mb-4">Évaluer un projet</h2>

  <form action="api/update_evaluation.php" method="post" class="bg-light p-4 rounded shadow-sm">
    <input type="hidden" name="project_id" value="<?= (int)$projectId; ?>">

    <div class="mb-3">
      <label for="comment" class="form-label">Remarques</label>
      <textarea name="comment" id="comment" rows="4" class="form-control"><?= htmlspecialchars($evaluation['comment']); ?></textarea>
    </div>

    <div class="mb-3">
      <label for="grade" class="form-label">Note (sur 20)</label>
      <input type="number" name="grade" id="grade" class="form-control" min="0" max="20" step="0.1" required value="<?= htmlspecialchars($evaluation['grade']); ?>">
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Enregistrer l'évaluation</button>
    <a href="projet.php" class="btn btn-secondary ms-2">Retour</a>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


