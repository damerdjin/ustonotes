<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_admin();

// Start session if not already started for CSRF token
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$success = null;
$error = null;

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Traitement de l'attribution d'un professeur à un groupe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['groupe']) && isset($_POST['prof_id']) && isset($_POST['csrf_token'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $prof_id_to_set = $_POST['prof_id'] === '' ? null : $_POST['prof_id'];
        $stmt = $db->prepare("UPDATE usto_students SET id_prof = ? WHERE groupe = ?");
        $result = $stmt->execute([$prof_id_to_set, $_POST['groupe']]);
        
        if ($result) {
            $success = "Professeur attribué au groupe avec succès";
        } else {
            $error = "Erreur lors de l'attribution du professeur au groupe";
        }
    } else {
        $error = "Erreur de validation CSRF.";
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = "Données de formulaire invalides.";
}

// Récupération des groupes uniques depuis la table usto_students
$stmt = $db->query("SELECT DISTINCT groupe, id_prof FROM usto_students WHERE groupe IS NOT NULL ORDER BY groupe");
$groupes = $stmt->fetchAll();

// Récupération des professeurs
$stmt_profs = $db->query("SELECT id, nom, prenom FROM usto_users WHERE admin = 0 AND activated = 1 ORDER BY nom, prenom");
$profs_raw = $stmt_profs->fetchAll();
$profs_map = [];
foreach ($profs_raw as $p) {
    $profs_map[$p['id']] = htmlspecialchars($p['nom'] . ' ' . $p['prenom']);
}
$profs = $profs_raw; // Garder $profs pour la liste déroulante
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Groupes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1>Administration - Gestion des Groupes</h1>
                <nav class="nav nav-pills">
                    <a class="nav-link active" href="gerer_groupes.php">Groupes</a>
                    <a class="nav-link" href="creer_prof.php">Professeurs</a>
                    <a class="nav-link" href="import_etudiants.php">Étudiants</a>
                    <a class="nav-link" href="gestion_notes.php">Notes</a>
                    <a class="nav-link text-danger" href="../logout.php">Déconnexion</a>
                </nav>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Liste des groupes et attribution des professeurs</div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Note:</strong> Les groupes sont créés automatiquement lors de l'importation des étudiants. 
                            <a href="import_etudiants.php" class="alert-link">Aller à la page d'importation</a>
                        </div>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nom du groupe</th>
                                    <th>Professeur actuel</th>
                                    <th>Attribuer à un professeur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupes as $groupe): ?>
                                <tr>
                                    <td><?= htmlspecialchars($groupe['groupe']) ?></td>
                                    <td>
                                        <?php 
                                        if (isset($groupe['id_prof']) && isset($profs_map[$groupe['id_prof']])) {
                                            echo $profs_map[$groupe['id_prof']];
                                        } else {
                                            echo 'Non attribué';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-flex">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="groupe" value="<?= htmlspecialchars($groupe['groupe']) ?>">
                                            <select name="prof_id" class="form-select form-select-sm me-2">
                                                <option value="">-- Non attribué --</option> <!-- Option pour désattribuer -->
                                                <?php foreach ($profs as $prof): ?>
                                                <option value="<?= $prof['id'] ?>" <?= (isset($groupe['id_prof']) && $prof['id'] == $groupe['id_prof']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($prof['nom'] . ' ' . $prof['prenom']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">Attribuer</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($groupes)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">Aucun groupe trouvé</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>