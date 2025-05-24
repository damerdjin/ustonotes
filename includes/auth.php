<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = $_POST['email'];
    $password = $_POST['passwd'];
    
    $stmt = $db->prepare("SELECT * FROM usto_users WHERE email = ? AND activated = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && $password === $user['passwd']) {
        $_SESSION['user'] = $user;
        
        if ($user['admin'] == 1) {
            header('Location: admin/gestion.php');
        } else {
            header('Location: prof/dashboard.php');
        }
        exit();
    } else {
        $error = "Identifiants incorrects";
    }
}
?>