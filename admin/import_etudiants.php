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
                $note = !empty($row[3]) ? floatval($row[3]) : null;
                
                // Extraire le groupe de la dernière colonne (format: section1/groupe 1)
                $groupe_info = explode('/', end($row));
                $groupe = trim(end($groupe_info));
                
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
        $error = "Veuillez sélectionner un fichier XLSX à importer.";
    }
}

// Traitement de l'affichage des étudiants
$filter_groupe = isset($_GET['filter_groupe']) ? $_GET['filter_groupe'] : '';
$filter_prof = isset($_GET['filter_prof']) ? $_GET['filter_prof'] : '';

$sql = "SELECT s.*, u.nom as prof_nom, u.prenom as prof_prenom 
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
                                        <option value="<?= $g['id'] ?>" <?= $filter_groupe == $g['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($g['nom_groupe']) ?>
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
                                            <th>ID</th>
                                            <th>Matricule</th>
                                            <th>Nom</th>
                                            <th>Prénom</th>
                                            <th>Groupe</th>
                                            <th>Professeur</th>
                                            <th>Moyenne</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etudiants as $e): ?>
                                            <tr>
                                                <td><?= $e['id'] ?></td>
                                                <td><?= htmlspecialchars($e['matricule']) ?></td>
                                                <td><?= htmlspecialchars($e['nom']) ?></td>
                                                <td><?= htmlspecialchars($e['prenom']) ?></td>
                                                <td>
                                                    <?php 
                                                    $groupe_nom = "";
                                                    foreach ($groupes as $g) {
                                                        if ($g['id'] == $e['groupe']) {
                                                            $groupe_nom = $g['nom_groupe'];
                                                            break;
                                                        }
                                                    }
                                                    echo htmlspecialchars($groupe_nom);
                                                    ?>
                                                </td>
                                                <td><?= htmlspecialchars($e['prof_nom'] . ' ' . $e['prof_prenom']) ?></td>
                                                <td>
                                                    <?php if ($e['moygen']): ?>
                                                        <span class="badge bg-<?= $e['moygen'] >= 10 ? 'success' : 'danger' ?>">
                                                            <?= $e['moygen'] ?>/20
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Non évalué</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="?edit=<?= $e['id'] ?>" class="btn btn-sm btn-warning">Modifier</a>
                                                    <a href="?delete=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet étudiant?')">Supprimer</a>
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
