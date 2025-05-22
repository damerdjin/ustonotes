<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_prof();

// Récupération des groupes associés au professeur
$stmt = $db->prepare("SELECT g.* FROM usto_groupes g 
                     JOIN usto_prof_groupes pg ON g.id = pg.groupe_id 
                     WHERE pg.prof_id = ? 
                     ORDER BY g.nom_groupe");
$stmt->execute([$_SESSION['user']['id']]);
$groupes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Professeur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1>Tableau de bord - Professeur</h1>
                <p>Bienvenue, <?= htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']) ?></p>
                <nav class="nav nav-pills">
                    <a class="nav-link active" href="dashboard.php">Tableau de bord</a>
                    <a class="nav-link" href="saisie_notes.php">Saisie des notes</a>
                    <a class="nav-link text-danger" href="../logout.php">Déconnexion</a>
                </nav>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Mes groupes</div>
                    <div class="card-body">
                        <?php if (empty($groupes)): ?>
                            <div class="alert alert-info">Aucun groupe ne vous a été assigné pour le moment.</div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($groupes as $groupe): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($groupe['nom_groupe']) ?></h5>
                                                <a href="saisie_notes.php?groupe=<?= $groupe['id'] ?>" class="btn btn-primary">Saisir les notes</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>