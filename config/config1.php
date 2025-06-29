<?php
// Configuration de la base de données pour PDO
$servername = "localhost";
$username = "root"; // Changez pour votre nom d'utilisateur de base de données
$password = ""; // Changez pour votre mot de passe de base de données
$dbname = "consult_pro"; // Changez pour votre nom de base de données

// Créer la connexion PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
