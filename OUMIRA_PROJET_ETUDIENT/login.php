<?php
session_start();
require 'connexion.php'; // Connexion à la base de données

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $student_id = trim($_POST['student_id']);

    // Rechercher l’utilisateur avec email + student_id
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND student_id = :student_id");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Démarrer la session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];

        // Redirection selon le rôle
        $_SESSION['user_id'] = $user['user_id']; // ✔️ C'EST ICI que tu définis la session
        header("Location: chat.php");            // ✔️ Redirection vers le tableau de bord
        exit();
    } else {
        // ❌ Identifiants incorrects
        $error = "Email ou mot de passe incorrect.";
    }
}
?>

<!-- Formulaire HTML -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion Étudiant</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f9fafb;
            padding: 40px;
        }
        .form-container {
            max-width: 500px;
            margin: auto;
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        h2 {
            color: #1a73e8;
            margin-bottom: 20px;
        }
        label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
        }
        input[type="email"],
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        button {
            background-color: #1a73e8;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #1669c1;
        }
        .error {
            color: red;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Connexion Étudiant</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST" action="">
            <label>Email :</label>
            <input type="email" name="email" required>

            <label>ID Étudiant :</label>
            <input type="text" name="student_id" required>

            <button type="submit">Connexion</button>
        </form>
    </div>
</body>
</html>
