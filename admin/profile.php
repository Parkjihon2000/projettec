<?php
// profile.php - Page de profil utilisateur
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Connexion à la base de données
require_once '../include/connexion.php';

// Récupérer les informations de l'utilisateur connecté
try {
    $stmt = $db->prepare("SELECT u.user_id, u.first_name, u.last_name, u.email, r.role_name 
                         FROM users u 
                         JOIN roles r ON u.role_id = r.role_id 
                         WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des données: " . $e->getMessage();
}

// Ensure $user is initialized to avoid undefined variable warnings
$user = $user ?? [];

// Check if $user is empty before accessing its properties
if (empty($user)) {
    $error_message = "Utilisateur non trouvé ou erreur lors de la récupération des données.";
    $user = [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        
        'role_name' => ''
    ];
}

// Traitement du formulaire de mise à jour du profil
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
  
    
    // Validation simple
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "Tous les champs obligatoires doivent être remplis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format d'email invalide.";
    } else {
        try {
            // Vérifier si l'email existe déjà pour un autre utilisateur
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id");
            $stmt->execute(['email' => $email, 'user_id' => $_SESSION['user_id']]);
            if ($stmt->rowCount() > 0) {
                $error_message = "Cet email est déjà utilisé par un autre compte.";
            } else {
                // Mettre à jour les informations du profil
                $stmt = $db->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email WHERE user_id = :user_id");
                $stmt->execute([
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'user_id' => $_SESSION['user_id']
                ]);
                
                // Mettre à jour les informations de session
                $_SESSION['email'] = $email;
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                
                $success_message = "Votre profil a été mis à jour avec succès.";
                
                // Mettre à jour les données affichées
                $user['first_name'] = $first_name;
                $user['last_name'] = $last_name;
                $user['email'] = $email;
                
            }
        } catch (PDOException $e) {
            $error_message = "Erreur lors de la mise à jour du profil: " . $e->getMessage();
        }
    }
}

// Déterminer le lien de retour en fonction du rôle
$dashboard_link = 'dashboard.php';
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $dashboard_link = 'admin/dashboard3.php';
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
    $dashboard_link = 'teacher/dashboard2.php';
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'program_director') {
    $dashboard_link = 'admin/dashboard3.php';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - ENSA Gestion des Projets</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Style personnalisé -->
    <style>
        .profile-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .profile-header {
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <!-- Messages d'alerte -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h1 class="h3 mb-0">Mon Profil</h1>
                    </div>
                    <div class="card-body">
                        <div class="profile-header d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="h4"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                                <p class="text-muted mb-0">
                                    <span class="badge bg-info"><?php echo htmlspecialchars(ucfirst($user['role_name'])); ?></span>
                                </p>
                            </div>
                            <div>
                                <a href="dashboard3.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Retour au tableau de bord
                                </a>
                            </div>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">Prénom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                               
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="settings.php" class="btn btn-outline-primary">
                                    <i class="fas fa-cog me-2"></i> Paramètres du compte
                                </a>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>