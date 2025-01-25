<?php
// Vérifie si l'utilisateur est administrateur via le fichier de vérification spécifique
include('check-wp-admin.php');

// Inclusion de l'en-tête depuis le dossier parent
include('../header.php');

$videoFolder = realpath(__DIR__ . '/../videos'); 
$datavideoFile = realpath(__DIR__ . '/../') . '/datavideo.json'; 
$errors = [];
$successMessage = "";

$data = ['video_categories' => []];
// Charger les données existantes depuis datavideo.json
if (file_exists($datavideoFile)) {
    $jsonData = file_get_contents($datavideoFile);
    $parsedData = json_decode($jsonData, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($parsedData['video_categories'])) {
        $data = $parsedData;
    }
}

// Traitement de la suppression de catégorie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category']) && isset($_POST['category'])) {
    $delCategory = $_POST['category'];

    if (isset($data['video_categories'][$delCategory])) {
        // Supprimer tous les fichiers vidéo de la catégorie
        foreach ($data['video_categories'][$delCategory] as $video) {
            $filePath = $videoFolder . DIRECTORY_SEPARATOR . basename($video);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        // Supprimer la catégorie du tableau
        unset($data['video_categories'][$delCategory]);
        
        // Enregistrer les modifications dans datavideo.json
        if (file_put_contents($datavideoFile, json_encode($data, JSON_PRETTY_PRINT)) === false) {
            $errors[] = "Failed to update JSON data after deleting category.";
        } else {
            $successMessage = "Category and its videos deleted successfully.";
        }
    } else {
        $errors[] = "Category not found.";
    }
}

// Traitement de la suppression de vidéo individuelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['category'], $_POST['video'])) {
    $delCategory = $_POST['category'];
    $delVideo = $_POST['video'];

    // Vérifier si la catégorie et la vidéo existent
    if (isset($data['video_categories'][$delCategory])) {
        $key = array_search($delVideo, $data['video_categories'][$delCategory]);
        if ($key !== false) {
            // Supprimer la vidéo du tableau
            unset($data['video_categories'][$delCategory][$key]);
            // Réindexer le tableau
            $data['video_categories'][$delCategory] = array_values($data['video_categories'][$delCategory]);
            
            // Si la catégorie est vide après suppression, on peut la supprimer
            if (empty($data['video_categories'][$delCategory])) {
                unset($data['video_categories'][$delCategory]);
            }

            // Supprimer le fichier vidéo s'il existe
            $filePath = $videoFolder . DIRECTORY_SEPARATOR . basename($delVideo);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Enregistrer les modifications dans datavideo.json
            file_put_contents($datavideoFile, json_encode($data, JSON_PRETTY_PRINT));
            $successMessage = "Video deleted successfully.";
        } else {
            $errors[] = "Video not found in the specified category.";
        }
    } else {
        $errors[] = "Category not found.";
    }
}

// Traitement de l'upload de vidéo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete']) && !isset($_POST['delete_category'])) {
    // Récupération et validation de la catégorie ou création d'une nouvelle
    $selectedCategory = isset($_POST['existing_category']) ? trim($_POST['existing_category']) : '';
    $newCategory = isset($_POST['new_category']) ? trim($_POST['new_category']) : '';
    $category = '';

    if ($selectedCategory === 'new') {
        if (empty($newCategory)) {
            $errors[] = "New category name is required.";
        } else {
            $category = $newCategory;
        }
    } else {
        if (empty($selectedCategory)) {
            $errors[] = "Please select or create a category.";
        } else {
            $category = $selectedCategory;
        }
    }

    // Vérification du fichier vidéo
    if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Video upload failed.";
    }

    $allowedExtensions = ['mp4', 'mov', 'avi', 'mkv'];
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $fileInfo = pathinfo($_FILES['video']['name']);
        $extension = strtolower($fileInfo['extension']);
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = "Unsupported video format. Allowed formats: " . implode(', ', $allowedExtensions);
        }
    }

    if (empty($errors)) {
        // Préparation du chemin cible
        $filename = basename($_FILES['video']['name']);
        $targetPath = $videoFolder . DIRECTORY_SEPARATOR . $filename;

        // Déplacement du fichier téléchargé vers le dossier des vidéos
        if (!move_uploaded_file($_FILES['video']['tmp_name'], $targetPath)) {
            $errors[] = "Failed to move uploaded file.";
        } else {
            // Ajout de la vidéo à la catégorie correspondante
            if (!isset($data['video_categories'][$category])) {
                $data['video_categories'][$category] = [];
            }
            $data['video_categories'][$category][] = $filename;

            // Enregistrement des modifications dans datavideo.json
            if (file_put_contents($datavideoFile, json_encode($data, JSON_PRETTY_PRINT)) === false) {
                $errors[] = "Failed to update JSON data.";
            } else {
                $successMessage = "Video uploaded successfully.";
            }
        }
    }
}

