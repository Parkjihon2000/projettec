<?php
// admin/project_detail.php - Affichage détaillé d'un projet
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'program_director'])) {
    header('Location: ../login.php');
    exit();
}

// Connexion à la base de données
require_once '../include/connexion.php';

// Récupération de l'ID du projet
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($project_id <= 0) {
    header('Location: projects.php');
    exit();
}

// Traitement des actions
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Traitement de la validation ou du rejet d'un projet
if ($action == 'validate') {
    try {
        $stmt = $db->prepare("UPDATE projects SET status = 'validated' WHERE project_id = :project_id");
        $stmt->execute(['project_id' => $project_id]);
        $success_message = "Le projet a été validé avec succès.";
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la validation du projet: " . $e->getMessage();
    }
} elseif ($action == 'reject') {
    try {
        $stmt = $db->prepare("UPDATE projects SET status = 'rejected' WHERE project_id = :project_id");
        $stmt->execute(['project_id' => $project_id]);
        $success_message = "Le projet a été rejeté.";
    } catch (PDOException $e) {
        $error_message = "Erreur lors du rejet du projet: " . $e->getMessage();
    }
} elseif ($action == 'complete') {
    try {
        $stmt = $db->prepare("UPDATE projects SET status = 'completed' WHERE project_id = :project_id");
        $stmt->execute(['project_id' => $project_id]);
        $success_message = "Le projet a été marqué comme complété.";
    } catch (PDOException $e) {
        $error_message = "Erreur lors du marquage du projet comme complété: " . $e->getMessage();
    }
}

