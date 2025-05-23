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

// Configuration de l'encodage pour l'importation
mb_internal_encoding('UTF-8');
$db->exec("SET NAMES utf8mb4");
$db->exec("SET CHARACTER SET utf8mb4");
$db->exec("SET collation_connection = utf8mb4_unicode_ci");

$allowed_note_types = ['note_cc', 'exam', 'ratt', 't01', 't02', 'participation'];
$note_type = $_POST['note_type'] ?? '';

// Traitement de l'importation des étudiants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_import'])) {
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        try {
            $db->beginTransaction(); // Démarrer la transaction

            // Ouvrir le fichier en mode UTF-8
            setlocale(LC_ALL, 'fr_FR.UTF-8');
            $content = file_get_contents($_FILES['csv_file']['tmp_name']);

            // Détecter et convertir l'encodage si nécessaire
            $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
            if ($encoding !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            } else {
                // Ensure content is valid UTF-8, remove BOM if present
                $content = preg_replace('/^\xef\xbb\xbf/', '', $content);
            }

            // Créer un flux temporaire avec le contenu converti
            $handle = fopen('php://memory', 'r+');
            fwrite($handle, $content);
            rewind($handle);

            if ($handle === false) {
                throw new Exception('Impossible de traiter le fichier CSV');
            } else {
                // Ignorer les deux premières lignes (titre et en-têtes)
                for ($i = 0; $i < 2; $i++) {
                    fgetcsv($handle, 0, ";");
                }
            }

            $importes = 0;
            $erreurs = 0;

            // Optimisation: Récupérer les matricules existants en une seule fois
            $stmt_all_matricules = $db->query("SELECT matricule, id FROM usto_students");
            $existing_students_data = $stmt_all_matricules->fetchAll(PDO::FETCH_KEY_PAIR); // matricule => id

            while (($row = fgetcsv($handle, 0, ";")) !== false) {
                // Skip empty rows or rows that don't look like student data
                if (empty($row[0]) || count($row) < 4) continue;

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
                $etudiant_id = $existing_students_data[$matricule] ?? null;

                if ($etudiant_id) {
                    // Mise à jour de l'étudiant existant
                    $sql = "UPDATE usto_students SET nom = ?, prenom = ?, groupe = ?"
                        . ($note_type ? ", $note_type = ?" : "")
                        . " WHERE matricule = ?";

                    $params = [$nom, $prenom, $groupe];
                    if ($note_type && $note !== null) {
                        $params[] = $note;
                    } else if ($note_type && $note === null) {
                        // If note_type is set but note is null, insert null
                        $params[] = null;
                    }
                    $params[] = $matricule;

                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute($params);
                } else {
                    // Insertion d'un nouvel étudiant
                    $sql = "INSERT INTO usto_students (matricule, nom, prenom, groupe"
                        . ($note_type ? ", $note_type" : "")
                        . ") VALUES (?, ?, ?, ?"
                        . ($note_type ? ", ?" : "")
                        . ")";

                    $params = [$matricule, $nom, $prenom, $groupe];
                    if ($note_type && $note !== null) {
                        $params[] = $note;
                    } else if ($note_type && $note === null) {
                        // If note_type is set but note is null, insert null
                        $params[] = null;
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
                } else {
                    $error = "Aucune donnée valide trouvée dans le fichier CSV.";
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
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['submit_import'])) {
    // This block catches POST requests that are not the import form or the group assignment form
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
    <title>Importation et Gestion des Groupes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1>Administration - Importation et Gestion des Groupes</h1>
                <nav class="nav nav-pills">
                    <a class="nav-link active" href="gestion.php">Gestion</a>
                    <a class="nav-link" href="creer_prof.php">Professeurs</a>
                    <a class="nav-link " href="etudiants.php">Étudiants</a>
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

        <div class="accordion" id="gestionAccordion">
            <!-- Section gestion Profs -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingProfesseurs">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProfesseurs" aria-expanded="false" aria-controls="collapseProfesseurs">
                        Gestion des professeurs
                    </button>
                </h2>
                <div id="collapseProfesseurs" class="accordion-collapse collapse" aria-labelledby="headingProfesseurs" data-bs-parent="#gestionAccordion">

                    <div class="accordion-body">

                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">Liste des professeurs</div>
                                    <div class="card-body">
                                        <?php
                                        $profs = $db->query("SELECT * FROM usto_users WHERE admin = 0 ORDER BY nom, prenom")->fetchAll();
                                        if (count($profs) > 0):
                                        ?>
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Nom</th>
                                                        <th>Prénom</th>
                                                        <th>Email</th>
                                                        <th>Statut</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($profs as $prof): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($prof['nom']) ?></td>
                                                            <td><?= htmlspecialchars($prof['prenom']) ?></td>
                                                            <td><?= htmlspecialchars($prof['email']) ?></td>
                                                            <td>
                                                                <?php if ($prof['activated'] == 1): ?>
                                                                    <span class="badge bg-success">Actif</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger">Inactif</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <a href="?toggle=<?= $prof['id'] ?>" class="btn btn-sm btn-warning">
                                                                    <?= $prof['activated'] == 1 ? 'Désactiver' : 'Activer' ?>
                                                                </a>
                                                                <a href="?reset=<?= $prof['id'] ?>" class="btn btn-sm btn-info">Réinitialiser MDP</a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <div class="alert alert-info">Aucun professeur trouvé</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">Ajouter un professeur</div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label for="nom" class="form-label">Nom</label>
                                                <input type="text" class="form-control" id="nom" name="nom" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="prenom" class="form-label">Prénom</label>
                                                <input type="text" class="form-control" id="prenom" name="prenom" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Créer le professeur</button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
                        <!-- Section Gestion des groupes -->
                        <div class="accordion-item">
                <h2 class="accordion-header" id="headingGestion">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGestion" aria-expanded="false" aria-controls="collapseGestion">
                        Gestion des groupes
                    </button>
                </h2>
                <div id="collapseGestion" class="accordion-collapse collapse" aria-labelledby="headingGestion" data-bs-parent="#gestionAccordion">
                    <div class="accordion-body">
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
                                                    <option value="">-- Non attribué --</option>
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
            <!-- Section Importation des étudiants -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingImport">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseImport" aria-expanded="true" aria-controls="collapseImport">
                        Importation des étudiants
                    </button>
                </h2>
                <div id="collapseImport" class="accordion-collapse collapse show" aria-labelledby="headingImport" data-bs-parent="#gestionAccordion">
                    <div class="accordion-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">Fichier CSV</label>
                                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                                <div class="form-text">Format attendu: Matricule, Nom, Prénom, Note (CC, Examen, Rattrapage, T01, T02, Participation), Groupe (séparés par des points-virgules)</div>
                            </div>
                            <div class="mb-3">
                                <label for="note_type" class="form-label">Type de note à importer (optionnel)</label>
                                <select name="note_type" id="note_type" class="form-select">
                                    <option value="">-- Ne pas importer de note --</option>
                                    <option value="note_cc">Note CC</option>
                                    <option value="exam">Note Examen</option>
                                    <option value="ratt">Note Rattrapage</option>
                                    <option value="t01">Note T01</option>
                                    <option value="t02">Note T02</option>
                                    <option value="participation">Note Participation</option>
                                </select>
                                <div class="form-text">Sélectionnez le type de note si la 4ème colonne du CSV contient une note spécifique. Sinon, seuls les informations étudiant et groupe seront importés/mis à jour.</div>
                            </div>
                            <button type="submit" name="submit_import" class="btn btn-primary">Importer</button>
                        </form>
                    </div>
                </div>
            </div>



        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>