<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_admin();

// Configuration de l'encodage
mb_internal_encoding('UTF-8');
$db->exec("SET NAMES utf8mb4");
$db->exec("SET CHARACTER SET utf8mb4");
$db->exec("SET collation_connection = utf8mb4_unicode_ci");

$success = null;
$error = null;

// Récupération des groupes
$stmt = $db->query("SELECT DISTINCT groupe FROM usto_students ORDER BY groupe");
$groupes = $stmt->fetchAll();

// Récupération des professeurs
$stmt = $db->query("SELECT * FROM usto_users WHERE admin = 0 ORDER BY nom, prenom");
$professeurs = $stmt->fetchAll();


// Traitement de l'affichage des étudiants
$filter_groupe = isset($_GET['filter_groupe']) ? $_GET['filter_groupe'] : '';
$filter_prof = isset($_GET['filter_prof']) ? $_GET['filter_prof'] : '';
$note_type = isset($_POST['note_type']) ? $_POST['note_type'] : '';

$sql = "SELECT s.*, u.nom as prof_nom, u.prenom as prof_prenom,
        CAST(s.note_cc AS DECIMAL(4,2)) as note_cc,
        CAST(s.exam AS DECIMAL(4,2)) as exam,
        CAST(s.ratt AS DECIMAL(4,2)) as ratt,
        CAST(s.t01 AS DECIMAL(4,2)) as t01,
        CAST(s.t02 AS DECIMAL(4,2)) as t02,
        CAST(s.participation AS DECIMAL(4,2)) as participation,
        CAST(s.moygen AS DECIMAL(4,2)) as moygen,
        CAST(s.moy1 AS DECIMAL(4,2)) as moy1,
        CAST(s.moy2 AS DECIMAL(4,2)) as moy2
        FROM usto_students s 
        LEFT JOIN usto_users u ON s.id_prof = u.id 
        WHERE 1=1";
$params = [];

if (!empty($filter_groupe)) {
    $sql .= " AND s.groupe = ?";
    $params[] = $filter_groupe;
}

if (!empty($filter_prof)) {
    $sql .= " AND s.id_prof = ?";
    $params[] = $filter_prof;
}

$sql .= " ORDER BY s.nom, s.prenom";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$etudiants = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importation des Étudiants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1>Administration - Importation des Étudiants</h1>
                <nav class="nav nav-pills">
                    <a class="nav-link" href="gestion.php">Gestion</a>
                    <a class="nav-link active" href="etudiants.php">Étudiants</a>
                    <a class="nav-link" href="gestion_notes.php">Notes</a>
                    <a class="nav-link" href="gestion_permissions.php">Permissions</a>
                    <a class="nav-link text-danger" href="../logout.php">Déconnexion</a>
                </nav>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Filtrer les étudiants</div>
                    <div class="card-body">
                        <form method="GET" class="row g-3" id="filterForm">
                            <div class="col-md-4">
                                <select name="filter_groupe" class="form-select" onchange="this.form.submit()">
                                    <option value="">Tous les groupes</option>
                                    <?php foreach ($groupes as $g): ?>
                                        <option value="<?= htmlspecialchars($g['groupe']) ?>" <?= $filter_groupe == $g['groupe'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($g['groupe']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="filter_prof" class="form-select" onchange="this.form.submit()">
                                    <option value="">Tous les professeurs</option>
                                    <?php foreach ($professeurs as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $filter_prof == $p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex flex-wrap gap-2">
                                    <?php
                                    $note_columns = [
                                        't01' => 'Test 1',
                                        't02' => 'Test 2',
                                        'participation' => 'Participation',
                                        'note_cc' => 'CC',
                                        'exam' => 'Examen',
                                        'moy1' => 'Moyenne 1',
                                        'ratt' => 'Rattrapage',
                                        'moy2' => 'Moyenne 2',
                                        'moygen' => 'Moyenne Finale'
                                    ];
                                    $selected_columns = isset($_GET['columns']) ? $_GET['columns'] : ['note_cc', 'exam'];
                                    foreach ($note_columns as $col => $label): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[]" 
                                                value="<?= $col ?>" id="col_<?= $col ?>" 
                                                <?= in_array($col, $selected_columns) ? 'checked' : '' ?>
                                                onchange="this.form.submit()">
                                            <label class="form-check-label" for="col_<?= $col ?>">
                                                <?= $label ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Liste des étudiants</div>
                    <div class="card-body">
                        <?php if (count($etudiants) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Matricule</th>
                                            <th>Nom</th>
                                            <th>Prénom</th>
                                            <th>Groupe</th>
                                            <th>Professeur</th>
                                            <?php foreach ($selected_columns as $col): ?>
                                                <th><?= $note_columns[$col] ?></th>
                                            <?php endforeach; ?>                                            
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etudiants as $e): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($e['matricule']) ?></td>
                                                <td><?= htmlspecialchars($e['nom']) ?></td>
                                                <td><?= htmlspecialchars($e['prenom']) ?></td>
                                                <td><?= htmlspecialchars($e['groupe']) ?></td>
                                                <td><?= htmlspecialchars($e['prof_nom']) ?></td>
                                                <?php foreach ($selected_columns as $col): ?>
                                                    <td>
                                                        <?php if (isset($e[$col]) && $e[$col] !== null): ?>
                                                            <span class="badge bg-<?= $e[$col] >= 10 ? 'success' : 'danger' ?>">
                                                                <?= number_format($e[$col], 2, '.', '') ?>/20
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Non évalué</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">Aucun étudiant trouvé</div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <button class="btn btn-success" onclick="exportToExcel()">Exporter en Excel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        const studentData = <?php echo json_encode($etudiants); ?>;
        const noteColumnsMapping = <?php echo json_encode($note_columns); ?>;

        function exportToExcel() {
             const selectedColumns = Array.from(document.querySelectorAll('input[name="columns[]"]:checked')).map(c => c.value);
             if (selectedColumns.length > 1) {
                 alert('Veuillez sélectionner une seule colonne de notes pour l\'export.');
                 return;
             }

             const filterGroupeSelect = document.querySelector('select[name="filter_groupe"]');
             const groupe = filterGroupeSelect.options[filterGroupeSelect.selectedIndex].text || 'tous';
             const noteKey = selectedColumns[0] || 'note';
             const noteLabel = noteColumnsMapping[noteKey] || 'Note';

             const data = [['Matricule', 'Nom', 'Prénom', 'Note', 'Groupe']];
             data.unshift([]); // Add an empty array at the beginning

             studentData.forEach(student => {
                 const matricule = student.matricule;
                 const nom = student.nom;
                 const prenom = student.prenom;
                 const studentGroupe = student.groupe;
                 const noteValue = student[noteKey] !== null ? `${parseFloat(student[noteKey]).toFixed(2)}` : 'Non évalué';
                 data.push([matricule, nom, prenom, noteValue, studentGroupe]);
             });

             const ws = XLSX.utils.aoa_to_sheet(data);
             const wb = XLSX.utils.book_new();
             XLSX.utils.book_append_sheet(wb, ws, 'Etudiants');
             XLSX.writeFile(wb, `${groupe}_${noteKey}.xlsx`);
         }
    </script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.table').DataTable({
                responsive: true,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
                },
                pageLength: 25,
                order: [[1, 'asc']],
                dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex"f>>rtip'
            });
        });
    </script>
</body>

</html>