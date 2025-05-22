<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_prof();

$success = null;
$error = null;
$groupe = isset($_GET['groupe']) ? $_GET['groupe'] : null;

// Récupération des groupes associés au professeur
$stmt = $db->prepare("SELECT g.* FROM usto_groupes g 
                     JOIN usto_prof_groupes pg ON g.id = pg.groupe_id 
                     WHERE pg.prof_id = ? 
                     ORDER BY g.nom_groupe");
$stmt->execute([$_SESSION['user']['id']]);
$groupes = $stmt->fetchAll();

// Si un groupe est sélectionné, récupérer les étudiants
$etudiants = [];
if ($groupe) {
    $etudiants = getEtudiantsByGroupeAndProf($db, $groupe, $_SESSION['user']['id']);
}

// Traitement du formulaire de saisie des notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_notes'])) {
    $student_ids = $_POST['student_id'];
    $updated = 0;
    
    foreach ($student_ids as $index => $student_id) {
        // Récupération des notes
        $t01 = isset($_POST['t01'][$index]) ? $_POST['t01'][$index] : null;
        $t02 = isset($_POST['t02'][$index]) ? $_POST['t02'][$index] : null;
        $participation = isset($_POST['participation'][$index]) ? $_POST['participation'][$index] : null;
        $note_cc = isset($_POST['note_cc'][$index]) ? $_POST['note_cc'][$index] : null;
        $exam = isset($_POST['exam'][$index]) ? $_POST['exam'][$index] : null;
        $ratt = isset($_POST['ratt'][$index]) ? $_POST['ratt'][$index] : null;
        
        // Calcul des moyennes
        $moy1 = calculerMoyenne($t01, $t02, $participation, $note_cc, $exam);
        $moy2 = calculerMoyenneFinale($moy1, $ratt);
        $moygen = $moy2;
        
        // Préparation des données pour la mise à jour
        $notes = [
            't01' => $t01,
            't02' => $t02,
            'participation' => $participation,
            'note_cc' => $note_cc,
            'exam' => $exam,
            'moy1' => $moy1,
            'ratt' => $ratt,
            'moy2' => $moy2,
            'moygen' => $moygen
        ];
        
        // Mise à jour des notes
        if (updateNotes($db, $student_id, $notes)) {
            $updated++;
        }
    }
    
    if ($updated > 0) {
        $success = "Notes mises à jour pour $updated étudiant(s)";
    } else {
        $error = "Aucune note n'a été mise à jour";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie des Notes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-notes input {
            width: 60px;
            text-align: center;
        }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1>Saisie des Notes</h1>
                <p>Professeur: <?= htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']) ?></p>
                <nav class="nav nav-pills">
                    <a class="nav-link" href="dashboard.php">Tableau de bord</a>
                    <a class="nav-link active" href="saisie_notes.php">Saisie des notes</a>
                    <a class="nav-link text-danger" href="../logout.php">Déconnexion</a>
                </nav>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Sélectionner un groupe</div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <select name="groupe" class="form-select" required>
                                    <option value="">-- Choisir un groupe --</option>
                                    <?php foreach ($groupes as $g): ?>
                                        <option value="<?= $g['id'] ?>" <?= $groupe == $g['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($g['nom_groupe']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary">Afficher les étudiants</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($groupe && !empty($etudiants)): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Saisie des notes</div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="table-responsive">
                                <table class="table table-striped table-notes">
                                    <thead>
                                        <tr>
                                            <th>Matricule</th>
                                            <th>Nom</th>
                                            <th>Prénom</th>
                                            <th>T01</th>
                                            <th>T02</th>
                                            <th>Participation</th>
                                            <th>Note CC</th>
                                            <th>Examen</th>
                                            <th>Moyenne</th>
                                            <th>Rattrapage</th>
                                            <th>Moyenne Finale</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etudiants as $index => $etudiant): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                            <td><?= htmlspecialchars($etudiant['nom']) ?></td>
                                            <td><?= htmlspecialchars($etudiant['prenom']) ?></td>
                                            <td>
                                                <input type="hidden" name="student_id[<?= $index ?>]" value="<?= $etudiant['id'] ?>">
                                                <input type="number" name="t01[<?= $index ?>]" value="<?= $etudiant['t01'] ?>" min="0" max="20" class="form-control">
                                            </td>
                                            <td>
                                                <input type="number" name="t02[<?= $index ?>]" value="<?= $etudiant['t02'] ?>" min="0" max="20" class="form-control">
                                            </td>
                                            <td>
                                                <input type="number" name="participation[<?= $index ?>]" value="<?= $etudiant['participation'] ?>" min="0" max="20" class="form-control">
                                            </td>
                                            <td>
                                                <input type="number" name="note_cc[<?= $index ?>]" value="<?= $etudiant['note_cc'] ?>" min="0" max="20" class="form-control">
                                            </td>
                                            <td>
                                                <input type="number" name="exam[<?= $index ?>]" value="<?= $etudiant['exam'] ?>" min="0" max="20" class="form-control">
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= $etudiant['moy1'] ?? '-' ?></span>
                                            </td>
                                            <td>
                                                <input type="number" name="ratt[<?= $index ?>]" value="<?= $etudiant['ratt'] ?>" min="0" max="20" class="form-control">
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?= $etudiant['moy2'] ?? '-' ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" name="submit_notes" class="btn btn-primary">Enregistrer les notes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif ($groupe): ?>
            <div class="alert alert-info">Aucun étudiant trouvé pour ce groupe</div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>