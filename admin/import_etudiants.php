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
$stmt = $db->query("SELECT * FROM usto_users WHERE admin = 0 AND activated = 1 ORDER BY nom, prenom");
$professeurs = $stmt->fetchAll();

// Traitement du formulaire d'importation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_import'])) {
    $allowed_note_types = ['note_cc', 'exam', 'ratt', 't01', 't02', 'participation'];
    $note_type = $_POST['note_type'] ?? '';

    if ($note_type !== '' && !in_array($note_type, $allowed_note_types)) {
        $error = "Type de note invalide sélectionné.";
    } elseif (!empty($_FILES['csv_file']['tmp_name'])) {
        try {
            $db->beginTransaction(); // Démarrer la transaction

            // Ouvrir le fichier en mode UTF-8
            setlocale(LC_ALL, 'fr_FR.UTF-8');
            $content = file_get_contents($_FILES['csv_file']['tmp_name']);

            // Détecter et convertir l'encodage si nécessaire
            $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
            if ($encoding !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            }

            // Créer un flux temporaire avec le contenu converti
            $handle = fopen('php://memory', 'r+');
            fwrite($handle, $content);
            rewind($handle);

            if ($handle === false) {
                throw new Exception('Impossible de traiter le fichier CSV');
            }

            // Ignorer les deux premières lignes (titre et en-têtes)
            for ($i = 0; $i < 2; $i++) {
                fgetcsv($handle, 0, ";");
            }

            $importes = 0;
            $erreurs = 0;

            // Optimisation: Récupérer les matricules existants en une seule fois
            $stmt_all_matricules = $db->query("SELECT matricule, id FROM usto_students");
            $existing_students_data = $stmt_all_matricules->fetchAll(PDO::FETCH_KEY_PAIR); // matricule => id

            while (($row = fgetcsv($handle, 0, ";")) !== false) {
                if (empty($row[0])) continue;

                $matricule = substr(trim($row[0]), 0, 20); // Limiter la longueur du matricule
                
                // Extraire uniquement la partie française du nom et prénom
                $nom_complet = trim($row[1]);
                $prenom_complet = trim($row[2]);

                $nom_parts = explode('/', $nom_complet);
                $nom = substr(trim($nom_parts[0]), 0, 50); 

                $prenom_parts = explode('/', $prenom_complet);
                $prenom = substr(trim($prenom_parts[0]), 0, 50); 
                
                $note = !empty($row[3]) ? number_format((float)str_replace(',', '.', $row[3]), 2, '.', '') : null;

                // Récupérer la valeur complète de la dernière colonne (format: section1/groupe 1)
                $groupe = trim(end($row));

                // Vérifier si le prénom contient des caractères invalides
                // Vérifier et nettoyer les caractères invalides
                if (!mb_check_encoding($prenom, 'UTF-8')) {
                    $prenom = '';
                    $erreurs++;
                }

                if (!mb_check_encoding($nom, 'UTF-8')) {
                    $nom = '';
                    $erreurs++;
                }

                // Vérifier si l'étudiant existe
                // $check = $db->prepare("SELECT id FROM usto_students WHERE matricule = ?"); // Ancienne méthode
                // $check->execute([$matricule]); // Ancienne méthode
                // $etudiant_id = $check->fetchColumn(); // Ancienne méthode
                $etudiant_id = $existing_students_data[$matricule] ?? null;

                if ($etudiant_id) {
                    // Mise à jour de l'étudiant existant
                    $sql = "UPDATE usto_students SET nom = ?, prenom = ?, groupe = ?"
                        . ($note_type && $note !== null ? ", $note_type = ?" : "")
                        . " WHERE matricule = ?";

                    $params = [$nom, $prenom, $groupe];
                    if ($note_type && $note !== null) {
                        $params[] = $note;
                    }
                    $params[] = $matricule;

                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute($params);
                } else {
                    // Insertion d'un nouvel étudiant
                    $sql = "INSERT INTO usto_students (matricule, nom, prenom, groupe"
                        . ($note_type && $note !== null ? ", $note_type" : "")
                        . ") VALUES (?, ?, ?, ?"
                        . ($note_type && $note !== null ? ", ?" : "")
                        . ")";

                    $params = [$matricule, $nom, $prenom, $groupe];
                    if ($note_type && $note !== null) {
                        $params[] = $note;
                    }

                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute($params);
                }

                if ($result) {
                    $importes++;
                } else {
                    $erreurs++;
                }
            }

            if ($importes > 0) {
                $success = "$importes étudiant(s) importé(s) avec succès. $erreurs erreur(s) rencontrée(s).";
            } else {
                $error = "Aucun étudiant n'a été importé. Vérifiez le format des données.";
                if ($erreurs > 0) {
                    $error .= " $erreurs erreur(s) rencontrée(s).";
                }
            }
            $db->commit(); // Valider la transaction
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack(); // Annuler la transaction en cas d'erreur
            }
            $error = "Erreur lors de l'importation du fichier: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = "Veuillez sélectionner un fichier CSV à importer.";
    }
}

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
                    <a class="nav-link" href="gerer_groupes.php">Groupes</a>
                    <a class="nav-link" href="creer_prof.php">Professeurs</a>
                    <a class="nav-link active" href="import_etudiants.php">Étudiants</a>
                    <a class="nav-link" href="gestion_notes.php">Notes</a>
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
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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