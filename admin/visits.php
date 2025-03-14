<?php
session_start();

// (Optionnel) Activer l’affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1) Connexion à la base "gallery" (contient site_visits)
require '../db_config.php'; // ici, $pdo pointe vers la base "gallery"

// 2) Connexion à la base WordPress
$wp_host = 'localhost';
$wp_dbname = 'hansplaastai';  
$wp_user = 'hansplaastai';
$wp_pass = 'azerty';

try {
    $wp_pdo = new PDO(
        "mysql:host=$wp_host;dbname=$wp_dbname;charset=utf8",
        $wp_user,
        $wp_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Erreur WP: " . $e->getMessage());
}

// 3) (Optionnel) Inclusion du header
require 'header.php';

// 4) Nombre total de visites
$totalVisits = $pdo->query("SELECT COUNT(*) FROM site_visits")->fetchColumn();

// 5) Récupérer les 50 dernières visites
$stmt = $pdo->query("
    SELECT
        id,
        user_id,
        ip_address,
        user_agent,
        country,
        city,
        visited_at
    FROM site_visits
    ORDER BY visited_at DESC
    LIMIT 50
");
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6) Extraire tous les user_id distincts
$userIds = [];
foreach ($visits as $visit) {
    if (!empty($visit['user_id'])) {
        $userIds[$visit['user_id']] = true; 
    }
}
$userIds = array_keys($userIds);

// 7) Récupérer les pseudos WP correspondants
$userMap = []; // [ user_id => user_login ]
if (!empty($userIds)) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sqlWp = "SELECT ID, user_login FROM wp_users WHERE ID IN ($placeholders)";
    $stmtWp = $wp_pdo->prepare($sqlWp);
    $stmtWp->execute($userIds);
    foreach ($stmtWp->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $userMap[$row['ID']] = $row['user_login'];
    }
}

// 8) Fusion des données : ajouter user_login dans $visits
foreach ($visits as &$visit) {
    $uid = $visit['user_id'];
    $visit['user_login'] = isset($userMap[$uid]) ? $userMap[$uid] : null;
}
unset($visit); // On libère la référence

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statistiques des Visites</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f1f1;
            margin: 0; 
            padding: 0;
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0px 0px 8px rgba(0,0,0,0.1);
        }
        h2 {
            margin-top: 0;
            color: #333;
            text-align: center;
        }
        .total-visits {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 auto;
            background: #fff;
        }
        thead {
            background: #007bff;
            color: #fff;
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
        }
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .no-user {
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Statistiques des Visites</h2>
    <div class="total-visits">
        <strong>Total des visites :</strong> <?= htmlspecialchars($totalVisits) ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>IP</th>
                <th>User-Agent</th>
                <th>Pays</th>
                <th>Ville</th>
                <th>Date</th>
                <th>Pseudo WP</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($visits as $visit): ?>
            <tr>
                <td><?= htmlspecialchars($visit['ip_address']) ?></td>
                <td><?= htmlspecialchars($visit['user_agent']) ?></td>
                <td><?= htmlspecialchars($visit['country']) ?></td>
                <td><?= htmlspecialchars($visit['city']) ?></td>
                <td><?= htmlspecialchars($visit['visited_at']) ?></td>
                <td>
                    <?php if (!empty($visit['user_login'])): ?>
                        <?= htmlspecialchars($visit['user_login']) ?>
                    <?php else: ?>
                        <span class="no-user">Visiteur non connecté</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
