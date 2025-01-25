<?php
include('header.php');
require '../db_config.php'; // Connexion à la base de données
$waitingDir = __DIR__ . '/../images/waiting/';
$targetDir = __DIR__ . '/../images/';
$thumbnailsDir = __DIR__ . '/../images/thumbnails/';

// Vérification des images dans le dossier `waiting`
$waitingImages = array_diff(scandir($waitingDir), ['.', '..']);

// Récupération des catégories depuis SQL
try {
    $categoriesQuery = $pdo->query("SELECT name FROM categories ORDER BY name");
    $categories = $categoriesQuery->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}

// Ajouter une nouvelle catégorie
if (isset($_POST['new_category'])) {
    $newCategory = trim($_POST['new_category']);
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
        $stmt->execute(['name' => $newCategory]);
        $message = "Catégorie '$newCategory' ajoutée avec succès.";
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
    }
    header("Location: index.php?message=" . urlencode($message));
    exit;
}

// Renommer une catégorie
if (isset($_POST['modify_category'], $_POST['old_category'], $_POST['new_category_name'])) {
    $oldCategory = $_POST['old_category'];
    $newCategory = trim($_POST['new_category_name']);
    try {
        $stmt = $pdo->prepare("UPDATE categories SET name = :new_name WHERE name = :old_name");
        $stmt->execute(['new_name' => $newCategory, 'old_name' => $oldCategory]);
        $message = "Catégorie '$oldCategory' renommée en '$newCategory'.";
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
    }
    header("Location: index.php?message=" . urlencode($message));
    exit;
}

// Supprimer une image ou la déplacer vers waiting
if (isset($_POST['delete_image'])) {
    $imageToDelete = $_POST['delete_image'];
    $imagePath = $targetDir . $imageToDelete;
    $thumbnailPath = $thumbnailsDir . $imageToDelete;

    if (file_exists($imagePath)) unlink($imagePath);
    if (file_exists($thumbnailPath)) unlink($thumbnailPath);

    $stmt = $pdo->prepare("DELETE FROM images WHERE file_name = :file_name");
    $stmt->execute(['file_name' => $imageToDelete]);

    $message = "L'image '$imageToDelete' a été supprimée.";
    header("Location: index.php?category=" . urlencode($_GET['category']) . "&message=" . urlencode($message));
    exit;
}

if (isset($_POST['move_to_waiting'])) {
    $imageToMove = $_POST['move_to_waiting'];
    $imagePath = $targetDir . $imageToMove;
    $destinationPath = $waitingDir . $imageToMove;

    if (file_exists($imagePath)) rename($imagePath, $destinationPath);

    $stmt = $pdo->prepare("DELETE FROM images WHERE file_name = :file_name");
    $stmt->execute(['file_name' => $imageToMove]);

    $message = "L'image '$imageToMove' a été déplacée vers 'waiting'.";
    header("Location: index.php?category=" . urlencode($_GET['category']) . "&message=" . urlencode($message));
    exit;
}

// Afficher les images d'une catégorie
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : null;
$imagesInCategory = [];
if ($selectedCategory) {
    try {
        $stmt = $pdo->prepare("
            SELECT file_name FROM images
            WHERE category_id = (SELECT id FROM categories WHERE name = :name)
        ");
        $stmt->execute(['name' => $selectedCategory]);
        $imagesInCategory = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin Galerie Moderne</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Styles modernes */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }
        h1, h2 {
            color: #333;
        }
        .message, .waiting-notification {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .message {
            background-color: #d4edda;
            color: #155724;
        }
        .waiting-notification {
            background-color: #fff3cd;
            color: #856404;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        ul li {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        .thumbnail {
            text-align: center;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            background-color: #fff;
        }
        .thumbnail img {
            max-width: 100%;
            height: auto;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Administration de la Galerie</h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="message"><?= htmlspecialchars($_GET['message']); ?></div>
    <?php endif; ?>

    <?php if (!empty($waitingImages)): ?>
        <div class="waiting-notification">
            <strong><?= count($waitingImages); ?> images</strong> sont en attente dans le dossier "waiting".
            <a href="waiting.php">Gérer les images en attente</a>
        </div>
    <?php endif; ?>

    <h2>Ajouter une catégorie</h2>
    <form method="POST">
        <input type="text" name="new_category" placeholder="Nom de la nouvelle catégorie" required>
        <button type="submit">Ajouter</button>
    </form>

    <h2>Catégories existantes</h2>
    <ul>
        <?php foreach ($categories as $category): ?>
            <li>
                <span><?= htmlspecialchars($category); ?></span>
                <div>
                    <a href="index.php?category=<?= urlencode($category); ?>">Voir</a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="old_category" value="<?= htmlspecialchars($category); ?>">
                        <input type="text" name="new_category_name" placeholder="Nouveau nom" required>
                        <button type="submit" name="modify_category">Renommer</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($selectedCategory): ?>
        <h2>Images de la catégorie : <?= htmlspecialchars($selectedCategory); ?></h2>
        <div class="gallery">
            <?php foreach ($imagesInCategory as $image): ?>
                <div class="thumbnail">
                    <img src="../images/thumbnails/<?= htmlspecialchars($image); ?>" alt="<?= htmlspecialchars($image); ?>">
                    <form method="POST">
                        <button type="submit" name="delete_image" value="<?= htmlspecialchars($image); ?>">Supprimer</button>
                        <button type="submit" name="move_to_waiting" value="<?= htmlspecialchars($image); ?>">Déplacer</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
