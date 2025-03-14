<?php
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

// (6) Fetch the images + likes for the given model
try {
    // Retrieve ID, file name, and total likes
    $sql = "
        SELECT 
            i.id AS image_id,
            i.file_name,
            (SELECT COUNT(*) FROM image_likes WHERE image_id = i.id) AS total_likes
        FROM images i
        INNER JOIN categories c ON i.category_id = c.id
        WHERE c.name = :modelName
        ORDER BY i.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['modelName' => $modelSelected]);
    $allImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('<p>DB Error: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// (7) Total number of images
$totalImages = count($allImages);

// (8) Build the $groupedImages array for the modal
//     Expected structure for modal.php: groupedImages[modelName] = [ list of file names... ]
//     Or, if your modal.php just expects an array of file_name, we’ll store it accordingly.
$groupedImages = [
    $modelSelected => []
];

// Loop to fill $groupedImages[$modelSelected]
foreach ($allImages as $img) {
    // We only store the file_name, because modal.php uses "modelImages[currentIndex]" = file name
    $groupedImages[$modelSelected][] = $img['file_name'];
}
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
    <!-- (9) Image gallery -->
    <div class="gallery">
    <?php 
    // Reuse $allImages for display
    foreach ($allImages as $index => $row) {
        $imageId   = (int)$row['image_id'];
        $fileName  = $row['file_name'];
        $likeCount = (int)$row['total_likes'];

        // Construct the thumbnail path
        $thumbPath = $thumbnailsFolder . '/' . $fileName;
        // We'll set "data-model" and "data-index" for the modal
        ?>
        <div class="image-item">
            <img 
                src="<?= htmlspecialchars($thumbPath) ?>" 
                alt="<?= htmlspecialchars($fileName) ?>"
                data-model="<?= htmlspecialchars($modelSelected) ?>"
                data-index="<?= $index ?>"
                onclick="openModal(this)"
                loading="lazy"
            >

            <!-- Like button + counter -->
            <div class="like-container">
                <button onclick="handleLike(<?= $imageId ?>)">Like</button>
                <span id="like-count-<?= $imageId ?>"><?= $likeCount ?></span> Likes
            </div>
        </div>
    <?php } ?>
    </div>
<?php endif; ?>

<a href="index.php" class="back-link">Back to Home</a>

<!-- (10) Include the modal WITH the full script -->
<?php include('modal.php'); ?>

<!-- (11) Define the JavaScript object groupedImages for the modal -->
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
            // Update the <span id="like-count-N">
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