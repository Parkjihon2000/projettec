<?php
session_start();
require_once 'connexion.php';

// Récupération des types et années
$types = $pdo->query("SELECT type_id, type_name FROM project_types")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT year_id, year_label FROM academic_years")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Soumettre un projet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="text-primary mb-4">Soumettre un nouveau projet</h2>

        <?php
        // Traitement du formulaire
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $project_type_id = $_POST['project_type_id'] ?? null;
            $organization_id = !empty($_POST['organization_id']) ? $_POST['organization_id'] : null;
            $module_id = !empty($_POST['module_id']) ? $_POST['module_id'] : null;
            $academic_year_id = $_POST['academic_year_id'] ?? null;
            $start_date = $_POST['start_date'] ?? null;
            $end_date = $_POST['end_date'] ?? null;

            if (!$academic_year_id || !$project_type_id || !$title) {
                echo '<div class="alert alert-danger">Veuillez remplir tous les champs requis.</div>';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO projects (title, description, project_type_id, module_id, organization_id, academic_year_id, start_date, end_date)
                        VALUES (:title, :description, :project_type_id, :module_id, :organization_id, :academic_year_id, :start_date, :end_date)
                    ");
                    $stmt->execute([
                        ':title' => $title,
                        ':description' => $description,
                        ':project_type_id' => $project_type_id,
                        ':module_id' => $module_id,
                        ':organization_id' => $organization_id,
                        ':academic_year_id' => $academic_year_id,
                        ':start_date' => $start_date,
                        ':end_date' => $end_date
                    ]);

                    $project_id = $pdo->lastInsertId();
                    $user_id = $_SESSION['user_id'];

                    $stmt = $pdo->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, 'member')");
                    $stmt->execute([$project_id, $user_id]);

                    echo '<div class="alert alert-success">✅ Projet soumis avec succès et vous avez été ajouté en tant que membre.</div>';
                } catch (PDOException $e) {
                    echo '<div class="alert alert-danger">❌ Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
        ?>

        <form method="POST" class="bg-white p-4 rounded shadow-sm">
            <div class="mb-3">
                <label for="title" class="form-label">Titre du projet <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="title" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label for="project_type_id" class="form-label">Type de projet <span class="text-danger">*</span></label>
                <select class="form-select" name="project_type_id" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= $type['type_id'] ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="organization_id" class="form-label">ID de l'organisation (si stage)</label>
                <input type="number" class="form-control" name="organization_id">
            </div>

            <div class="mb-3">
                <label for="module_id" class="form-label">ID du module (si projet de module)</label>
                <input type="number" class="form-control" name="module_id">
            </div>

            <div class="mb-3">
                <label for="academic_year_id" class="form-label">Année académique <span class="text-danger">*</span></label>
                <select class="form-select" name="academic_year_id" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= $year['year_id'] ?>"><?= htmlspecialchars($year['year_label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="start_date" class="form-label">Date de début</label>
                <input type="date" class="form-control" name="start_date">
            </div>

            <div class="mb-3">
                <label for="end_date" class="form-label">Date de fin</label>
                <input type="date" class="form-control" name="end_date">
            </div>

            <button type="submit" class="btn btn-primary">Soumettre</button>
        </form>
    </div>
</body>
</html>
