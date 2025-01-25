<?php
// [1] Inclure votre script de vérification
require_once __DIR__ . '/check-wp-admin.php';

/*************************************************************
 *          ADMIN DE GESTION DE GALERIE
 * 
 *  DÉTAILS :
 *   - Gère les catégories via data.json (ajout, suppression, renommage)
 *   - Liste les images d’une catégorie
 *   - Sélection multiple pour supprimer de la catégorie ou déplacer vers waiting
 *   - Affiche le nom du fichier sous chaque image
 * 
 *  IMPORTANT :
 *   - Ne supprime pas physiquement les fichiers (sauf si vous ajoutez unlink())
 *   - Déplace physiquement les fichiers vers waiting/ si demandé
 *   - Protégez l'accès à /admin/ !
 *
 *************************************************************/

// ------------------------------------------------------------------
// 1) Configuration des chemins / Répertoires
// ------------------------------------------------------------------
$dataFile   = __DIR__ . '/../data.json';        // Fichier JSON (un niveau au-dessus du /admin/)
$waitingDir = __DIR__ . '/../images/waiting/';  // Dossier "waiting"
$targetDir  = __DIR__ . '/../images/';          // Dossier principal des images

// ------------------------------------------------------------------
// 2) Chargement / sauvegarde des données JSON
// ------------------------------------------------------------------
$data = loadData($dataFile);

function loadData($dataFile) {
    if (file_exists($dataFile)) {
        // json_decode renvoie un tableau associatif
        return json_decode(file_get_contents($dataFile), true) ?: ['categories' => []];
    }
    // Si le fichier n'existe pas, on renvoie une structure de base
    return ['categories' => []];
}

function saveData($dataFile, $data) {
    return file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}

// ------------------------------------------------------------------
// 3) Déplacer physiquement un fichier vers waiting/
// ------------------------------------------------------------------
function moveImageToWaiting($image) {
    global $waitingDir, $targetDir;

    $sourcePath      = $targetDir . $image;      
    $destinationPath = $waitingDir . $image;     

    if (!file_exists($sourcePath)) {
        return "Erreur : L'image '$image' n'existe pas dans le dossier de la catégorie.";
    }

    if (rename($sourcePath, $destinationPath)) {
        return true;
    } else {
        return "Erreur : Impossible de déplacer l'image '$image' vers 'waiting'.";
    }
}

// ------------------------------------------------------------------
// 4) Actions : Supprimer / Renommer / Ajouter une catégorie
// ------------------------------------------------------------------

// Supprimer une catégorie : déplace toutes ses images vers waiting/ avant de la retirer
if (isset($_GET['delete_category'])) {
    $catToDelete = $_GET['delete_category'];

    if (isset($data['categories'][$catToDelete])) {
        $errorMessages = [];
        $moveFailed    = false;

        // Déplacer toutes les images de cette catégorie dans "waiting"
        foreach ($data['categories'][$catToDelete] as $image) {
            $result = moveImageToWaiting($image);
            if ($result !== true) {
                $moveFailed      = true;
                $errorMessages[] = $result;
            }
        }

        // Si tout le déplacement a fonctionné, on supprime la catégorie
        if (!$moveFailed) {
            unset($data['categories'][$catToDelete]);
            if (saveData($dataFile, $data)) {
                $message = "Catégorie '$catToDelete' supprimée. Les images sont déplacées dans 'waiting'.";
            } else {
                $message = "Erreur : Impossible de sauvegarder après la suppression de la catégorie.";
            }
        } else {
            $message = implode("<br>", $errorMessages);
        }
    } else {
        $message = "Erreur : Catégorie '$catToDelete' introuvable.";
    }

    header("Location: index.php?message=" . urlencode($message));
    exit;
}

