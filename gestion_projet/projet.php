<?php
include('includes/db.php');

$year_filter = $_POST['year'] ?? '';
$semester_filter = $_POST['semester'] ?? '';
$module_filter = $_POST['module'] ?? '';

$query = "
    SELECT p.*, 
           u.first_name, u.last_name, 
           y.year_label, 
           m.module_name
    FROM projects p
    LEFT JOIN users u ON p.student_id = u.user_id
    LEFT JOIN academic_years y ON p.academic_year_id = y.year_id
    LEFT JOIN modules m ON p.module_id = m.module_id
    WHERE 1=1
";

$params = [];

if ($year_filter) {
    $query .= " AND p.academic_year_id = :year";
    $params[':year'] = $year_filter;
}
if ($semester_filter) {
    $query .= " AND p.semester = :semester";
    $params[':semester'] = $semester_filter;
}
if ($module_filter) {
    $query .= " AND p.module_id = :module";
    $params[':module'] = $module_filter;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT * FROM academic_years")->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Liste des Projets</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>

<div class="container-fluid">
  <div class="row" style="height: 100vh;">
    <!-- Sidebar -->
    <div class="col-2 col-sm-3 col-xl-2 bg-dark">
  <div class="d-flex flex-column vh-100">
    <h4 class="text-white text-center py-3 border-bottom">Enseignant</h4>
    <nav class="nav flex-column px-2">
      <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'fw-bold' : '' ?>" href="index.php">
        <i class="bi bi-speedometer2 me-2"></i>Tableau de bord
      </a>
      <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'projet.php' ? 'fw-bold' : '' ?>" href="projet.php">
        <i class="bi bi-folder2-open me-2"></i>Projets
      </a>
      <a class="nav-link text-white" href="logout.php">
        <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
      </a>
    </nav>
  </div>
</div>

    <!-- Main content -->
    <div class="col-10 col-sm-9 col-xl-10 p-0 m-0">
      <nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
        <div class="container-fluid">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item">
              <a class="nav-link" href="#"><i class="bi bi-arrow-bar-right me-2"></i>Logout</a>
            </li>
          </ul>
        </div>
      </nav>

      <div class="px-4">
        <?php if (isset($_GET['message']) && $_GET['message'] === 'success'): ?>
        <div id="successMessage" class="alert alert-success">
          ✅ Évaluation enregistrée avec succès.
        </div>
        <script>
          setTimeout(() => {
            const msg = document.getElementById("successMessage");
            if (msg) msg.style.display = "none";
          }, 4000);
        </script>
        <?php endif; ?>

        <h1 class="mb-4">Liste des Projets</h1>

        <form method="POST" action="projet.php" class="row g-3 mb-4">
          <div class="col-md-3">
            <label for="year" class="form-label">Année académique</label>
            <select name="year" id="year" class="form-select">
              <option value="">Toutes les années</option>
              <?php foreach ($years as $year): ?>
              <option value="<?= $year['year_id']; ?>" <?= ($year_filter == $year['year_id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($year['year_label']); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label for="semester" class="form-label">Semestre</label>
            <select name="semester" id="semester" class="form-select">
              <option value="">Tous les semestres</option>
              <option value="Semestre 1" <?= ($semester_filter == 'Semestre 1') ? 'selected' : ''; ?>>Semestre 1</option>
              <option value="Semestre 2" <?= ($semester_filter == 'Semestre 2') ? 'selected' : ''; ?>>Semestre 2</option>
            </select>
          </div>

          <div class="col-md-3">
            <label for="module" class="form-label">Module</label>
            <select name="module" id="module" class="form-select">
              <option value="">Tous les modules</option>
              <?php foreach ($modules as $module): ?>
              <option value="<?= $module['module_id']; ?>" <?= ($module_filter == $module['module_id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($module['module_name']); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filtrer</button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead class="table-dark">
              <tr>
                <th>Titre</th>
                <th>Étudiant</th>
                <th>Année</th>
                <th>Semestre</th>
                <th>Module</th>
                <th>Statut</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($projects as $project): ?>
              <tr>
                <td><?= htmlspecialchars($project['title']); ?></td>
                <td><?= htmlspecialchars($project['first_name'] . ' ' . $project['last_name']); ?></td>
                <td><?= htmlspecialchars($project['year_label'] ?? 'Non défini'); ?></td>
                <td><?= htmlspecialchars($project['semester'] ?? '-'); ?></td>
                <td><?= htmlspecialchars($project['module_name'] ?? 'Non défini'); ?></td>
                <td><?= htmlspecialchars($project['status']); ?></td>
                <td>
                  <a href="evaluation.php?project_id=<?= $project['project_id']; ?>" class="btn btn-outline-info btn-sm">Évaluer</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
