<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_prof();

// Check if it's the first login, otherwise redirect to dashboard
if (!isset($_SESSION['user']['first_login']) || $_SESSION['user']['first_login'] != 1) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        // Update password and set first_login to 0
        // Note: Passwords should ideally be hashed before storing in the database.
        // The current database schema uses VARCHAR(15) for password, which is very short and insecure.
        // Consider changing the password column type to VARCHAR(255) and using password_hash() and password_verify().
        $stmt = $db->prepare("UPDATE usto_users SET passwd = ?, first_login = 0 WHERE id = ?");
        if ($stmt->execute([$new_password, $_SESSION['user']['id']])) {
            // Update session variable
            $_SESSION['user']['first_login'] = 0;
            $success = 'Votre mot de passe a été mis à jour avec succès.';
            // Redirect to dashboard after a short delay
            header('Refresh: 3; URL=dashboard.php');
        } else {
            $error = 'Erreur lors de la mise à jour du mot de passe.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header">Changer votre mot de passe</div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php else: ?>
                            <p>Veuillez changer votre mot de passe pour continuer.</p>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>