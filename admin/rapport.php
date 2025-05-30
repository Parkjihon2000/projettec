<?php
// reports.php - Rapports et statistiques des projets
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'program_director', 'teacher'])) {
    header('Location: ../include/login.php');
    exit();
}

// Connexion à la base de données
require_once '../include/connexion.php';

// Traitement des actions
$message = '';
$error = '';

// Traitement de l'exportation
if (isset($_GET['action']) && $_GET['action'] == 'export') {
    $export_format = $_GET['format'] ?? 'excel';
    
    // Selon le format choisi, on prépare l'export
    if ($export_format == 'excel') {
        // En-têtes pour Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="ensa_projets_stats_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: max-age=0');
        
        // On va récupérer les données et les formater en excel plus bas
        $export_mode = true;
    } elseif ($export_format == 'pdf') {
        // Pour PDF, on utiliserait une bibliothèque comme FPDF ou TCPDF
        // Mais pour cet exemple, on simule juste l'export
        $message = "L'export PDF serait généré ici. Intégrez une bibliothèque comme FPDF pour l'implémenter.";
        $export_mode = false;
    } elseif ($export_format == 'csv') {
        // En-têtes pour CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="ensa_projets_stats_' . date('Y-m-d') . '.csv"');
        header('Cache-Control: max-age=0');
        
        $export_mode = true;
    } else {
        $export_mode = false;
    }
} else {
    $export_mode = false;
}

