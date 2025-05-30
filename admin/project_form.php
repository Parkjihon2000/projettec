<?php
// admin/project_form.php - Formulaire d'ajout/modification d'un projet
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'program_director'])) {
    header('Location: ../include/login.php');
    exit();
}

// Connexion à la base de données
require_once '../include/connexion.php';

// Récupération de l'ID du projet si modification
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit = ($project_id > 0);

// Initialisation du projet
$project = [
    'title' => '',
    'description' => '',
    'project_type_id' => '',
    'module_id' => '',
    'organization_id' => '',
    'academic_year_id' => '',
    'start_date' => '',
    'end_date' => '',
    'budget' => '',
    'hours_allocated' => '',
    'is_confidential' => 0,
    'is_featured' => 0,
    'status' => 'draft'
];

// Récupération des informations du projet si modification
if ($is_edit) {
    try {
        $stmt = $db->prepare("SELECT * FROM projects WHERE project_id = :project_id");
        $stmt->execute(['project_id' => $project_id]);
        $project_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($project_data) {
            $project = $project_data;
        } else {
            // Projet non trouvé, redirection
            header('Location: projects.php');
            exit();
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la récupération des informations du projet: " . $e->getMessage();
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $project = [
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        'project_type_id' => intval($_POST['project_type_id']),
        'module_id' => !empty($_POST['module_id']) ? intval($_POST['module_id']) : null,
        'organization_id' => !empty($_POST['organization_id']) ? intval($_POST['organization_id']) : null,
        'academic_year_id' => intval($_POST['academic_year_id']),
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'budget' => !empty($_POST['budget']) ? floatval($_POST['budget']) : null,
        'hours_allocated' => !empty($_POST['hours_allocated']) ? intval($_POST['hours_allocated']) : null,
        'is_confidential' => isset($_POST['is_confidential']) ? 1 : 0,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'status' => $_POST['status']
    ];
    
    try {
        if ($is_edit) {
            // Mise à jour du projet
            $sql = "UPDATE projects SET 
                    title = :title, 
                    description = :description,
                    project_type_id = :project_type_id,
                    module_id = :module_id,
                    organization_id = :organization_id,
                    academic_year_id = :academic_year_id,
                    start_date = :start_date,
                    end_date = :end_date,
                    budget = :budget,
                    hours_allocated = :hours_allocated,
                    is_confidential = :is_confidential,
                    is_featured = :is_featured,
                    status = :status,
                    updated_at = NOW()
                    WHERE project_id = :project_id";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':project_id', $project_id);
        } else {
            // Création d'un nouveau projet
            $sql = "INSERT INTO projects (title, description, project_type_id, module_id, organization_id, academic_year_id, 
                    start_date, end_date, budget, hours_allocated, is_confidential, is_featured, status, created_at, updated_at) 
                    VALUES (:title, :description, :project_type_id, :module_id, :organization_id, :academic_year_id, 
                    :start_date, :end_date, :budget, :hours_allocated, :is_confidential, :is_featured, :status, NOW(), NOW())";
            
            $stmt = $db->prepare($sql);
        }
        
        // Binding des valeurs
        $stmt->bindValue(':title', $project['title']);
        $stmt->bindValue(':description', $project['description']);
        $stmt->bindValue(':project_type_id', $project['project_type_id']);
        $stmt->bindValue(':module_id', $project['module_id'], PDO::PARAM_INT);
        $stmt->bindValue(':organization_id', $project['organization_id'], PDO::PARAM_INT);
        $stmt->bindValue(':academic_year_id', $project['academic_year_id']);
        $stmt->bindValue(':start_date', $project['start_date']);
        $stmt->bindValue(':end_date', $project['end_date']);
        $stmt->bindValue(':budget', $project['budget'], PDO::PARAM_STR);
        $stmt->bindValue(':hours_allocated', $project['hours_allocated'], PDO::PARAM_INT);
        $stmt->bindValue(':is_confidential', $project['is_confidential'], PDO::PARAM_INT);
        $stmt->bindValue(':is_featured', $project['is_featured'], PDO::PARAM_INT);
        $stmt->bindValue(':status', $project['status']);
        
        // Exécution de la requête
        $stmt->execute();
        
        if (!$is_edit) {
            $project_id = $db->lastInsertId();
        }
        
        // Redirection vers la page de détail du projet
        header("Location: project_detail.php?id=" . $project_id);
        exit();
        
    } catch (PDOException $e) {
        $error_message = "Erreur lors de l'enregistrement du projet: " . $e->getMessage();
    }
}

// Récupération des types de projets
try {
    $stmt = $db->query("SELECT * FROM project_types ORDER BY type_name");
    $project_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des types de projets: " . $e->getMessage();
    $project_types = [];
}

// Récupération des modules
try {
    $stmt = $db->query("SELECT m.*, d.name AS department_name FROM modules m JOIN departments d ON m.department_id = d.department_id ORDER BY m.module_name");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des modules: " . $e->getMessage();
    $modules = [];
}

// Récupération des organisations
try {
    $stmt = $db->query("SELECT * FROM organizations ORDER BY name");
    $organizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des organisations: " . $e->getMessage();
    $organizations = [];
}

// Récupération des années académiques
try {
    $stmt = $db->query("SELECT * FROM academic_years ORDER BY year_label DESC");
    $academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des années académiques: " . $e->getMessage();
    $academic_years = [];
}

// Page actuelle pour le menu
$current_page = 'projects';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Modifier' : 'Ajouter'; ?> un projet - ENSA</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Style personnalisé -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (simplifié) -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <!-- Contenu du sidebar -->
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1 class="h2"><?php echo $is_edit ? 'Modifier' : 'Ajouter'; ?> un projet</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="projects.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Retour à la liste
                        </a>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="title" class="form-label">Titre du projet *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($project['title']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="5" required><?php echo htmlspecialchars($project['description']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="project_type_id" class="form-label">Type de projet *</label>
                                    <select class="form-select" id="project_type_id" name="project_type_id" required>
                                        <option value="">Sélectionner un type</option>
                                        <?php foreach ($project_types as $type): ?>
                                            <option value="<?php echo $type['type_id']; ?>" 
                                                    <?php echo ($project['project_type_id'] == $type['type_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['type_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="academic_year_id" class="form-label">Année académique *</label>
                                    <select class="form-select" id="academic_year_id" name="academic_year_id" required>
                                        <option value="">Sélectionner une année</option>
                                        <?php foreach ($academic_years as $year): ?>
                                            <option value="<?php echo $year['year_id']; ?>" 
                                                    <?php echo ($project['academic_year_id'] == $year['year_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($year['year_label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Autres champs du formulaire (statut, dates, etc.) -->
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="status" class="form-label">Statut *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="draft" <?php echo ($project['status'] == 'draft') ? 'selected' : ''; ?>>Brouillon</option>
                                        <option value="submitted" <?php echo ($project['status'] == 'submitted') ? 'selected' : ''; ?>>Soumis</option>
                                        <option value="validated" <?php echo ($project['status'] == 'validated') ? 'selected' : ''; ?>>Validé</option>
                                        <option value="rejected" <?php echo ($project['status'] == 'rejected') ? 'selected' : ''; ?>>Rejeté</option>
                                        <option value="completed" <?php echo ($project['status'] == 'completed') ? 'selected' : ''; ?>>Complété</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="start_date" class="form-label">Date de début *</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($project['start_date']); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label">Date de fin *</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($project['end_date']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_confidential" name="is_confidential" 
                                               <?php echo $project['is_confidential'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_confidential">Projet confidentiel</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" 
                                               <?php echo $project['is_featured'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_featured">Mettre en avant sur la page d'accueil</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="<?php echo $is_edit ? "project_detail.php?id=$project_id" : "projects.php"; ?>" class="btn btn-outline-secondary me-md-2">Annuler</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> <?php echo $is_edit ? 'Enregistrer les modifications' : 'Créer le projet'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>