<?php
// model_detail.php

// (1) Check access (if needed)
include('access-test.php');

// (2) Include a header (if you want a menu, etc.)
include('header.php');

// (3) Database connection
require 'db_config.php';

// (4) Paths for images
$imageFolder = 'images';
$thumbnailsFolder = 'images/thumbnails';

// (5) Check the ?model=... parameter
$modelSelected = isset($_GET['model']) ? trim($_GET['model']) : '';
if ($modelSelected === '') {
    die('<p>Error: No model specified.</p>');
}

// (6) Fetch the images + likes for the given model, ordered by date
try {
    $sql = "
        SELECT 
            DATE(i.created_at) AS upload_date,
            i.id AS image_id,
            i.file_name,
            (SELECT COUNT(*) FROM image_likes WHERE image_id = i.id) AS total_likes
        FROM images i
        INNER JOIN categories c ON i.category_id = c.id
        WHERE c.name = :modelName
        ORDER BY i.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['modelName' => $modelSelected]);
    $allImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('<p>DB Error: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// (7) Regrouper par date
$imagesByDate = [];
foreach ($allImages as $row) {
    $date = $row['upload_date'];
    if (!isset($imagesByDate[$date])) {
        $imagesByDate[$date] = [];
    }
    $imagesByDate[$date][] = $row;
}

// (8) Construire un tableau linéaire pour le modal
$groupedImages = [$modelSelected => []];
foreach ($allImages as $img) {
    $groupedImages[$modelSelected][] = $img['file_name'];
}

// (9) Calculer le total d’images
$totalImages = count($allImages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Model: <?= htmlspecialchars($modelSelected) ?> (<?= $totalImages ?>)</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<h1>Model: <?= htmlspecialchars($modelSelected) ?> (<?= $totalImages ?>)</h1>

<?php if ($totalImages === 0): ?>
    <p>No images available for this model.</p>
<?php else: ?>
    <?php
    // (A) On initialise un compteur global pour les indices
    $globalIndex = 0;
    ?>

    <?php foreach ($imagesByDate as $date => $rows): ?>
        <h2 class="date-title"><?= htmlspecialchars($date) ?></h2>
        <div class="gallery">
            <?php foreach ($rows as $row): 
                $imageId   = (int)$row['image_id'];
                $fileName  = $row['file_name'];
                $likeCount = (int)$row['total_likes'];

                // Chemin de la miniature
                $thumbPath = $thumbnailsFolder . '/' . $fileName;
            ?>
                <div class="image-item">
                    <img 
                        src="<?= htmlspecialchars($thumbPath) ?>" 
                        alt="<?= htmlspecialchars($fileName) ?>"
                        data-model="<?= htmlspecialchars($modelSelected) ?>"
                        data-index="<?= $globalIndex ?>"
                        onclick="openModal(this)"
                        loading="lazy"
                    >
                    <div class="like-container">
                        <button onclick="handleLike(<?= $imageId ?>)">Like</button>
                        <span id="like-count-<?= $imageId ?>"><?= $likeCount ?></span> Likes
                    </div>
                </div>

                <?php 
                // Incrémentation de l'index global après chaque image
                $globalIndex++;
                ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<a href="index.php" class="back-link">Back to Home</a>

<!-- (10) Include the modal -->
<?php include('modal.php'); ?>

<!-- (11) Define the JS object for the modal -->
<script>
const groupedImages = <?= json_encode($groupedImages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

/**
 * AJAX Like function
 */
function handleLike(imageId) {
    fetch('like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ image_id: imageId })
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            const span = document.getElementById('like-count-' + imageId);
            if (span) {
                span.innerText = data.likes;
            }
        } else {
            alert(data.message || "Error during like operation.");
        }
    })
    .catch(err => console.error("Fetch like error:", err));
}
</script>

</body>
</html>
