<?php
require_once '../include/connexion.php';
require_once '../fpdf/fpdf.php'; // Ensure the FPDF library is included correctly

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error'] = "Vous devez être connecté pour exporter un projet.";
    header('Location: login.php');
    exit;
}

// Récupérer l'ID du projet
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'pdf'; // Par défaut: PDF

if ($project_id === 0) {
    $_SESSION['error'] = "ID de projet non valide.";
    header('Location: projects.php');
    exit;
}

// Vérifier si le projet existe
try {
    $stmt = $db->prepare("
        SELECT p.*, pt.type_name AS project_type, ay.year_label, 
               o.name AS organization_name, o.city AS organization_city, o.country AS organization_country, o.website AS organization_website,
               m.module_name, m.module_code
        FROM projects p
        LEFT JOIN project_types pt ON p.project_type_id = pt.type_id
        LEFT JOIN academic_years ay ON p.academic_year_id = ay.year_id
        LEFT JOIN organizations o ON p.organization_id = o.organization_id
        LEFT JOIN modules m ON p.module_id = m.module_id
        WHERE p.project_id = :project_id
    ");
    $stmt->execute(['project_id' => $project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        $_SESSION['error'] = "Projet non trouvé.";
        header('Location: projects.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la récupération du projet: " . $e->getMessage();
    header('Location: projects.php');
    exit;
}

// Vérifier les droits d'accès
$hasAccess = false;

if (isAdmin()) {
    $hasAccess = true;
} else {
    try {
        // Membre ?
        $stmt = $db->prepare("SELECT * FROM project_members WHERE project_id = :project_id AND user_id = :user_id");
        $stmt->execute(['project_id' => $project_id, 'user_id' => $_SESSION['user_id']]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($member) {
            $hasAccess = true;
        } else {
            // Superviseur ?
            $stmt = $db->prepare("SELECT * FROM project_supervisors WHERE project_id = :project_id AND user_id = :user_id");
            $stmt->execute(['project_id' => $project_id, 'user_id' => $_SESSION['user_id']]);
            $supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($supervisor) {
                $hasAccess = true;
            } else if (isTeacher() && !empty($project['module_id'])) {
                // Enseignant du module ?
                $stmt = $db->prepare("SELECT * FROM module_instructors WHERE module_id = :module_id AND user_id = :user_id");
                $stmt->execute(['module_id' => $project['module_id'], 'user_id' => $_SESSION['user_id']]);
                $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($instructor) {
                    $hasAccess = true;
                }
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la vérification des droits: " . $e->getMessage();
        header('Location: projects.php');
        exit;
    }
}

if (!$hasAccess) {
    $_SESSION['error'] = "Vous n'avez pas les droits pour exporter ce projet.";
    header('Location: projects.php');
    exit;
}

// Récupérer les données supplémentaires
try {
    // Membres
    $stmt = $db->prepare("SELECT u.first_name, u.last_name, u.email, pm.role FROM project_members pm JOIN users u ON pm.user_id = u.user_id WHERE pm.project_id = :project_id");
    $stmt->execute(['project_id' => $project_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Superviseurs
    $stmt = $db->prepare("
        SELECT 
            CASE WHEN ps.user_id IS NOT NULL THEN u.first_name ELSE ps.external_name END AS first_name,
            CASE WHEN ps.user_id IS NOT NULL THEN u.last_name ELSE '' END AS last_name,
            CASE WHEN ps.user_id IS NOT NULL THEN u.email ELSE ps.external_email END AS email,
            CASE WHEN ps.user_id IS NOT NULL THEN 'ENSA' ELSE ps.external_organization END AS organization,
            ps.supervision_role
        FROM project_supervisors ps
        LEFT JOIN users u ON ps.user_id = u.user_id
        WHERE ps.project_id = :project_id
    ");
    $stmt->execute(['project_id' => $project_id]);
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Livrables
    $stmt = $db->prepare("SELECT d.*, dt.type_name, u.first_name, u.last_name FROM deliverables d JOIN deliverable_types dt ON d.deliverable_type_id = dt.deliverable_type_id JOIN users u ON d.uploaded_by = u.user_id WHERE d.project_id = :project_id ORDER BY d.upload_date DESC");
    $stmt->execute(['project_id' => $project_id]);
    $deliverables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Évaluations
    $stmt = $db->prepare("SELECT e.*, u.first_name, u.last_name FROM evaluations e JOIN users u ON e.evaluator_id = u.user_id WHERE e.project_id = :project_id ORDER BY e.evaluation_date DESC");
    $stmt->execute(['project_id' => $project_id]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tags
    $stmt = $db->prepare("SELECT t.tag_name FROM project_tags pt JOIN tags t ON pt.tag_id = t.tag_id WHERE pt.project_id = :project_id");
    $stmt->execute(['project_id' => $project_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur récupération données export: " . $e->getMessage();
    header('Location: project_detail.php?id=' . $project_id);
    exit;
}

// Format date
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// EXPORT PDF
if ($format === 'pdf') {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);

    $pdf->Cell(0, 10, utf8_decode("Fiche du Projet"), 0, 1, 'C');
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 12);

    $pdf->Cell(0, 10, "Titre: " . utf8_decode($project['title']), 0, 1);
    $pdf->Cell(0, 10, "Année académique: " . $project['year_label'], 0, 1);
    $pdf->Cell(0, 10, "Type: " . $project['project_type'], 0, 1);
    if ($project['module_name']) {
        $pdf->Cell(0, 10, "Module: " . $project['module_code'] . " - " . utf8_decode($project['module_name']), 0, 1);
    }

    if ($tags) {
        $pdf->MultiCell(0, 10, "Tags: " . implode(", ", $tags));
    }

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, "Membres du projet:", 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($members as $m) {
        $pdf->Cell(0, 10, "- " . utf8_decode($m['first_name'] . " " . $m['last_name']) . " (" . $m['email'] . ") - " . $m['role'], 0, 1);
    }

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, "Encadrants:", 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($supervisors as $s) {
        $pdf->Cell(0, 10, "- " . utf8_decode($s['first_name'] . " " . $s['last_name']) . " (" . $s['email'] . ") - " . $s['organization'] . " - " . $s['supervision_role'], 0, 1);
    }

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, "Livrables:", 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($deliverables as $d) {
        $pdf->MultiCell(0, 10, "- " . utf8_decode($d['type_name']) . " : " . $d['title'] . " (" . formatDate($d['upload_date']) . ") par " . utf8_decode($d['first_name']) . " " . utf8_decode($d['last_name']));
    }

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, "Évaluations:", 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($evaluations as $e) {
        $pdf->MultiCell(0, 10, "- Par " . utf8_decode($e['first_name'] . " " . $e['last_name']) . " le " . formatDate($e['evaluation_date']) . " - Note: " . $e['score'] . "/20 - Commentaire: " . utf8_decode($e['comments']));
    }

    $pdf->Output('I', 'Projet_' . $project_id . '.pdf');
    exit;
} else {
    $_SESSION['error'] = "Format non supporté pour l'export.";
    header('Location: project_detail.php?id=' . $project_id);
    exit;
}
?>
