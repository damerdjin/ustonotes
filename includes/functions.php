<?php
// Fonction pour vérifier si l'utilisateur est un administrateur
function require_admin() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['admin'] != 1) {
        header('Location: ../index.php');
        exit();
    }
}

// Fonction pour vérifier si l'utilisateur est un professeur
function require_prof() {
    if (!isset($_SESSION['user'])) {
        header('Location: ../index.php');
        exit();
    }
}

// Fonction pour envoyer un email de bienvenue avec les identifiants
function sendWelcomeEmail($email, $password) {
    // Cette fonction est un placeholder pour l'envoi d'email
    // Dans un environnement de production, utilisez la fonction mail() ou une bibliothèque comme PHPMailer
    // Pour l'instant, nous allons simplement afficher un message
    return "Email envoyé à $email avec le mot de passe $password";
}

// Fonction pour calculer la moyenne des notes
function calculerMoyenne($t01, $t02, $participation, $note_cc, $exam) {
    // Vérifier si les valeurs sont nulles
    $t01 = $t01 ?? 0;
    $t02 = $t02 ?? 0;
    $participation = $participation ?? 0;
    $note_cc = $note_cc ?? 0;
    $exam = $exam ?? 0;
    
    // Calcul de la moyenne du contrôle continu (40%)
    $moy_cc = ($t01 + $t02 + $participation + $note_cc) / 4;
    
    // Calcul de la moyenne générale (CC 40% + Examen 60%)
    $moygen = ($moy_cc * 0.4) + ($exam * 0.6);
    
    return round($moygen);
}

// Fonction pour calculer la moyenne finale après rattrapage
function calculerMoyenneFinale($moy1, $ratt) {
    // Si pas de rattrapage, retourner la moyenne initiale
    if ($ratt === null) {
        return $moy1;
    }
    
    // Sinon prendre la meilleure des deux notes
    return max($moy1, $ratt);
}

// Fonction pour obtenir les étudiants d'un groupe pour un professeur
function getEtudiantsByGroupeAndProf($db, $groupe, $prof_id) {
    $stmt = $db->prepare("SELECT * FROM usto_students WHERE groupe = ? AND id_prof = ? ORDER BY nom, prenom");
    $stmt->execute([$groupe, $prof_id]);
    return $stmt->fetchAll();
}

// Fonction pour mettre à jour les notes d'un étudiant
function updateNotes($db, $student_id, $notes) {
    $sql = "UPDATE usto_students SET 
            t01 = ?, t02 = ?, participation = ?, note_cc = ?, exam = ?, 
            moy1 = ?, ratt = ?, moy2 = ?, moygen = ? 
            WHERE id = ?"; 
    
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        $notes['t01'], 
        $notes['t02'], 
        $notes['participation'], 
        $notes['note_cc'], 
        $notes['exam'],
        $notes['moy1'],
        $notes['ratt'],
        $notes['moy2'],
        $notes['moygen'],
        $student_id
    ]);
}
?>