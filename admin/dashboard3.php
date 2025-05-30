<?php
// dashboard3.php - Dashboard Administrateur
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'program_director'])) {
    header('Location: login.php');
    exit();
}

// Connexion à la base de données
require_once '../include/connexion.php';

// Récupérer les informations de l'administrateur connecté
try {
    $stmt = $db->prepare("SELECT user_id, first_name, last_name, email FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des données: " . $e->getMessage();
}

// Fonction pour récupérer des statistiques générales
function getStatistics($db) {
    $stats = [
        'total_users' => 0,
        'total_projects' => 0,
        'total_departments' => 0,
        'total_modules' => 0,
        'pending_projects' => 0,
    ];
    
    try {
        // Total des utilisateurs
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        $stats['total_users'] = $stmt->fetchColumn();
        
        // Total des projets
        $stmt = $db->query("SELECT COUNT(*) FROM projects");
        $stats['total_projects'] = $stmt->fetchColumn();
        
        // Total des départements
        $stmt = $db->query("SELECT COUNT(*) FROM departments");
        $stats['total_departments'] = $stmt->fetchColumn();
        
        // Total des modules
        $stmt = $db->query("SELECT COUNT(*) FROM modules");
        $stats['total_modules'] = $stmt->fetchColumn();
        
        // Projets en attente de validation
        $stmt = $db->query("SELECT COUNT(*) FROM projects WHERE status = 'submitted'");
        $stats['pending_projects'] = $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        // En cas d'erreur, on laisse les valeurs par défaut
    }
    
    return $stats;
}

// Obtenir les statistiques
$statistics = getStatistics($db);

// Récupérer les projets récents
try {
    $stmt = $db->query("SELECT p.project_id, p.title, p.status, u.first_name, u.last_name, pt.type_name
                       FROM projects p
                       JOIN project_members pm ON p.project_id = pm.project_id
                       JOIN users u ON pm.user_id = u.user_id
                       JOIN project_types pt ON p.project_type_id = pt.type_id
                       ORDER BY p.created_at DESC
                       LIMIT 5");
    $recent_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_projects = [];
}

// Récupérer les utilisateurs récents
try {
    $stmt = $db->query("SELECT u.user_id, u.first_name, u.last_name, u.email, r.role_name
                       FROM users u
                       JOIN roles r ON u.role_id = r.role_id
                       ORDER BY u.created_at DESC
                       LIMIT 5");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_users = [];
}

// Page actuelle pour le menu
$current_page = 'dashboard';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrateur - ENSA Gestion des Projets</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Style personnalisé -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color:rgb(92, 102, 103);
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
        .stats-card {
            border-left: 4px solid;
            border-radius: 3px;
        }
        .card-users { border-color: #4e73df; }
        .card-projects { border-color: #1cc88a; }
        .card-departments { border-color: #36b9cc; }
        .card-modules { border-color: #f6c23e; }
        .stats-icon {
            font-size: 2rem;
            opacity: 0.3;
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
                        <img src="../image/logo.png" alt="ENSA Logo" class="img-fluid mb-2" style="width: 100px; height:50px;">
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
                    <h1 class="h2">Tableau de bord administrateur</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                                <li><a class="dropdown-item" href="profile.php">Mon profil</a></li>
                                <li><a class="dropdown-item" href="settings.php">Paramètres</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Déconnexion</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Statistiques générales -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body stats-card card-users">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Utilisateurs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statistics['total_users']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300 stats-icon"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <a href="users.php" class="small text-primary">Détails <i class="fas fa-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body stats-card card-projects">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Projets</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statistics['total_projects']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-project-diagram fa-2x text-gray-300 stats-icon"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <a href="projects.php" class="small text-success">Détails <i class="fas fa-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body stats-card card-departments">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Filières</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statistics['total_departments']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-building fa-2x text-gray-300 stats-icon"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <a href="departments.php" class="small text-info">Détails <i class="fas fa-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    
                </div>

                <!-- Actions rapides -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Actions rapides</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <a href="users.php" class="btn btn-primary w-100">
                                            <i class="fas fa-user-plus me-2"></i> Nouvel utilisateur
                                        </a>
                                    </div>
                                    
                                
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <a href="rapport.php" class="btn btn-secondary w-100">
                                            <i class="fas fa-file-export me-2"></i> Exporter statistiques
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

               

                <!-- Projets récents et utilisateurs récents -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Projets récents</h5>
                                <a href="projects.php" class="btn btn-sm btn-outline-primary">Voir tous</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Titre</th>
                                                <th>Type</th>
                                                <th>Étudiant</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_projects)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">Aucun projet récent</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_projects as $project): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="../admin/projects.php?id=<?php echo $project['project_id']; ?>">
                                                                <?php echo htmlspecialchars($project['title']); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($project['type_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($project['first_name'] . ' ' . $project['last_name']); ?></td>
                                                        <td>
                                                            <?php
                                                            $status_class = '';
                                                            switch ($project['status']) {
                                                                case 'draft': $status_class = 'text-secondary'; break;
                                                                case 'submitted': $status_class = 'text-warning'; break;
                                                                case 'validated': $status_class = 'text-success'; break;
                                                                case 'rejected': $status_class = 'text-danger'; break;
                                                                case 'completed': $status_class = 'text-primary'; break;
                                                            }
                                                            ?>
                                                            <span class="<?php echo $status_class; ?>">
                                                                <?php echo ucfirst(htmlspecialchars($project['status'])); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Utilisateurs récents</h5>
                                <a href="users.php" class="btn btn-sm btn-outline-primary">Voir tous</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nom</th>
                                                <th>Email</th>
                                                <th>Rôle</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_users)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">Aucun utilisateur récent</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_users as $user): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="users.php?id=<?php echo $user['user_id']; ?>">
                                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                        <td>
                                                            <?php
                                                            $role_class = '';
                                                            switch ($user['role_name']) {
                                                                case 'admin': $role_class = 'text-danger'; break;
                                                                case 'teacher': $role_class = 'text-primary'; break;
                                                                case 'student': $role_class = 'text-success'; break;
                                                                case 'program_director': $role_class = 'text-warning'; break;
                                                            }
                                                            ?>
                                                            <span class="<?php echo $role_class; ?>">
                                                                <?php echo ucfirst(htmlspecialchars($user['role_name'])); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js initialization -->
    <script>
        // Données pour le graphique des types de projets (à remplacer par des données réelles)
        document.addEventListener('DOMContentLoaded', function() {
            fetch('../admin/api/project-stats.php')
                .then(response => response.json())
                .then(data => {
                    // Graphique de distribution par type
                    const ctxType = document.getElementById('projectTypeChart').getContext('2d');
                    new Chart(ctxType, {
                        type: 'bar',
                        data: {
                            labels: ['Stage initiation', 'Stage ingénieur adjoint', 'PFE', 'Projet module'],
                            datasets: [{
                                label: 'Nombre de projets',
                                data: [
                                    data.type_stats?.stage_initiation ?? 12,
                                    data.type_stats?.stage_ing_adj ?? 23,
                                    data.type_stats?.pfe ?? 18,
                                    data.type_stats?.projet_module ?? 34
                                ],
                                backgroundColor: [
                                    'rgba(78, 115, 223, 0.7)',
                                    'rgba(28, 200, 138, 0.7)',
                                    'rgba(54, 185, 204, 0.7)',
                                    'rgba(246, 194, 62, 0.7)'
                                ],
                                borderColor: [
                                    'rgb(78, 115, 223)',
                                    'rgb(28, 200, 138)',
                                    'rgb(54, 185, 204)',
                                    'rgb(246, 194, 62)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    
                    // Graphique des statuts
                    const ctxStatus = document.getElementById('projectStatusChart').getContext('2d');
                    new Chart(ctxStatus, {
                        type: 'doughnut',
                        data: {
                            labels: ['Brouillon', 'Soumis', 'Validé', 'Rejeté', 'Complété'],
                            datasets: [{
                                data: [
                                    data.status_stats?.draft ?? 15,
                                    data.status_stats?.submitted ?? 25,
                                    data.status_stats?.validated ?? 30,
                                    data.status_stats?.rejected ?? 5,
                                    data.status_stats?.completed ?? 40
                                ],
                                backgroundColor: [
                                    'rgba(108, 117, 125, 0.7)',
                                    'rgba(246, 194, 62, 0.7)',
                                    'rgba(28, 200, 138, 0.7)',
                                    'rgba(231, 74, 59, 0.7)',
                                    'rgba(78, 115, 223, 0.7)'
                                ],
                                borderColor: [
                                    'rgb(108, 117, 125)',
                                    'rgb(246, 194, 62)',
                                    'rgb(28, 200, 138)',
                                    'rgb(231, 74, 59)',
                                    'rgb(78, 115, 223)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des données:', error);
                    // Charger des données par défaut en cas d'erreur
                });
        });
    </script>
</body>
</html>