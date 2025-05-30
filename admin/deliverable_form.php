<?php
require_once '../include/connexion.php';


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Initialize variables
$deliverable_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;
$deliverable = null;
$error_message = '';
$success_message = '';

// Check if project_id is provided
if (!$project_id) {
    header('Location: projects.php');
    exit;
}

// Get deliverable types
try {
    $stmt = $db->prepare("SELECT * FROM deliverable_types ORDER BY type_name");
    $stmt->execute();
    $deliverable_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des types de livrables: " . $e->getMessage();
    $deliverable_types = [];
}

// If editing, get the existing deliverable data
if ($deliverable_id) {
    try {
        $stmt = $db->prepare("
            SELECT d.*, dt.type_name
            FROM deliverables d
            JOIN deliverable_types dt ON d.deliverable_type_id = dt.deliverable_type_id
            WHERE d.deliverable_id = :deliverable_id
        ");
        $stmt->execute(['deliverable_id' => $deliverable_id]);
        $deliverable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if deliverable exists and belongs to the specified project
        if (!$deliverable || $deliverable['project_id'] != $project_id) {
            header('Location: project_detail.php?id=' . $project_id);
            exit;
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la récupération du livrable: " . $e->getMessage();
    }
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $deliverable_type_id = intval($_POST['deliverable_type_id']);
    
    // Validate input
    if (empty($title)) {
        $error_message = "Le titre du livrable est requis.";
    } elseif ($deliverable_type_id <= 0) {
        $error_message = "Veuillez sélectionner un type de livrable valide.";
    } else {
        // Check if a file was uploaded
        $file_uploaded = isset($_FILES['file']) && $_FILES['file']['error'] == 0;
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            if ($deliverable_id) {
                // Update existing deliverable
                if ($file_uploaded) {
                    // Process new file upload
                    $upload_dir = '../uploads/deliverables/';
                    $file_name = time() . '_' . basename($_FILES['file']['name']);
                    $file_path = $upload_dir . $file_name;
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Move the uploaded file
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                        // Delete old file if it exists
                        if ($deliverable['file_path'] && file_exists($deliverable['file_path'])) {
                            unlink($deliverable['file_path']);
                        }
                        
                        // Update database with new file info
                        $stmt = $db->prepare("
                            UPDATE deliverables
                            SET title = :title,
                                deliverable_type_id = :deliverable_type_id,
                                file_path = :file_path,
                                file_size = :file_size,
                                file_type = :file_type,
                                uploaded_by = :uploaded_by,
                                upload_date = NOW()
                            WHERE deliverable_id = :deliverable_id
                        ");
                        $stmt->execute([
                            'title' => $title,
                            'deliverable_type_id' => $deliverable_type_id,
                            'file_path' => $file_path,
                            'file_size' => $_FILES['file']['size'],
                            'file_type' => $_FILES['file']['type'],
                            'uploaded_by' => $_SESSION['user_id'],
                            'deliverable_id' => $deliverable_id
                        ]);
                    } else {
                        throw new Exception("Échec du téléchargement du fichier.");
                    }
                } else {
                    // Update without changing the file
                    $stmt = $db->prepare("
                        UPDATE deliverables
                        SET title = :title,
                            deliverable_type_id = :deliverable_type_id
                        WHERE deliverable_id = :deliverable_id
                    ");
                    $stmt->execute([
                        'title' => $title,
                        'deliverable_type_id' => $deliverable_type_id,
                        'deliverable_id' => $deliverable_id
                    ]);
                }
                
                $success_message = "Le livrable a été mis à jour avec succès.";
            } else {
                // Add new deliverable
                if (!$file_uploaded) {
                    throw new Exception("Veuillez télécharger un fichier.");
                }
                
                // Process file upload
                $upload_dir = '../uploads/deliverables/';
                $file_name = time() . '_' . basename($_FILES['file']['name']);
                $file_path = $upload_dir . $file_name;
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Move the uploaded file
                if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                    // Insert new deliverable
                    $stmt = $db->prepare("
                        INSERT INTO deliverables (
                            project_id, deliverable_type_id, title, file_path, file_size, file_type, uploaded_by
                        ) VALUES (
                            :project_id, :deliverable_type_id, :title, :file_path, :file_size, :file_type, :uploaded_by
                        )
                    ");
                    $stmt->execute([
                        'project_id' => $project_id,
                        'deliverable_type_id' => $deliverable_type_id,
                        'title' => $title,
                        'file_path' => $file_path,
                        'file_size' => $_FILES['file']['size'],
                        'file_type' => $_FILES['file']['type'],
                        'uploaded_by' => $_SESSION['user_id']
                    ]);
                    
                    $success_message = "Le livrable a été ajouté avec succès.";
                } else {
                    throw new Exception("Échec du téléchargement du fichier.");
                }
            }
            
            // Commit the transaction
            $db->commit();
            
            // Redirect after successful submission
            header('Location: project_detail.php?id=' . $project_id . '&success=' . urlencode($success_message));
            exit;
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            $db->rollBack();
            $error_message = "Erreur: " . $e->getMessage();
        }
    }
}

// Get project information
try {
    $stmt = $db->prepare("SELECT title FROM projects WHERE project_id = :project_id");
    $stmt->execute(['project_id' => $project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        header('Location: projects.php');
        exit;
    }
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération du projet: " . $e->getMessage();
}

$page_title = $deliverable_id ? 'Modifier un livrable' : 'Ajouter un livrable';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | ENSA Project Manager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="projects.php">Projets</a></li>
                        <li class="breadcrumb-item"><a href="project_detail.php?id=<?php echo $project_id; ?>"><?php echo htmlspecialchars($project['title']); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                </div>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $deliverable_id . '&project_id=' . $project_id; ?>" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Titre du livrable <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                       value="<?php echo isset($deliverable['title']) ? htmlspecialchars($deliverable['title']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="deliverable_type_id" class="form-label">Type de livrable <span class="text-danger">*</span></label>
                                <select class="form-select" id="deliverable_type_id" name="deliverable_type_id" required>
                                    <option value="">-- Sélectionner un type --</option>
                                    <?php foreach ($deliverable_types as $type): ?>
                                        <option value="<?php echo $type['deliverable_type_id']; ?>" 
                                                <?php echo (isset($deliverable['deliverable_type_id']) && $deliverable['deliverable_type_id'] == $type['deliverable_type_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['type_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="file" class="form-label">Fichier <?php echo $deliverable_id ? '' : '<span class="text-danger">*</span>'; ?></label>
                                <input type="file" class="form-control" id="file" name="file" <?php echo $deliverable_id ? '' : 'required'; ?>>
                                <?php if ($deliverable_id): ?>
                                    <div class="form-text">
                                        Fichier actuel: <?php echo basename($deliverable['file_path']); ?> 
                                        (<?php echo formatFileSize($deliverable['file_size']); ?>)
                                        <a href="<?php echo $deliverable['file_path']; ?>" target="_blank" class="ms-2">
                                            <i class="fas fa-download"></i> Télécharger
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="project_detail.php?id=<?php echo $project_id; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Retour
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> <?php echo $deliverable_id ? 'Enregistrer les modifications' : 'Ajouter le livrable'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>