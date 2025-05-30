<?php
// Include database connection
include_once '../include/connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error'] = "Vous devez être connecté pour supprimer un commentaire.";
    header('Location: login.php');
    exit;
}

// Récupérer les IDs
$comment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($comment_id === 0 || $project_id === 0) {
    $_SESSION['error'] = "Paramètres invalides.";
    header('Location: projects.php');
    exit;
}

// Vérifier si le commentaire existe et appartient au projet
try {
    $stmt = $db->prepare("
        SELECT c.*, p.project_id 
        FROM comments c
        JOIN projects p ON c.project_id = p.project_id
        WHERE c.comment_id = :comment_id AND c.project_id = :project_id
    ");
    $stmt->execute([
        'comment_id' => $comment_id,
        'project_id' => $project_id
    ]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comment) {
        $_SESSION['error'] = "Commentaire non trouvé.";
        header('Location: project_detail.php?id=' . $project_id);
        exit;
    }
    
    // Vérifier si l'utilisateur est l'auteur du commentaire ou un admin ou un enseignant responsable
    $canDelete = false;
    
    if ($comment['user_id'] == $_SESSION['user_id'] || isAdmin()) {
        $canDelete = true;
    } else if (isTeacher()) {
        // Vérifier si l'enseignant est superviseur du projet
        $stmt = $db->prepare("
            SELECT * FROM project_supervisors 
            WHERE project_id = :project_id AND user_id = :user_id
        ");
        $stmt->execute([
            'project_id' => $project_id,
            'user_id' => $_SESSION['user_id']
        ]);
        $supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($supervisor) {
            $canDelete = true;
        }
    }
    
    if (!$canDelete) {
        $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour supprimer ce commentaire.";
        header('Location: project_detail.php?id=' . $project_id);
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la récupération du commentaire: " . $e->getMessage();
    header('Location: project_detail.php?id=' . $project_id);
    exit;
}

// Supprimer le commentaire
try {
    $stmt = $db->prepare("DELETE FROM comments WHERE comment_id = :comment_id");
    $stmt->execute(['comment_id' => $comment_id]);
    
    $_SESSION['success'] = "Le commentaire a été supprimé avec succès.";
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la suppression du commentaire: " . $e->getMessage();
}

// Redirection vers la page de détail du projet
header('Location: project_detail.php?id=' . $project_id);
exit;