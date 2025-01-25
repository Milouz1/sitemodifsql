<?php
// Chemin vers wp-load.php : adapte selon ton arborescence
require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';

// Si l’utilisateur n’est pas connecté : on redirige vers login WP
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( $_SERVER['REQUEST_URI'] ) );
    exit;
}

// Si l’utilisateur n’est pas admin : on bloque
if ( ! current_user_can( 'administrator' ) ) {
    die('Accès interdit : réservée aux administrateurs WordPress.');
}
