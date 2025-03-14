<?php
// update.php

// 1) Check access
include('access-test.php');

// 2) Include the header
include('header.php');

// 3) Database connection
require 'db_config.php';

// 4) Retrieve images by date and category
try {
    $sql = "
        SELECT 
            DATE(i.created_at) AS upload_date,
            c.name AS category,
            COUNT(*) AS image_count
        FROM images i
        JOIN categories c ON i.category_id = c.id
        GROUP BY DATE(i.created_at), c.name
        ORDER BY DATE(i.created_at) DESC, c.name
    ";
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars($e->getMessage()));
}

// 5) Organize the results in an associative array:
//    $groupedData[date] = [ [ 'category' => ..., 'image_count' => ... ], ... ]
$groupedData = [];
foreach ($results as $row) {
    $date = $row['upload_date'];
    if (!isset($groupedData[$date])) {
        $groupedData[$date] = [];
    }
    $groupedData[$date][] = [
        'category'    => $row['category'],
        'image_count' => $row['image_count']
    ];
}
?>
<style>
.update-container {
    max-width: 800px;
    margin: 20px auto;
    color: #ccc;
    font-family: Arial, sans-serif;
}

.update-container h1 {
    text-align: center;
    color: #29e642;
    margin-bottom: 20px;
}

.date-block {
    margin-bottom: 30px;
    border: 1px solid #444;
    padding: 10px;
    background-color: #2a2a2a;
}

.date-block h2 {
    color: #fff;
    margin-top: 0;
}

.category-list {
    list-style-type: none;
    margin: 0;
    padding: 0;
}

.category-list li {
    margin: 5px 0;
}

.category-link {
    color: #00afff;
    text-decoration: none;
}
.category-link:hover {
    text-decoration: underline;
}

.count-badge {
    color: #bbb;
    margin-left: 5px;
    font-size: 0.9em;
}

.back-link {
    display: inline-block;
    margin: 15px;
    color: #29e642;
    text-decoration: none;
}
.back-link:hover {
    text-decoration: underline;
}
</style>

<div class="update-container">
    <h1>Images grouped by Date and Category</h1>

    <?php if (!empty($groupedData)): ?>
        <?php foreach ($groupedData as $date => $categories): ?>
            <div class="date-block">
                <h2><?= htmlspecialchars($date, ENT_QUOTES) ?></h2>
                <ul class="category-list">
                    <?php foreach ($categories as $catRow): ?>
                        <?php 
                            $catName  = $catRow['category'];
                            $catCount = $catRow['image_count'];
                            $catLink  = 'model-detail.php?model=' . urlencode($catName);
                        ?>
                        <li>
                            <a class="category-link" href="<?= $catLink ?>">
                                <?= htmlspecialchars($catName, ENT_QUOTES) ?>
                            </a>
                            <!-- Added "images" after the count -->
                            <span class="count-badge">(<?= $catCount ?> images)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No results found.</p>
    <?php endif; ?>

    <a href="index.php" class="back-link">Back to Home</a>
</div>

<?php include('footer.php'); ?>
