<?php
// Inclure le header et les configurations WordPress
include('header.php'); // Assure que le menu d'administration est affiché
require '../db_config.php'; // Connexion à la base de données

$waitingDir = __DIR__ . '/../images/waiting/';
$targetDir = __DIR__ . '/../images/';

// Récupération des catégories depuis la base de données
try {
    $categoriesQuery = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $categoriesQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}

// Actions groupées : Déplacer ou Supprimer
if (isset($_POST['group_action']) && isset($_POST['selected_images']) && is_array($_POST['selected_images'])) {
    $action = $_POST['group_action'];
    $selectedImages = $_POST['selected_images'];
    $errorMessages = [];

    if ($action === 'move_to_category') {
        $chosenCategoryId = $_POST['category_id'] ?? null;

        if (!$chosenCategoryId) {
            $message = "Erreur : Aucune catégorie sélectionnée.";
            header("Location: waiting.php?message=" . urlencode($message));
            exit;
        }

        foreach ($selectedImages as $image) {
            $sourcePath = $waitingDir . $image;
            $extension = pathinfo($image, PATHINFO_EXTENSION);

            try {
                // Générer un nouveau nom unique pour l'image
                $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = :id");
                $stmt->execute(['id' => $chosenCategoryId]);
                $categoryName = $stmt->fetchColumn();
                $newFilename = generateUniqueFilename($targetDir, $categoryName, $extension);

                $destinationPath = $targetDir . $newFilename;

                if (!rename($sourcePath, $destinationPath)) {
                    $errorMessages[] = "Impossible de déplacer l'image '$image'.";
                    continue;
                }

                // Ajouter l'image dans la base de données
                $stmt = $pdo->prepare("INSERT INTO images (category_id, file_name) VALUES (:category_id, :file_name)");
                $stmt->execute(['category_id' => $chosenCategoryId, 'file_name' => $newFilename]);
            } catch (PDOException $e) {
                $errorMessages[] = "Erreur SQL pour l'image '$image': " . $e->getMessage();
            }
        }

        $message = empty($errorMessages) ? "Les images sélectionnées ont été déplacées." : implode(" | ", $errorMessages);
    } elseif ($action === 'delete') {
        foreach ($selectedImages as $image) {
            $filePath = $waitingDir . $image;

            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    $errorMessages[] = "Impossible de supprimer l'image '$image'.";
                }
            } else {
                $errorMessages[] = "L'image '$image' n'existe pas.";
            }
        }

        $message = empty($errorMessages) ? "Les images sélectionnées ont été supprimées." : implode(" | ", $errorMessages);
    } else {
        $message = "Action inconnue.";
    }

    header("Location: waiting.php?message=" . urlencode($message));
    exit;
}

// Fonction pour générer un nom de fichier unique
function generateUniqueFilename($targetDir, $categoryName, $extension) {
    $i = 1;
    do {
        $candidate = $categoryName . "($i)." . $extension;
        $candidatePath = $targetDir . $candidate;
        $i++;
    } while (file_exists($candidatePath));

    return $candidate;
}

// Récupération des images en attente
$waitingImages = array_diff(scandir($waitingDir), ['.', '..']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Images en attente</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }
        h1 {
            color: #333;
        }
        .message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        .image-item {
            position: relative;
            text-align: center;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        .image-item img {
            max-width: 100%;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .image-item input[type="checkbox"] {
            display: none;
        }
        .image-item.selected {
            border: 2px solid #007bff;
            background-color: #eef7ff;
        }
        button, select {
            padding: 10px 15px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button {
            background-color: #007bff;
            color: #fff;
        }
        button:hover {
            background-color: #0056b3;
        }
        .delete-btn {
            background-color: #dc3545;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Images en attente</h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="message"><?= htmlspecialchars($_GET['message']); ?></div>
    <?php endif; ?>

    <?php if (empty($waitingImages)): ?>
        <p>Aucune image en attente.</p>
    <?php else: ?>
        <form method="POST" id="imageForm">
            <div class="image-grid">
                <?php foreach ($waitingImages as $image): ?>
                    <div class="image-item" onclick="toggleSelection(this, '<?= htmlspecialchars($image); ?>')">
                        <input type="checkbox" name="selected_images[]" value="<?= htmlspecialchars($image); ?>" id="<?= htmlspecialchars($image); ?>">
                        <img src="../images/waiting/<?= htmlspecialchars($image); ?>" alt="<?= htmlspecialchars($image); ?>">
                        <p><?= htmlspecialchars($image); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div>
                <select name="category_id" required>
                    <option value="">-- Sélectionner une catégorie --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id']; ?>"><?= htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="group_action" value="move_to_category">Déplacer</button>
                <button type="submit" name="group_action" value="delete" class="delete-btn">Supprimer</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<script>
    function toggleSelection(element, imageId) {
        const checkbox = document.getElementById(imageId);
        checkbox.checked = !checkbox.checked;
        element.classList.toggle('selected', checkbox.checked);
    }
</script>
</body>
</html>
