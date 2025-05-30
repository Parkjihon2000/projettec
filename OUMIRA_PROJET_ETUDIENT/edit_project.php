<?php
require_once 'connexion.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("<div class='alert alert-danger'>❌ Accès refusé. Veuillez vous connecter.</div>");
}

$user_id = $_SESSION['user_id'];
$project_id = $_GET['project_id'] ?? null;
if (!$project_id) {
    die("<div class='alert alert-warning'>❌ Aucun projet spécifié.</div>");
}

// Récupération des données
$stmt = $pdo->prepare("SELECT * FROM projects WHERE project_id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$project) die("<div class='alert alert-danger'>❌ Projet introuvable.</div>");

// Vérification des droits
$check = $pdo->prepare("SELECT * FROM project_members WHERE project_id = ? AND user_id = ?");
$check->execute([$project_id, $user_id]);
if (!$check->fetch()) die("<div class='alert alert-danger'>❌ Accès non autorisé à ce projet.</div>");

// Listes de données
$types = $pdo->query("SELECT type_id, type_name FROM project_types")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT year_id, year_label FROM academic_years")->fetchAll(PDO::FETCH_ASSOC);
$deliverable_types = $pdo->query("SELECT deliverable_type_id, type_name FROM deliverable_types")->fetchAll(PDO::FETCH_ASSOC);

// Dernier livrable
$stmt = $pdo->prepare("SELECT * FROM deliverables WHERE project_id = ? ORDER BY upload_date DESC LIMIT 1");
$stmt->execute([$project_id]);
$deliverable = $stmt->fetch(PDO::FETCH_ASSOC);

// Message de statut
$status = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $start_date = $_POST['start_date'] ?: null;
    $end_date = $_POST['end_date'] ?: null;

    // Mise à jour du projet
    $update = $pdo->prepare("UPDATE projects SET title = ?, description = ?, start_date = ?, end_date = ? WHERE project_id = ?");
    $update->execute([$title, $description, $start_date, $end_date, $project_id]);

    // Mise à jour du livrable
    if (!empty($_FILES['deliverable_file']['name']) && $_FILES['deliverable_file']['error'] === UPLOAD_ERR_OK) {
        $deliverable_title = $_POST['deliverable_title'] ?? 'Sans titre';
        $deliverable_type_id = $_POST['deliverable_type_id'] ?? null;

        $file_name = basename($_FILES['deliverable_file']['name']);
        $file_path = 'uploads/' . uniqid() . '_' . $file_name;
        move_uploaded_file($_FILES['deliverable_file']['tmp_name'], $file_path);

        $file_size = $_FILES['deliverable_file']['size'];
        $file_type = mime_content_type($file_path);

        $stmt = $pdo->prepare("INSERT INTO deliverables 
            (project_id, deliverable_type_id, title, file_path, file_size, file_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $project_id,
            $deliverable_type_id,
            $deliverable_title,
            $file_path,
            $file_size,
            $file_type,
            $user_id
        ]);

        $status .= "<div class='alert alert-success'>📎 Livrable mis à jour.</div>";
    }

    $status .= "<div class='alert alert-success'>✅ Projet modifié avec succès.</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier le projet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="text-primary mb-4">✏️ Modifier le projet</h2>

    <?= $status ?>

    <form method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm bg-white">
        <div class="mb-3">
            <label class="form-label">Titre du projet <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($project['title']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($project['description']) ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Date de début</label>
                <input type="date" name="start_date" class="form-control" value="<?= $project['start_date'] ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Date de fin</label>
                <input type="date" name="end_date" class="form-control" value="<?= $project['end_date'] ?>">
            </div>
        </div>

        <hr class="my-4">

        <h5 class="text-secondary">Livrable associé</h5>

        <div class="mb-3">
            <label class="form-label">Titre du livrable</label>
            <input type="text" name="deliverable_title" class="form-control"
                   value="<?= $deliverable ? htmlspecialchars($deliverable['title']) : '' ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Type de livrable</label>
            <select name="deliverable_type_id" class="form-select">
                <option value="">-- Choisir --</option>
                <?php foreach ($deliverable_types as $type): ?>
                    <option value="<?= $type['deliverable_type_id'] ?>"
                        <?= $deliverable && $deliverable['deliverable_type_id'] == $type['deliverable_type_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type['type_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Fichier livrable</label>
            <input type="file" name="deliverable_file" class="form-control">
        </div>

        <button type="submit" class="btn btn-success">💾 Enregistrer les modifications</button>
    </form>
</div>
</body>
</html>

