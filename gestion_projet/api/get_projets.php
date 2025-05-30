
<?php
// Inclusion des fichiers nécessaires
include('includes/db.php');
include('includes/header.php');

// Initialisation des variables de filtrage
$year_filter = isset($_POST['year']) ? $_POST['year'] : '';
$semester_filter = isset($_POST['semester']) ? $_POST['semester'] : '';
$module_filter = isset($_POST['module']) ? $_POST['module'] : '';

// Préparation de la requête SQL de filtrage
$query = "SELECT * FROM projects WHERE 1=1";

if ($year_filter) {
    $query .= " AND year_of_study = '$year_filter'";
}

if ($semester_filter) {
    $query .= " AND semester = '$semester_filter'";
}

if ($module_filter) {
    $query .= " AND module_id = '$module_filter'";
}

$projects = $mysqli->query($query);
?>

<div class="container">
    <h1>Liste des Projets</h1>
    
    <!-- Formulaire de filtrage -->
    <form method="POST" action="projet.php">
        <label for="year">Année académique :</label>
        <select name="year" id="year">
            <option value="">Toutes les années</option>
            <option value="1" <?php if($year_filter == '1') echo 'selected'; ?>>1ère année</option>
            <option value="2" <?php if($year_filter == '2') echo 'selected'; ?>>2ème année</option>
            <option value="3" <?php if($year_filter == '3') echo 'selected'; ?>>3ème année</option>
        </select>

        <label for="semester">Semestre :</label>
        <select name="semester" id="semester">
            <option value="">Tous les semestres</option>
            <option value="1" <?php if($semester_filter == '1') echo 'selected'; ?>>Semestre 1</option>
            <option value="2" <?php if($semester_filter == '2') echo 'selected'; ?>>Semestre 2</option>
        </select>

        <label for="module">Module :</label>
        <select name="module" id="module">
            <option value="">Tous les modules</option>
            <!-- Vous pouvez générer les modules dynamiquement depuis la base de données -->
            <?php
            $modules = $mysqli->query("SELECT * FROM modules");
            while ($module = $modules->fetch_assoc()) {
                echo '<option value="' . $module['module_id'] . '" ' . ($module_filter == $module['module_id'] ? 'selected' : '') . '>' . $module['module_name'] . '</option>';
            }
            ?>
        </select>

        <button type="submit">Filtrer</button>
    </form>

    <!-- Tableau des projets -->
    <table>
        <thead>
            <tr>
                <th>Nom du projet</th>
                <th>Année académique</th>
                <th>Semestre</th>
                <th>Module</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Affichage des projets filtrés
            while ($project = $projects->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . $project['project_name'] . '</td>';
                echo '<td>' . $project['year_of_study'] . '</td>';
                echo '<td>' . $project['semester'] . '</td>';
                echo '<td>' . $project['module_id'] . '</td>';
                echo '<td>' . $project['status'] . '</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<?php include('includes/footer.php'); ?>