<?php
// admin/projects.php - Gestion des projets
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'program_director'])) {
    header('Location: ../include/login.php');
    exit();
}

// Connexion à la base de données
require_once '../include/connexion.php';

// Traitement des actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$department_filter = isset($_GET['department']) ? intval($_GET['department']) : 0;
$supervisor_filter = isset($_GET['supervisor']) ? intval($_GET['supervisor']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Traitement de la validation ou du rejet d'un projet
if ($action == 'validate' && $project_id > 0) {
    try {
        $stmt = $db->prepare("UPDATE projects SET status = 'validated' WHERE project_id = :project_id");
        $stmt->execute(['project_id' => $project_id]);
        $success_message = "Le projet a été validé avec succès.";
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la validation du projet: " . $e->getMessage();
    }
} elseif ($action == 'reject' && $project_id > 0) {
    try {
        $stmt = $db->prepare("UPDATE projects SET status = 'rejected' WHERE project_id = :project_id");
        $stmt->execute(['project_id' => $project_id]);
        $success_message = "Le projet a été rejeté.";
    } catch (PDOException $e) {
        $error_message = "Erreur lors du rejet du projet: " . $e->getMessage();
    }
}

// Construction de la requête SQL avec filtres
$sql = "SELECT p.project_id, p.title, p.description, p.status, p.created_at, 
               pt.type_name, m.module_name, d.name AS department_name,
               GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') AS students,
               GROUP_CONCAT(DISTINCT CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') AS supervisors
        FROM projects p
        LEFT JOIN project_types pt ON p.project_type_id = pt.type_id
        LEFT JOIN modules m ON p.module_id = m.module_id
        LEFT JOIN departments d ON m.department_id = d.department_id
        LEFT JOIN project_members pm ON p.project_id = pm.project_id
        LEFT JOIN users u ON pm.user_id = u.user_id AND u.role_id = (SELECT role_id FROM roles WHERE role_name = 'student')
        LEFT JOIN project_supervisors ps ON p.project_id = ps.project_id
        LEFT JOIN users s ON ps.user_id = s.user_id
        WHERE 1=1";

$params = [];

// Ajout des conditions de filtrage
if (!empty($status_filter)) {
    $sql .= " AND p.status = :status";
    $params['status'] = $status_filter;
}

if ($department_filter > 0) {
    $sql .= " AND m.department_id = :department_id";
    $params['department_id'] = $department_filter;
}

if ($supervisor_filter > 0) {
    $sql .= " AND ps.user_id = :supervisor_id";
    $params['supervisor_id'] = $supervisor_filter;
}

if (!empty($search)) {
    $sql .= " AND (p.title LIKE :search OR p.description LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $params['search'] = "%$search%";
}

// Groupement et ordre
$sql .= " GROUP BY p.project_id ORDER BY p.created_at DESC";

// Récupération des projets
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des projets: " . $e->getMessage();
    $projects = [];
}

// Récupération des filières pour le filtre
try {
    $stmt = $db->query("SELECT department_id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

// Récupération des encadrants pour le filtre
try {
    $stmt = $db->query("SELECT DISTINCT u.user_id, u.first_name, u.last_name 
                        FROM users u 
                        JOIN project_supervisors ps ON u.user_id = ps.user_id 
                        ORDER BY u.last_name, u.first_name");
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $supervisors = [];
}

// Page actuelle pour le menu
$current_page = 'projects';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Projets - ENSA</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Style personnalisé -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color:rgb(81, 101, 104);
            padding-top: 20px;
        }
        .sidebar a {
            color: rgba(255, 255, 255, 0.71);
            padding: 10px 15px;
            display: block;
            text-decoration: none;
        }
        .sidebar a:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar a.active {
            color: #fff;
            background-color: #007bff;
        }
        .content {
            padding: 20px;
        }
        .filter-form {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .badge.status-draft { background-color: #6c757d; }
        .badge.status-submitted { background-color: #ffc107; }
        .badge.status-validated { background-color: #28a745; }
        .badge.status-rejected { background-color: #dc3545; }
        .badge.status-completed { background-color: #007bff; }
        .project-actions .btn { margin-right: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky">
                    <div class="text-center mb-4">
                        
                        <h5 class="text-white">ENSA Projets</h5>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="dashboard3.php" class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                                <i class="fas fa-tachometer-alt me-2"></i> Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="users.php" class="nav-link <?php echo $current_page == 'users' ? 'active' : ''; ?>">
                                <i class="fas fa-users me-2"></i> Gestion des utilisateurs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="projects.php" class="nav-link <?php echo $current_page == 'projects' ? 'active' : ''; ?>">
                                <i class="fas fa-project-diagram me-2"></i> Gestion des projets
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="departments.php" class="nav-link <?php echo $current_page == 'departments' ? 'active' : ''; ?>">
                                <i class="fas fa-building me-2"></i> Filières et départements
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a href="rapport.php" class="nav-link <?php echo $current_page == 'reports' ? 'active' : ''; ?>">
                                <i class="fas fa-chart-bar me-2"></i> Rapports et statistiques
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings.php" class="nav-link <?php echo $current_page == 'settings' ? 'active' : ''; ?>">
                                <i class="fas fa-cog me-2"></i> Paramètres
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a href="logout.php" class="nav-link text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i> Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestion des Projets</h1>
                    <div>
                         <img src="../image/logo.png" alt="ENSA Logo" class="img-fluid mb-2" style="width: 100px; height:50px;">
                    </div>
                    
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtres -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Filtres</h5>
                    </div>
                    <div class="card-body filter-form">
                        <form action="" method="get" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Statut</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">Tous les statuts</option>
                                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Soumis</option>
                                    <option value="validated" <?php echo $status_filter == 'validated' ? 'selected' : ''; ?>>Validé</option>
                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejeté</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Complété</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="department" class="form-label">Filière</label>
                                <select name="department" id="department" class="form-select">
                                    <option value="">Toutes les filières</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo $department['department_id']; ?>" <?php echo $department_filter == $department['department_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($department['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="supervisor" class="form-label">Encadrant</label>
                                <select name="supervisor" id="supervisor" class="form-select">
                                    <option value="">Tous les encadrants</option>
                                    <?php foreach ($supervisors as $supervisor): ?>
                                        <option value="<?php echo $supervisor['user_id']; ?>" <?php echo $supervisor_filter == $supervisor['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Recherche</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Titre, description, étudiant...">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Filtrer
                                </button>
                                <a href="projects.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-undo me-1"></i> Réinitialiser
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Liste des projets -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Liste des projets (<?php echo count($projects); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover" id="projectsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Titre</th>
                                        <th>Type</th>
                                        <th>Filière</th>
                                        <th>Étudiants</th>
                                        <th>Encadrants</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($projects)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Aucun projet trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($projects as $project): ?>
                                            <tr>
                                                <td><?php echo $project['project_id']; ?></td>
                                                <td>
                                                    <a href="project_detail.php?id=<?php echo $project['project_id']; ?>" class="fw-bold text-primary">
                                                        <?php echo htmlspecialchars($project['title']); ?>
                                                    </a>
                                                    <div class="text-muted small text-truncate" style="max-width: 200px;">
                                                        <?php echo htmlspecialchars(substr($project['description'], 0, 60)) . (strlen($project['description']) > 60 ? '...' : ''); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($project['type_name']); ?></td>
                                                <td><?php echo htmlspecialchars($project['department_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($project['students'] ?? 'Aucun'); ?></td>
                                                <td><?php echo htmlspecialchars($project['supervisors'] ?? 'Aucun'); ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    $status_text = '';
                                                    
                                                    switch ($project['status']) {
                                                        case 'draft':
                                                            $status_class = 'status-draft';
                                                            $status_text = 'Brouillon';
                                                            break;
                                                        case 'submitted':
                                                            $status_class = 'status-submitted';
                                                            $status_text = 'Soumis';
                                                            break;
                                                        case 'validated':
                                                            $status_class = 'status-validated';
                                                            $status_text = 'Validé';
                                                            break;
                                                        case 'rejected':
                                                            $status_class = 'status-rejected';
                                                            $status_text = 'Rejeté';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'status-completed';
                                                            $status_text = 'Complété';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                </td>
                                                <td class="project-actions">
                                                    <a href="project_detail.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-info text-white" title="Voir détails">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="project_form.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-primary" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($project['status'] == 'submitted'): ?>
                                                        <a href="projects.php?action=validate&id=<?php echo $project['project_id']; ?>" 
                                                           class="btn btn-sm btn-success" title="Valider"
                                                           onclick="return confirm('Êtes-vous sûr de vouloir valider ce projet ?');">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                        <a href="projects.php?action=reject&id=<?php echo $project['project_id']; ?>" 
                                                           class="btn btn-sm btn-danger" title="Rejeter"
                                                           onclick="return confirm('Êtes-vous sûr de vouloir rejeter ce projet ?');">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="#" class="btn btn-sm btn-danger" title="Supprimer" 
                                                       data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $project['project_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    
                                                    <!-- Modal de confirmation de suppression -->
                                                    <div class="modal fade" id="deleteModal<?php echo $project['project_id']; ?>" tabindex="-1" 
                                                         aria-labelledby="deleteModalLabel<?php echo $project['project_id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteModalLabel<?php echo $project['project_id']; ?>">
                                                                        Confirmer la suppression
                                                                    </h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Êtes-vous sûr de vouloir supprimer le projet "<?php echo htmlspecialchars($project['title']); ?>" ?
                                                                    Cette action est irréversible.
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                    <a href="project_delete.php?id=<?php echo $project['project_id']; ?>" class="btn btn-danger">
                                                                        Supprimer
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#projectsTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                order: [[0, 'desc']], // Trier par ID décroissant par défaut
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tous"]],
                responsive: true
            });
        });
    </script>
</body>
</html>