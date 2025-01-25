<?php
include('access-test.php');

// ========================
// 1. Compter le nombre total d'images (dans /images)
// ========================
$imageFolder = 'images';
$totalImages = 0;

if (is_dir($imageFolder)) {
    $allFiles = array_diff(scandir($imageFolder), ['.', '..']);
    foreach ($allFiles as $file) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $totalImages++;
        }
    }
}

// ========================
// 2. Charger data.json + calcul "Last update" + sauvegarde conditionnelle
// ========================
$dataFile = 'data.json';
$backupFile = 'backup_data.json';
$searchCategories = [];

$lastUpdateCategory = '';
$lastUpdateText     = '';

if (file_exists($dataFile)) {
    if (!file_exists($backupFile) || filemtime($dataFile) > filemtime($backupFile)) {
        if (!copy($dataFile, $backupFile)) {
            error_log("Erreur : Impossible de sauvegarder '$dataFile' vers '$backupFile'.");
        }
    }

    $jsonContent = file_get_contents($dataFile);
    $decodedJson = json_decode($jsonContent, true);

    if (isset($decodedJson['categories']) && is_array($decodedJson['categories'])) {
        $searchCategories = array_keys($decodedJson['categories']);
    }

    if (isset($decodedJson['meta']['lastUpdatedCategory'])) {
        $lastUpdateCategory = $decodedJson['meta']['lastUpdatedCategory'];
    }

    $timestamp = 0;
    if (isset($decodedJson['meta']['lastUpdatedTime'])) {
        $timestamp = (int) $decodedJson['meta']['lastUpdatedTime'];
    }
    if ($timestamp === 0) {
        $timestamp = filemtime($dataFile);
    }

    $diffSeconds = time() - $timestamp;
    $diffHours   = floor($diffSeconds / 3600);
    $diffMinutes = floor(($diffSeconds % 3600) / 60);

    if ($diffHours > 0) {
        $lastUpdateText = $diffHours . ' hour' . ($diffHours > 1 ? 's' : '') . ' ago';
    } else {
        $lastUpdateText = $diffMinutes . ' minute' . ($diffMinutes > 1 ? 's' : '') . ' ago';
    }
}

// ========================
// Vérifier si l'utilisateur est administrateur
// ========================
$isAdmin = is_user_logged_in() && current_user_can('administrator');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Image Gallery'; ?></title>
  <link rel="stylesheet" href="styles.css" />
  <script>
    // Script pour gérer les likes
    function likeImage(imageName, index) {
        fetch('likes-handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                image: imageName
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const likeCount = document.getElementById(`like-count-${index}`);
                if (likeCount) {
                    likeCount.textContent = data.likes;
                }
            } else {
                console.error('Erreur : ', data.message);
            }
        })
        .catch(error => {
            console.error('Erreur réseau :', error);
        });
    }
  </script>
  <style>
    /* Ajout de styles inline pour correspondre à votre mise en page */
    header {
        background-color: #121212;
        padding: 10px;
    }
    .header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1200px;
        margin: auto;
    }
    .menu a {
        color: white;
        text-decoration: none;
        margin: 0 10px;
    }
    .menu a:hover {
        color: #1db954;
    }
    .search-bar input {
        width: 250px;
        padding: 5px;
    }
    .last-update {
        font-size: 0.8rem;
        color: #aaa;
    }
  </style>
</head>
<body>
<header>
  <div class="header-container">
    <!-- Recherche -->
    <div class="search-bar">
      <input type="text" id="search-input" placeholder="Search model...">
      <div id="suggestions" style="position: absolute; background: white; display: none;"></div>
    </div>

    <!-- Navigation -->
    <div class="menu">
      <a href="index.php">Home</a>
      <a href="model.php">Models</a>
      <a href="video.php">Video</a>
      <a href="new.php">New Content</a>
      <?php if ($isAdmin): ?>
        <a href="admin/index.php">Admin Panel</a>
      <?php endif; ?>
    </div>

    <!-- Infos utilisateur -->
    <div class="user-info">
      <?php if (is_user_logged_in()): ?>
        <span>Hello, <?php echo htmlspecialchars(wp_get_current_user()->display_name); ?>!</span>
        <a href="../index.php" style="color: #1db954;">Back to main site</a>
      <?php endif; ?>
      <div>Total images: <?php echo $totalImages; ?></div>
      <div class="last-update">Last updated: <?php echo $lastUpdateCategory . ' ' . $lastUpdateText; ?></div>
    </div>
  </div>
</header>
<script>
  // Fonctionnalité de recherche
  const searchInput = document.getElementById('search-input');
  const suggestionsBox = document.getElementById('suggestions');
  const searchCategories = <?php echo json_encode($searchCategories); ?>;

  searchInput.addEventListener('input', function() {
    const query = this.value.toLowerCase();
    suggestionsBox.innerHTML = '';
    suggestionsBox.style.display = 'none';

    if (query) {
      const matches = searchCategories.filter(cat => cat.toLowerCase().includes(query));
      matches.forEach(match => {
        const div = document.createElement('div');
        div.textContent = match;
        div.addEventListener('click', () => {
          window.location.href = `model-detail.php?model=${encodeURIComponent(match)}`;
        });
        suggestionsBox.appendChild(div);
      });
      suggestionsBox.style.display = 'block';
    }
  });
</script>
</body>
</html>
