<?php
session_start();

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../db_config.php';
require 'header.php';

define('WP_USE_THEMES', false);
require_once(__DIR__ . '/../../wp-load.php');

// Connexion WordPress
$wp_host   = 'localhost';
$wp_dbname = 'hansplaastai';
$wp_user   = 'hansplaastai';
$wp_pass   = 'azerty';

try {
    $wp_pdo = new PDO("mysql:host=$wp_host;dbname=$wp_dbname;charset=utf8", $wp_user, $wp_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Erreur WP: " . $e->getMessage());
}

// Connexion Gallery
$gallery_host   = 'localhost';
$gallery_dbname = 'gallery';
$gallery_user   = 'hansplaastai';
$gallery_pass   = 'azerty';

try {
    $pdo = new PDO("mysql:host=$gallery_host;dbname=$gallery_dbname;charset=utf8", $gallery_user, $gallery_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Erreur Gallery: " . $e->getMessage());
}

// Récupérer le classement des 15 images les plus likées
$topImagesStmt = $pdo->query("
    SELECT i.file_name, COUNT(il.id) AS like_count
    FROM image_likes il
    LEFT JOIN images i ON il.image_id = i.id
    GROUP BY il.image_id
    ORDER BY like_count DESC
    LIMIT 15
");
$topImages = $topImagesStmt->fetchAll(PDO::FETCH_ASSOC);

// Vérifier période de filtrage
$interval = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if (!in_array($interval, [7, 30, 90, 365, 0])) {
    $interval = 7;
}

// Récupérer la liste des utilisateurs WordPress avec leur nombre de likes
$totalLikesByUserStmt = $pdo->query("
    SELECT il.user_id, COUNT(il.id) AS total
    FROM image_likes il
    GROUP BY il.user_id
");
$totalLikesByUser = $totalLikesByUserStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$userListStmt = $wp_pdo->query("SELECT ID, user_login FROM wp_users ORDER BY user_login ASC");
$userList = $userListStmt->fetchAll(PDO::FETCH_ASSOC);

// Sélection utilisateur
$selectedUser = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// Récupérer les likes triés par date, selon l'intervalle
$sql = "
    SELECT il.user_id,
           i.file_name,
           c.name AS categorie,
           il.liked_at
    FROM image_likes il
    LEFT JOIN images i ON il.image_id = i.id
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE il.liked_at >= DATE_SUB(NOW(), INTERVAL $interval DAY)
";

if ($selectedUser > 0) {
    $sql .= " AND il.user_id = :selectedUser";
}

$sql .= " ORDER BY il.liked_at DESC";

$stmt = $pdo->prepare($sql);
if ($selectedUser > 0) {
    $stmt->bindParam(':selectedUser', $selectedUser, PDO::PARAM_INT);
}
$stmt->execute();
$likesByUser = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Associer les pseudos depuis WordPress et regrouper par utilisateur
$groupedLikes = [];
foreach ($likesByUser as $like) {
    $userId = $like['user_id'];

    // Si on n'a pas encore enregistré cet utilisateur dans $groupedLikes
    if (!isset($groupedLikes[$userId])) {
        // Récupérer le pseudo WP
        $stmtUser = $wp_pdo->prepare("SELECT user_login FROM wp_users WHERE ID = ?");
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        $username = $userRow ? $userRow['user_login'] : 'Utilisateur inconnu';

        // Initialiser la structure
        $groupedLikes[$userId] = [
            'username' => $username,
            'likes'    => []
        ];
    }

    // Ajouter ce like à la liste
    $groupedLikes[$userId]['likes'][] = $like;
}

// Total des likes globaux
$totalLikesStmt = $pdo->query("SELECT COUNT(*) AS total FROM image_likes");
$totalLikes = $totalLikesStmt->fetch(PDO::FETCH_ASSOC)['total'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Likes par Utilisateur</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0; 
            padding: 0;
            background-color: #f8f9fa;
            color: #333;
        }
        /* Container principal (layout) */
        .container {
            display: flex;
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            gap: 20px;
        }
        /* Sidebar (top 15 images) */
        .sidebar {
            width: 270px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .sidebar h3 {
            text-align: center;
            color: #007bff;
            margin-bottom: 15px;
        }
        .top-images {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .top-image {
            text-align: center;
        }
        .top-image img {
            width: 100px;
            border-radius: 5px;
            max-width: 100%;
            height: auto;
        }
        .top-image p {
            margin-top: 5px;
            font-size: 0.9rem;
            color: #555;
        }
        /* Section principale */
        .content {
            flex: 1;
            max-width: 1000px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .content h2 {
            margin-top: 0;
            color: #007bff;
            text-align: center;
            margin-bottom: 15px;
        }
        /* Formulaire de filtrage */
        .filter-section {
            text-align: center;
            margin-bottom: 20px;
        }
        label[for="user"] {
            font-weight: bold;
            margin-right: 8px;
        }
        select {
            padding: 5px 10px;
            font-size: 15px;
        }
        /* Liste des utilisateurs (groupedLikes) */
        .user-section {
            background: #fdfdfd;
            margin: 20px auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 8px rgba(0,0,0,0.05);
        }
        .user-section .user-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        /* Grille des images likées */
        .image-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }
        .image-card {
            width: 130px;
            text-align: center;
        }
        .image-card img {
            width: 100%;
            border-radius: 5px;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .image-card p {
            margin-top: 5px;
            font-size: 0.9rem;
            color: #555;
        }
        /* Footer dans la section */
        .total-likes {
            text-align: center;
            margin-top: 30px;
            font-size: 1rem;
        }
        .total-likes strong {
            color: #007bff;
        }
        @media (max-width: 900px) {
            .container {
                flex-direction: column;
                align-items: center;
            }
            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }
            .content {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Barre latérale (Top 15 Images) -->
    <div class="sidebar">
        <h3>Top 15 Images</h3>
        <div class="top-images">
            <?php foreach ($topImages as $image): ?>
                <div class="top-image">
                    <img src="/test/images/thumbnails/<?= htmlspecialchars($image['file_name']) ?>" alt="Preview">
                    <p>
                        <?= htmlspecialchars($image['file_name']) ?>
                        <br>
                        <strong>(<?= $image['like_count'] ?> likes)</strong>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Zone principale -->
    <div class="content">
        <h2>Likes par Utilisateur</h2>

        <!-- Filtre : Sélection d’utilisateur -->
        <div class="filter-section">
            <form method="GET">
                <label for="user">Utilisateur :</label>
                <select name="user" id="user" onchange="this.form.submit()">
                    <option value="0">Tous</option>
                    <?php foreach ($userList as $user): ?>
                        <?php
                            $uid = $user['ID'];
                            $selected = ($selectedUser == $uid) ? 'selected' : '';
                            $count = isset($totalLikesByUser[$uid]) ? $totalLikesByUser[$uid] : 0;
                        ?>
                        <option value="<?= $uid ?>" <?= $selected ?>>
                            <?= htmlspecialchars($user['user_login']) ?> (<?= $count ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Affichage des likes groupés par utilisateur -->
        <?php if (empty($groupedLikes)): ?>
            <p>Aucun like récent pour la période sélectionnée.</p>
        <?php else: ?>
            <?php foreach ($groupedLikes as $userGroup): ?>
                <div class="user-section">
                    <div class="user-title">
                        <?= htmlspecialchars($userGroup['username']) ?>
                    </div>
                    <div class="image-grid">
                        <?php foreach ($userGroup['likes'] as $like): ?>
                            <div class="image-card">
                                <img src="/test/images/thumbnails/<?= htmlspecialchars($like['file_name']) ?>" alt="Preview">
                                <p><?= htmlspecialchars($like['file_name']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Total global -->
        <div class="total-likes">
            Total des likes globaux : <strong><?= $totalLikes ?></strong>
        </div>
    </div>
</div>

</body>
</html>