// [MODIFICATION] Renommer une catégorie + renommer physiquement les fichiers
if (
    isset($_POST['modify_category']) && 
    isset($_POST['old_category']) &&
    isset($_POST['new_category_name'])
) {
    $oldCat = $_POST['old_category'];
    $newCat = trim($_POST['new_category_name']);

    if (isset($data['categories'][$oldCat]) && !empty($newCat)) {
        if (!isset($data['categories'][$newCat])) {
            
            // Récupérer la liste des anciens fichiers
            $oldFiles = $data['categories'][$oldCat];

            // On prépare un nouveau tableau (fichiers renommés)
            $renamedFiles = [];

            // Pour chaque fichier, on va remplacer $oldCat par $newCat dans le nom
            // (ou tout autre logique de renommage)
            foreach ($oldFiles as $oldFilename) {
                $oldPath = $targetDir . $oldFilename;
                if (!file_exists($oldPath)) {
                    // On peut ignorer ou signaler
                    continue;
                }

                // Extraire extension et baseName
                $extension = pathinfo($oldFilename, PATHINFO_EXTENSION);
                $base      = pathinfo($oldFilename, PATHINFO_FILENAME);

                // Remplacer $oldCat par $newCat dans le baseName,
                // s'il y a un schéma du genre oldCat(1).jpg, on obtient newCat(1).jpg
                // Sinon, vous pouvez implémenter une autre logique.
                $newBase = str_replace($oldCat, $newCat, $base);

                // Nouveau nom complet
                $candidate = $newBase . '.' . $extension;
                $newPath   = $targetDir . $candidate;

                // Vérifier si un fichier du même nom existe déjà
                $suffix = 1;
                while (file_exists($newPath)) {
                    // On ajoute un suffixe pour éviter l'écrasement
                    $candidate = $newBase . '_' . $suffix . '.' . $extension;
                    $newPath   = $targetDir . $candidate;
                    $suffix++;
                }

                // Renommer physiquement
                if (rename($oldPath, $newPath)) {
                    $renamedFiles[] = $candidate;
                } else {
                    // En cas d'échec, on peut ajouter un message d'erreur
                    // ou ignorer
                }
            }

            // Maintenant, on crée la nouvelle clé dans $data en utilisant les noms renommés
            $data['categories'][$newCat] = $renamedFiles;

            // On supprime l'ancienne clé
            unset($data['categories'][$oldCat]);

            // Sauvegarde
            if (saveData($dataFile, $data)) {
                $message = "Catégorie '$oldCat' renommée en '$newCat', et fichiers renommés.";
            } else {
                $message = "Erreur : Impossible de sauvegarder après le renommage.";
            }

        } else {
            $message = "Erreur : La catégorie '$newCat' existe déjà.";
        }
    } else {
        $message = "Erreur : Catégorie '$oldCat' introuvable ou nouveau nom vide.";
    }

    header("Location: index.php?message=" . urlencode($message));
    exit;
}

// Ajouter une nouvelle catégorie
if (isset($_POST['new_category'])) {
    $newCat = trim($_POST['new_category']);

    if (!empty($newCat) && !isset($data['categories'][$newCat])) {
        $data['categories'][$newCat] = [];
        if (saveData($dataFile, $data)) {
            $message = "Catégorie '$newCat' ajoutée avec succès.";
        } else {
            $message = "Erreur : Impossible de sauvegarder la nouvelle catégorie.";
        }
    } else {
        $message = "Erreur : La catégorie '$newCat' existe déjà ou le nom est invalide.";
    }

    header("Location: index.php?message=" . urlencode($message));
    exit;
}

