<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include('includes/db.php');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tableau de bord - Enseignant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 300px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row" style="height: 100vh;">
        <div class="col-2 col-sm-3 col-xl-2 bg-dark">
            <div class="sticky-top">
                <nav class="navbar bg-dark border-bottom border-white mb-3" data-bs-theme="dark">
                    <div class="container-fluid">
                        <a class="navbar-brand text-white" href="#">
                            <i class="bi bi-house-door"></i><span class="d-none d-sm-inline ms-2">Accueil</span>
                        </a>
                    </div>
                </nav>
                <nav class="nav flex-column">
                    <a class="nav-link text-white" href="index.php"><i class="bi bi-speedometer2"></i><span class="d-none d-sm-inline ms-2">Tableau de bord</span></a>
                    <a class="nav-link text-white" href="projet.php"><i class="bi bi-journal-bookmark-fill"></i><span class="d-none d-sm-inline ms-2">Projets</span></a>
                    <a class="nav-link text-white" href="logout.php"><i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-2">Déconnexion</span></a>
                </nav>
            </div>
        </div>

        <div class="col-10 col-sm-9 col-xl-10 p-4">
            <h1>Liste des projets</h1>

            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Étudiant</th>
                        <th>Projet</th>
                        <th>Année académique</th>
                        <th>Module</th>
                        <th>Statut</th>
                        <th>Livrable</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $query = "
                SELECT 
                    p.project_id,
                    p.title AS project_title,
                    p.status,
                    p.semester,
                    u.first_name,
                    u.last_name,
                    m.module_name,
                    d.file_path,
                    ay.year_label
                FROM projects p
                LEFT JOIN users u ON p.student_id = u.user_id
                LEFT JOIN modules m ON p.module_id = m.module_id
                LEFT JOIN deliverables d ON d.project_id = p.project_id
                LEFT JOIN academic_years ay ON p.academic_year_id = ay.year_id
                ORDER BY p.created_at DESC";

                $stmt = $pdo->query($query);
                $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <?php if (count($projects) > 0): ?>
                    <?php foreach ($projects as $proj): ?>
                        <tr>
                            <td><?= htmlspecialchars($proj['first_name'] . ' ' . $proj['last_name']) ?></td>
                            <td><?= htmlspecialchars($proj['project_title']) ?></td>
                            <td><?= htmlspecialchars($proj['year_label']) ?></td>
                            <td><?= htmlspecialchars($proj['module_name']) ?></td>
                            <td><?= htmlspecialchars($proj['status']) ?></td>
                            <td>
                                <?php if (!empty($proj['file_path'])): ?>
                                    <a href="<?= htmlspecialchars($proj['file_path']) ?>" target="_blank">Télécharger</a>
                                <?php else: ?>
                                    Aucun fichier
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="details_projet.php?project_id=<?= $proj['project_id'] ?>" class="btn btn-sm btn-primary">Détails</a>
                                <a href="evaluation.php?project_id=<?= $proj['project_id'] ?>" class="btn btn-sm btn-success">Evaluer</a>
                                <button class="btn btn-sm btn-warning" onclick="openModal(<?= $proj['project_id'] ?>)">Valider</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">Aucun projet trouvé.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Modal de confirmation -->
            <div id="confirmationModal" class="modal">
                <div class="modal-content">
                    <p>Que souhaitez-vous faire avec ce projet ?</p>
                    <form id="validationForm" method="get">
                        <input type="hidden" name="project_id" id="modalProjectId">
                        <button type="submit" formaction="valider_projet.php" class="btn btn-success mb-2">Valider</button>
                        <button type="submit" formaction="refuser_projet.php" class="btn btn-danger mb-2">Refuser</button>
                    </form>
                    <button onclick="closeModal()" class="btn btn-secondary">Annuler</button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function openModal(projectId) {
    document.getElementById('modalProjectId').value = projectId;
    document.getElementById('confirmationModal').style.display = 'block';
}
function closeModal() {
    document.getElementById('confirmationModal').style.display = 'none';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
