<?php
// settings.php - Page de paramètres utilisateur
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Connexion à la base de données
require_once '../include/connexion.php';

// Messages
$success_message = '';
$error_message = '';

// Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Tous les champs sont obligatoires.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Les nouveaux mots de passe ne correspondent pas.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    } else {
        try {
            // Vérifier le mot de passe actuel
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($current_password, $user['password_hash'])) {
                // Hasher le nouveau mot de passe
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Mettre à jour le mot de passe
                $stmt = $db->prepare("UPDATE users SET password_hash = :password WHERE user_id = :user_id");
                $stmt->execute([
                    'password' => $hashed_password,
                    'user_id' => $_SESSION['user_id']
                ]);
                
                $success_message = "Votre mot de passe a été modifié avec succès.";
            } else {
                $error_message = "Le mot de passe actuel est incorrect.";
            }
        } catch (PDOException $e) {
            $error_message = "Erreur lors de la modification du mot de passe: " . $e->getMessage();
        }
    }
}

// Traitement du formulaire des préférences de notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $site_notifications = isset($_POST['site_notifications']) ? 1 : 0;
    
    try {
        // Vérifier si des préférences existent déjà
        $stmt = $db->prepare("SELECT * FROM user_preferences WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            // Mettre à jour les préférences existantes
            $stmt = $db->prepare("UPDATE user_preferences SET 
                                 email_notifications = :email_notifications, 
                                 site_notifications = :site_notifications 
                                 WHERE user_id = :user_id");
        } else {
            // Insérer de nouvelles préférences
            $stmt = $db->prepare("INSERT INTO user_preferences 
                                 (user_id, email_notifications, site_notifications) VALUES 
                                 (:user_id, :email_notifications, :site_notifications)");
        }
        
        $stmt->execute([
            'user_id' => $_SESSION['user_id'],
            'email_notifications' => $email_notifications,
            'site_notifications' => $site_notifications
        ]);
        
        $success_message = "Vos préférences ont été mises à jour avec succès.";
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la mise à jour des préférences: " . $e->getMessage();
    }
}

// Récupérer les préférences actuelles
try {
    $stmt = $db->prepare("SELECT * FROM user_preferences WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Valeurs par défaut si aucune préférence n'existe
    if (!$preferences) {
        $preferences = [
            'email_notifications' => 1,
            'site_notifications' => 1
        ];
    }
} catch (PDOException $e) {
    // En cas d'erreur, utiliser des valeurs par défaut
    $preferences = [
        'email_notifications' => 1,
        'site_notifications' => 1
    ];
}

// Déterminer le lien de retour en fonction du rôle
$dashboard_link = 'dashboard.php';
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $dashboard_link = 'dashboard3.php';
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
    $dashboard_link = 'teacher/dashboard2.php';
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'program_director') {
    $dashboard_link = 'dashboard3.php';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - ENSA Gestion des Projets</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Style personnalisé -->
    <style>
        .settings-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h1 class="h3 mb-0">Paramètres du compte</h1>
                        <a href="dashboard3.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-2"></i> Retour au tableau de bord
                        </a>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="security-tab" data-bs-toggle="tab" 
                                        data-bs-target="#security" type="button" role="tab" 
                                        aria-controls="security" aria-selected="true">
                                    <i class="fas fa-lock me-2"></i> Sécurité
                                </button>
                            </li>
                            
                        </ul>
                        
                        <div class="tab-content p-3" id="settingsTabsContent">
                            <!-- Onglet Sécurité -->
                            <div class="tab-pane fade show active" id="security" role="tabpanel" aria-labelledby="security-tab">
                                <h4 class="mb-4">Modifier votre mot de passe</h4>
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Mot de passe actuel <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="form-text">Le mot de passe doit contenir au moins 8 caractères.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="fas fa-check me-2"></i> Modifier le mot de passe
                                    </button>
                                </form>
                            </div>
                          
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="profile.php" class="btn btn-outline-primary">
                        <i class="fas fa-user me-2"></i> Retour au profil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>