// ------------------------------------------------------------------
// 5) Actions groupées sur les images (sélection multiple)
// ------------------------------------------------------------------
if (isset($_POST['group_action']) && isset($_POST['selected_images']) && is_array($_POST['selected_images'])) {
    $cat          = $_POST['category'] ?? null;
    $selectedImgs = $_POST['selected_images'];
    $action       = $_POST['group_action'];

    if (!isset($data['categories'][$cat])) {
        $message = "Erreur : La catégorie '$cat' n'existe pas.";
        header("Location: index.php?message=" . urlencode($message));
        exit;
    }

    if ($action === 'delete') {
        $errorMessages = [];
        foreach ($selectedImgs as $img) {
            $index = array_search($img, $data['categories'][$cat]);
            if ($index !== false) {
                unset($data['categories'][$cat][$index]);
            } else {
                $errorMessages[] = "L'image '$img' n'est pas dans la catégorie '$cat'.";
            }
        }
        // Réindexer
        $data['categories'][$cat] = array_values($data['categories'][$cat]);
        if (saveData($dataFile, $data)) {
            $message = empty($errorMessages)
                ? "Images supprimées de la catégorie '$cat'."
                : implode(" | ", $errorMessages);
        } else {
            $message = "Erreur : Impossible de sauvegarder après suppression.";
        }
    }
    elseif ($action === 'move_to_waiting') {
        $errorMessages = [];
        foreach ($selectedImgs as $img) {
            $index = array_search($img, $data['categories'][$cat]);
            if ($index !== false) {
                $moveResult = moveImageToWaiting($img);
                if ($moveResult === true) {
                    unset($data['categories'][$cat][$index]);
                } else {
                    $errorMessages[] = $moveResult;
                }
            } else {
                $errorMessages[] = "L'image '$img' n'est pas dans la catégorie '$cat'.";
            }
        }
        // Réindexer
        $data['categories'][$cat] = array_values($data['categories'][$cat]);
        if (saveData($dataFile, $data)) {
            $message = empty($errorMessages)
                ? "Images déplacées dans 'waiting'."
                : implode(" | ", $errorMessages);
        } else {
            $message = "Erreur : Impossible de sauvegarder après déplacement.";
        }
    }
    else {
        $message = "Action inconnue : $action.";
    }

    header("Location: index.php?category=" . urlencode($cat) . "&message=" . urlencode($message));
    exit;
}

// ------------------------------------------------------------------
// 5.1) Effacer tous les thumbnails du dossier
// ------------------------------------------------------------------
if (isset($_GET['delete_thumbnails'])) {
    $thumbnailDir = __DIR__ . '/../images/thumbnails/';
    deleteAllThumbnails($thumbnailDir);

    $message = "Tous les thumbnails ont été supprimés avec succès.";
    header("Location: index.php?message=" . urlencode($message));
    exit;
}

