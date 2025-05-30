<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Infos utilisateur
$stmt = $pdo->prepare("SELECT first_name, department, year_of_study FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo "Utilisateur non trouvé."; exit();
}

// Projets
$stmt = $pdo->prepare("
    SELECT p.title, p.status
    FROM projects p
    JOIN project_members pm ON p.project_id = pm.project_id
    WHERE pm.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$user_id]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Livrables
$stmt = $pdo->prepare("
    SELECT d.title, d.upload_date
    FROM deliverables d
    JOIN projects p ON d.project_id = p.project_id
    JOIN project_members pm ON pm.project_id = p.project_id
    WHERE pm.user_id = ?
    ORDER BY d.upload_date DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$deliverables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Notifications
$notifStmt = $pdo->prepare("SELECT title, message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$notifStmt->execute([$user_id]);
$notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

// Certificats
$certStmt = $pdo->prepare("
    SELECT c.certificate_number, c.issue_date, p.title AS project_title
    FROM certificates c
    JOIN projects p ON c.project_id = p.project_id
    WHERE c.user_id = ?
    ORDER BY c.issue_date DESC
    LIMIT 5
");
$certStmt->execute([$user_id]);
$certificates = $certStmt->fetchAll(PDO::FETCH_ASSOC);

// Feedbacks
$feedbackStmt = $pdo->prepare("
    SELECT e.comment, e.grade, e.evaluation_date, p.title AS project_title
    FROM evaluations e
    JOIN projects p ON e.project_id = p.project_id
    JOIN project_members pm ON pm.project_id = p.project_id
    WHERE pm.user_id = ?
    ORDER BY e.evaluation_date DESC
    LIMIT 5
");
$feedbackStmt->execute([$user_id]);
$recentFeedbacks = $feedbackStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Tableau de Bord Étudiant</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <style>
    .nav-link.active { background-color: #0d6efd !important; color: white !important; }
  </style>
</head>
<body>
<div class="container-fluid">
  <div class="row" style="height: 100vh">
    <!-- Sidebar -->
    <div class="col-2 col-sm-3 col-xl-2 bg-dark">
      <div class="sticky-top">
        <nav class="navbar bg-dark border-bottom border-white" data-bs-theme="dark">
          <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="bi bi-house-door"></i><span class="d-none d-sm-inline ms-2">ENSA Projets</span></a>
          </div>
        </nav>
        <nav class="nav flex-column">
        <a class="nav-link text-white" href="#" data-section="dashboard"><i class="bi bi-speedometer2"></i><span class="d-none d-sm-inline ms-2">Tableau du bord</span></a>
<a class="nav-link text-white" href="login.php" data-section="login"><i class="bi bi-person-lock"></i><span class="d-none d-sm-inline ms-2">Login</span></a>
<a class="nav-link text-white" href="submit_project.php" data-section="submit"><i class="bi bi-folder-plus"></i><span class="d-none d-sm-inline ms-2">Submit Projet</span></a>
<a class="nav-link text-white" href="upload_deliverable.php" data-section="upload"><i class="bi bi-cloud-upload"></i><span class="d-none d-sm-inline ms-2">Upload Deliverable</span></a>
<a class="nav-link text-white" href="edit_project.php" data-section="edit"><i class="bi bi-pencil-square"></i><span class="d-none d-sm-inline ms-2">Edit Projet</span></a>
<a class="nav-link text-white" href="my_projects_Historique.php" data-section="historique"><i class="bi bi-clock-history"></i><span class="d-none d-sm-inline ms-2">Historique</span></a>
<a class="nav-link text-white" href="view_notifications.php" data-section="notif"><i class="bi bi-bell"></i><span class="d-none d-sm-inline ms-2">Notifications</span></a>
<a class="nav-link text-white" href="view_certificates.php" data-section="certif"><i class="bi bi-award"></i><span class="d-none d-sm-inline ms-2">Certificates</span></a>
<a class="nav-link text-white" href="view_feedback.php" data-section="feedback"><i class="bi bi-chat-left-text"></i><span class="d-none d-sm-inline ms-2">Feedback</span></a>

        </nav>
      </div>
    </div>

    <!-- Main content -->
    <div class="col-10 col-sm-9 col-xl-10 p-4">
      <div class="d-flex justify-content-between mb-4">
        <h2>Bienvenue sur votre Tableau de Bord</h2>
        <a href="logout.php" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>

      <div class="row g-4">
        <!-- Infos générales -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-secondary text-white">Informations générales</div>
            <div class="card-body">
              <p><strong>Nom :</strong> <?= htmlspecialchars($user['first_name']) ?></p>
              <p><strong>Département :</strong> <?= htmlspecialchars($user['department']) ?></p>
              <p><strong>Année :</strong> <?= htmlspecialchars($user['year_of_study']) ?></p>
            </div>
          </div>
        </div>

        <!-- Projets -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-warning text-dark">Mes projets</div>
            <div class="card-body">
              <?php if (empty($projects)): ?>
                <p class="text-muted">Aucun projet en cours.</p>
              <?php else: ?>
                <ul class="list-unstyled mb-0">
                  <?php foreach ($projects as $p): ?>
                    <li class="mb-2"><strong><?= htmlspecialchars($p['title']) ?></strong><br><em><?= htmlspecialchars($p['status']) ?></em></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Livrables -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-dark text-white">Livrables récents</div>
            <div class="card-body">
              <?php if (empty($deliverables)): ?>
                <p class="text-muted">Aucun livrable récent.</p>
              <?php else: ?>
                <ul class="list-unstyled mb-0">
                  <?php foreach ($deliverables as $d): ?>
                    <li class="mb-2">
                      <strong><?= htmlspecialchars($d['title']) ?></strong><br>
                      <small class="text-muted">Déposé le <?= date('d/m/Y', strtotime($d['upload_date'])) ?></small>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Notifications -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-primary text-white">Notifications</div>
            <div class="card-body">
              <?php if (empty($notifications)): ?>
                <p class="text-muted">Aucune notification.</p>
              <?php else: ?>
                <ul class="list-unstyled mb-0">
                  <?php foreach ($notifications as $n): ?>
                    <li class="mb-2">
                      <strong><?= htmlspecialchars($n['title']) ?></strong><br>
                      <?= htmlspecialchars($n['message']) ?><br>
                      <small class="text-muted"><?= $n['created_at'] ?></small>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Certificats -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-success text-white">Mes certificats</div>
            <div class="card-body">
              <?php if (empty($certificates)): ?>
                <p class="text-muted">Aucun certificat disponible.</p>
              <?php else: ?>
                <ul class="list-unstyled mb-0">
                  <?php foreach ($certificates as $c): ?>
                    <li class="mb-2">
                      <strong><?= htmlspecialchars($c['project_title']) ?></strong><br>
                      Certificat #<?= htmlspecialchars($c['certificate_number']) ?><br>
                      <small class="text-muted">Délivré le <?= $c['issue_date'] ?></small>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Feedbacks -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-info text-white">Mes feedbacks</div>
            <div class="card-body">
              <?php if (empty($recentFeedbacks)): ?>
                <p class="text-muted">Aucun feedback pour l’instant.</p>
              <?php else: ?>
                <ul class="list-unstyled mb-0">
                  <?php foreach ($recentFeedbacks as $fb): ?>
                    <li class="mb-3">
                      <strong><?= htmlspecialchars($fb['project_title']) ?></strong><br>
                      Note : <?= is_null($fb['grade']) ? 'Non noté' : $fb['grade'].'/20' ?><br>
                      <em><?= nl2br(htmlspecialchars($fb['comment'])) ?></em><br>
                      <small class="text-muted"><?= $fb['evaluation_date'] ?></small>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const navLinks = document.querySelectorAll('.nav-link');
  function activateLink(section) {
    navLinks.forEach(link => {
      link.classList.toggle('active', link.dataset.section === section);
    });
  }
  navLinks.forEach(link => {
    link.addEventListener('click', function () {
      const section = this.dataset.section;
      localStorage.setItem('activeSection', section);
      activateLink(section);
    });
  });
  window.addEventListener('DOMContentLoaded', () => {
    const activeSection = localStorage.getItem('activeSection');
    if (activeSection) activateLink(activeSection);
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
