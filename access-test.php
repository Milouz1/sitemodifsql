<?php
// Désactiver les thèmes pour un chargement plus rapide
define('WP_USE_THEMES', false);

// Inclure WordPress depuis la racine du site
require $_SERVER['DOCUMENT_ROOT'] . '/wp-blog-header.php';

// Vérifier si l'utilisateur est connecté
if (!is_user_logged_in()) {
    // Vérifier si l'utilisateur est déjà sur la page de connexion
    if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') === false) {
        // Rediriger vers la page de connexion
        wp_redirect(wp_login_url());
        exit;
    }
}

// Le reste de votre code protégé peut aller ici
?>