function deleteAllThumbnails($thumbDir) {
    $files = array_diff(scandir($thumbDir), ['.', '..']);
    foreach ($files as $file) {
        $filePath = $thumbDir . $file;
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
}

// ------------------------------------------------------------------
// 5.2) Forcer le renommage des fichiers existants dans une catégorie
// ------------------------------------------------------------------
if (isset($_GET['force_rename'])) {
    $catToRename = $_GET['force_rename'];
    $result      = renameCategoryImages($catToRename);

    header("Location: index.php?message=" . urlencode($result));
    exit;
}

function renameCategoryImages($cat) {
    global $data, $targetDir, $dataFile;

    if (!isset($data['categories'][$cat])) {
        return "Erreur : La catégorie '$cat' est introuvable.";
    }

    $images   = $data['categories'][$cat];
    $newNames = [];
    $i        = 1;

    foreach ($images as $oldFilename) {
        $extension   = pathinfo($oldFilename, PATHINFO_EXTENSION);
        $newFilename = $cat . '(' . $i . ').' . $extension;

        $oldPath = $targetDir . $oldFilename;
        $newPath = $targetDir . $newFilename;

        // Collision ?
        if (file_exists($newPath)) {
            $newFilename = $cat . '(' . $i . ')_' . time() . '.' . $extension;
            $newPath     = $targetDir . $newFilename;
        }

        if (!file_exists($oldPath)) {
            return "Erreur : Le fichier '$oldFilename' n'existe pas dans '$targetDir'.";
        }

        if (!rename($oldPath, $newPath)) {
            return "Erreur : Impossible de renommer '$oldFilename' en '$newFilename'.";
        }

        $newNames[] = $newFilename;
        $i++;
    }

    $data['categories'][$cat] = $newNames;
    if (!saveData($dataFile, $data)) {
        return "Erreur : Impossible de sauvegarder le fichier JSON après le renommage.";
    }

    return "Tous les fichiers de la catégorie '$cat' ont été renommés avec succès.";
}

// ------------------------------------------------------------------
// 6) Préparation des données pour l'affichage (HTML)
// ------------------------------------------------------------------
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : null;
$waitingImages = array_diff(scandir($waitingDir), ['.', '..']);
$waitingCount  = count($waitingImages);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin Galerie</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0; 
            padding: 0;
        }
        .container {
            width: 90%;
            max-width: 1100px;
            margin: 1rem auto;
            background-color: #fff;
            padding: 1rem 2rem;
            border-radius: 6px;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }
        h1, h2 {
            margin-top: 0;
        }
        .message {
            background-color: #e1f3e1;
            border: 1px solid #b6e6b6;
            padding: 0.6rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            color: #276727;
        }
        .category-list li {
            margin-bottom: 0.4rem;
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px,1fr));
            gap: 1rem;
        }
        .image-item {
            border: 1px solid #ddd;
            padding: 0.5rem;
            text-align: center;
            border-radius: 4px;
            background-color: #fafafa;
        }
        .image-item img {
            width: 100%;
            height: auto;
            border-radius: 4px;
        }
        .image-item p {
            margin: 0.5rem 0 0;
            font-size: 0.9rem;
        }
        input[type="checkbox"] {
            margin-bottom: 0.3rem;
        }
        button {
            margin: 0.2rem;
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        hr {
            margin: 1.5rem 0;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Administration de la Galerie</h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="message">
            <?= nl2br(htmlspecialchars($_GET['message'])); ?>
        </div>
    <?php endif; ?>

    <!-- Lien pour supprimer tous les thumbnails -->
    <p>
        <a href="index.php?delete_thumbnails=1" style="color:red; text-decoration:underline;">
            Supprimer tous les thumbnails
        </a>
    </p>

    <p>
        Il y a <strong><?= $waitingCount; ?></strong> image(s) en attente.
        <a href="waiting.php" style="text-decoration: underline;">Voir</a>
    </p>

    <!-- Formulaire d'ajout de catégorie -->
    <h2>Ajouter une catégorie</h2>
    <form method="POST" style="margin-bottom:1rem;">
        <input type="text" name="new_category" placeholder="Nom de la nouvelle catégorie" required />
        <button type="submit">Ajouter</button>
    </form>

    <!-- Liste des catégories -->
    <h2>Catégories existantes</h2>
    <ul class="category-list">
        <?php foreach ($data['categories'] as $cat => $imgArray): ?>
            <li>
                <a href="index.php?category=<?= urlencode($cat) ?>">
                    <?= htmlspecialchars($cat) ?>
                </a>
                <a href="index.php?delete_category=<?= urlencode($cat) ?>"
                   style="color:red; margin-left:1rem;">
                   [Supprimer]
                </a>
                <form method="POST" style="display:inline; margin-left:1rem;">
                    <input type="hidden" name="old_category" value="<?= htmlspecialchars($cat) ?>">
                    <input type="text" name="new_category_name" placeholder="Nouveau nom" required>
                    <button type="submit" name="modify_category">Renommer</button>
                </form>
                <a href="index.php?force_rename=<?= urlencode($cat) ?>"
                   style="color:orange; margin-left:1rem;">
                   [Forcer Rename]
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Affichage des images de la catégorie sélectionnée -->
    <?php if ($selectedCategory && isset($data['categories'][$selectedCategory])): 
        $imagesInCategory = $data['categories'][$selectedCategory];
    ?>
        <hr>
        <h2>Images de la catégorie : <?= htmlspecialchars($selectedCategory) ?></h2>

        <?php if (!empty($imagesInCategory)): ?>
            <form method="POST">
                <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">
                <div class="image-grid">
                    <?php foreach ($imagesInCategory as $image): ?>
                        <div class="image-item">
                            <input 
                                type="checkbox" 
                                name="selected_images[]" 
                                value="<?= htmlspecialchars($image) ?>" 
                                id="img_<?= htmlspecialchars($image) ?>"
                            >
                            <label for="img_<?= htmlspecialchars($image) ?>" style="cursor:pointer;">
                                <img src="../images/<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($image) ?>">
                            </label>
                            <p><?= htmlspecialchars($image) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:1rem;">
                    <button type="submit" name="group_action" value="delete">
                        Supprimer les images sélectionnées
                    </button>
                    <button type="submit" name="group_action" value="move_to_waiting">
                        Déplacer les images sélectionnées vers waiting
                    </button>
                </div>
            </form>
        <?php else: ?>
            <p>Aucune image dans cette catégorie.</p>
        <?php endif; ?>
    <?php endif; ?>

</div>
</body>
</html>
