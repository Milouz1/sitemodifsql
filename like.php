<?php
session_start();
require 'db_config.php';
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/../wp-load.php');

if (!is_user_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

wp_set_current_user(get_current_user_id());
$current_user = wp_get_current_user();
$userId = $current_user->ID;
$userIp = $_SERVER['REMOTE_ADDR'] ?? '';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['image_id'])) {
    echo json_encode(['success' => false, 'message' => 'image_id missing']);
    exit;
}

$imageId = (int)$data['image_id'];

try {
    // Check if the user has already liked this image
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM image_likes WHERE image_id=? AND user_id=?");
    $stmt->execute([$imageId, $userId]);
    $alreadyLiked = $stmt->fetchColumn();

    if ($alreadyLiked) {
        echo json_encode(['success' => false, 'message' => 'You have already liked this image.']);
        exit;
    }

    // Insert the like
    $stmt = $pdo->prepare("INSERT INTO image_likes (image_id, user_id, user_ip, liked_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$imageId, $userId, $userIp]);

    // Update the likes count in `images` table
    $stmt = $pdo->prepare("UPDATE images SET likes = likes + 1 WHERE id=?");
    $stmt->execute([$imageId]);

    // Get the new like count
    $stmt = $pdo->prepare("SELECT likes FROM images WHERE id=?");
    $stmt->execute([$imageId]);
    $newLikes = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'likes'   => $newLikes
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
