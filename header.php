<?php
date_default_timezone_set('Europe/Paris'); // Remplacez par votre fuseau horaire
include('access-test.php');
include('db_config.php');

// Fonction pour récupérer la vraie IP du visiteur
function getUserIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) { // Cloudflare
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { // Proxy ou Load Balancer
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]); // Prend la première IP
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) { // Proxy transparent
        return $_SERVER['HTTP_CLIENT_IP'];
    } else { // IP normale
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Fonction pour récupérer la localisation via l'API ip-api.com
function getIPDetails($ip) {
    $url = "http://ip-api.com/json/$ip";
    $response = @file_get_contents($url);
    return $response ? json_decode($response, true) : null;
}

$ip = getUserIP();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// Récupérer l'ID de l'utilisateur WP connecté (0 s'il n'est pas connecté)
$current_user_id = get_current_user_id();

// Vérifier si l'IP a déjà été enregistrée dans les 5 dernières minutes
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM site_visits 
    WHERE ip_address = ? 
      AND visited_at >= NOW() - INTERVAL 5 MINUTE
");
$stmt->execute([$ip]);
$recentVisits = $stmt->fetchColumn();

if ($recentVisits == 0) {
    // Récupérer la localisation de l'IP
    $ipDetails = getIPDetails($ip);
    $country = $ipDetails['country'] ?? 'Unknown';
    $city    = $ipDetails['city']    ?? 'Unknown';

    // Enregistrer la visite en base (y compris l'ID WP)
    // Assure-toi d'avoir ajouté la colonne user_id dans site_visits 
    // et vérifie si visited_at se remplit automatiquement
    $stmt = $pdo->prepare("
        INSERT INTO site_visits (ip_address, user_agent, country, city, user_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$ip, $userAgent, $country, $city, $current_user_id]);
}

// ========================
// 1. Compter le nombre total d'images dans la base de données
// ========================
try {
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM images");
    $row = $stmt->fetch();
    $totalImages = $row['total'] ?? 0;
} catch (Exception $e) {
    error_log('Error counting images: ' . $e->getMessage());
    $totalImages = 0;
}

// ========================
// 2. Récupérer les catégories depuis la base de données
// ========================
$searchCategories = [];
try {
    $stmt = $pdo->query("SELECT name FROM categories ORDER BY name ASC");
    while ($row = $stmt->fetch()) {
        $searchCategories[] = $row['name'];
    }
} catch (Exception $e) {
    error_log('Error fetching categories: ' . $e->getMessage());
    $searchCategories = [];
}

// ========================
// 3. Calculer "Last update" basé sur la base de données
// ========================
$lastUpdateCategory = '';
$lastUpdateText     = '';

try {
    // Récupérer la dernière image mise à jour
    $stmt = $pdo->query("
        SELECT images.file_name, categories.name AS category, images.last_modified
        FROM images
        INNER JOIN categories ON images.category_id = categories.id
        ORDER BY images.last_modified DESC, images.id DESC
        LIMIT 1
    ");
    $lastImage = $stmt->fetch();

    if ($lastImage) {
        $lastUpdateCategory = $lastImage['category'];
        $lastModifiedTime   = new DateTime($lastImage['last_modified']);
        $now               = new DateTime('now');

        // Calculer l'intervalle entre maintenant et la dernière modification
        $interval = $now->diff($lastModifiedTime);

        if ($lastModifiedTime > $now) {
            // Si la dernière modification est dans le futur
            $lastUpdateText = 'just now';
        } else {
            if ($interval->y > 0) {
                $lastUpdateText = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
            } elseif ($interval->m > 0) {
                $lastUpdateText = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
            } elseif ($interval->d > 0) {
                $lastUpdateText = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
            } elseif ($interval->h > 0) {
                $lastUpdateText = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
            } elseif ($interval->i > 0) {
                $lastUpdateText = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
            } else {
                $lastUpdateText = 'just now';
            }
        }
    }
} catch (Exception $e) {
    error_log('Error fetching last update: ' . $e->getMessage());
    $lastUpdateCategory = '';
    $lastUpdateText     = '';
}

// ========================
// Vérifier si l'utilisateur est administrateur
// ========================
$isAdmin = is_user_logged_in() && current_user_can('administrator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Image Gallery'; ?></title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    /* RESET DE BASE */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    html, body {
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background-color: #121212;
      color: #ffffff;
      scroll-behavior: smooth;
    }

    /* HEADER GLOBAL */
    header {
      position: sticky;
      top: 0;
      z-index: 999;
      background: linear-gradient(45deg, #1e1e1e, #3a3a3a);
      box-shadow: 0 2px 5px rgba(0,0,0,0.4);
      width: 100%;
      padding: 10px 0;
    }
    .header-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: nowrap;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
      width: 100%;
    }
    .header-left,
    .header-middle,
    .header-right {
      display: flex;
      align-items: center;
      flex: 0 0 auto;
    }
    .header-middle {
      flex-grow: 1;
      justify-content: center;
      gap: 15px;
    }
    .menu {
      display: flex;
      gap: 15px;
      list-style: none;
    }
    .menu a {
      color: #ffffff;
      text-decoration: none;
      padding: 8px 16px;
      border-radius: 4px;
      border: 1px solid transparent;
      transition: all 0.3s ease;
      font-size: 0.95rem;
      font-weight: 500;
    }
    .menu a:hover {
      background-color: #ffffff;
      color: #333;
      border-color: #ffffff;
    }

    /* DISCORD LOGO */
    .discord-logo img {
      width: 32px;
      height: auto;
      vertical-align: middle;
      margin-right: 10px;
    }

    /* SEARCH BAR */
    .search-bar {
      position: relative;
      max-width: 300px;
    }
    #search-input {
      padding: 8px 12px;
      font-size: 0.95rem;
      border: 1px solid #555;
      border-radius: 4px;
      outline: none;
      background-color: #1f1f1f;
      color: #fff;
      transition: border-color 0.3s ease;
      width: 100%;
    }
    #search-input:focus {
      border-color: #999;
    }
    #suggestions {
      position: absolute;
      top: 38px;
      left: 0;
      width: 100%;
      background-color: #2a2a2a;
      border: 1px solid #444;
      border-radius: 4px;
      z-index: 1000;
      display: none;
      max-height: 200px;
      overflow-y: auto;
    }
    .suggestion-item {
      padding: 8px 12px;
      cursor: pointer;
      font-size: 0.95rem;
      color: #fff;
      border-bottom: 1px solid #444;
    }
    .suggestion-item:hover {
      background-color: #3f3f3f;
    }
    .user-info {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .user-info span {
      font-size: 0.95rem;
      color: #f0f0f0;
    }
    .back-to-main-site {
      color: #ffffff;
      text-decoration: none;
      padding: 8px 16px;
      background-color: transparent;
      border-radius: 4px;
      border: 1px solid #ffffff;
      transition: all 0.3s ease;
      font-size: 0.85rem;
    }
    .back-to-main-site:hover {
      background-color: #ffffff;
      color: #333;
    }
    .total-images {
      color: #ccc;
      font-size: 0.85rem;
      margin-left: 1.2rem;
    }
    .last-update {
      color: #a0a0a0;
      font-size: 0.8rem;
      font-style: italic;
      margin-left: 1rem;
    }
    @media (max-width: 900px) {
      .header-container {
        flex-wrap: wrap;
      }
      .header-middle {
        flex-wrap: wrap;
        justify-content: center;
      }
    }
    @media (max-width: 600px) {
      .header-container {
        flex-direction: column;
        align-items: stretch;
      }
      .header-left,
      .header-middle,
      .header-right {
        width: 100%;
        justify-content: center;
        margin-bottom: 10px;
      }
      .menu {
        flex-wrap: wrap;
        justify-content: center;
      }
      .user-info {
        justify-content: center;
      }
    }
  </style>
</head>
<body>

<header>
  <div class="header-container">
    <div class="header-left">
      <!-- Lien vers Discord avec logo et texte -->
      <a href="https://discord.gg/vZuNaJSAkq" target="_blank" class="discord-logo">
        <img src="discord-logo.png" alt="Discord" />
        <span>Discord server</span>
      </a>

      <!-- Barre de recherche -->
      <div class="search-bar">
        <form action="model-detail.php" method="GET" onsubmit="return redirectToModel();">
          <input type="text" id="search-input" placeholder="Search model..." autocomplete="off" />
          <div id="suggestions"></div>
        </form>
      </div>
    </div>

    <div class="header-middle">
      <ul class="menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="model.php">Models</a></li>
        <li><a href="video.php">Video</a></li>
        <li><a href="new.php">New Content</a></li>
        <?php if ($isAdmin): ?>
          <li><a href="/test/admin/index.php">Admin Panel</a></li>
        <?php endif; ?>
      </ul>

      <?php if (is_user_logged_in()): ?>
        <div class="user-info">
          <span>Hello, <?php echo htmlspecialchars(wp_get_current_user()->display_name); ?>!</span>
          <a href="../index.php" class="back-to-main-site">Back to main site</a>
        </div>
      <?php endif; ?>

      <div class="total-images">
        Total: <?php echo htmlspecialchars($totalImages); ?> images
      </div>
    </div>

    <div class="header-right">
      <?php if (!empty($lastUpdateCategory) && !empty($lastUpdateText)): ?>
        <div class="last-update">
          Last update: <?php echo htmlspecialchars($lastUpdateCategory . ' ' . $lastUpdateText); ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</header>

<script>
  // Récupère les catégories depuis PHP
  const searchCategories = <?php echo json_encode($searchCategories); ?> || [];
  const searchInput = document.getElementById('search-input');
  const suggestionsBox = document.getElementById('suggestions');

  // Affichage des suggestions lors de la saisie
  searchInput.addEventListener('input', function () {
    const query = this.value.toLowerCase().trim();
    suggestionsBox.innerHTML = '';
    suggestionsBox.style.display = 'none';

    if (query.length > 0) {
      const filteredCategories = searchCategories.filter(cat =>
        cat.toLowerCase().includes(query)
      );

      if (filteredCategories.length > 0) {
        filteredCategories.forEach(category => {
          const suggestionItem = document.createElement('div');
          suggestionItem.className = 'suggestion-item';
          suggestionItem.textContent = category;

          suggestionItem.addEventListener('click', () => {
            window.location.href = 'model-detail.php?model=' + encodeURIComponent(category);
          });

          suggestionsBox.appendChild(suggestionItem);
        });
        suggestionsBox.style.display = 'block';
      }
    }
  });

  // Cacher les suggestions en cliquant hors de la zone
  document.addEventListener('click', function (event) {
    if (!searchInput.contains(event.target) && !suggestionsBox.contains(event.target)) {
      suggestionsBox.style.display = 'none';
    }
  });

  // Redirection quand on soumet le formulaire
  function redirectToModel() {
    const query = searchInput.value.toLowerCase().trim();
    const match = searchCategories.find(cat => cat.toLowerCase() === query);

    if (match) {
      window.location.href = 'model-detail.php?model=' + encodeURIComponent(match);
    } else {
      alert('No matching model found.');
    }
    return false; // Empêche la soumission standard du formulaire
  }
</script>

</body>
</html>
