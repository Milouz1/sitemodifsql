<?php
include('header.php');
require 'db_config.php';

$intervalDays = 15;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Recent Additions by Day</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<h1>Images added in the last <?= $intervalDays; ?> days, by day</h1>

<?php
try {
    $sql = "
        SELECT DATE(i.created_at) AS added_day,
               c.name AS catName,
               COUNT(i.id) AS countNew
        FROM images i
        JOIN categories c ON i.category_id = c.id
        WHERE i.created_at >= (NOW() - INTERVAL :days DAY)
        GROUP BY DATE(i.created_at), c.id
        ORDER BY added_day DESC, c.name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['days' => $intervalDays]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "<p>No new images in the last {$intervalDays} days.</p>";
    } else {
        // Regrouper dans un tableau associatif par date
        $grouped = [];
        foreach ($rows as $row) {
            $day = $row['added_day'];
            $cat = $row['catName'];
            $nb  = (int)$row['countNew'];

            if (!isset($grouped[$day])) {
                $grouped[$day] = [];
            }
            $grouped[$day][] = [
                'category' => $cat,
                'count'    => $nb,
            ];
        }

        // Afficher jour par jour
        foreach ($grouped as $day => $infos) {
            echo "<h2>Day: $day</h2>";
            echo "<ul>";
            foreach ($infos as $item) {
                echo "<li>Category <strong>" 
                     . htmlspecialchars($item['category']) 
                     . "</strong>: " 
                     . $item['count'] 
                     . " image(s)</li>";
            }
            echo "</ul>";
        }
    }
} catch (PDOException $e) {
    echo "<p>Erreur SQL: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<a href="index.php" class="back-link">Back to Home</a>
</body>
</html>
