<?php
require_once '../include/connexion.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Initialize variables
$deliverable_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;
$error_message = '';
$success_message = '';

// Check if all required parameters are provided
if (!$deliverable_id || !$status || !$project_id) {
    header('Location: projects.php');
    exit;
}

// Validate status
$valid_statuses = ['pending', 'approved', 'rejected'];
if (!in_array($status, $valid_statuses)) {
    header('Location: project_detail.php?id=' . $project_id);
    exit;
}

// Check if the deliverable exists and belongs to the project
try {
    $stmt = $db->prepare("
        SELECT d.*, p.title as project_title 
        FROM deliverables d
        JOIN projects p ON d.project_id = p.project_id
        WHERE d.deliverable_id = :deliverable_id AND d.project_id = :project_id
    ");
    $stmt->execute([
        'deliverable_id' => $deliverable_id,
        'project_id' => $project_id
    ]);
    $deliverable = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deliverable) {
        // Deliverable not found or doesn't belong to the project
        header('Location: project_detail.php?id=' . $project_id);
        exit;
    }
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération du livrable: " . $e->getMessage();
    header('Location: project_detail.php?id=' . $project_id . '&error=' . urlencode($error_message));
    exit;
}

// Check if the user has permission to change the status (teachers, admins)
// Add additional permission checks here if needed

// Update the deliverable status
try {
    $stmt = $db->prepare("
        UPDATE deliverables 
        SET status = :status 
        WHERE deliverable_id = :deliverable_id
    ");
    $stmt->execute([
        'status' => $status,
        'deliverable_id' => $deliverable_id
    ]);
    
    // Prepare status message based on action
    switch ($status) {
        case 'approved':
            $success_message = "Le livrable '" . $deliverable['title'] . "' a été approuvé avec succès.";
            break;
        case 'rejected':
            $success_message = "Le livrable '" . $deliverable['title'] . "' a été rejeté.";
            break;
        case 'pending':
            $success_message = "Le statut du livrable '" . $deliverable['title'] . "' a été changé à 'En attente'.";
            break;
    }
    
    // Create notification for the uploader
    $notification_title = "Mise à jour du statut d'un livrable";
    $notification_message = "Le statut de votre livrable '" . $deliverable['title'] . "' pour le projet '" . 
                           $deliverable['project_title'] . "' a été changé à '" . 
                           ($status == 'approved' ? 'Approuvé' : ($status == 'rejected' ? 'Rejeté' : 'En attente')) . "'.";
    
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message)
        VALUES (:user_id, :title, :message)
    ");
    $stmt->execute([
        'user_id' => $deliverable['uploaded_by'],
        'title' => $notification_title,
        'message' => $notification_message
    ]);
    
    // Redirect with success message
    header('Location: project_detail.php?id=' . $project_id . '&success=' . urlencode($success_message));
    exit;
} catch (PDOException $e) {
    $error_message = "Erreur lors de la mise à jour du statut: " . $e->getMessage();
    header('Location: project_detail.php?id=' . $project_id . '&error=' . urlencode($error_message));
    exit;
}
?>