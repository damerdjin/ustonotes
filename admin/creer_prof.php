<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_admin();

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Génération d'un mot de passe aléatoire de 6 caractères
    $password = generatePassword();

    // Vérification que l'email n'existe pas déjà
    $check = $db->prepare("SELECT COUNT(*) FROM usto_users WHERE email = ?");
    $check->execute([$_POST['email']]);

    if ($check->fetchColumn() > 0) {
        $error = "Cet email est déjà utilisé par un autre utilisateur";
    } else {
        // Insertion en BDD
        $stmt = $db->prepare("INSERT INTO usto_users (nom, prenom, email, passwd, admin, activated) VALUES (?, ?, ?, ?, 0, 1)");
        $result = $stmt->execute([
            $_POST['nom'],
            $_POST['prenom'],
            $_POST['email'],
            $password
        ]);

        if ($result) {
            $success = "Professeur créé avec succès. Mot de passe : " . $password;
            // Envoi d'email (pseudo-code)
            $emailResult = sendWelcomeEmail($_POST['email'], $password);
        } else {
            $error = "Erreur lors de la création du professeur";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création de Professeur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1>Administration - Création de Professeur</h1>
                <nav class="nav nav-pills">
                    <a class="nav-link" href="gestion.php">Gestion</a>
                    <a class="nav-link active" href="creer_prof.php">Professeurs</a>
                    <a class="nav-link" href="etudiants.php">Étudiants</a>
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
                                        <th>ID</th>
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
                                            <td><?= $prof['id'] ?></td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>