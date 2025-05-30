<?php
// fichier login.php - Formulaire de connexion
session_start();

// Vérifier si l'utilisateur est déjà connecté, si oui rediriger selon son rôle
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    redirectByRole($_SESSION['role']);
    exit();
}

// Configuration de la connexion à la base de données
$host = 'localhost';
$dbname = 'ensa_project_db';
$username = 'root';
$password = '';

// Message d'erreur
$error = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Connexion à la base de données
        $db= new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Récupération des données du formulaire
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        // Préparer la requête pour récupérer l'utilisateur
        $stmt = $db->prepare("SELECT u.user_id, u.password_hash, r.role_name 
                              FROM users u 
                              JOIN roles r ON u.role_id = r.role_id 
                              WHERE u.email = :email AND u.active = TRUE");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Vérifier si l'utilisateur existe et le mot de passe est correct
        if ($user && password_verify($password, $user['password_hash'])) {
            // Créer la session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role_name'];
            
            // Enregistrer la date de dernière connexion
            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
            $updateStmt->execute(['user_id' => $user['user_id']]);
            
            // Rediriger selon le rôle
            redirectByRole($user['role_name']);
            exit();
        } else {
            $error = "Email ou mot de passe incorrect.";
        }
    } catch (PDOException $e) {
        $error = "Erreur de connexion à la base de données: " . $e->getMessage();
    }
}

// Fonction de redirection selon le rôle
function redirectByRole($role) {
    switch ($role) {
        case 'student':
            header('Location:dashboard1.php');
            break;
        case 'teacher':
            header('Location:dashboard2.php');
            break;
        case 'admin':
        case 'program_director':
            header('Location:../admin/dashboard3.php');
            break;
        default:
            // Si le rôle n'est pas reconnu, déconnexion et redirection vers la page de connexion
            session_destroy();
            header('Location: login.php?error=role_inconnu');
            break;
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ENSA Gestion des Projets</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
     body {
            background-image: url('../image/capture.png'); /* Path to your image */
            background-size: cover; /* Ensure the image covers the entire screen */
            background-position: center; /* Center the image */
            background-repeat: no-repeat; /* Prevent the image from repeating */
            height: 100vh; /* Full viewport height */
        }
        .card {
            background-color: rgba(255, 255, 255, 0.9); /* White with 90% opacity */
            border-radius: 10px;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Black with 50% opacity */
            z-index: 1; /* Place it behind the content */
        }

        /* Ensure the card content is above the overlay */
        .container {
            position: relative;
            z-index: 2;
        }
        </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4>ENSA - Gestion des Projets</h4>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title text-center mb-4">Connexion</h5>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Se connecter</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0">École Nationale des Sciences Appliquées</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>