// Récupération des statistiques
function getProjectStats($db) {
    $stats = [];
    
    try {
        // Statistiques par type de projet
        $stmt = $db->query("SELECT pt.type_name, COUNT(p.project_id) as count
                           FROM projects p
                           JOIN project_types pt ON p.project_type_id = pt.type_id
                           GROUP BY pt.type_name
                           ORDER BY count DESC");
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Statistiques par filière
        $stmt = $db->query("SELECT d.name as department, COUNT(p.project_id) as count
                           FROM projects p
                           JOIN modules m ON p.module_id = m.module_id
                           JOIN departments d ON m.department_id = d.department_id
                           GROUP BY d.name
                           ORDER BY count DESC");
        $stats['by_department'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Statistiques par statut
        $stmt = $db->query("SELECT status, COUNT(project_id) as count
                           FROM projects
                           GROUP BY status
                           ORDER BY count DESC");
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Statistiques par année académique
        $stmt = $db->query("SELECT ay.year_label, COUNT(p.project_id) as count
                           FROM projects p
                           JOIN academic_years ay ON p.academic_year_id = ay.year_id
                           GROUP BY ay.year_label
                           ORDER BY ay.year_label DESC");
        $stats['by_academic_year'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Les 5 tags les plus utilisés
        $stmt = $db->query("SELECT t.tag_name, COUNT(pt.project_id) as count
                           FROM project_tags pt
                           JOIN tags t ON pt.tag_id = t.tag_id
                           GROUP BY t.tag_name
                           ORDER BY count DESC
                           LIMIT 5");
        $stats['top_tags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Évaluations moyennes par type de projet
        $stmt = $db->query("SELECT pt.type_name, AVG(e.grade) as avg_grade
                           FROM evaluations e
                           JOIN projects p ON e.project_id = p.project_id
                           JOIN project_types pt ON p.project_type_id = pt.type_id
                           GROUP BY pt.type_name
                           ORDER BY avg_grade DESC");
        $stats['avg_grades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Projets par mois (tendance)
        $stmt = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(project_id) as count
                           FROM projects
                           GROUP BY month
                           ORDER BY month DESC
                           LIMIT 12");
        $stats['by_month'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organisations les plus actives (pour les stages)
        $stmt = $db->query("SELECT o.name as organization, COUNT(p.project_id) as count
                           FROM projects p
                           JOIN organizations o ON p.organization_id = o.organization_id
                           GROUP BY o.name
                           ORDER BY count DESC
                           LIMIT 10");
        $stats['top_organizations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // En cas d'erreur
        return ['error' => $e->getMessage()];
    }
    
    return $stats;
}

// Récupération des statistiques détaillées
$stats = getProjectStats($db);

// Si mode export, on génère le fichier
if ($export_mode) {
    if ($export_format == 'excel') {
        // Début du document Excel
        echo "<html xmlns:x=\"urn:schemas-microsoft-com:office:excel\">
              <head>
              <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
              <style>
              table { border-collapse: collapse; }
              td, th { border: 1px solid #000; padding: 5px; }
              th { background-color: #f2f2f2; }
              </style>
              </head>
              <body>";
        
        // Titre
        echo "<h1>Statistiques des projets ENSA - " . date('d/m/Y') . "</h1>";
        
        // Par type de projet
        echo "<h2>Projets par type</h2>";
        echo "<table border='1'>
              <tr><th>Type de projet</th><th>Nombre</th></tr>";
        foreach ($stats['by_type'] as $row) {
            echo "<tr><td>" . $row['type_name'] . "</td><td>" . $row['count'] . "</td></tr>";
        }
        echo "</table><br>";
        
        // Par filière
        echo "<h2>Projets par filière</h2>";
        echo "<table border='1'>
              <tr><th>Filière</th><th>Nombre</th></tr>";
        foreach ($stats['by_department'] as $row) {
            echo "<tr><td>" . $row['department'] . "</td><td>" . $row['count'] . "</td></tr>";
        }
        echo "</table><br>";
        
        // Par statut
        echo "<h2>Projets par statut</h2>";
        echo "<table border='1'>
              <tr><th>Statut</th><th>Nombre</th></tr>";
        foreach ($stats['by_status'] as $row) {
            echo "<tr><td>" . ucfirst($row['status']) . "</td><td>" . $row['count'] . "</td></tr>";
        }
        echo "</table><br>";
        
        // Par année académique
        echo "<h2>Projets par année académique</h2>";
        echo "<table border='1'>
              <tr><th>Année académique</th><th>Nombre</th></tr>";
        foreach ($stats['by_academic_year'] as $row) {
            echo "<tr><td>" . $row['year_label'] . "</td><td>" . $row['count'] . "</td></tr>";
        }
        echo "</table><br>";
        
        // Notes moyennes
        echo "<h2>Notes moyennes par type de projet</h2>";
        echo "<table border='1'>
              <tr><th>Type de projet</th><th>Note moyenne</th></tr>";
        foreach ($stats['avg_grades'] as $row) {
            echo "<tr><td>" . $row['type_name'] . "</td><td>" . number_format($row['avg_grade'], 2) . "/20</td></tr>";
        }
        echo "</table><br>";
        
        // Tendance mensuelle
        echo "<h2>Tendance mensuelle</h2>";
        echo "<table border='1'>
              <tr><th>Mois</th><th>Nombre de projets</th></tr>";
        foreach ($stats['by_month'] as $row) {
            echo "<tr><td>" . $row['month'] . "</td><td>" . $row['count'] . "</td></tr>";
        }
        echo "</table><br>";
        
        // Organisations
        echo "<h2>Organisations les plus actives</h2>";
        echo "<table border='1'>
              <tr><th>Organisation</th><th>Nombre de projets</th></tr>";
        foreach ($stats['top_organizations'] as $row) {
            echo "<tr><td>" . $row['organization'] . "</td><td>" . $row['count'] . "</td></tr>";
        }
        echo "</table>";
        
        echo "</body></html>";
        exit;
    } elseif ($export_format == 'csv') {
        // Fonction pour formater une ligne CSV
        function formatCSVLine($data) {
            return implode(',', array_map(function($item) {
                return '"' . str_replace('"', '""', $item) . '"';
            }, $data)) . "\n";
        }
        
        // Par type de projet
        echo formatCSVLine(['Type de projet', 'Nombre']);
        foreach ($stats['by_type'] as $row) {
            echo formatCSVLine([$row['type_name'], $row['count']]);
        }
        echo "\n";
        
        // Par filière
        echo formatCSVLine(['Filière', 'Nombre']);
        foreach ($stats['by_department'] as $row) {
            echo formatCSVLine([$row['department'], $row['count']]);
        }
        echo "\n";
        
        // Par statut
        echo formatCSVLine(['Statut', 'Nombre']);
        foreach ($stats['by_status'] as $row) {
            echo formatCSVLine([ucfirst($row['status']), $row['count']]);
        }
        
        exit;
    }
}

// Statistiques complémentaires pour le tableau de bord
function getAdditionalStats($db) {
    $addStats = [];
    
    try {
        // Projets par enseignant superviseur
        $stmt = $db->query("SELECT CONCAT(u.first_name, ' ', u.last_name) as supervisor, COUNT(ps.project_id) as count
                           FROM project_supervisors ps
                           JOIN users u ON ps.user_id = u.user_id
                           WHERE ps.supervision_role = 'academic_supervisor'
                           GROUP BY ps.user_id
                           ORDER BY count DESC
                           LIMIT 5");
        $addStats['top_supervisors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Projets par module
        $stmt = $db->query("SELECT m.module_name, COUNT(p.project_id) as count
                           FROM projects p
                           JOIN modules m ON p.module_id = m.module_id
                           GROUP BY m.module_id
                           ORDER BY count DESC
                           LIMIT 5");
        $addStats['top_modules'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Nombre de projets par étudiant (moyenne)
        $stmt = $db->query("SELECT COUNT(DISTINCT pm.project_id) / COUNT(DISTINCT pm.user_id) as avg_projects_per_student
                           FROM project_members pm");
        $addStats['avg_projects_per_student'] = $stmt->fetchColumn();
        
        // Étudiants les plus actifs (nombre de projets)
        $stmt = $db->query("SELECT CONCAT(u.first_name, ' ', u.last_name) as student, COUNT(pm.project_id) as count
                           FROM project_members pm
                           JOIN users u ON pm.user_id = u.user_id
                           GROUP BY pm.user_id
                           ORDER BY count DESC
                           LIMIT 5");
        $addStats['top_students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Données pour carte thermique des projets par année et type
        $stmt = $db->query("SELECT ay.year_label, pt.type_name, COUNT(p.project_id) as count
                           FROM projects p
                           JOIN academic_years ay ON p.academic_year_id = ay.year_id
                           JOIN project_types pt ON p.project_type_id = pt.type_id
                           GROUP BY ay.year_label, pt.type_name
                           ORDER BY ay.year_label, pt.type_name");
        $addStats['heatmap_data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // En cas d'erreur
        return ['error' => $e->getMessage()];
    }
    
    return $addStats;
}

// Récupération des statistiques additionnelles
$addStats = getAdditionalStats($db);

// Page actuelle pour le menu
$current_page = 'reports';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports et Statistiques - ENSA Gestion des Projets</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Datatable pour les tableaux -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <!-- Styles personnalisés -->
    <link rel="stylesheet" href="assets/css/admin.css">
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
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
        }
        .export-btn {
            margin-right: 10px;
        }
        .stats-card {
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .table-stats th {
            background-color: #f8f9fa;
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
                    <h1 class="h2">Rapports et Statistiques</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="dropdown me-2">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-download me-1"></i> Exporter
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                                <li><a class="dropdown-item" href="?action=export&format=excel"><i class="far fa-file-excel text-success me-2"></i> Excel (.xls)</a></li>
                                <li><a class="dropdown-item" href="?action=export&format=pdf"><i class="far fa-file-pdf text-danger me-2"></i> PDF</a></li>
                                <li><a class="dropdown-item" href="?action=export&format=csv"><i class="fas fa-file-csv text-primary me-2"></i> CSV</a></li>
                            </ul>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Imprimer
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Filtres -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Filtres</h5>
                    </div>
                    <div class="card-body">
                        <form class="row g-3" id="filter-form" method="get">
                            <div class="col-md-3">
                                <label for="academic-year" class="form-label">Année académique</label>
                                <select class="form-select" id="academic-year" name="academic_year">
                                    <option value="">Toutes les années</option>
                                    <?php foreach ($stats['by_academic_year'] as $year): ?>
                                    <option value="<?php echo $year['year_label']; ?>"><?php echo $year['year_label']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="project-type" class="form-label">Type de projet</label>
                                <select class="form-select" id="project-type" name="project_type">
                                    <option value="">Tous les types</option>
                                    <?php foreach ($stats['by_type'] as $type): ?>
                                    <option value="<?php echo $type['type_name']; ?>"><?php echo $type['type_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="department" class="form-label">Filière</label>
                                <select class="form-select" id="department" name="department">
                                    <option value="">Toutes les filières</option>
                                    <?php foreach ($stats['by_department'] as $dept): ?>
                                    <option value="<?php echo $dept['department']; ?>"><?php echo $dept['department']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter me-1"></i> Filtrer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Aperçu général -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-4">
                        <div class="card border-0 shadow-sm h-100 stats-card">
                            <div class="card-body text-center">
                                <div class="display-4 text-primary mb-2">
                                    <i class="fas fa-project-diagram"></i>
                                </div>
                                <h2 class="h3 mb-0"><?php echo array_sum(array_column($stats['by_type'], 'count')); ?></h2>
                                <p class="text-muted">Projets au total</p>
                            </div>
                        </div>
                    </div>
                   
                    <div class="col-md-3 mb-4">
                        <div class="card border-0 shadow-sm h-100 stats-card">
                            <div class="card-body text-center">
                                <div class="display-4 text-warning mb-2">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <h2 class="h3 mb-0"><?php echo number_format($addStats['avg_projects_per_student'], 2); ?></h2>
                                <p class="text-muted">Projets par étudiant</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card border-0 shadow-sm h-100 stats-card">
                            <div class="card-body text-center">
                                <div class="display-4 text-info mb-2">
                                    <i class="fas fa-star"></i>
                                </div>
                                <?php
                                $avg_grade = 0;
                                $total_counts = 0;
                                foreach ($stats['avg_grades'] as $grade) {
                                    $avg_grade += $grade['avg_grade'];
                                    $total_counts++;
                                }
                                $overall_avg = $total_counts > 0 ? $avg_grade / $total_counts : 0;
                                ?>
                                <h2 class="h3 mb-0"><?php echo number_format($overall_avg, 2); ?>/20</h2>
                                <p class="text-muted">Note moyenne globale</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphiques principaux -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Projets par type</h5>
                                <button class="btn btn-sm btn-outline-secondary" onclick="downloadChart('typeChart', 'projets_par_type')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="typeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Projets par filière</h5>
                                <button class="btn btn-sm btn-outline-secondary" onclick="downloadChart('departmentChart', 'projets_par_filiere')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="departmentChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Projets par statut</h5>
                                <button class="btn btn-sm btn-outline-secondary" onclick="downloadChart('statusChart', 'projets_par_statut')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
<div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Évolution mensuelle</h5>
                                <button class="btn btn-sm btn-outline-secondary" onclick="downloadChart('monthlyChart', 'evolution_mensuelle')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableaux de données détaillées -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Notes moyennes par type de projet</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover table-stats">
                                        <thead>
                                            <tr>
                                                <th>Type de projet</th>
                                                <th class="text-end">Note moyenne</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats['avg_grades'] as $grade): ?>
                                            <tr>
                                                <td><?php echo $grade['type_name']; ?></td>
                                                <td class="text-end"><?php echo number_format($grade['avg_grade'], 2); ?>/20</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Organisations les plus actives</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover table-stats">
                                        <thead>
                                            <tr>
                                                <th>Organisation</th>
                                                <th class="text-end">Nombre de projets</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats['top_organizations'] as $org): ?>
                                            <tr>
                                                <td><?php echo $org['organization']; ?></td>
                                                <td class="text-end"><?php echo $org['count']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Enseignants les plus actifs</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover table-stats">
                                        <thead>
                                            <tr>
                                                <th>Enseignant</th>
                                                <th class="text-end">Projets supervisés</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($addStats['top_supervisors'] as $supervisor): ?>
                                            <tr>
                                                <td><?php echo $supervisor['supervisor']; ?></td>
                                                <td class="text-end"><?php echo $supervisor['count']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Étudiants les plus actifs</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover table-stats">
                                        <thead>
                                            <tr>
                                                <th>Étudiant</th>
                                                <th class="text-end">Nombre de projets</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($addStats['top_students'] as $student): ?>
                                            <tr>
                                                <td><?php echo $student['student']; ?></td>
                                                <td class="text-end"><?php echo $student['count']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tags et Modules -->
                <div class="row mb-4">
                    
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Top 5 des modules</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover table-stats">
                                        <thead>
                                            <tr>
                                                <th>Module</th>
                                                <th class="text-end">Nombre de projets</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($addStats['top_modules'] as $module): ?>
                                            <tr>
                                                <td><?php echo $module['module_name']; ?></td>
                                                <td class="text-end"><?php echo $module['count']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Carte thermique -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Distribution des projets par année et type</h5>
                            </div>
                            <div class="card-body">
                                <div style="height: 400px;">
                                    <canvas id="heatmapChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery pour Datatables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Datatables JS -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <!-- FileSaver.js pour télécharger les graphiques -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    <script>
        // Initialisation des dataTables
        $(document).ready(function() {
            $('.table-stats').DataTable({
                "paging": true,
                "ordering": true,
                "info": true,
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.3/i18n/fr_fr.json"
                },
                "pageLength": 5,
                "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "Tous"]]
            });
        });

        // Fonction pour télécharger un graphique
        function downloadChart(chartId, filename) {
            var canvas = document.getElementById(chartId);
            canvas.toBlob(function(blob) {
                saveAs(blob, filename + '.png');
            });
        }

        // Graphique par type de projet (Pie Chart)
        var typeCtx = document.getElementById('typeChart').getContext('2d');
        var typeChart = new Chart(typeCtx, {
            type: 'pie',
            data: {
                labels: [<?php echo implode(', ', array_map(function($item) { return '"' . $item['type_name'] . '"'; }, $stats['by_type'])); ?>],
                datasets: [{
                    data: [<?php echo implode(', ', array_map(function($item) { return $item['count']; }, $stats['by_type'])); ?>],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(201, 203, 207, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(101, 143, 255, 0.7)',
                        'rgba(255, 180, 90, 0.7)',
                        'rgba(90, 200, 250, 0.7)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(201, 203, 207, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(101, 143, 255, 1)',
                        'rgba(255, 180, 90, 1)',
                        'rgba(90, 200, 250, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: false,
                        text: 'Projets par type'
                    }
                }
            }
        });

        // Graphique par filière (Bar Chart)
        var deptCtx = document.getElementById('departmentChart').getContext('2d');
        var deptChart = new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(', ', array_map(function($item) { return '"' . $item['department'] . '"'; }, $stats['by_department'])); ?>],
                datasets: [{
                    label: 'Nombre de projets',
                    data: [<?php echo implode(', ', array_map(function($item) { return $item['count']; }, $stats['by_department'])); ?>],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Graphique par statut (Doughnut Chart)
        var statusCtx = document.getElementById('statusChart').getContext('2d');
        var statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(', ', array_map(function($item) { return '"' . ucfirst($item['status']) . '"'; }, $stats['by_status'])); ?>],
                datasets: [{
                    data: [<?php echo implode(', ', array_map(function($item) { return $item['count']; }, $stats['by_status'])); ?>],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // Graphique évolution mensuelle (Line Chart)
        var monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        var monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(', ', array_map(function($item) { return '"' . $item['month'] . '"'; }, $stats['by_month'])); ?>],
                datasets: [{
                    label: 'Nombre de projets',
                    data: [<?php echo implode(', ', array_map(function($item) { return $item['count']; }, $stats['by_month'])); ?>],
                    backgroundColor: 'rgba(153, 102, 255, 0.2)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Removed the tagsChart initialization as the corresponding canvas is no longer present.

        // Préparation des données pour la heatmap
        function prepareHeatmapData() {
            var years = [];
            var types = [];
            var data = [];
            
            <?php
            // Extraire les années et types uniques
            $years = [];
            $types = [];
            foreach ($addStats['heatmap_data'] as $item) {
                if (!in_array($item['year_label'], $years)) {
                    $years[] = $item['year_label'];
                }
                if (!in_array($item['type_name'], $types)) {
                    $types[] = $item['type_name'];
                }
            }
            sort($years);
            ?>
            
            // Années et types
            var years = <?php echo json_encode($years); ?>;
            var types = <?php echo json_encode($types); ?>;
            
            // Créer un tableau de données pour la heatmap
            var heatmapData = <?php echo json_encode($addStats['heatmap_data']); ?>;
            
            return {
                years: years,
                types: types,
                data: heatmapData
            };
        }

        // Création de la heatmap avec Chart.js
        var heatmapData = prepareHeatmapData();
        var heatmapCtx = document.getElementById('heatmapChart').getContext('2d');

        // Préparer les datasets pour la heatmap
        var datasets = [];
        heatmapData.types.forEach(function(type, index) {
            var data = heatmapData.years.map(function(year) {
                var found = heatmapData.data.find(function(item) {
                    return item.year_label === year && item.type_name === type;
                });
                return found ? found.count : 0;
            });
            
            datasets.push({
                label: type,
                data: data,
                backgroundColor: getColor(index, 0.7),
                borderColor: getColor(index, 1),
                borderWidth: 1
            });
        });

        function getColor(index, alpha) {
            var colors = [
                `rgba(255, 99, 132, ${alpha})`,
                `rgba(54, 162, 235, ${alpha})`,
                `rgba(255, 206, 86, ${alpha})`,
                `rgba(75, 192, 192, ${alpha})`,
                `rgba(153, 102, 255, ${alpha})`,
                `rgba(255, 159, 64, ${alpha})`,
                `rgba(199, 199, 199, ${alpha})`,
                `rgba(83, 102, 255, ${alpha})`,
                `rgba(40, 159, 64, ${alpha})`,
                `rgba(210, 199, 199, ${alpha})`
            ];
            return colors[index % colors.length];
        }

        var heatmapChart = new Chart(heatmapCtx, {
            type: 'bar',
            data: {
                labels: heatmapData.years,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true,
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>