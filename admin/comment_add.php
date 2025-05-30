<?php
require_once '../include/connexion.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error'] = "Vous devez être connecté pour ajouter un commentaire.";
    header('Location: login.php');
    exit;
}

// Vérifier si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Méthode non autorisée.";
    header('Location: projects.php');
    exit;
}

// Récupérer les données du formulaire
$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if ($project_id === 0) {
    $_SESSION['error'] = "ID de projet non valide.";
    header('Location: projects.php');
    exit;
}

// Validation des données
if (empty($content)) {
    $_SESSION['error'] = "Le contenu du commentaire ne peut pas être vide.";
    header('Location: project_detail.php?id=' . $project_id);
    exit;
}

// Vérifier si le projet existe
try {
    $stmt = $db->prepare("SELECT * FROM projects WHERE project_id = :project_id");
    $stmt->execute(['project_id' => $project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        $_SESSION['error'] = "Projet non trouvé.";
        header('Location: projects.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la vérification du projet: " . $e->getMessage();
    header('Location: projects.php');
    exit;
}

// Insérer le commentaire dans la base de données
try {
    $stmt = $db->prepare("
        INSERT INTO comments (project_id, user_id, content, created_at)
        VALUES (:project_id, :user_id, :content, CURRENT_TIMESTAMP)
    ");
    
    $params = [
        'project_id' => $project_id,
        'user_id' => $_SESSION['user_id'],
        'content' => $content
    ];
    
    $stmt->execute($params);
    
    // Notification pour les membres du projet (optionnel)
    try {
        // Récupérer les membres du projet
        $member_stmt = $db->prepare("
            SELECT u.user_id 
            FROM project_members pm
            JOIN users u ON pm.user_id = u.user_id
            WHERE pm.project_id = :project_id AND u.user_id != :current_user_id
        ");
        $member_stmt->execute([
            'project_id' => $project_id,
            'current_user_id' => $_SESSION['user_id']
        ]);
        $members = $member_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Créer des notifications pour chaque membre
        if (!empty($members)) {
            $notification_stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, is_read, created_at)
                VALUES (:user_id, :title, :message, FALSE, CURRENT_TIMESTAMP)
            ");
            
            $notification_title = "Nouveau commentaire sur un projet";
            $notification_message = $_SESSION['user_name'] . " a commenté le projet \"" . $project['title'] . "\".";
            
            foreach ($members as $member_id) {
                $notification_stmt->execute([
                    'user_id' => $member_id,
                    'title' => $notification_title,
                    'message' => $notification_message
                ]);
            }
        }
    } catch (PDOException $e) {
        // En cas d'erreur dans les notifications, on continue (non critique)
        error_log("Erreur lors de l'envoi des notifications: " . $e->getMessage());
    }
    
    $_SESSION['success'] = "Votre commentaire a été ajouté avec succès.";
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de l'ajout du commentaire: " . $e->getMessage();
}

// Redirection vers la page de détail du projet
header('Location: project_detail.php?id=' . $project_id);
exit;
