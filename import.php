<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Paramètres de connexion à la base de données
$host = 'localhost';
$dbname = 'gallery';  
$user = 'hansplaast';
$password = 'rEabdNJV9YqexhG';

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Charger et décoder le fichier JSON
$jsonData = file_get_contents('data.json');
$data = json_decode($jsonData, true);

if (!$data) {
    die("Erreur : Impossible de lire ou de décoder le fichier JSON.");
}

// Parcourir chaque catégorie et ses images
foreach ($data['categories'] as $categoryName => $imagesList) {
    // Vérifier si la catégorie existe déjà
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = :name");
    $stmt->execute(['name' => $categoryName]);
    $categoryId = $stmt->fetchColumn();

    if (!$categoryId) {
        // Si elle n'existe pas, l'insérer
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
        $stmt->execute(['name' => $categoryName]);
        $categoryId = $pdo->lastInsertId();
    }

    // Insérer chaque image liée à cette catégorie
    $stmt = $pdo->prepare("INSERT INTO images (filename, category_id) VALUES (:filename, :category_id)");
    foreach ($imagesList as $filename) {
        $stmt->execute([
            'filename' => $filename,
            'category_id' => $categoryId
        ]);
    }
}

echo "Importation terminée avec succès.";
