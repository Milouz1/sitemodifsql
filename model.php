<?php
// ========================
// 1. Configuration de l'environnement
// ========================

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========================
// 2. Inclusion des fichiers nécessaires
// ========================

if (file_exists('access-test.php')) {
    include('access-test.php');
}

if (file_exists('header.php')) {
    include('header.php');
} else {
    die('Erreur interne du serveur. Veuillez réessayer plus tard.');
}

// ========================
// 3. Définition des dossiers d'images et de miniatures
// ========================

$imageFolder = 'images'; 
$thumbnailsFolder = 'images/thumbnails';

// ========================
// 4. Fonctions Utilitaires
// ========================

function sanitizeFileName($filename) {
    return basename($filename);
}

function createThumbnail($originalImagePath, $thumbnailPath, $thumbWidth = 250) {
    if (!file_exists($originalImagePath)) {
        error_log("Image non trouvée : $originalImagePath");
        return;
    }
    list($width, $height, $type) = getimagesize($originalImagePath);
    if (!$width || !$height) {
        error_log("Dimensions d'image invalides : $originalImagePath");
        return;
    }
    $thumbHeight = floor($height * ($thumbWidth / $width));
    $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
    $imageExtension = strtolower(pathinfo($originalImagePath, PATHINFO_EXTENSION));
    if (in_array($imageExtension, ['png', 'gif'])) {
        imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }
    switch ($imageExtension) {
        case 'jpg':
        case 'jpeg':
            $source = imagecreatefromjpeg($originalImagePath);
            break;
        case 'png':
            $source = imagecreatefrompng($originalImagePath);
            break;
        case 'gif':
            $source = imagecreatefromgif($originalImagePath);
            break;
        default:
            error_log("Type de fichier non supporté : $imageExtension");
            return;
    }
    if (!$source) {
        error_log("Échec de la création de la ressource image depuis : $originalImagePath");
        return;
    }
    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
    switch ($imageExtension) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($thumbnail, $thumbnailPath, 90);
            break;
        case 'png':
            imagepng($thumbnail, $thumbnailPath, 8);
            break;
        case 'gif':
            imagegif($thumbnail, $thumbnailPath);
            break;
    }
    imagedestroy($source);
    imagedestroy($thumbnail);
}

// ========================
// 5. Vérification et Création des Dossiers
// ========================

if (!is_dir($thumbnailsFolder)) {
    if (!mkdir($thumbnailsFolder, 0755, true)) {
        error_log("Échec de la création du dossier des miniatures : $thumbnailsFolder");
        die('Erreur interne du serveur. Veuillez réessayer plus tard.');
    }
}

$logFolder = __DIR__ . '/logs';
if (!is_dir($logFolder)) {
    if (!mkdir($logFolder, 0755, true)) {
        die('Erreur interne du serveur. Veuillez réessayer plus tard.');
    }
}

// ========================
// 6. Charger et Traiter les Données
// ========================

require 'db_config.php'; // Connexion à la base de données

$groupedImages = [];

try {
    $stmt = $pdo->query("
        SELECT categories.name AS category, images.file_name
        FROM categories
        JOIN images ON categories.id = images.category_id
        ORDER BY categories.name
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $groupedImages[$row['category']][] = $row['file_name'];
    }
} catch (PDOException $e) {
    error_log('Erreur lors de la récupération des données : ' . $e->getMessage());
    die('Erreur interne du serveur. Veuillez réessayer plus tard.');
}

// ========================
// 7. Trier les Modèles par Ordre Alphabétique
// ========================
ksort($groupedImages, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Galerie d'Images par Modèle</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="styles.css">

    <style>
        /* Styles spécifiques à la grille d'images */
        .model-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
            align-items: start;
        }

        .model-card {
            background-color: #27292b;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .model-card:hover {
            transform: scale(1.03);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.6);
        }

        .model-card img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 15px;
            object-fit: cover;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.4);
        }

        .model-title {
            font-size: 1.2rem;
            color: #00adee;
            text-decoration: none;
            transition: color 0.3s;
        }

        .model-title:hover {
            color: #58c6f7;
        }

        .model-count {
            font-size: 0.95rem;
            color: #bbb;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <h1>Image gallery by model</h1>
    <div class="content">
        <div class="model-container">
            <?php if (!empty($groupedImages)): ?>
                <?php foreach ($groupedImages as $modelName => $modelImages): ?>
                    <?php
                        $randomImage = null;
                        if (!empty($modelImages)) {
                            $randomImage = $modelImages[array_rand($modelImages)];
                        }
                        $imageCount = count($modelImages);
                        $safeImage = $randomImage ? sanitizeFileName($randomImage) : null;
                        $thumbnailPath = $safeImage ? $thumbnailsFolder . '/' . $safeImage : 'images/default-thumbnail.jpg';
                    ?>
                    <div class="model-card">
                        <a href="model-detail.php?model=<?= urlencode($modelName); ?>" class="model-title">
                            <?php if ($safeImage && file_exists($thumbnailPath)): ?>
                                <img src="<?= htmlspecialchars($thumbnailPath, ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="<?= htmlspecialchars($modelName, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                                <img src="images/default-thumbnail.jpg" 
                                     alt="<?= htmlspecialchars($modelName, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                            <div><?= htmlspecialchars($modelName, ENT_QUOTES, 'UTF-8'); ?></div>
                        </a>
                        <div class="model-count">
                            <?= intval($imageCount); ?> image(s)
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Aucun modèle disponible.</p>
            <?php endif; ?>
        </div>

        <div style="text-align: center;">
            <a href="index.php" class="back-link">Back to Home</a>
        </div>
    </div>
</body>
</html>
