<?php
// Configuration de la connexion à la base de données MariaDB
$host = '127.0.0.1'; // Adresse du serveur
$dbname = 'gallery'; // Nom de la base de données
$username = 'admin2'; // Nom d'utilisateur
$password = '789551'; // Mot de passe

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>