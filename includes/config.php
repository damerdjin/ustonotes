<?php
session_start();
define('DB_HOST', 'db');
define('DB_NAME', 'my_database');
define('DB_USER', 'user');
define('DB_PASS', 'user_password');

// Vérifier si l'extension PDO est disponible
if (!extension_loaded('pdo_mysql')) {
    die("Erreur : L'extension PDO pour MySQL n'est pas activée. Veuillez activer l'extension pdo_mysql dans votre configuration PHP.");
}

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Fonction de génération de mot de passe
function generatePassword($length = 6) {
    return str_pad(rand(0, 999999), $length, '0', STR_PAD_LEFT);
}
?>