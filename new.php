<?php
// Check if the user is logged in
include('access-test.php');

// Include the header
include('header.php');

// Define image and thumbnail folders
$imageFolder = 'images';
$thumbnailsFolder = 'images/thumbnails';

// Connect to the database
require 'db_config.php'; // Fichier pour la connexion à votre base de données

// Retrieve grouped images by category from the database
try {
    $stmt = $pdo->query("
        SELECT categories.name AS category, images.file_name, images.last_modified
        FROM categories
        JOIN images ON categories.id = images.category_id
        ORDER BY images.last_modified DESC
    ");

    $allImages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $allImages[] = [
            'category' => $row['category'],
            'image' => $row['file_name'],
            'mtime' => strtotime($row['last_modified'])
        ];
    }
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Select the 50 most recent images
$latestImages = array_slice($allImages, 0, 50);

// Thumbnail creation function identical to the one in index.php
function createThumbnail($originalImagePath, $thumbnailPath, $thumbWidth = 250) {
    if (!file_exists($originalImagePath)) {
        echo "Image not found: $originalImagePath<br>";
        return;
    }

    list($width, $height) = getimagesize($originalImagePath);
    $thumbHeight = floor($height * ($thumbWidth / $width));
    $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
    $imageExtension = strtolower(pathinfo($originalImagePath, PATHINFO_EXTENSION));

    if (in_array($imageExtension, ['png', 'gif'])) {
        imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }

    if ($imageExtension == 'jpg' || $imageExtension == 'jpeg') {
        $source = imagecreatefromjpeg($originalImagePath);
    } elseif ($imageExtension == 'png') {
        $source = imagecreatefrompng($originalImagePath);
    } elseif ($imageExtension == 'gif') {
        $source = imagecreatefromgif($originalImagePath);
    } else {
        echo "Unsupported file type: $imageExtension<br>";
        return;
    }

    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

    if ($imageExtension == 'jpg' || $imageExtension == 'jpeg') {
        imagejpeg($thumbnail, $thumbnailPath, 90);
    } elseif ($imageExtension == 'png') {
        imagepng($thumbnail, $thumbnailPath, 8);
    } elseif ($imageExtension == 'gif') {
        imagegif($thumbnail, $thumbnailPath);
    }

    imagedestroy($source);
    imagedestroy($thumbnail);
}

// Create the thumbnails folder if it doesn't exist
if (!is_dir($thumbnailsFolder)) {
    mkdir($thumbnailsFolder, 0755, true);
}

// Create thumbnails for the selected images if they don't exist
foreach ($latestImages as $img) {
    $safeImage = $img['image'];
    $imagePath = "$imageFolder/$safeImage";
    $thumbnailPath = "$thumbnailsFolder/$safeImage";
    if (!file_exists($thumbnailPath)) {
        createThumbnail($imagePath, $thumbnailPath);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Latest Images - HansplaastAi</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <h1>50 Most Recent Images Added</h1>
  <div class="gallery">
    <?php
    // Display the gallery of the 50 most recent images
    foreach ($latestImages as $img) {
        $safeImage = htmlspecialchars($img['image'], ENT_QUOTES, 'UTF-8');
        $categoryName = htmlspecialchars($img['category'], ENT_QUOTES, 'UTF-8');
        $thumbnailPath = "$thumbnailsFolder/$safeImage";

        echo "<div>
                <img src='" . htmlspecialchars($thumbnailPath, ENT_QUOTES, 'UTF-8') . "'
                     alt='$safeImage'
                     class='thumbnail'
                     data-model='$categoryName'
                     loading='lazy'>
              </div>";
    }
    ?>
  </div>
  <a href='index.php' class='back-link'>Back to Home</a>

  <?php include('modal.php'); ?>

  <script>
    // Make groupedImages accessible to the JavaScript for the modal
    const groupedImages = <?php echo json_encode($allImages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
</body>
</html>
