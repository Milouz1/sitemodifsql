<?php
// Inclure le fichier principal de WordPress
require_once('../../wp-load.php');

// Vérifier si l'utilisateur est connecté
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url()); // Rediriger vers la page de connexion de WordPress
    exit;
}

// Récupérer l'utilisateur connecté
$current_user = wp_get_current_user();

// Vérifier si l'utilisateur a le rôle "administrator"
if (!in_array('administrator', $current_user->roles)) {
    wp_die('Accès réservé aux administrateurs uniquement.', 'Accès refusé', ['response' => 403]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        .header {
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header .logo {
            font-size: 20px;
            font-weight: bold;
        }
        .header nav {
            display: flex;
            gap: 15px;
        }
        .header nav a {
            color: #fff;
            text-decoration: none;
            font-size: 16px;
            padding: 5px 10px;
            border-radius: 3px;
        }
        .header nav a:hover {
            background-color: #0056b3;
        }
        .user-info {
            margin-right: 15px;
            color: #fff;
            font-size: 16px;
        }
        .logout {
            background-color: #dc3545;
            color: #fff;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
        }
        .logout:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">Administration</div>
        <nav>
            <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="waiting.php"><i class="fas fa-clock"></i> Images en attente</a>
            <a href="users.php"><i class="fas fa-users"></i> Utilisateurs</a>
            <a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a>
        </nav>
        <div class="user-info">
            Connecté en tant que : <strong><?= esc_html($current_user->display_name); ?></strong>
        </div>
        <a href="<?= esc_url(wp_logout_url(home_url())); ?>" class="logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>
</body>
</html>
