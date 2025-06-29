<?php
// Configuration de la base de données
$servername = "localhost";
$username = "root"; // Changez pour votre nom d'utilisateur de base de données
$password = ""; // Changez pour votre mot de passe de base de données
$dbname = "consult_pro"; // Changez pour votre nom de base de données

// Créer la connexion
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>