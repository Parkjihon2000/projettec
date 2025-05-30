<?php
session_start();
require_once 'connexion.php';

// Vérification de session
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("<div class='alert alert-danger'>❌ Accès refusé. Veuillez vous connecter.</div>");
}

// Récupérer les projets associés à l'utilisateur
$stmt = $pdo->prepare("
    SELECT p.project_id, p.title 
    FROM projects p
    INNER JOIN project_members pm ON p.project_id = pm.project_id
    WHERE pm.user_id = ?
");
$stmt->execute([$user_id]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les types de livrables
$stmt = $pdo->query("SELECT deliverable_type_id, type_name FROM deliverable_types");
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'] ?? null;
    $deliverable_type_id = $_POST['deliverable_type_id'] ?? null;
    $title = $_POST['title'] ?? '';

    if (!$project_id || !$deliverable_type_id || empty($title)) {
        $message = "<div class='alert alert-danger'>❌ Tous les champs sont requis.</div>";
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $file = $_FILES['file'];
        $targetDir = 'uploads/';
        $file_path = $targetDir . uniqid() . '_' . basename($file['name']);

        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        move_uploaded_file($file['tmp_name'], $file_path);

        $file_size = $file['size'];
        $file_type = mime_content_type($file_path);

        $stmt = $pdo->prepare("INSERT INTO deliverables 
            (project_id, deliverable_type_id, title, file_path, file_size, file_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $project_id,
            $deliverable_type_id,
            $title,
            $file_path,
            $file_size,
            $file_type,
            $user_id
        ]);

        $message = "<div class='alert alert-success'>✅ Livrable déposé avec succès.</div>";
    } else {
        $message = "<div class='alert alert-danger'>❌ Erreur lors de l'upload du fichier.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Déposer un livrable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="text-primary mb-4">📤 Dépôt de livrable</h2>

        <?= $message ?>

        <form method="POST" enctype="multipart/form-data" class="bg-white p-4 rounded shadow-sm">
            <div class="mb-3">
                <label for="project_id" class="form-label">Projet <span class="text-danger">*</span></label>
                <select name="project_id" class="form-select" required>
                    <option value="">-- Choisir un projet --</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['project_id'] ?>"><?= htmlspecialchars($project['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="deliverable_type_id" class="form-label">Type de livrable <span class="text-danger">*</span></label>
                <select name="deliverable_type_id" class="form-select" required>
                    <option value="">-- Choisir le type --</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= $type['deliverable_type_id'] ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="title" class="form-label">Titre du livrable <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="Ex: Rapport final" required>
            </div>

            <div class="mb-3">
                <label for="file" class="form-label">Fichier à téléverser <span class="text-danger">*</span></label>
                <input type="file" name="file" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary">📎 Déposer le fichier</button>
        </form>
    </div>
</body>
</html>
