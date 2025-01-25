<?php
// Dossier contenant les images téléchargées
$imageFolder = 'images'; // Assurez-vous que ce chemin correspond à votre dossier d'images

// Fonction pour générer un nom de fichier unique en ajoutant un suffixe
function getUniqueFileName($filePath) {
    $pathInfo = pathinfo($filePath);
    $baseName = $pathInfo['filename'];  // Le nom du fichier sans extension
    $extension = $pathInfo['extension']; // L'extension du fichier

    $counter = 1;
    $newFileName = $filePath;

    // Tant qu'un fichier avec le même nom existe, ajouter un suffixe
    while (file_exists($newFileName)) {
        $newFileName = $pathInfo['dirname'] . '/' . $baseName . "($counter)." . $extension;
        $counter++;
    }

    return $newFileName;
}

// Ouvrir le dossier
$images = scandir($imageFolder); // Liste les fichiers dans le dossier images
foreach ($images as $image) {
    // Ignorer les éléments spéciaux "." et ".."
    if ($image === '.' || $image === '..') {
        continue;
    }

    // Créer le chemin complet de l'image
    $filePath = $imageFolder . '/' . $image;

    // Si le fichier existe déjà, essayer de générer un nouveau nom
    if (file_exists($filePath)) {
        // Vérifier s'il existe un autre fichier avec le même nom et le renommer si nécessaire
        $newFilePath = getUniqueFileName($filePath);

        // Si le nom a changé, renommer le fichier
        if ($filePath !== $newFilePath) {
            rename($filePath, $newFilePath);
            echo "Le fichier '$image' a été renommé en '" . basename($newFilePath) . "'<br>";
        }
    }
}
?>