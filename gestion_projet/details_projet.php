<?php
include('includes/db.php');


// Vérification de l'ID
if (isset($_GET['project_id']) && is_numeric($_GET['project_id'])) {
    $projectId = (int) $_GET['project_id'];
} else {
    echo "<div class='alert alert-danger m-3'>ID de projet invalide ou manquant.</div>";
    include('includes/footer.php');
    exit;
}

// Requête pour récupérer les informations du projet
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE project_id = :id");
    $stmt->execute([':id' => $projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        echo "<div class='alert alert-warning m-3'>Projet non trouvé avec l'ID $projectId.</div>";
        include('includes/footer.php');
        exit;
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
  <title>Détails du Projet</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary mb-4 border-bottom">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="#"><i class="bi bi-info-circle"></i> Détails</a>
    <ul class="navbar-nav ms-auto">
      <li class="nav-item">
        <a class="nav-link" href="index.php"><i class="bi bi-arrow-bar-right me-2"></i>Déconnexion</a>
      </li>
    </ul>
  </div>
</nav>

<div class="container">
  <h2 class="mb-4">Détails du Projet</h2>

  <div class="card shadow-sm">
    <div class="card-body">
      <p><strong>Titre :</strong> <?= htmlspecialchars($project['title']) ?></p>
      <p><strong>Description :</strong> <?= nl2br(htmlspecialchars($project['description'])) ?></p>
      <p><strong>Statut :</strong> <?= htmlspecialchars($project['status']) ?></p>
      <p><strong>Semestre :</strong> <?= htmlspecialchars($project['semester']) ?></p>
    </div>
  </div>

  <div class="mt-4">
    <a href="evaluation.php?project_id=<?= $projectId ?>" class="btn btn-primary">
      <i class="bi bi-pencil-square me-1"></i> Évaluer ce projet
    </a>
    <a href="projet.php" class="btn btn-secondary ms-2">Retour</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


