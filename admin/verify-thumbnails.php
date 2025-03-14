<?php
// verify-thumbnails.php - placé dans /admin/
include('header.php');
/**
 * 1) Inclusion de la configuration (un dossier au-dessus)
 */
require __DIR__ . '/../db_config.php';

/**
 * 2) Définition des chemins d'images
 */
$imageFolder      = __DIR__ . '/../images';
$thumbnailsFolder = __DIR__ . '/../images/thumbnails';

/**
 * 3) Fonction pour créer la vignette
 */
function createThumbnail($originalImagePath, $thumbnailPath, $thumbWidth = 300)
{
    if (!file_exists($originalImagePath)) {
        return false;
    }

    list($width, $height) = getimagesize($originalImagePath);
    if (!$width || !$height) {
        return false;
    }

    $thumbHeight = floor($height * ($thumbWidth / $width));
    $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);

    $ext = strtolower(pathinfo($originalImagePath, PATHINFO_EXTENSION));

    if (in_array($ext, ['png','gif'])) {
        imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }

    switch ($ext) {
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
            return false;
    }

    imagecopyresampled(
        $thumbnail, $source,
        0, 0, 0, 0,
        $thumbWidth, $thumbHeight,
        $width, $height
    );

    switch ($ext) {
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

    return true;
}

/**
 * 4) S'assurer que le dossier thumbnails existe
 */
if (!is_dir($thumbnailsFolder)) {
    mkdir($thumbnailsFolder, 0755, true);
}

/**
 * 5) Page HTML
 */
echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Vérification des Thumbnails</title>
</head>
<body>
<h1>Vérification/Création de Thumbnails</h1>
<p>Script de maintenance pour s’assurer que toutes les vignettes sont présentes.</p>
";

/**
 * 6) Récupérer la liste des images depuis la base
 */
try {
    $stmt = $pdo->query("SELECT file_name FROM images");
    $allImages = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("<p>Erreur SQL : " . htmlspecialchars($e->getMessage()) . "</p></body></html>");
}

if (!$allImages) {
    echo "<p>Aucune image dans la base.</p></body></html>";
    exit;
}

/**
 * 7) Vérifier/Créer les vignettes
 */
$createdCount = 0;
foreach ($allImages as $fileName) {
    $originalPath = $imageFolder . '/' . $fileName;
    $thumbPath    = $thumbnailsFolder . '/' . $fileName;

    if (!file_exists($thumbPath)) {
        $ok = createThumbnail($originalPath, $thumbPath, 300);
        if ($ok) {
            $createdCount++;
        }
    }
}

/**
 * 8) Afficher le résultat
 */
if ($createdCount === 0) {
    echo "<p>Toutes les thumbnails existent déjà. Aucune création nécessaire.</p>";
} else {
    echo "<p><strong>$createdCount</strong> vignette(s) créées avec succès.</p>";
}

echo "<p>Fin du script.</p>
</body>
</html>";