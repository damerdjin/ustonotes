<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_admin();

$success = null;
$error = null;

// Récupération des groupes
$stmt = $db->query("SELECT DISTINCT groupe FROM usto_students ORDER BY groupe");
$groupes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Récupération des professeurs
$stmt = $db->query("SELECT * FROM usto_users WHERE admin = 0 AND activated = 1 ORDER BY nom, prenom");
$professeurs = $stmt->fetchAll();

// Traitement des filtres
$filter_groupe = isset($_GET['filter_groupe']) ? $_GET['filter_groupe'] : '';
$filter_prof = isset($_GET['filter_prof']) ? $_GET['filter_prof'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

// Construction de la requête SQL avec les filtres
$sql = "SELECT s.*, u.nom as prof_nom, u.prenom as prof_prenom, s.groupe as nom_groupe 
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

if ($filter_status === 'success') {
    $sql .= " AND s.moygen >= 10";
} elseif ($filter_status === 'fail') {
    $sql .= " AND s.moygen < 10 AND s.moygen IS NOT NULL";
} elseif ($filter_status === 'not_evaluated') {
    $sql .= " AND s.moygen IS NULL";
}

$sql .= " ORDER BY s.nom, s.prenom";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$etudiants = $stmt->fetchAll();

// Calcul des statistiques
$total_etudiants = count($etudiants);
$admis = 0;
$ajournes = 0;
$non_evalues = 0;
$somme_notes = 0;

foreach ($etudiants as $e) {
    if ($e['moygen'] === null) {
        $non_evalues++;
    } elseif ($e['moygen'] >= 10) {
        $admis++;
        $somme_notes += $e['moygen'];
    } else {
        $ajournes++;
        $somme_notes += $e['moygen'];
    }
}

$moyenne_generale = ($total_etudiants - $non_evalues) > 0 ? round($somme_notes / ($total_etudiants - $non_evalues), 2) : 0;
$taux_reussite = ($total_etudiants - $non_evalues) > 0 ? round(($admis / ($total_etudiants - $non_evalues)) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Notes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1>Administration - Gestion des Notes</h1>
                <nav class="nav nav-pills">
                    <a class="nav-link" href="gerer_groupes.php">Groupes</a>
                    <a class="nav-link" href="creer_prof.php">Professeurs</a>
                    <a class="nav-link" href="import_etudiants.php">Étudiants</a>
                    <a class="nav-link active" href="gestion_notes.php">Notes</a>
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
                    <div class="card-header">Filtrer les résultats</div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select name="filter_groupe" class="form-select">
                                    <option value="">Tous les groupes</option>
                                    <?php foreach ($groupes as $groupe): ?>
                                        <option value="<?= $groupe ?>" <?= $filter_groupe == $groupe ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($groupe) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="filter_prof" class="form-select">
                                    <option value="">Tous les professeurs</option>
                                    <?php foreach ($professeurs as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $filter_prof == $p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="filter_status" class="form-select">
                                    <option value="">Tous les statuts</option>
                                    <option value="success" <?= $filter_status === 'success' ? 'selected' : '' ?>>Admis</option>
                                    <option value="fail" <?= $filter_status === 'fail' ? 'selected' : '' ?>>Ajournés</option>
                                    <option value="not_evaluated" <?= $filter_status === 'not_evaluated' ? 'selected' : '' ?>>Non évalués</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Statistiques</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Total étudiants:</strong> <?= $total_etudiants ?>
                        </div>
                        <div class="mb-3">
                            <strong>Admis:</strong> <?= $admis ?> (<?= $taux_reussite ?>%)
                        </div>
                        <div class="mb-3">
                            <strong>Ajournés:</strong> <?= $ajournes ?> (<?= ($total_etudiants - $non_evalues) > 0 ? round(($ajournes / ($total_etudiants - $non_evalues)) * 100, 2) : 0 ?>%)
                        </div>
                        <div class="mb-3">
                            <strong>Non évalués:</strong> <?= $non_evalues ?> (<?= $total_etudiants > 0 ? round(($non_evalues / $total_etudiants) * 100, 2) : 0 ?>%)
                        </div>
                        <div class="mb-3">
                            <strong>Moyenne générale:</strong> <?= $moyenne_generale ?>/20
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Graphique des résultats</div>
                    <div class="card-body">
                        <canvas id="resultsChart"></canvas>
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
                                            <th>CC</th>
                                            <th>Examen</th>
                                            <th>Moyenne</th>
                                            <th>Rattrapage</th>
                                            <th>Moyenne Finale</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etudiants as $e): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($e['matricule']) ?></td>
                                                <td><?= htmlspecialchars($e['nom']) ?></td>
                                                <td><?= htmlspecialchars($e['prenom']) ?></td>
                                                <td><?= htmlspecialchars($e['nom_groupe']) ?></td>
                                                <td><?= htmlspecialchars($e['prof_nom'] . ' ' . $e['prof_prenom']) ?></td>
                                                <td>
                                                    <?php 
                                                    $cc = ($e['t01'] + $e['t02'] + $e['participation'] + $e['note_cc']) / 4;
                                                    echo round($cc, 2);
                                                    ?>
                                                </td>
                                                <td><?= $e['exam'] ?></td>
                                                <td><?= $e['moy1'] ?></td>
                                                <td><?= $e['ratt'] ?: '-' ?></td>
                                                <td><?= $e['moy2'] ?></td>
                                                <td>
                                                    <?php if ($e['moygen'] === null): ?>
                                                        <span class="badge bg-secondary">Non évalué</span>
                                                    <?php elseif ($e['moygen'] >= 10): ?>
                                                        <span class="badge bg-success">Admis</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Ajourné</span>
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
    
    <script>
        // Configuration du graphique
        const ctx = document.getElementById('resultsChart').getContext('2d');
        const resultsChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Admis', 'Ajournés', 'Non évalués'],
                datasets: [{
                    data: [<?= $admis ?>, <?= $ajournes ?>, <?= $non_evalues ?>],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(108, 117, 125, 0.7)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Répartition des résultats'
                    }
                }
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>