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
    $note_type = $_POST['note_type'] ?? '';
    
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        try {
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
            
            while (($row = fgetcsv($handle, 0, ";")) !== false) {
                if (empty($row[0])) continue;
                
                $matricule = substr(trim($row[0]), 0, 20); // Limiter la longueur du matricule
                $nom = substr(trim($row[1]), 0, 50); // Limiter la longueur du nom
                $prenom = substr(trim($row[2]), 0, 50); // Limiter la longueur du prénom
                $note = !empty($row[3]) ? number_format((float)$row[3], 2, '.', '') : null;
                
                // Récupérer la valeur complète de la dernière colonne (format: section1/groupe 1)
                $groupe = trim(end($row));
                
                    // Vérifier si le prénom contient des caractères invalides
    if (!mb_check_encoding($prenom, 'UTF-8')) {
        throw new Exception("$prenom");
    }

    // Vérifier si le nom contient des caractères invalides
    if (!mb_check_encoding($nom, 'UTF-8')) {
        throw new Exception("Caractère invalide dans le nom à la ligne $line_number: $nom");
    }

                // Vérifier si l'étudiant existe
                $check = $db->prepare("SELECT id FROM usto_students WHERE matricule = ?");
                $check->execute([$matricule]);
                $etudiant_id = $check->fetchColumn();
                
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
            }
            
        } catch (Exception $e) {
            $error = "Erreur lors de l'importation du fichier: " . $e->getMessage();
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
        CAST(s.moygen AS DECIMAL(4,2)) as moygen
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
                    <div class="card-header">Importer des étudiants</div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">Fichier CSV</label>
                                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                                <div class="form-text">Format attendu: Matricule, Nom, Prénom, Note, etc. (séparés par des points-virgules)</div>
                            </div>
                            <div class="mb-3">
                                <label for="note_type" class="form-label">Type de note à importer</label>
                                <select name="note_type" id="note_type" class="form-select">
                                    <option value="">Aucune note</option>
                                    <option value="note_cc">Note CC</option>
                                    <option value="exam">Note Examen</option>
                                    <option value="ratt">Note Rattrapage</option>
                                    <option value="t01">Test 1</option>
                                    <option value="t02">Test 2</option>
                                    <option value="participation">Participation (Note/2)</option>
                                </select>
                            </div>
                            <button type="submit" name="submit_import" class="btn btn-primary">Importer</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Filtrer les étudiants</div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <select name="filter_groupe" class="form-select">
                                    <option value="">Tous les groupes</option>
                                    <?php foreach ($groupes as $g): ?>
                                        <option value="<?= htmlspecialchars($g['groupe']) ?>" <?= $filter_groupe == $g['groupe'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($g['groupe']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <select name="filter_prof" class="form-select">
                                    <option value="">Tous les professeurs</option>
                                    <?php foreach ($professeurs as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $filter_prof == $p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
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
                                            <th><?php
                                                switch($note_type) {
                                                    case 'note_cc': echo 'CC'; break;
                                                    case 'exam': echo 'Examen'; break;
                                                    case 'ratt': echo 'Rattrapage'; break;
                                                    case 't01': echo 'Test 1'; break;
                                                    case 't02': echo 'Test 2'; break;
                                                    case 'participation': echo 'Participation'; break;
                                                    default: echo 'Note';
                                                }
                                            ?></th>
                                            <th>Moyenne</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etudiants as $e): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($e['matricule']) ?></td>
                                                <td><?= htmlspecialchars($e['nom']) ?></td>
                                                <td><?= htmlspecialchars($e['prenom']) ?></td>
                                                <td>
                                                    <?php 
                                                    echo htmlspecialchars($e['groupe']);
                                                    ?>
                                                </td>
                                                <td><?= htmlspecialchars($e['prof_nom']) ?></td>
                                                <td>
                                                    <?php if (isset($e[$note_type])): ?>
                                                        <span class="badge bg-<?= $e[$note_type] >= 10 ? 'success' : 'danger' ?>">
                                                            <?= number_format($e[$note_type], 2, '.', '') ?>/20
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Non évalué</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($e['moygen']): ?>
                                                        <span class="badge bg-<?= $e['moygen'] >= 10 ? 'success' : 'danger' ?>">
                                                            <?= $e['moygen'] ?>/20
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Non évalué</span>
                                                    <?php endif; ?>
                                                </td>
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
</body>
</html>
