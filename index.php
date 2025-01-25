<?php
// Include the access-test.php file to check user login
include('access-test.php'); // Checks if the user is logged in

// Include the header.php
include('header.php');

// Define the image and thumbnail folders
$imageFolder = 'images'; 
$thumbnailsFolder = 'images/thumbnails'; // Thumbnail folder

// Connect to the database
require 'db_config.php'; // Ensure you have this file for the database connection

// Fetch data from the database (categories and images)
try {
    $stmt = $pdo->query("
        SELECT categories.name AS category, images.file_name
        FROM categories
        JOIN images ON categories.id = images.category_id
        ORDER BY categories.name
    ");

    $groupedImages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $groupedImages[$row['category']][] = $row['file_name'];
    }
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Check if there are any categories
if (empty($groupedImages)) {
    die('No categories found in the database.');
}

// Function to create a thumbnail
function createThumbnail($originalImagePath, $thumbnailPath, $thumbWidth = 250) {
    if (!file_exists($originalImagePath)) {
        echo "Image not found: $originalImagePath<br>";
        return;
    }

    list($width, $height) = getimagesize($originalImagePath);
    $thumbHeight = floor($height * ($thumbWidth / $width));
    $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
    $imageExtension = strtolower(pathinfo($originalImagePath, PATHINFO_EXTENSION));

    // Handle transparency for PNG and GIF
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

// Create the thumbnail folder if it does not exist
if (!is_dir($thumbnailsFolder)) {
    mkdir($thumbnailsFolder, 0755, true);
}

// Create thumbnails for each image
foreach ($groupedImages as $categoryName => $categoryImages) {
    foreach ($categoryImages as $image) {
        $safeImage = basename($image);
        $imagePath = "$imageFolder/" . $safeImage;
        $thumbnailPath = "$thumbnailsFolder/" . $safeImage;
        
        if (!file_exists($thumbnailPath)) {
            createThumbnail($imagePath, $thumbnailPath);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HansplaastAi</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>HansplaastAi</h1>

    <?php
    // Display images grouped by model
    if (!empty($groupedImages)) {
        foreach ($groupedImages as $categoryName => $categoryImages) {
            $imageCount = count($categoryImages);
            $limitedImages = $imageCount > 8 ? array_slice($categoryImages, -8) : $categoryImages;
            $limitedImages = array_reverse($limitedImages);

            echo "<h2><a href='model-detail.php?model=" . urlencode($categoryName) . "'>Model: " . htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') . " (Total images: " . $imageCount . ")</a></h2>";
            echo "<div class='gallery'>";
            foreach ($limitedImages as $index => $image) {
                $safeImage = basename($image);
                $imagePath = "$imageFolder/" . $safeImage;
                $thumbnailPath = "$thumbnailsFolder/" . $safeImage;
                $originalIndex = array_search($image, $categoryImages);
                $originalIndex = $originalIndex !== false ? (int)$originalIndex : 0;

                echo "<div>
                        <img src='" . htmlspecialchars($thumbnailPath, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($safeImage, ENT_QUOTES, 'UTF-8') . "' class='thumbnail' data-model='" . htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') . "' data-index='$originalIndex' onclick='openModal(this)' loading='lazy'>
                      </div>";
            }
            echo "</div>";
        }
        echo "<a href='index.php' class='back-link'>Back to Home</a>";
    } else {
        echo "<p>No models available in the database.</p>";
    }
    ?>

    <?php include('modal.php'); ?>

    <script>
        const groupedImages = <?php echo json_encode($groupedImages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
</body> 
</html>
