<?php
// users.php - Page de gestion des utilisateurs
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'program_director'])) {
    header('Location: ../include/login.php');
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

// Traitement des actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$messageType = '';

// Action: supprimer un utilisateur
if ($action === 'delete' && isset($_POST['user_id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $_POST['user_id']]);
        $message = "Utilisateur supprimé avec succès.";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Erreur lors de la suppression: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Action: ajouter un nouvel utilisateur
if ($action === 'create' && isset($_POST['submit'])) {
    try {
        // Validation des champs
        if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['role_id'])) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }

        // Vérifier si l'email existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute(['email' => $_POST['email']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Cet email est déjà utilisé.");
        }

        // Hachage du mot de passe
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Insertion de l'utilisateur
        $stmt = $db->prepare("INSERT INTO users (role_id, first_name, last_name, email, password_hash, student_id, department, year_of_study) 
                             VALUES (:role_id, :first_name, :last_name, :email, :password_hash, :student_id, :department, :year_of_study)");
        
        $stmt->execute([
            'role_id' => $_POST['role_id'],
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'email' => $_POST['email'],
            'password_hash' => $password_hash,
            'student_id' => !empty($_POST['student_id']) ? $_POST['student_id'] : null,
            'department' => !empty($_POST['department']) ? $_POST['department'] : null,
            'year_of_study' => !empty($_POST['year_of_study']) ? $_POST['year_of_study'] : null
        ]);

        $message = "Utilisateur ajouté avec succès.";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Erreur: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Action: mettre à jour un utilisateur
if ($action === 'update' && isset($_POST['submit'])) {
    try {
        // Validation des champs
        if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email']) || empty($_POST['role_id'])) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }

        // Vérifier si l'email existe déjà (sauf pour cet utilisateur)
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :user_id");
        $stmt->execute([
            'email' => $_POST['email'],
            'user_id' => $_POST['user_id']
        ]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Cet email est déjà utilisé par un autre utilisateur.");
        }

        // Mise à jour de l'utilisateur
        $sql = "UPDATE users SET 
                role_id = :role_id,
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                student_id = :student_id,
                department = :department,
                year_of_study = :year_of_study,
                active = :active";
        
        // Ajouter mise à jour du mot de passe si un nouveau est fourni
        $params = [
            'user_id' => $_POST['user_id'],
            'role_id' => $_POST['role_id'],
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'email' => $_POST['email'],
            'student_id' => !empty($_POST['student_id']) ? $_POST['student_id'] : null,
            'department' => !empty($_POST['department']) ? $_POST['department'] : null,
            'year_of_study' => !empty($_POST['year_of_study']) ? $_POST['year_of_study'] : null,
            'active' => isset($_POST['active']) ? 1 : 0
        ];
        
        if (!empty($_POST['password'])) {
            $sql .= ", password_hash = :password_hash";
            $params['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        $sql .= " WHERE user_id = :user_id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $message = "Utilisateur mis à jour avec succès.";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Erreur: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Récupérer tous les rôles
try {
    $stmt = $db->query("SELECT role_id, role_name FROM roles ORDER BY role_name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $roles = [];
}

// Récupérer tous les départements
try {
    $stmt = $db->query("SELECT department_id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

// Filtrage et pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Construction de la requête SQL
$sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.student_id, u.department, 
               u.year_of_study, u.active, u.created_at, r.role_name 
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.student_id LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($role_filter)) {
    $sql .= " AND r.role_id = :role_filter";
    $params['role_filter'] = $role_filter;
}

if (!empty($department_filter)) {
    $sql .= " AND u.department = :department_filter";
    $params['department_filter'] = $department_filter;
}

// Compte total pour pagination
$count_sql = str_replace("SELECT u.user_id, u.first_name, u.last_name, u.email, u.student_id, u.department, 
               u.year_of_study, u.active, u.created_at, r.role_name", "SELECT COUNT(*)", $sql);
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();

$total_pages = ceil($total_users / $per_page);

// Récupérer les utilisateurs avec pagination
$sql .= " ORDER BY u.created_at DESC LIMIT :offset, :per_page";
$params['offset'] = $offset;
$params['per_page'] = $per_page;

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer un utilisateur spécifique pour l'édition
$edit_user = null;
if ($action === 'edit' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $_GET['id']]);
        $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = "Erreur lors de la récupération des données: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Page actuelle pour le menu
$current_page = 'users';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - ENSA Gestion des Projets</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #6c757d;
        }
        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-active {
            background-color: #28a745;
        }
        .status-inactive {
            background-color: #dc3545;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .filters-container {
            background-color: #f8f9fa;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
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
                    <h1 class="h2">Gestion des utilisateurs</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus me-1"></i> Ajouter un utilisateur
                        </button>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtres -->
                <div class="filters-container">
                    <form method="GET" action="users.php" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" placeholder="Rechercher..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="role">
                                <option value="">Tous les rôles</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['role_id']; ?>" <?php echo $role_filter == $role['role_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="department">
                                <option value="">Toutes les filières</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['name']; ?>" <?php echo $department_filter == $dept['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                        </div>
                    </form>
                </div>

                <!-- Liste des utilisateurs -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="usersTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Email</th>
                                        <th>Rôle</th>
                                        <th>Filière</th>
                                        <th>Statut</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Aucun utilisateur trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-3">
                                                            <?php if (!empty($user['profile_picture'])): ?>
                                                                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Avatar" class="img-fluid">
                                                            <?php else: ?>
                                                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                            <?php if (!empty($user['student_id'])): ?>
                                                                <small class="text-muted"><?php echo htmlspecialchars($user['student_id']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
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
                                                <td>
                                                    <?php echo !empty($user['department']) ? htmlspecialchars($user['department']) : '-'; ?>
                                                    <?php if (!empty($user['year_of_study'])): ?>
                                                        <small class="text-muted">(<?php echo $user['year_of_study']; ?>ème année)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-dot <?php echo $user['active'] ? 'status-active' : 'status-inactive'; ?>"></span>
                                                    <?php echo $user['active'] ? 'Actif' : 'Inactif'; ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="action-buttons">
                                                        <a href="users.php?action=edit&id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteUserModal" 
                                                                data-user-id="<?php echo $user['user_id']; ?>"
                                                                data-user-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
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

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($start_page + 4, $total_pages);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>"><?php echo $total_pages; ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal Ajouter Utilisateur -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Ajouter un utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="users.php?action=create" method="POST">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">Prénom*</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Nom*</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email*</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Mot de passe*</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="role_id" class="form-label">Rôle*</label>
                                <select class="form-select" id="role_id" name="role_id" required onchange="toggleStudentFields(this.value)">
                                    <option value="">Sélectionner un rôle</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div id="studentFields" style="display: none;">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="student_id" class="form-label">Numéro étudiant</label>
                                    <input type="text" class="form-control" id="student_id" name="student_id">
                                </div>
                                <div class="col-md-6">
                                    <label for="department" class="form-label">Filière</label>
                                    <select class="form-select" id="department" name="department">
                                        <option value="">Sélectionner une filière</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['name']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="year_of_study" class="form-label">Année d'étude</label>
                                    <select class="form-select" id="year_of_study" name="year_of_study">
                                        <option value="">Sélectionner</option>
                                        <option value="1">1ère année</option>
                                        <option value="2">2ème année</option>
                                        <option value="3">3ème année</option>
                                        <option value="4">4ème année</option>
                                        <option value="5">5ème année</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Éditer Utilisateur -->
    <?php if ($edit_user): ?>
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Modifier l'utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="users.php?action=update" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_first_name" class="form-label">Prénom*</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" value="<?php echo htmlspecialchars($edit_user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_last_name" class="form-label">Nom*</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" value="<?php echo htmlspecialchars($edit_user['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_email" class="form-label">Email*</label>
                                <input type="email" class="form-control" id="edit_email" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="edit_password" name="password" placeholder="Laisser vide pour conserver l'actuel">
                                <small class="form-text text-muted">Laisser vide si vous ne souhaitez pas modifier le mot de passe</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_role_id" class="form-label">Rôle*</label>
                                <select class="form-select" id="edit_role_id" name="role_id" required onchange="toggleEditStudentFields(this.value)">
                                    <option value="">Sélectionner un rôle</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['role_id']; ?>" <?php echo $edit_user['role_id'] == $role['role_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="edit_active" name="active" <?php echo $edit_user['active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="edit_active">
                                        Compte actif
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div id="editStudentFields" style="display: <?php echo in_array($edit_user['role_id'], [1]) ? 'block' : 'none'; ?>;">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="edit_student_id" class="form-label">Numéro étudiant</label>
                                    <input type="text" class="form-control" id="edit_student_id" name="student_id" value="<?php echo htmlspecialchars($edit_user['student_id'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_department" class="form-label">Filière</label>
                                    <select class="form-select" id="edit_department" name="department">
                                        <option value="">Sélectionner une filière</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['name']; ?>" <?php echo ($edit_user['department'] ?? '') == $dept['name'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="edit_year_of_study" class="form-label">Année d'étude</label>
                                    <select class="form-select" id="edit_year_of_study" name="year_of_study">
                                        <option value="">Sélectionner</option>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo ($edit_user['year_of_study'] ?? '') == $i ? 'selected' : ''; ?>>
                                                <?php echo $i; ?>ème année
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Supprimer Utilisateur -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer l'utilisateur <strong id="deleteUserName"></strong> ?</p>
                    <p class="text-danger"><small>Cette action est irréversible et supprimera toutes les données associées à cet utilisateur.</small></p>
                </div>
                <div class="modal-footer">
                    <form action="users.php?action=delete" method="POST">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery JS -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialiser DataTables sans pagination (car on utilise la pagination personnalisée)
            $('#usersTable').DataTable({
                "paging": false,
                "info": false,
                "searching": false,
                "language": {
                    "emptyTable": "Aucun utilisateur trouvé",
                    "zeroRecords": "Aucun utilisateur trouvé"
                }
            });
            
            // Afficher le modal d'édition automatiquement si demandé
            <?php if ($action === 'edit' && $edit_user): ?>
                var editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                editUserModal.show();
            <?php endif; ?>
            
            // Configuration du modal de suppression
            $('#deleteUserModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var userId = button.data('user-id');
                var userName = button.data('user-name');
                
                $('#deleteUserId').val(userId);
                $('#deleteUserName').text(userName);
            });
        });
        
        // Fonction pour afficher/masquer les champs spécifiques aux étudiants lors de l'ajout
        function toggleStudentFields(roleId) {
            var studentFields = document.getElementById('studentFields');
            if (roleId == 3) { // Supposons que 3 soit l'ID du rôle étudiant
                studentFields.style.display = 'block';
            } else {
                studentFields.style.display = 'none';
            }
        }
        
        // Fonction pour afficher/masquer les champs spécifiques aux étudiants lors de l'édition
        function toggleEditStudentFields(roleId) {
            var studentFields = document.getElementById('editStudentFields');
            if (roleId == 3) { // Supposons que 3 soit l'ID du rôle étudiant
                studentFields.style.display = 'block';
            } else {
                studentFields.style.display = 'none';
            }
        }
    </script>
</body>
</html>