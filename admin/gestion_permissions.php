<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_admin();

// Création table permissions si inexistante
$db->exec("CREATE TABLE IF NOT EXISTS usto_prof_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prof_id INT NOT NULL,
    note_type VARCHAR(20) NOT NULL,
    can_view BOOLEAN DEFAULT 1,
    can_edit BOOLEAN DEFAULT 0,
    FOREIGN KEY (prof_id) REFERENCES usto_users(id),
    UNIQUE(prof_id, note_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
error_log("Full POST data: " . print_r($_POST, true));
error_log("Received permissions datas: " . print_r($_POST['permissions'], true));

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permissions'])) {
    // Debugging: Log the received permissions data
    error_log("Received permissions data: " . print_r($_POST['permissions'], true));

    try {
        if (!$db->inTransaction()) {
    $db->beginTransaction();
}
        if (!$db->inTransaction()) {
    $db->beginTransaction();
}
        try {
            // Server-side validation: Enforce the 'Modifier' permission rules across all professors.
            // Rules:
            // - Only one non-exception type (t01, exam, ratt) can be ON across all professors.
            // - t02 and participation can be ON simultaneously across all professors.
            // - A non-exception type cannot be ON at the same time as t02 or participation across all professors.

            $allEditEnabledNoteTypes = [];
            foreach ($_POST['permissions'] as $profId => $permissions) {
                foreach ($permissions as $noteType => $settings) {
                    if (isset($settings['edit'])) { // Check if the 'edit' key exists (checkbox was checked)
                         if (!in_array($noteType, $allEditEnabledNoteTypes)) {
                             $allEditEnabledNoteTypes[] = $noteType;
                         }
                    }
                }
            }

            $exceptionTypes = ['t02', 'participation'];
            $nonExceptionTypes = array_diff($allEditEnabledNoteTypes, $exceptionTypes);
            $enabledExceptions = array_intersect($allEditEnabledNoteTypes, $exceptionTypes);

            // Rule 1: Only one non-exception type can be ON across all professors.
            if (count($nonExceptionTypes) > 1) {
                throw new Exception("Erreur: Seul un type de note (T01, Exam, Ratt) peut avoir la permission 'Modifier' activée à la fois pour tous les professeurs.");
            }

            // Rule 3: A non-exception type cannot be ON at the same time as t02 or participation across all professors.
            if (count($nonExceptionTypes) >= 1 && count($enabledExceptions) >= 1) {
                 throw new Exception("Erreur: Un type de note (T01, Exam, Ratt) ne peut pas être activé en même temps que T02 ou Participation pour tous les professeurs.");
            }

            // Rule 2: t02 and participation can be ON simultaneously across all professors (implicitly allowed if Rule 3 is not triggered and count($nonExceptionTypes) is 0)

            // If validation passes, proceed with database update
            $db->exec("DELETE FROM usto_prof_permissions");
            foreach ($_POST['permissions'] as $profId => $permissions) {
                foreach ($permissions as $noteType => $settings) {
                    $stmt = $db->prepare("INSERT INTO usto_prof_permissions 
                        (prof_id, note_type, can_view, can_edit)
                        VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $profId,
                        $noteType,
                        isset($settings['view']) ? 1 : 0,
                        isset($settings['edit']) ? 1 : 0
                    ]);
                }
            }
            if ($db->inTransaction()) {
    $db->commit();
}
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erreur lors de la mise à jour: " . $e->getMessage();
        }
        $success = "Permissions mises à jour avec succès!";
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}

// Récupération données
$profs = $db->query("SELECT id, nom, prenom FROM usto_users WHERE admin = 0")->fetchAll();
$noteTypes = ['t01', 't02', 'participation', 'exam', 'ratt'];
$stmt = $db->query("SELECT prof_id, note_type, can_view, can_edit FROM usto_prof_permissions");
// Remove the first fetchAll and its debugging
// $rawPermissionsData = $stmt->fetchAll();
// error_log("Raw Permissions Data: " . print_r($rawPermissionsData, true));

// Fetch data once and process it manually
$fetchedPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$permissionsData = [];
foreach ($fetchedPermissions as $permission) {
    $permissionsData[$permission['prof_id']][$permission['note_type']] = [
        'can_view' => $permission['can_view'],
        'can_edit' => $permission['can_edit']
    ];
}

// Debugging: Check the structure of $permissionsData
error_log("Permissions Data Structure: " . print_r($permissionsData, true));

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Permissions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sticky-col {
            position: sticky;
            left: 0;
            background: white;
            z-index: 1;
        }
        .table-responsive {
            max-height: 70vh;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <?php include '../includes/alerts.php'; ?>

        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Gestion des Permissions</h3>
            </div>
            
            <div class="card-body">
                <form method="POST">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th class="sticky-col">Enseignant</th>
                                    <?php foreach ($noteTypes as $type): ?>
                                        <th colspan="2" class="text-center header-<?= $type ?>" data-note-type="<?= $type ?>"><?= strtoupper($type) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <th class="sticky-col"></th>
                                    <?php foreach ($noteTypes as $type): ?>
                                        <th class="text-center small header-view" data-note-type="<?= $type ?>">Voir</th>
                                        <th class="text-center small header-edit" data-note-type="<?= $type ?>">Modifier</th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            
                            <tbody>
                                <?php foreach ($profs as $prof): ?>
                                <tr>
                                    <td class="sticky-col fw-bold">
                                        <?= htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']) ?>
                                    </td>
                                    
                                    <?php foreach ($noteTypes as $type): 
                                        // Debugging: Check the type and value of keys
                                        error_log("Accessing permissionsData with Prof ID: " . $prof['id'] . " (Type: " . gettype($prof['id']) . ") and Note Type: " . $type . " (Type: " . gettype($type) . ")");
                                        $perm = $permissionsData[$prof['id']][$type] ?? ['can_view' => 0, 'can_edit' => 0];
                                        // Debugging output
                                        error_log("Prof ID: " . $prof['id'] . ", Note Type: " . $type . ", Permissions: " . print_r($perm, true));
                                    ?>
                                        <td class="text-center align-middle">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input" type="checkbox"
                                                    name="permissions[<?= $prof['id'] ?>][<?= $type ?>][view]"
                                                    id="view-<?= $prof['id'] ?>-<?= $type ?>"
                                                    <?= $perm['can_view'] ? 'checked' : '' ?>>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input" type="checkbox"
                                                    name="permissions[<?= $prof['id'] ?>][<?= $type ?>][edit]"
                                                    id="edit-<?= $prof['id'] ?>-<?= $type ?>"
                                                    <?= $perm['can_edit'] ? 'checked' : '' ?>
                                                    <?= $perm['can_view'] ? '' : 'disabled' ?>>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-secondary" id="toggle-all-view">Tout Voir</button>
                        <button type="button" class="btn btn-secondary" id="toggle-all-edit">Tout Modifier</button>
                        <button type="submit" class="btn btn-success">Sauvegarder les Permissions</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const viewHeaders = document.querySelectorAll('.header-view');
    const editHeaders = document.querySelectorAll('.header-edit');

    viewHeaders.forEach(header => {
        header.style.cursor = 'pointer'; // Indicate clickable
        header.addEventListener('click', function() {
            const noteType = this.getAttribute('data-note-type');
            const checkboxes = document.querySelectorAll(`input[name^="permissions"][name$="[${noteType}][view]"]`);
            checkboxes.forEach(checkbox => {
                checkbox.checked = !checkbox.checked;
            });
        });
    });

    editHeaders.forEach(header => {
        header.style.cursor = 'pointer'; // Indicate clickable
        header.addEventListener('click', function() {
            const noteType = this.getAttribute('data-note-type');
            const checkboxes = document.querySelectorAll(`input[name^="permissions"][name$="[${noteType}][edit]"]`);
            checkboxes.forEach(checkbox => {
                // Only toggle if the corresponding 'Voir' checkbox is checked
                const viewCheckboxId = checkbox.id.replace('edit-', 'view-');
                const viewCheckbox = document.getElementById(viewCheckboxId);
                if (viewCheckbox && viewCheckbox.checked) {
                     checkbox.checked = !checkbox.checked;
                }
            });
        });
    });
});
</script>
</body>
</html>