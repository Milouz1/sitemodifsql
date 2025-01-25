<?php
require 'db_config.php';

// Vérifiez si le nom de l'image est fourni
if (!isset($_POST['image'])) {
    echo json_encode(['status' => 'error', 'message' => 'Image non spécifiée.']);
    exit;
}

$image = $_POST['image'];

try {
    // Ajoute 1 au compteur de likes de l'image
    $stmt = $pdo->prepare("UPDATE images SET likes = likes + 1 WHERE file_name = :image");
    $stmt->execute(['image' => $image]);

    // Récupère le nouveau compteur de likes
    $stmt = $pdo->prepare("SELECT likes FROM images WHERE file_name = :image");
    $stmt->execute(['image' => $image]);
    $likes = $stmt->fetchColumn();

    echo json_encode(['status' => 'success', 'likes' => $likes]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
