<?php
// Dossier des images en attente
$waitingDir = __DIR__ . '/../images/waiting/';
$imageBaseURL = "http://176.123.6.127/test/images/waiting/"; // Utilisez l'URL correcte de votre serveur

// Charger les images en attente
$waitingImages = array_diff(scandir($waitingDir), array('..', '.'));

// Si le formulaire est soumis pour ajouter un watermark
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image']) && isset($_POST['watermark_text']) && isset($_POST['position'])) {
    $image = $_POST['image'];
    $watermarkText = $_POST['watermark_text'];
    $position = $_POST['position'];

    // Vérifier si l'image existe
    $imagePath = $waitingDir . $image;
    if (file_exists($imagePath)) {
        // Ajouter le watermark à l'image et afficher la prévisualisation
        addWatermark($imagePath, $watermarkText, $position);
    }
}

// Fonction pour ajouter le watermark avec une police personnalisée
function addWatermark($imagePath, $watermarkText, $position) {
    // Charger l'image
    list($imgWidth, $imgHeight, $imgType) = getimagesize($imagePath);
    switch ($imgType) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($imagePath);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($imagePath);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($imagePath);
            break;
        default:
            echo "Type d'image non supporté.";
            return;
    }

    // Définir la police et la taille
    $fontPath = __DIR__ . '/../fonts/Lexend-VariableFont_wght.ttf'; // Chemin vers la police TTF
    $fontSize = 20; // Taille du texte

    // Couleur du texte (blanc)
    $textColor = imagecolorallocate($image, 255, 255, 255);

    // Calculer les dimensions du texte pour centrer le watermark (pour être plus flexible)
    $bbox = imagettfbbox($fontSize, 0, $fontPath, $watermarkText);
    $textWidth = $bbox[2] - $bbox[0];
    $textHeight = $bbox[3] - $bbox[5];

    // Définir la position du watermark
    switch ($position) {
        case 'top-left':
            $x = 10;
            $y = 10 + $textHeight;
            break;
        case 'top-right':
            $x = $imgWidth - $textWidth - 10;
            $y = 10 + $textHeight;
            break;
        case 'bottom-left':
            $x = 10;
            $y = $imgHeight - $textHeight - 10;
            break;
        case 'bottom-right':
            $x = $imgWidth - $textWidth - 10;
            $y = $imgHeight - $textHeight - 10;
            break;
        default:
            $x = 10;
            $y = 10 + $textHeight;
    }

    // Ajouter le texte à l'image avec la police TTF
    imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $watermarkText);

    // Enregistrer l'image avec le watermark dans un fichier temporaire
    $watermarkedImagePath = __DIR__ . '/temp_watermarked_image.jpg';

    // Enregistrer l'image modifiée
    imagejpeg($image, $watermarkedImagePath);

    // Libérer la mémoire
    imagedestroy($image);

    // Afficher l'image directement dans le navigateur
    header('Content-Type: image/jpeg');
    imagejpeg(imagecreatefromjpeg($watermarkedImagePath));
    imagedestroy(imagecreatefromjpeg($watermarkedImagePath));
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Watermark</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .image-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .image-list div {
            margin: 10px;
            text-align: center;
            width: 200px;
        }
        .image-list img {
            width: 100%;
            height: auto;
            object-fit: contain;
            margin-bottom: 10px;
        }
        .category-selector {
            margin: 20px 0;
        }
        .preview {
            margin-top: 20px;
            text-align: center;
        }
        .preview img {
            max-width: 100%; /* Ajuste la taille de l'image */
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ajouter un Watermark</h1>
        
        <!-- Formulaire pour la sélection de l'image et du watermark -->
        <form method="POST">
            <div class="image-list">
                <?php 
                // Afficher toutes les images dans le dossier "waiting"
                foreach ($waitingImages as $image):
                    $imagePath = $imageBaseURL . htmlspecialchars($image);
                ?>
                    <div>
                        <img src="<?= $imagePath; ?>" alt="<?= htmlspecialchars($image); ?>" style="width: 100px; height: auto;">
                        <label>
                            <input type="radio" name="image" value="<?= htmlspecialchars($image); ?>" required> Sélectionner
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Champ pour le texte du watermark -->
            <label for="watermark_text">Texte du Watermark :</label><br>
            <input type="text" id="watermark_text" name="watermark_text" required><br><br>

            <!-- Choix de la position du watermark -->
            <label for="position">Position du watermark :</label><br>
            <select name="position" id="position" required>
                <option value="top-left">En haut à gauche</option>
                <option value="top-right">En haut à droite</option>
                <option value="bottom-left">En bas à gauche</option>
                <option value="bottom-right">En bas à droite</option>
            </select><br><br>

            <button type="submit">Prévisualiser</button>
        </form>

        <br><br>
        
        <!-- Lien pour revenir à la page d'index -->
        <a href="index.php">Retour à l'index</a>
    </div>
</body>
</html>
