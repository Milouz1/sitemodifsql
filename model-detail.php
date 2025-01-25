<?php
// Inclure les fichiers nécessaires
include('access-test.php');
include('header.php');
require 'db_config.php';

// Dossiers des images et miniatures
$imageFolder = 'images';
$thumbnailsFolder = 'images/thumbnails';

// Vérifier si un modèle est spécifié
$modelSelected = isset($_GET['model']) ? trim($_GET['model']) : '';
if (empty($modelSelected)) {
    die('<p>Erreur : Aucun modèle spécifié.</p>');
}

try {
    // Récupérer les images pour le modèle sélectionné
    $stmt = $pdo->prepare("
        SELECT file_name 
        FROM images 
        INNER JOIN categories ON images.category_id = categories.id 
        WHERE categories.name = :model 
        ORDER BY images.id DESC
    ");
    $stmt->execute(['model' => $modelSelected]);
    $modelImages = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die('<p>Erreur de base de données : ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// Préparer les données pour JavaScript
$groupedImages = [$modelSelected => $modelImages];

$totalImages = count($modelImages);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modèle : <?= htmlspecialchars($modelSelected, ENT_QUOTES, 'UTF-8'); ?> (<?= $totalImages; ?>)</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Modèle : <?= htmlspecialchars($modelSelected, ENT_QUOTES, 'UTF-8'); ?> (<?= $totalImages; ?>)</h1>

    <?php if ($totalImages === 0): ?>
        <p>Aucune image disponible pour ce modèle.</p>
    <?php else: ?>
        <div class="gallery">
            <?php foreach ($modelImages as $index => $image): ?>
                <div>
                    <img 
                        src="<?= htmlspecialchars("$thumbnailsFolder/$image", ENT_QUOTES, 'UTF-8'); ?>" 
                        alt="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" 
                        class="thumbnail" 
                        data-model="<?= htmlspecialchars($modelSelected, ENT_QUOTES, 'UTF-8'); ?>" 
                        data-index="<?= $index; ?>" 
                        onclick="openModal(this)"
                        loading="lazy"
                    >
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Inclure le modal -->
    <?php include('modal.php'); ?>

    <!-- Initialisation JavaScript -->
    <script>
        const groupedImages = <?= json_encode($groupedImages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            console.log('Grouped Images:', groupedImages);
        });
    </script>
</body>
</html>
