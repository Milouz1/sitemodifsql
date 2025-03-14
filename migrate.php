<?php
// migrate.php

// Configuration de la connexion à la base de données
$host = 'localhost';
$db   = 'gallery'; // Nom de votre base de données
$user = 'admin2'; // Remplacez par votre utilisateur
$pass = '789551'; // Remplacez par votre mot de passe
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Activer les exceptions en cas d'erreur
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Mode de récupération des données
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Désactiver l'émulation des requêtes préparées
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connexion à la base de données réussie.\n";
} catch (PDOException $e) {
    die('Connexion échouée : ' . $e->getMessage());
}

// Chemin vers le fichier data.json
$dataFile = 'data.json';

// Vérifier si le fichier JSON existe
if (!file_exists($dataFile)) {
    die("Erreur : Le fichier '$dataFile' n'existe pas.\n");
}

// Lire le contenu du fichier JSON
$jsonContent = file_get_contents($dataFile);

// Décoder le JSON en tableau associatif
$data = json_decode($jsonContent, true);

// Vérifier si le décodage a réussi
if ($data === null) {
    die("Erreur : Impossible de décoder le fichier JSON.\n");
}

// Vérifier si la clé 'categories' existe
if (!isset($data['categories']) || !is_array($data['categories'])) {
    die("Erreur : Le fichier JSON ne contient pas de clé 'categories' valide.\n");
}

// Préparer les requêtes SQL
$insertCategorySQL = "INSERT INTO categories (name) VALUES (:name) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
$insertImageSQL = "INSERT INTO images (file_name, category_id, likes, last_modified) VALUES (:file_name, :category_id, :likes, :last_modified)";

$insertCategoryStmt = $pdo->prepare($insertCategorySQL);
$insertImageStmt = $pdo->prepare($insertImageSQL);

// Commencer une transaction
$pdo->beginTransaction();

try {
    foreach ($data['categories'] as $categoryName => $images) {
        // Insérer la catégorie ou récupérer son ID si elle existe déjà
        $insertCategoryStmt->execute([':name' => $categoryName]);
        $categoryId = $pdo->lastInsertId();

        // Insérer chaque image dans la catégorie
        foreach ($images as $imageFileName) {
            // Optionnel : Vérifier si l'image existe dans le dossier 'images'
            $imagePath = "images/$imageFileName";
            if (!file_exists($imagePath)) {
                echo "Attention : L'image '$imageFileName' n'existe pas dans le dossier 'images'.\n";
                // Vous pouvez choisir de continuer ou d'arrêter la migration
                // continue;
            }

            // Optionnel : Définir des valeurs par défaut pour 'likes' et 'last_modified'
            $likes = 0;

            // Utiliser la date actuelle pour 'last_modified' ou personnaliser selon vos besoins
            $lastModified = date('Y-m-d H:i:s'); // Vous pouvez ajuster cette valeur

            // Exécuter l'insertion de l'image
            $insertImageStmt->execute([
                ':file_name'    => $imageFileName,
                ':category_id'  => $categoryId,
                ':likes'        => $likes,
                ':last_modified'=> $lastModified
            ]);

            echo "Insérée : $imageFileName dans la catégorie '$categoryName' (ID: $categoryId).\n";
        }
    }

    // Valider la transaction
    $pdo->commit();
    echo "Migration terminée avec succès.\n";
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $pdo->rollBack();
    die('Erreur lors de la migration : ' . $e->getMessage());
}
?>