// Recharge des données après modifications possibles
if (file_exists($datavideoFile)) {
    $jsonData = file_get_contents($datavideoFile);
    $parsedData = json_decode($jsonData, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($parsedData['video_categories'])) {
        $data = $parsedData;
    }
}

// Préparation des catégories existantes pour le formulaire
$existingCategories = array_keys($data['video_categories']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Upload Video - Admin</title>
  <link rel="stylesheet" href="../styles.css"> <!-- Assurez-vous du bon chemin vers votre CSS -->
  <script>
    // Script pour afficher/cacher le champ pour une nouvelle catégorie
    function toggleNewCategory(selectElement) {
        var newCategoryField = document.getElementById('new_category_field');
        if (selectElement.value === 'new') {
            newCategoryField.style.display = 'block';
        } else {
            newCategoryField.style.display = 'none';
        }
    }
  </script>
</head>
<body>
  <h1>Upload Video</h1>
  <?php
  if (!empty($errors)) {
      echo '<ul class="errors">';
      foreach ($errors as $error) {
          echo "<li>" . htmlspecialchars($error) . "</li>";
      }
      echo '</ul>';
  }
  if (!empty($successMessage)) {
      echo "<p>" . htmlspecialchars($successMessage) . "</p>";
  }
  ?>
  <form action="" method="post" enctype="multipart/form-data">
      <label for="existing_category">Select Category:</label>
      <select name="existing_category" id="existing_category" onchange="toggleNewCategory(this)" required>
          <option value="">--Select a category--</option>
          <?php foreach ($existingCategories as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
              </option>
          <?php endforeach; ?>
          <option value="new">--Create New Category--</option>
      </select>
      <br><br>
      
      <div id="new_category_field" style="display:none;">
          <label for="new_category">New Category:</label>
          <input type="text" name="new_category" id="new_category">
          <br><br>
      </div>
      
      <label for="video">Select Video File:</label>
      <input type="file" name="video" id="video" accept="video/*" required><br><br>
      
      <input type="submit" value="Upload Video">
  </form>
  
  <h2>Existing Videos</h2>
  <?php if (!empty($data['video_categories'])): ?>
      <?php foreach ($data['video_categories'] as $category => $videos): ?>
          <h3>
            <?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>
            <!-- Formulaire pour supprimer la catégorie -->
            <form action="" method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this category and all its videos?');">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="delete_category" value="1">
                <input type="submit" value="Delete Category">
            </form>
          </h3>
          <ul>
              <?php foreach ($videos as $video): ?>
                  <li>
                      <?php echo htmlspecialchars($video, ENT_QUOTES, 'UTF-8'); ?>
                      <form action="" method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this video?');">
                          <input type="hidden" name="category" value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>">
                          <input type="hidden" name="video" value="<?php echo htmlspecialchars($video, ENT_QUOTES, 'UTF-8'); ?>">
                          <input type="hidden" name="delete" value="1">
                          <input type="submit" value="Delete">
                      </form>
                  </li>
              <?php endforeach; ?>
          </ul>
      <?php endforeach; ?>
  <?php else: ?>
      <p>No videos uploaded yet.</p>
  <?php endif; ?>

  <p><a href="../index.php">Back to Home</a></p>
</body>
</html>
