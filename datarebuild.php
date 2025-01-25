<?php
// Connexion à la base de données
$host = 'localhost';
$dbname = 'gallery';
$username = 'admin2';
$password = '789551';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Charger le fichier JSON
    $jsonFile = 'data.json';
    $jsonData = json_decode(file_get_contents($jsonFile), true);

    // Insérer les catégories et les images
    foreach ($jsonData['categories'] as $category => $images) {
        // Insérer la catégorie
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
        $stmt->execute(['name' => $category]);
        $categoryId = $pdo->lastInsertId();

        // Insérer les images associées
        $stmt = $pdo->prepare("INSERT INTO images (file_name, category_id) VALUES (:file_name, :category_id)");
        foreach ($images as $image) {
            $stmt->execute(['file_name' => $image, 'category_id' => $categoryId]);
        }
    }

    echo "Migration réussie !";
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>
