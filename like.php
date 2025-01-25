<?php
require 'db_config.php';

// Vérifiez si l'image est spécifiée
if (!isset($_POST['image'])) {
    echo json_encode(['status' => 'error', 'message' => 'Aucune image spécifiée.']);
    exit;
}

$image = $_POST['image'];

try {
    // Mettre à jour les likes dans la base de données
    $stmt = $pdo->prepare("UPDATE images SET likes = likes + 1 WHERE file_name = :image");
    $stmt->execute(['image' => $image]);

    // Récupérer le nouveau nombre de likes
    $stmt = $pdo->prepare("SELECT likes FROM images WHERE file_name = :image");
    $stmt->execute(['image' => $image]);
    $likes = $stmt->fetchColumn();

    echo json_encode(['status' => 'success', 'likes' => $likes]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}