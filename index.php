<?php
// (1) Check user access (optional)
include('access-test.php');

// (2) Include header (optional)
include('header.php');

// (3) Database connection
require 'db_config.php';

// (4) Folders for images
$imageFolder = 'images';
$thumbnailsFolder = 'images/thumbnails';

// (5) Retrieve all images, categories, and like counts
try {
    $stmt = $pdo->query("
        SELECT
            i.id AS image_id,
            i.file_name,
            c.name AS category,
            (SELECT COUNT(*) FROM image_likes WHERE image_id = i.id) AS total_likes
        FROM images i
        JOIN categories c ON i.category_id = c.id
        ORDER BY c.name, i.id DESC
    ");
    $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database Error: ' . htmlspecialchars($e->getMessage()));
}

// (6) Group results by category for HTML display
$groupedData = [];
foreach ($allRows as $row) {
    $cat = $row['category'];
    if (!isset($groupedData[$cat])) {
        $groupedData[$cat] = [];
    }
    $groupedData[$cat][] = $row;
}

// (7) Build "groupedImages" array for the modal
//     In modal.php, openModal() expects a JS object: groupedImages["CategoryName"] = [ "file1.jpg", "file2.jpg", ... ]
$groupedImages = [];
foreach ($groupedData as $catName => $list) {
    $fileNames = [];
    foreach ($list as $item) {
        $fileNames[] = $item['file_name'];
    }
    $groupedImages[$catName] = $fileNames;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HansplaastAi (Index)</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<h1>HansplaastAi - Index</h1>

<?php
// (8) Display images grouped by category
if (empty($groupedData)) {
    echo "<p>No images found.</p>";
} else {
    foreach ($groupedData as $catName => $images) {
        $total = count($images);

        // Limit to 8 most recent images (already sorted DESC by query)
        // The query "ORDER BY c.name, i.id DESC" sorts by category, then i.id descending.
        // The first element in $images is the most recent (index 0).
        $limited = array_slice($images, 0, 8);

        // Make the category name clickable
        echo "<h2>
                <a href='model-detail.php?model=" . urlencode($catName) . "'>
                    Category: " . htmlspecialchars($catName) . " (Total: $total)
                </a>
              </h2>";

        echo "<div class='gallery'>";
        // Display only the "limited" portion
        foreach ($limited as $index => $imgRow) {
            $imageId   = (int)$imgRow['image_id'];
            $fileName  = $imgRow['file_name'];
            $likeCount = (int)$imgRow['total_likes'];

            // Thumbnail path
            $thumbPath = $thumbnailsFolder . '/' . $fileName;

            // The modal expects data-model and data-index
            echo "<div class='image-item'>
                    <img
                        src='" . htmlspecialchars($thumbPath, ENT_QUOTES) . "'
                        alt='" . htmlspecialchars($fileName, ENT_QUOTES) . "'
                        data-model='" . htmlspecialchars($catName, ENT_QUOTES) . "'
                        data-index='$index'
                        onclick='openModal(this)'
                        loading='lazy'
                    />

                    <div class='like-container'>
                        <button onclick='handleLike($imageId)'>Like</button>
                        <span id='like-count-$imageId'>$likeCount</span> Likes
                    </div>
                  </div>";
        }
        echo "</div>";
    }
}
?>

<!-- Navigation link or similar -->
<a href="index.php" class="back-link">Back to Home</a>

<!-- (9) Include the advanced modal (zoom, drag, pinch, download, navigation) -->
<?php include('modal.php'); ?>

<!-- (10) Declare the groupedImages JS variable -->
<script>
const groupedImages = <?= json_encode($groupedImages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

/**
 * AJAX Like Function
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
            alert(data.message || 'Error while liking.');
        }
    })
    .catch(err => console.error('AJAX Like Error:', err));
}
</script>

</body>
</html>
