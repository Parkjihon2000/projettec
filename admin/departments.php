<?php
// departments.php - Filières et départements
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

// Informations statiques sur les départements et filières (sans connexion BDD)
// Départements
$departments = [
    "Département Informatique, Logistique et Mathématiques",
    "Département Génie Électrique, Réseaux et Systèmes de Télécommunication",
    "Département des Enseignements Généraux"
];

// Cycle Ingénieur
$engineeringPrograms = [
    "Génie Informatique",
    "Génie Industriel",
    "Génie Électrique",
    "Génie Mécatronique",
    "Génie des Réseaux et Systèmes de Télécommunication",
    "Efficacité Énergétique et Bâtiment Intelligent"
];

// Licences Universitaires Spécialisées
$lusPrograms = [
    "Ingénierie des Systèmes Automobiles",
    "Ingénierie Logicielle et Systèmes d'Information",
    "Génie Industriel",
    "Ingénierie des Systèmes Informatiques",
    "Ingénierie Électrique, Énergies Renouvelables et Management de la Qualité (IEERMAQ)",
    "Ingénierie des Systèmes de Transport Intelligent (ITS)",
    "Ingénierie des Bases de Données et Développement",
    "Génie Mécanique, Productique et Aéronautique"
];

// Masters Universitaires Spécialisés
$musPrograms = [
    "Sécurité des Systèmes d'Information (SSI)",
    "Sécurité des Technologies Informatiques Émergentes",
    "Génie Industriel et Logistique",
    "Génie des Systèmes Intelligents (GSI)",
    "Ingénierie & Administration des Affaires (MBA)",
    "Internet des Objets et Intelligence Artificielle pour l'Industrie 4.0",
    "Génie Électromécanique des Systèmes Industriels (GESI)",
    "Management des ERP et Ingénierie de la Chaîne Logistique",
    "Big Data et Business Intelligence"
];

// Page actuelle pour le menu
$current_page = 'departments';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filières et Départements - ENSA Gestion des Projets</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .department-section {
            margin-bottom: 30px;
        }
        .department-card {
            transition: transform 0.3s;
            border-left: 4px solid #004080;
        }
        .department-card:hover {
            transform: translateY(-5px);
        }
        .department-icon {
            font-size: 2rem;
            opacity: 0.7;
            color: #004080;
        }
        .program-list {
            list-style-type: none;
            padding-left: 0;
        }
        .program-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .program-list li:last-child {
            border-bottom: none;
        }
        .program-list li::before {
            content: "\f0da";
            font-family: "Font Awesome 5 Free"; 
            font-weight: 900;
            margin-right: 10px;
            color: #004080;
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
                    <h1 class="h2">Filières et Départements</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                                <li><a class="dropdown-item" href="../profile.php">Mon profil</a></li>
                                <li><a class="dropdown-item" href="../settings.php">Paramètres</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Déconnexion</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Départements -->
                <div class="row department-section">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-university me-2 text-primary"></i>
                                    Départements
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($departments as $index => $department): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card h-100 border-0 shadow-sm department-card">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div class="department-icon me-3">
                                                            <?php 
                                                            $icons = ['fa-laptop-code', 'fa-bolt', 'fa-book'];
                                                            echo '<i class="fas ' . $icons[$index % 3] . '"></i>';
                                                            ?>
                                                        </div>
                                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($department); ?></h5>
                                                    </div>
                                                    <p class="card-text text-muted">
                                                        <?php 
                                                        // Descriptions fictives pour les départements
                                                        $descriptions = [
                                                            "Regroupe les enseignements et la recherche en informatique, logistique et mathématiques appliquées.",
                                                            "Se concentre sur l'électricité, les réseaux et les systèmes de télécommunication modernes.",
                                                            "Couvre les matières communes et transversales à toutes les formations."
                                                        ];
                                                        echo $descriptions[$index % 3];
                                                        ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filières -->
                <div class="row">
                    <!-- Cycle Ingénieur -->
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-graduation-cap me-2 text-success"></i>
                                    Filières du Cycle Ingénieur
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="program-list">
                                    <?php foreach ($engineeringPrograms as $program): ?>
                                        <li><?php echo htmlspecialchars($program); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- LUS -->
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-award me-2 text-warning"></i>
                                    Licences Universitaires Spécialisées (LUS)
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="program-list">
                                    <?php foreach ($lusPrograms as $program): ?>
                                        <li><?php echo htmlspecialchars($program); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- MUS -->
                    <div class="col-12 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-certificate me-2 text-danger"></i>
                                    Masters Universitaires Spécialisés (MUS)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php 
                                    // Diviser les masters en deux colonnes
                                    $half = ceil(count($musPrograms) / 2);
                                    $firstHalf = array_slice($musPrograms, 0, $half);
                                    $secondHalf = array_slice($musPrograms, $half);
                                    ?>
                                    <div class="col-md-6">
                                        <ul class="program-list">
                                            <?php foreach ($firstHalf as $program): ?>
                                                <li><?php echo htmlspecialchars($program); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="program-list">
                                            <?php foreach ($secondHalf as $program): ?>
                                                <li><?php echo htmlspecialchars($program); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
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
</body>
</html>