// Récupération des informations du projet
try {
    $stmt = $db->prepare("
        SELECT p.*, pt.type_name, m.module_name, m.module_code,
               d.name AS department_name, d.abbreviation AS department_abbr,
               o.name AS organization_name, o.city AS organization_city,
               o.country AS organization_country, o.website AS organization_website,
               ay.year_label
        FROM projects p
        LEFT JOIN project_types pt ON p.project_type_id = pt.type_id
        LEFT JOIN modules m ON p.module_id = m.module_id
        LEFT JOIN departments d ON m.department_id = d.department_id
        LEFT JOIN organizations o ON p.organization_id = o.organization_id
        LEFT JOIN academic_years ay ON p.academic_year_id = ay.year_id
        WHERE p.project_id = :project_id
    ");
    $stmt->execute(['project_id' => $project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        header('Location: projects.php');
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des informations du projet: " . $e->getMessage();
    $project = [];
}

// Récupération des étudiants associés au projet
try {
    $stmt = $db->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.student_id, u.year_of_study,
               pm.role AS project_role
        FROM project_members pm
        JOIN users u ON pm.user_id = u.user_id
        WHERE pm.project_id = :project_id
        ORDER BY pm.role DESC, u.last_name, u.first_name
    ");
    $stmt->execute(['project_id' => $project_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des étudiants: " . $e->getMessage();
    $students = [];
}

// Récupération des encadrants associés au projet
try {
    $stmt = $db->prepare("
        SELECT ps.user_id, u.first_name, u.last_name, u.email, u.department, 
               ps.external_name, ps.external_email, ps.external_organization,
               ps.supervision_role
        FROM project_supervisors ps
        LEFT JOIN users u ON ps.user_id = u.user_id
        WHERE ps.project_id = :project_id
        ORDER BY ps.supervision_role
    ");
    $stmt->execute(['project_id' => $project_id]);
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des encadrants: " . $e->getMessage();
    $supervisors = [];
}

// Récupération des livrables du projet
try {
    $stmt = $db->prepare("
        SELECT d.*, dt.type_name, u.first_name, u.last_name
        FROM deliverables d
        JOIN deliverable_types dt ON d.deliverable_type_id = dt.deliverable_type_id
        JOIN users u ON d.uploaded_by = u.user_id
        WHERE d.project_id = :project_id
        ORDER BY d.upload_date DESC
    ");
    $stmt->execute(['project_id' => $project_id]);
    $deliverables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des livrables: " . $e->getMessage();
    $deliverables = [];
}

// Récupération des évaluations du projet
try {
    $stmt = $db->prepare("
        SELECT e.*, u.first_name, u.last_name
        FROM evaluations e
        JOIN users u ON e.evaluator_id = u.user_id
        WHERE e.project_id = :project_id
        ORDER BY e.evaluation_date DESC
    ");
    $stmt->execute(['project_id' => $project_id]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des évaluations: " . $e->getMessage();
    $evaluations = [];
}

// Récupération des tags du projet
try {
    $stmt = $db->prepare("
        SELECT t.*
        FROM project_tags pt
        JOIN tags t ON pt.tag_id = t.tag_id
        WHERE pt.project_id = :project_id
        ORDER BY t.tag_name
    ");
    $stmt->execute(['project_id' => $project_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des tags: " . $e->getMessage();
    $tags = [];
}

// Comptage des likes du projet
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as like_count
        FROM project_likes
        WHERE project_id = :project_id
    ");
    $stmt->execute(['project_id' => $project_id]);
    $likes = $stmt->fetch(PDO::FETCH_ASSOC);
    $like_count = $likes ? $likes['like_count'] : 0;
} catch (PDOException $e) {
    $error_message = "Erreur lors du comptage des likes: " . $e->getMessage();
    $like_count = 0;
}

// Fonction pour formater les dates
function formatDate($date) {
    if (!$date) return 'Non défini';
    return date('d/m/Y', strtotime($date));
}

// Fonction pour formater la taille des fichiers
function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    elseif ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    elseif ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
    else return round($bytes / 1073741824, 2) . ' GB';
}

// Page actuelle pour le menu
$current_page = 'projects';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['title']); ?> - ENSA</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .badge.status-draft { background-color: #6c757d; }
        .badge.status-submitted { background-color: #ffc107; }
        .badge.status-validated { background-color: #28a745; }
        .badge.status-rejected { background-color: #dc3545; }
        .badge.status-completed { background-color: #007bff; }
        
        .project-header {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .project-info {
            margin-bottom: 30px;
        }
        
        .project-actions {
            margin-bottom: 20px;
        }
        
        .section-title {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .deliverable-card {
            border-left: 4px solid #007bff;
        }
        
        .evaluation-card {
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky">
                    <div class="text-center mb-4">
                        <img src="../image/logo.png" alt="ENSA Logo" class="img-fluid mb-2" style="width: 100px; height:70px;">
                        <h5 class="text-white">ENSA Projets</h5>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="../dashboard3.php" class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="projects.php">Projets</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($project['title']); ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="projects.php" class="btn btn-sm btn-secondary me-2">
                            <i class="fas fa-arrow-left me-1"></i> Retour à la liste
                        </a>
                        
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

                <!-- En-tête du projet -->
                <div class="project-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h2 mb-2"><?php echo htmlspecialchars($project['title']); ?></h1>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-primary me-2"><?php echo htmlspecialchars($project['type_name']); ?></span>
                                
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
                                <span class="badge <?php echo $status_class; ?> me-2"><?php echo $status_text; ?></span>
                                
                                <?php if (!empty($project['department_name'])): ?>
                                    <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($project['department_name']); ?></span>
                                <?php endif; ?>
                                
                                <span class="text-muted small"><?php echo htmlspecialchars($project['year_label']); ?></span>
                            </div>
                            <div>
                                <?php foreach ($tags as $tag): ?>
                                    <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($tag['tag_name']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="project-actions">
                                <?php if ($project['status'] == 'submitted'): ?>
                                    <a href="project_detail.php?id=<?php echo $project_id; ?>&action=validate" 
                                       class="btn btn-success me-1" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir valider ce projet ?');">
                                        <i class="fas fa-check me-1"></i> Valider
                                    </a>
                                    <a href="project_detail.php?id=<?php echo $project_id; ?>&action=reject" 
                                       class="btn btn-danger me-1" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir rejeter ce projet ?');">
                                        <i class="fas fa-times me-1"></i> Rejeter
                                    </a>
                                <?php elseif ($project['status'] == 'validated'): ?>
                                    <a href="project_detail.php?id=<?php echo $project_id; ?>&action=complete" 
                                       class="btn btn-info text-white me-1" 
                                       onclick="return confirm('Marquer ce projet comme complété ?');">
                                        <i class="fas fa-check-double me-1"></i> Marquer comme complété
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-trash me-1"></i> Supprimer
                                </button>
                            </div>
                            <div class="d-flex align-items-center justify-content-md-end mt-2">
                                <i class="far fa-heart me-1"></i> <span class="me-3"><?php echo $like_count; ?> likes</span>
                                <i class="far fa-calendar-alt me-1"></i> <span>Créé le <?php echo formatDate($project['created_at']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Détails du projet -->
                    <div class="col-lg-8">
                        <!-- Description -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="section-title">Description du projet</h5>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                            </div>
                        </div>

                        <!-- Étudiants -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="section-title">Étudiants</h5>
                                <?php if (empty($students)): ?>
                                    <p class="text-muted">Aucun étudiant associé à ce projet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Nom</th>
                                                    <th>Email</th>
                                                    <th>ID Étudiant</th>
                                                    <th>Année</th>
                                                    <th>Rôle</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($students as $student): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="user_detail.php?id=<?php echo $student['user_id']; ?>">
                                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                                        <td><?php echo $student['year_of_study'] ? $student['year_of_study'] . 'ème année' : 'N/A'; ?></td>
                                                        <td>
                                                            <?php if ($student['project_role'] == 'team_leader'): ?>
                                                                <span class="badge bg-primary">Chef d'équipe</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Membre</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Encadrants -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="section-title">Encadrants</h5>
                                <?php if (empty($supervisors)): ?>
                                    <p class="text-muted">Aucun encadrant associé à ce projet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Nom</th>
                                                    <th>Email</th>
                                                    <th>Affiliation</th>
                                                    <th>Rôle</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($supervisors as $supervisor): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if ($supervisor['user_id']): ?>
                                                                <a href="user_detail.php?id=<?php echo $supervisor['user_id']; ?>">
                                                                    <?php echo htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name']); ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($supervisor['external_name']); ?>
                                                                <span class="badge bg-secondary ms-1">Externe</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($supervisor['user_id'] ? $supervisor['email'] : $supervisor['external_email']); ?>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($supervisor['user_id'] ? $supervisor['department'] : $supervisor['external_organization']); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($supervisor['supervision_role'] == 'academic_supervisor'): ?>
                                                                <span class="badge bg-info text-white">Encadrant académique</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-success">Encadrant industriel</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Livrables -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="section-title mb-0">Livrables du projet</h5>
                                   
                                </div>
                                
                                <?php if (empty($deliverables)): ?>
                                    <p class="text-muted">Aucun livrable disponible pour ce projet.</p>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($deliverables as $deliverable): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card deliverable-card h-100">
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?php echo htmlspecialchars($deliverable['title']); ?></h6>
                                                        <div class="mb-2">
                                                            <span class="badge bg-primary"><?php echo htmlspecialchars($deliverable['type_name']); ?></span>
                                                            <?php if ($deliverable['status'] == 'pending'): ?>
                                                                <span class="badge bg-warning">En attente</span>
                                                            <?php elseif ($deliverable['status'] == 'approved'): ?>
                                                                <span class="badge bg-success">Approuvé</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Rejeté</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <div><i class="fas fa-file me-1"></i> <?php echo htmlspecialchars($deliverable['file_type']); ?></div>
                                                            <div><i class="fas fa-weight-hanging me-1"></i> <?php echo formatFileSize($deliverable['file_size']); ?></div>
                                                            <div><i class="far fa-calendar-alt me-1"></i> <?php echo date('d/m/Y H:i', strtotime($deliverable['upload_date'])); ?></div>
                                                            <div><i class="fas fa-user me-1"></i> Par <?php echo htmlspecialchars($deliverable['first_name'] . ' ' . $deliverable['last_name']); ?></div>
                                                        </div>
                                                    </div>
                                                    <div class="card-footer bg-white border-top-0 d-flex justify-content-between">
                                                        <a href="../uploads/deliverables/<?php echo htmlspecialchars($deliverable['file_path']); ?>" class="btn btn-sm btn-outline-primary" download>
                                                            <i class="fas fa-download me-1"></i> Télécharger
                                                        </a>
                                                        <div>
                                                            <?php if ($deliverable['status'] == 'pending'): ?>
                                                                <a href="deliverable_status.php?id=<?php echo $deliverable['deliverable_id']; ?>&status=approved&project_id=<?php echo $project_id; ?>" 
                                                                   class="btn btn-sm btn-success" title="Approuver">
                                                                    <i class="fas fa-check"></i>
                                                                </a>
                                                                <a href="deliverable_status.php?id=<?php echo $deliverable['deliverable_id']; ?>&status=rejected&project_id=<?php echo $project_id; ?>"
                                                                <a href="deliverable_status.php?id=<?php echo $deliverable['deliverable_id']; ?>&status=rejected&project_id=<?php echo $project_id; ?>" 
                                                                   class="btn btn-sm btn-danger" title="Rejeter">
                                                                    <i class="fas fa-times"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                       
                                                     
                        
                        <!-- Commentaires -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="section-title">Commentaires</h5>
                                
                                <?php
                                // Récupération des commentaires du projet
                                try {
                                    $stmt = $db->prepare("
                                        SELECT c.*, u.first_name, u.last_name, u.profile_image
                                        FROM comments c
                                        JOIN users u ON c.user_id = u.user_id
                                        WHERE c.project_id = :project_id
                                        ORDER BY c.created_at DESC
                                    ");
                                    $stmt->execute(['project_id' => $project_id]);
                                    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (PDOException $e) {
                                    $error_message = "Erreur lors de la récupération des commentaires: " . $e->getMessage();
                                    $comments = [];
                                }
                                ?>
                                
                                <?php if (empty($comments)): ?>
                                    <p class="text-muted">Aucun commentaire pour ce projet.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($comments as $comment): ?>
                                            <div class="list-group-item border-0 border-bottom">
                                                <div class="d-flex">
                                                    <div class="flex-shrink-0">
                                                        <?php if ($comment['profile_image']): ?>
                                                            <img src="../uploads/profiles/<?php echo htmlspecialchars($comment['profile_image']); ?>" 
                                                                 class="rounded-circle" width="40" height="40" alt="Avatar">
                                                        <?php else: ?>
                                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" 
                                                                 style="width: 40px; height: 40px;">
                                                                <?php 
                                                                $initials = substr($comment['first_name'], 0, 1) . substr($comment['last_name'], 0, 1);
                                                                echo strtoupper($initials);
                                                                ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?></h6>
                                                            <small class="text-muted">
                                                                <?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                        <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                                                        <div class="d-flex justify-content-end mt-2">
                                                            <a href="comment_delete.php?id=<?php echo $comment['comment_id']; ?>&project_id=<?php echo $project_id; ?>" 
                                                               class="btn btn-sm btn-outline-danger"
                                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?');">
                                                                <i class="fas fa-trash me-1"></i> Supprimer
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Formulaire pour ajouter un commentaire -->
                                <div class="mt-3">
                                    <form action="comment_add.php" method="post" class="mb-0">
                                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                        <div class="form-group mb-2">
                                            <textarea class="form-control" name="content" rows="3" placeholder="Ajouter un commentaire..." required></textarea>
                                        </div>
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-1"></i> Envoyer
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar - Informations complémentaires -->
                    <div class="col-lg-4">
                        <!-- Organisation -->
                        <?php if (!empty($project['organization_id'])): ?>
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Organisation partenaire</h5>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" 
                                             style="width: 50px; height: 50px;">
                                            <i class="fas fa-building text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($project['organization_name']); ?></h6>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($project['organization_city'] . ', ' . $project['organization_country']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (!empty($project['organization_website'])): ?>
                                        <a href="<?php echo htmlspecialchars($project['organization_website']); ?>" target="_blank" class="btn btn-outline-primary btn-sm d-block">
                                            <i class="fas fa-globe me-1"></i> Visiter le site web
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Module -->
                        <?php if (!empty($project['module_id'])): ?>
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Module associé</h5>
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" 
                                             style="width: 50px; height: 50px;">
                                            <i class="fas fa-book text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($project['module_name']); ?></h6>
                                            <div class="text-muted small">
                                                Code: <?php echo htmlspecialchars($project['module_code']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="module_detail.php?id=<?php echo $project['module_id']; ?>" class="btn btn-outline-primary btn-sm d-block">
                                        <i class="fas fa-info-circle me-1"></i> Détails du module
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Dates importantes -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Dates importantes</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><i class="far fa-calendar-plus text-success me-2"></i> Date de début:</span>
                                        <strong><?php echo formatDate($project['start_date']); ?></strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><i class="far fa-calendar-check text-danger me-2"></i> Date de fin:</span>
                                        <strong><?php echo formatDate($project['end_date']); ?></strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><i class="far fa-calendar-alt text-primary me-2"></i> Créé le:</span>
                                        <strong><?php echo formatDate($project['created_at']); ?></strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><i class="fas fa-calendar-day text-info me-2"></i> Mis à jour le:</span>
                                        <strong><?php echo formatDate($project['updated_at']); ?></strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Informations supplémentaires -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Informations supplémentaires</h5>
                                <ul class="list-group list-group-flush">
                                    <?php if (!empty($project['budget'])): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-money-bill-wave text-success me-2"></i> Budget:</span>
                                            <strong><?php echo number_format($project['budget'], 2, ',', ' '); ?> DH</strong>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (!empty($project['hours_allocated'])): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-clock text-primary me-2"></i> Heures allouées:</span>
                                            <strong><?php echo $project['hours_allocated']; ?> heures</strong>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (isset($project['is_confidential'])): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-user-secret text-danger me-2"></i> Confidentialité:</span>
                                            <strong><?php echo $project['is_confidential'] ? 'Projet confidentiel' : 'Non confidentiel'; ?></strong>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (isset($project['is_featured'])): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-star text-warning me-2"></i> Mise en avant:</span>
                                            <strong><?php echo $project['is_featured'] ? 'Projet à la une' : 'Normal'; ?></strong>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Actions rapides -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Actions rapides</h5>
                                <div class="d-grid gap-2">
                                    <a href="project_export.php?id=<?php echo $project_id; ?>&format=pdf" class="btn btn-outline-primary">
                                        <i class="fas fa-file-pdf me-1"></i> Exporter en PDF
                                    </a>
               
                                    <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#shareModal">
                                        <i class="fas fa-share-alt me-1"></i> Partager
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer ce projet ? Cette action est irréversible et supprimera également tous les livrables, évaluations et commentaires associés.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <a href="project_delete.php?id=<?php echo $project_id; ?>" class="btn btn-danger">Supprimer définitivement</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de partage -->
    <div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shareModalLabel">Partager ce projet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="shareLink" class="form-label">Lien de partage</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="shareLink" value="<?php echo "http://$_SERVER[HTTP_HOST]/project_view.php?id=$project_id"; ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" id="copyLink" onclick="copyShareLink()">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <div id="copyMessage" class="form-text text-success d-none">Lien copié!</div>
                    </div>
                    
                    <div>
                        <label class="form-label">Partager sur les réseaux sociaux</label>
                        <div class="d-flex gap-2">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode("http://$_SERVER[HTTP_HOST]/project_view.php?id=$project_id"); ?>" 
                               class="btn btn-outline-primary" target="_blank" title="Partager sur Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode("http://$_SERVER[HTTP_HOST]/project_view.php?id=$project_id"); ?>&text=<?php echo urlencode($project['title']); ?>" 
                               class="btn btn-outline-info" target="_blank" title="Partager sur Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode("http://$_SERVER[HTTP_HOST]/project_view.php?id=$project_id"); ?>&title=<?php echo urlencode($project['title']); ?>" 
                               class="btn btn-outline-primary" target="_blank" title="Partager sur LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="mailto:?subject=<?php echo urlencode('Projet ENSA: ' . $project['title']); ?>&body=<?php echo urlencode("Découvrez ce projet: http://$_SERVER[HTTP_HOST]/project_view.php?id=$project_id"); ?>" 
                               class="btn btn-outline-secondary" title="Partager par email">
                                <i class="fas fa-envelope"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function copyShareLink() {
            var copyText = document.getElementById("shareLink");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            var copyMessage = document.getElementById("copyMessage");
            copyMessage.classList.remove("d-none");
            
            setTimeout(function() {
                copyMessage.classList.add("d-none");
            }, 2000);
        }
    </script>
</body>
</html>