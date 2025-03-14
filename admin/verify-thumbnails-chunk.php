<?php
// verify-thumbnails-chunk.php

/**
 * 0) NE PAS envoyer de HTML avant la logique de redirection,
 *    sinon on aura des "headers already sent".
 *    Optionnellement, on peut utiliser ob_start().
 */
ob_start();

/**
 * 1) Inclusion de la configuration & connexion PDO
 *    -> Adapte le chemin vers db_config.php selon ton arborescence
 */
require __DIR__ . '/../db_config.php';

/**
 * 2) Définition des chemins
 *    -> On suppose que le dossier images/ est au même niveau que /admin/
 *    -> Adapte si besoin
 */
$imageFolder      = __DIR__ . '/../images';
$thumbnailsFolder = __DIR__ . '/../images/thumbnails';

/**
 * 3) Fonction de création de la vignette (identique à ton script)
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
 * 4) Créer le dossier thumbnails si inexistant
 */
if (!is_dir($thumbnailsFolder)) {
    mkdir($thumbnailsFolder, 0755, true);
}

/**
 * 5) Paramètres du chunk (lot)
 */
$chunkSize = 50; // nombre d'images à traiter par exécution
// offset = à partir de quelle ligne on commence
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// on garde un compteur du total déjà créées
$createdSoFar = isset($_GET['created']) ? (int)$_GET['created'] : 0;

/**
 * 6) On va récupérer un lot de 50 en MySQL
 *    -> On évite les bindParam sur LIMIT, car MySQL peut poser souci
 *       sur certaines versions.
 */
$sql = "SELECT file_name FROM images ORDER BY id ASC LIMIT $offset, $chunkSize";
$stmt = $pdo->query($sql);
$images = $stmt->fetchAll(PDO::FETCH_COLUMN);

/**
 * 7) Si pas d'images dans ce lot, on a terminé !
 */
if (!$images) {
    // On affiche un message final (HTML)
    // Comme on a fait ob_start() avant, c’est OK d’envoyer du HTML
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <title>Thumbnails Terminés</title>
    </head>
    <body>
        <h1>Création des Thumbnails Terminée</h1>
        <p>Total de miniatures créées : <?= $createdSoFar ?></p>
        <p><a href="index.php">Retour</a></p>
    </body>
    </html>
    <?php

    // flush le buffer
    ob_end_flush();
    exit;
}

/**
 * 8) Générer les miniatures pour ce lot
 */
$createdCount = 0;
foreach ($images as $fileName) {
    $originalPath = $imageFolder . '/' . $fileName;
    $thumbPath    = $thumbnailsFolder . '/' . $fileName;

    // on évite de re-créer si le fichier existe déjà
    if (!file_exists($thumbPath)) {
        $ok = createThumbnail($originalPath, $thumbPath, 300);
        if ($ok) {
            $createdCount++;
        }
    }
}

// Met à jour le total
$createdSoFar += $createdCount;

/**
 * 9) Affichage rapide (on peut personnaliser le HTML)
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Création Thumbnails - Lot</title>
</head>
<body>
    <h1>Lot traité (offset = <?= $offset ?>)</h1>
    <p>Miniatures créées dans ce lot : <?= $createdCount ?></p>
    <p>Total miniatures créées jusque-là : <?= $createdSoFar ?></p>
    <p>Redirection automatique vers le lot suivant...</p>
</body>
</html>
<?php

// 10) Prochain offset
$nextOffset = $offset + $chunkSize;

/**
 * 11) Redirection automatique en rafraichissant la page
 *     On peut utiliser "Refresh" ou "Location" brute.
 */
header("Refresh: 2; url=verify-thumbnails-chunk.php?offset=$nextOffset&created=$createdSoFar");
// Si tu préfères rediriger direct sans délai :
// header("Location: verify-thumbnails-chunk.php?offset=$nextOffset&created=$createdSoFar");

ob_end_flush(); // on vide le buffer
