<?php
// Check if the user is logged in
include('access-test.php');

// Include the header
include('header.php');

// Define video and thumbnail folders
$videoFolder = 'videos';
$videoThumbnailsFolder = 'videos/thumbnails';

// Load and decode the JSON file for videos
$dataJson = file_get_contents('datavideo.json');
if (!$dataJson) {
    die('Error loading datavideo.json');
}

$data = json_decode($dataJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON decoding error: ' . json_last_error_msg());
}

// Check if the 'video_categories' key exists in the JSON structure
if (!isset($data['video_categories'])) {
    die('The "video_categories" key does not exist in datavideo.json.');
}

// Organize videos by category
$groupedVideos = $data['video_categories'];
ksort($groupedVideos); // Sort categories alphabetically

// Create the video thumbnails folder if it does not exist
if (!is_dir($videoThumbnailsFolder)) {
    mkdir($videoThumbnailsFolder, 0755, true);
}

/**
 * Create a GIF thumbnail from a video using ffmpeg.
 *
 * @param string $originalVideoPath Path to the original video.
 * @param string $thumbnailPath Path where the GIF thumbnail will be saved.
 * @param int $duration Duration in seconds of the video segment to convert.
 * @param int $fps Frames per second for the GIF.
 * @param int $width Width of the resulting GIF.
 */
function createVideoThumbnail($originalVideoPath, $thumbnailPath, $duration = 5, $fps = 10, $width = 320) {
    if (!file_exists($originalVideoPath)) {
        echo "Video not found: $originalVideoPath<br>";
        return;
    }
    // Build the ffmpeg command to create a GIF
    $cmd = "ffmpeg -t $duration -i " . escapeshellarg($originalVideoPath) . " -vf \"fps=$fps,scale=$width:-1\" " . escapeshellarg($thumbnailPath);
    shell_exec($cmd);
}

// Create GIF thumbnails for each video if they don't exist
foreach ($groupedVideos as $categoryName => $categoryVideos) {
    foreach ($categoryVideos as $video) {
        $safeVideo = basename($video);
        $videoPath = "$videoFolder/$safeVideo";
        // Define the thumbnail path with a .gif extension
        $thumbnailPath = "$videoThumbnailsFolder/" . pathinfo($safeVideo, PATHINFO_FILENAME) . ".gif";
        if (!file_exists($thumbnailPath)) {
            createVideoThumbnail($videoPath, $thumbnailPath);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Videos - HansplaastAi</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .gallery {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    .gallery div {
        width: 320px;
        text-align: center;
    }
    .thumbnail {
        width: 100%;
        cursor: pointer;
    }
    /* Style de base pour la modale */
    .modal {
      display: none; /* Masquée par défaut */
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.8);
    }
    .modal-content {
      margin: 5% auto;
      padding: 20px;
      width: 80%;
      max-width: 800px;
      background: #fff;
      position: relative;
    }
    .close {
      position: absolute;
      top: 10px;
      right: 25px;
      color: #000;
      font-size: 35px;
      font-weight: bold;
    }
    /* Ajustement pour la vidéo dans la modale */
    #modalVideo {
      width: 100%;
      height: auto;
    }
  </style>
</head>
<body>
  <h1>Video Gallery - HansplaastAi</h1>
  <?php
  // Display videos grouped by category
  if (!empty($groupedVideos)) {
      foreach ($groupedVideos as $categoryName => $categoryVideos) {
          $videoCount = count($categoryVideos);
          echo "<h2>Category: " . htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') . " (Total videos: $videoCount)</h2>";
          echo "<div class='gallery'>";
          foreach ($categoryVideos as $index => $video) {
              $safeVideo = basename($video);
              $videoPath = "$videoFolder/$safeVideo";
              $thumbnailPath = "$videoThumbnailsFolder/" . pathinfo($safeVideo, PATHINFO_FILENAME) . ".gif";
              echo "<div>
                      <img src='" . htmlspecialchars($thumbnailPath, ENT_QUOTES, 'UTF-8') . "' 
                           alt='" . htmlspecialchars($safeVideo, ENT_QUOTES, 'UTF-8') . "'
                           class='thumbnail'
                           onclick='openVideoModal(\"" . htmlspecialchars($videoPath, ENT_QUOTES, 'UTF-8') . "\")'>
                    </div>";
          }
          echo "</div>";
      }
      echo "<a href='index.php' class='back-link'>Back to Home</a>";
  } else {
      echo "<p>No videos available.</p>";
  }
  ?>

  <!-- Fenêtre modale pour la vidéo -->
  <div id="videoModal" class="modal">
    <span class="close" onclick="closeModal()">&times;</span>
    <div class="modal-content">
      <video id="modalVideo" controls>
        Your browser does not support the video tag.
      </video>
    </div>
  </div>

  <script>
    /**
     * Ouvre la vidéo dans la fenêtre modale.
     * @param {string} videoSrc - L'URL de la vidéo à afficher.
     */
    function openVideoModal(videoSrc) {
        var modal = document.getElementById('videoModal');
        var modalVideo = document.getElementById('modalVideo');
        modalVideo.src = videoSrc;
        modal.style.display = 'block';
        modalVideo.play();
    }

    /**
     * Ferme la fenêtre modale et arrête la vidéo.
     */
    function closeModal() {
        var modal = document.getElementById('videoModal');
        var modalVideo = document.getElementById('modalVideo');
        modalVideo.pause();
        modalVideo.src = "";
        modal.style.display = 'none';
    }

    // Ferme la modale si l'utilisateur clique en dehors du contenu modal
    window.onclick = function(event) {
      var modal = document.getElementById('videoModal');
      if (event.target == modal) {
        closeModal();
      }
    }
  </script>
</body>
</html>
