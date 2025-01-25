<?php
/*******************************************************
 *   UPLOAD-WAITING.PHP
 *   Permet d'uploader une ou plusieurs images
 *   directement dans le dossier /images/waiting/
 *******************************************************/

// 1) Configuration
$waitingDir = __DIR__ . '/../images/waiting/'; // Dossier où on stocke les images
$maxFileSize = 50 * 1024 * 1024; // 50MB en octets

/**
 * Liste d'extensions autorisées 
 * (en minuscule, on les comparera à l'extension en minuscule)
 */
$allowedExtensions = ['jpg','jpeg','png','gif','webp'];

// 2) Traitement du formulaire si envoyé
$message = '';
if (isset($_POST['submit_upload'])) {

    // Vérifier si au moins un fichier a été sélectionné
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        $message = "Aucun fichier sélectionné.";
    } else {
        // Gestion de l'upload multiple
        $files = $_FILES['images'];
        
        // On boucle sur chaque fichier uploadé
        $uploadedCount = 0;
        $errorMessages = [];

        for ($i = 0; $i < count($files['name']); $i++) {
            $tmpName  = $files['tmp_name'][$i];
            $fileName = $files['name'][$i];
            $error    = $files['error'][$i];
            $size     = $files['size'][$i];

            // Vérifier qu'il n'y a pas d'erreur d'upload
            if ($error !== UPLOAD_ERR_OK) {
                $errorMessages[] = "Erreur lors de l'upload du fichier '$fileName' (code erreur: $error).";
                continue;
            }

            // Vérifier la taille
            if ($size > $maxFileSize) {
                $errorMessages[] = "Fichier '$fileName' trop volumineux (> 50 MB).";
                continue;
            }

            // Vérifier l'extension
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                $errorMessages[] = "Extension non autorisée pour le fichier '$fileName'.";
                continue;
            }

            // Pour éviter les collisions, on peut renommer 
            // si jamais le fichier existe déjà dans waiting/
            $finalName = findUniqueName($waitingDir, $fileName);

            // Déplacement du fichier temporaire vers le dossier waiting/
            if (!move_uploaded_file($tmpName, $waitingDir . $finalName)) {
                $errorMessages[] = "Impossible de déplacer le fichier '$fileName' vers waiting/.";
                continue;
            }

            // Si tout s'est bien passé
            $uploadedCount++;
        }

        // Bilan
        if ($uploadedCount > 0) {
            $message = "$uploadedCount fichier(s) uploadé(s) avec succès dans waiting.";
        }
        if (!empty($errorMessages)) {
            // Concatène les messages d'erreur avec ceux éventuels de succès
            $message .= "\n" . implode("\n", $errorMessages);
        }
    }
}

/**
 * findUniqueName
 * Vérifie si le fichier existe déjà dans waiting/ ; 
 * si oui, rajoute un suffixe unique (p. ex. _1, _2, etc.).
 *
 * @param string $dir       Chemin du dossier (../images/waiting/)
 * @param string $fileName  Nom de fichier tel que l’utilisateur l’a envoyé
 * @return string           Nom de fichier final unique
 */
function findUniqueName($dir, $fileName) {
    $baseName  = pathinfo($fileName, PATHINFO_FILENAME);
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);

    $candidate = $fileName;
    $suffix    = 1;

    // Tant qu'un fichier du même nom existe, on modifie $candidate
    while (file_exists($dir . $candidate)) {
        $candidate = $baseName . '_' . $suffix . '.' . $extension;
        $suffix++;
    }

    return $candidate;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Upload dans Waiting</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 90%;
            max-width: 600px;
            margin: 1rem auto;
            background-color: #fff;
            padding: 1rem 2rem;
            border-radius: 6px;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }
        h1 {
            margin-top: 0;
        }
        .message {
            background-color: #e1f3e1;
            border: 1px solid #b6e6b6;
            padding: 0.6rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            color: #276727;
            white-space: pre-wrap; /* pour afficher les \n si on en a plusieurs */
        }
        form {
            margin: 1rem 0;
        }
        input[type="file"] {
            margin: 0.5rem 0;
        }
        button {
            margin: 0.2rem 0;
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 0.6rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .back-link {
            margin-top: 1rem;
            display: inline-block;
        }
        .back-link a {
            color: #007bff;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Uploader des images vers Waiting</h1>

    <?php if (!empty($message)): ?>
        <div class="message"><?= nl2br(htmlspecialchars($message)) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label for="images">Choisissez une ou plusieurs images :</label><br>
        <input 
            type="file" 
            name="images[]" 
            id="images" 
            accept=".jpg,.jpeg,.png,.gif,.webp" 
            multiple 
            required
        ><br><br>

        <button type="submit" name="submit_upload">Uploader dans Waiting</button>
    </form>

    <p class="back-link">
        <a href="waiting.php">&larr; Retour vers waiting.php</a>
    </p>
</div>
</body>
</html>
