<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_prof();

$success = null;
$error = null;
$groupe = isset($_GET['groupe']) ? $_GET['groupe'] : null;

// Récupération des permissions du professeur
$stmt_perm = $db->prepare("SELECT note_type, can_view, can_edit FROM usto_prof_permissions WHERE prof_id = ?");
$stmt_perm->execute([$_SESSION['user']['id']]);
$raw_permissions = $stmt_perm->fetchAll(PDO::FETCH_ASSOC);

$permissions = [];
foreach ($raw_permissions as $perm) {
    $permissions[$perm['note_type']] = [
        'can_view' => $perm['can_view'],
        'can_edit' => $perm['can_edit']
    ];
}

// Récupération des groupes
$stmt = $db->prepare("SELECT DISTINCT groupe FROM usto_students WHERE id_prof = ? ORDER BY groupe");
$stmt->execute([$_SESSION['user']['id']]);
$groupes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Initialisation de la variable $etudiants
$etudiants = [];

// Traitement du formulaire de saisie des notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_notes'])) {
    $student_ids = $_POST['student_id'];
    $updated = 0;

    foreach ($student_ids as $index => $student_id) {
        $t01 = (isset($_POST['t01'][$index]) && $_POST['t01'][$index] !== '') ? $_POST['t01'][$index] : null;
        $t02 = (isset($_POST['t02'][$index]) && $_POST['t02'][$index] !== '') ? $_POST['t02'][$index] : null;
        $participation = (isset($_POST['participation'][$index]) && $_POST['participation'][$index] !== '') ? $_POST['participation'][$index] : null;
        $note_cc = (isset($_POST['note_cc'][$index]) && $_POST['note_cc'][$index] !== '') ? $_POST['note_cc'][$index] : null;
        $exam = (isset($_POST['exam'][$index]) && $_POST['exam'][$index] !== '') ? $_POST['exam'][$index] : null;
        $ratt = (isset($_POST['ratt'][$index]) && $_POST['ratt'][$index] !== '') ? $_POST['ratt'][$index] : null;

        $moy1 = calculerMoyenne($t01, $t02, $participation, $note_cc, $exam);
        $moy2 = calculerMoyenneFinale($moy1, $ratt);
        $moygen = $moy2;

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

// Récupération des étudiants après traitement du formulaire
if ($groupe) {
    $decoded_groupe = urldecode($groupe);
    $etudiants = getEtudiantsByGroupeAndProf($db, $decoded_groupe, $_SESSION['user']['id']);
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
            width: 80px;
            text-align: center;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table-notes {
            position: relative;
        }

        .table-notes th:first-child,
        .table-notes td:first-child {
            position: sticky;
            left: 0;
            background-color: white;
            z-index: 1;
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
                                        <option value="<?= urlencode($g) ?>" <?= $groupe == $g ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($g) ?>
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
                        <div class="card-header">
                            Saisie des notes <?php if ($groupe): ?> - <?= htmlspecialchars(urldecode($groupe)) ?><?php endif; ?>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="table-responsive">
                                    <table class="table table-striped table-notes">
                                        <thead>
                                            <tr>
                                                <th>Nom</th>
                                                <th>Prénom</th>
                                                <?php
                                                $note_types = [
                                                    't01' => ['label' => 'T01', 'field' => 't01'],
                                                    't02' => ['label' => 'T02', 'field' => 't02'],
                                                    'participation' => ['label' => 'Participation', 'field' => 'participation'],
                                                    'note_cc' => ['label' => 'Note CC', 'field' => 'note_cc'],
                                                    'exam' => ['label' => 'Examen', 'field' => 'exam'],
                                                    'moy1' => ['label' => 'Moyenne', 'field' => 'moy1', 'calculated' => true],
                                                    'ratt' => ['label' => 'Rattrapage', 'field' => 'ratt'],
                                                    'moy2' => ['label' => 'Moyenne Finale', 'field' => 'moy2', 'calculated' => true]
                                                ];

                                                foreach ($note_types as $key => $note_info) {
                                                    // Exclure les colonnes de moyenne
                                                    if ($key === 'moy1' || $key === 'moy2') {
                                                        continue;
                                                    }
                                                    // Check if the note type is calculated or if the professor has view permission
                                                    if (isset($note_info['calculated']) || (isset($permissions[$key]) && $permissions[$key]['can_view'])) {
                                                        echo '<th>' . htmlspecialchars($note_info['label']) . '</th>';
                                                    }
                                                }
                                                ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($etudiants as $index => $etudiant): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($etudiant['nom']) ?></td>
                                                    <td><?= htmlspecialchars($etudiant['prenom']) ?></td>
                                                    <?php foreach ($note_types as $key => $note_info): ?>
                                                        <?php
                                                        $can_view = isset($note_info['calculated']) || (isset($permissions[$key]) && $permissions[$key]['can_view']);
                                                        $can_edit = isset($permissions[$key]) && $permissions[$key]['can_edit'];
                                                        ?>
                                                        <?php if ($can_view): ?>
                                                            <td>
                                                                <?php if ($key === 'moy1' || $key === 'moy2'): // Calculated fields - Exclure ces colonnes
                                                                    // Ne rien afficher pour les moyennes
                                                                    continue;
                                                                ?>
                                                                    <span class="badge bg-<?= ($key === 'moy1') ? 'info' : 'success' ?>"><?= $etudiant[$note_info['field']] ?? '-' ?></span>
                                                                <?php elseif ($can_edit): // Editable fields 
                                                                ?>
                                                                    <input type="hidden" name="student_id[<?= $index ?>]" value="<?= $etudiant['id'] ?>">
                                                                    <input type="number" name="<?= $note_info['field'] ?>[<?= $index ?>]" value="<?= $etudiant[$note_info['field']] ?>" min="0" max="<?= ($key === 't01' || $key === 't02') ? 9 : (($key === 'participation') ? 2 : 20) ?>" step="0.25" class="form-control">
                                                                <?php else: // View-only fields 
                                                                ?>
                                                                    <?= htmlspecialchars($etudiant[$note_info['field']] ?? '-') ?>
                                                                <?php endif; ?>
                                                            </td>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
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