<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_admin();

$success = null;
$error = null;

// Récupération des groupes
$stmt = $db->query("SELECT * FROM usto_groupes ORDER BY nom_groupe");
$groupes = $stmt->fetchAll();

// Récupération des professeurs
$stmt = $db->query("SELECT * FROM usto_users WHERE admin = 0 AND activated = 1 ORDER BY nom, prenom");
$professeurs = $stmt->fetchAll();

// Traitement du formulaire d'importation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_import'])) {
    $groupe = $_POST['groupe'];
    $prof_id = $_POST['prof_id'];
    $etudiants_data = $_POST['etudiants'];
    
    // Vérification des données
    if (empty($groupe) || empty($prof_id) || empty($etudiants_data)) {
        $error = "Tous les champs sont obligatoires";
    } else {
        // Traitement des données des étudiants
        $lignes = explode("\n", $etudiants_data);
        $importes = 0;
        $erreurs = 0;
        
        foreach ($lignes as $ligne) {
            $ligne = trim($ligne);
            if (empty($ligne)) continue;
            
            // Format attendu: Matricule;Nom;Prénom
            $data = explode(";", $ligne);
            if (count($data) >= 3) {
                $matricule = trim($data[0]);
                $nom = trim($data[1]);
                $prenom = trim($data[2]);
                
                // Vérification si l'étudiant existe déjà
                $check = $db->prepare("SELECT COUNT(*) FROM usto_students WHERE matricule = ?");
                $check->execute([$matricule]);
                
                if ($check->fetchColumn() > 0) {
                    // Mise à jour de l'étudiant existant
                    $stmt = $db->prepare("UPDATE usto_students SET nom = ?, prenom = ?, groupe = ?, id_prof = ? WHERE matricule = ?");
                    $result = $stmt->execute([$nom, $prenom, $groupe, $prof_id, $matricule]);
                } else {
                    // Insertion d'un nouvel étudiant
                    $stmt = $db->prepare("INSERT INTO usto_students (matricule, nom, prenom, groupe, id_prof) VALUES (?, ?, ?, ?, ?)");
                    $result = $stmt->execute([$matricule, $nom, $prenom, $groupe, $prof_id]);
                }
                
                if ($result) {
                    $importes++;
                } else {
                    $erreurs++;
                }
            } else {
                $erreurs++;
            }
        }
        
        if ($importes > 0) {
            $success = "$importes étudiant(s) importé(s) avec succès. $erreurs erreur(s) rencontrée(s).";
        } else {
            $error = "Aucun étudiant n'a été importé. Vérifiez le format des données.";
        }
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
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="groupe" class="form-label">Groupe</label>
                                    <select name="groupe" id="groupe" class="form-select" required>
                                        <option value="">-- Sélectionner un groupe --</option>
                                        <?php foreach ($groupes as $g): ?>
                                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nom_groupe']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="prof_id" class="form-label">Professeur</label>
                                    <select name="prof_id" id="prof_id" class="form-select" required>
                                        <option value="">-- Sélectionner un professeur --</option>
                                        <?php foreach ($professeurs as $p): ?>
                                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="etudiants" class="form-label">Liste des étudiants (format: Matricule;Nom;Prénom)</label>
                                <textarea name="etudiants" id="etudiants" class="form-control" rows="10" required></textarea>
                                <div class="form-text">Un étudiant par ligne. Format: Matricule;Nom;Prénom</div>
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