<?php
// modal.php
?>
<!-- HTML for the Modal -->
<div id="myModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="imageName" tabindex="-1">
    <button class="close" aria-label="Close Modal">&times;</button>
    <button class="nav-arrow nav-left" aria-label="Previous Image">&#10094;</button>
    <img class="modal-content" id="modalImage" alt="Enlarged view of the image">
    <button class="nav-arrow nav-right" aria-label="Next Image">&#10095;</button>
    <div class="image-name" id="imageName" aria-live="polite"></div>
    <a href="#" id="downloadBtn" class="download-btn" download aria-label="Download Image">⬇️</a>
</div>

<style>
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.85);
        justify-content: center;
        align-items: center;
        overflow: hidden;
        padding: 20px;
        transition: opacity 0.3s ease;
    }
    .modal[aria-hidden="false"] {
        display: flex;
    }
    .modal-content {
        max-width: 90%;
        max-height: 80%;
        border: 5px solid #fff;
        border-radius: 10px;
        object-fit: contain;
        cursor: grab;
        transition: transform 0.2s ease, box-shadow 0.3s ease;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }
    .modal-content:active {
        cursor: grabbing;
    }
    .close,
    .nav-arrow,
    .download-btn {
        position: absolute;
        background: rgba(0, 0, 0, 0.5);
        border: none;
        color: white;
        font-size: 30px;
        font-weight: bold;
        cursor: pointer;
        user-select: none;
        padding: 10px 15px;
        border-radius: 50%;
        transition: background 0.3s ease, transform 0.2s ease;
        z-index: 1000; /* Assure que les flèches sont au-dessus de l'image */
    }
    .close:hover,
    .nav-arrow:hover,
    .download-btn:hover {
        background: rgba(0, 0, 0, 0.8);
        transform: scale(1.1);
    }
    .close {
        top: 20px;
        right: 30px;
    }
    .nav-arrow {
        font-size: 40px;
    }
    .nav-left {
        left: 50px;
        top: 50%;
        transform: translateY(-50%);
    }
    .nav-right {
        right: 50px;
        top: 50%;
        transform: translateY(-50%);
    }
    .download-btn {
        bottom: 20px;
        right: 30px;
        font-size: 30px;
        text-decoration: none;
    }
    .image-name {
        color: white;
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 18px;
        text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.7);
        width: auto;
        text-align: center;
        pointer-events: none;
    }
    /* Accessibility Focus Styles */
    .close:focus,
    .nav-arrow:focus,
    .download-btn:focus {
        outline: 2px solid #fff;
        outline-offset: 4px;
    }
    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .nav-arrow {
            font-size: 30px;
        }
        /* Repositionner les flèches plus haut pour éviter qu'elles ne soient au centre de l'image */
        .nav-left {
            left: 20px;
            top: 10%;
            transform: none;
        }
        .nav-right {
            right: 20px;
            top: 10%;
            transform: none;
        }
        .download-btn {
            font-size: 24px;
            right: 20px;
        }
        .close {
            font-size: 24px;
            right: 20px;
            top: 15px;
        }
        .image-name {
            font-size: 16px;
            bottom: 15px;
        }
    }
    /* Styles supplémentaires pour faciliter l'interaction tactile */
    @media (max-width: 480px) {
        .nav-arrow, .close, .download-btn {
            padding: 15px;
            font-size: 32px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById("myModal");
    const closeBtn = modal.querySelector('.close');
    const prevBtn = modal.querySelector('.nav-left');
    const nextBtn = modal.querySelector('.nav-right');
    const downloadBtn = modal.querySelector('.download-btn');
    const modalImage = document.getElementById("modalImage");
    const imageName = document.getElementById("imageName");

    let currentIndex = -1;
    let modelImages = [];
    let zoomLevel = 1;
    let isDragging = false;
    let startX, startY, offsetX = 0, offsetY = 0;

    let initialDistance = null;
    let initialZoom = 1;
    let enableTouchDrag = false;  // Désactive le déplacement sur mobile

    window.openModal = function(imageElement) {
        const modelName = imageElement.getAttribute('data-model');
        const index = parseInt(imageElement.getAttribute('data-index'), 10);

        if (groupedImages.hasOwnProperty(modelName) && Array.isArray(groupedImages[modelName])) {
            modelImages = groupedImages[modelName];
            currentIndex = isNaN(index) ? 0 : index;

            updateModalImage();

            modal.style.display = "flex";
            modal.setAttribute('aria-hidden', 'false');
            modal.focus();
        } else {
            console.error('Model not found or invalid images for:', modelName);
        }
    }

    function closeModal(event) {
        if (event.target === modal || event.target.classList.contains('close')) {
            modal.style.display = "none";
            modal.setAttribute('aria-hidden', 'true');
            resetModal();
        }
    }

    function changeImage(direction) {
        if (modelImages.length === 0) return;
        currentIndex += direction;
        if (currentIndex < 0) {
            currentIndex = modelImages.length - 1;
        } else if (currentIndex >= modelImages.length) {
            currentIndex = 0;
        }
        updateModalImage();
    }

    function updateModalImage() {
        if (modelImages.length === 0 || currentIndex < 0 || currentIndex >= modelImages.length) return;
        const imageFolder = '<?php echo htmlspecialchars($imageFolder, ENT_QUOTES, 'UTF-8'); ?>/';
        const currentImage = modelImages[currentIndex];
        const fullImagePath = imageFolder + encodeURIComponent(currentImage);
        modalImage.src = fullImagePath;
        imageName.textContent = currentImage;
        downloadBtn.href = fullImagePath;
        zoomLevel = 1;
        offsetX = 0;
        offsetY = 0;
        modalImage.style.transform = 'scale(1) translate(0px, 0px)';
    }

    modalImage.addEventListener('wheel', (event) => {
        event.preventDefault();
        const delta = Math.sign(event.deltaY);
        if (delta < 0) {
            zoomLevel *= 1.1;
        } else {
            zoomLevel /= 1.1;
        }
        zoomLevel = Math.min(Math.max(zoomLevel, 1), 5);
        modalImage.style.transform = `scale(${zoomLevel}) translate(${offsetX}px, ${offsetY}px)`;
    });

    // Activation du glissement uniquement sur desktop
    if (!('ontouchstart' in window)) {
        modalImage.addEventListener('mousedown', (event) => {
            event.preventDefault();
            if (event.button !== 0) return;
            isDragging = true;
            startX = event.clientX - offsetX;
            startY = event.clientY - offsetY;
            modalImage.style.cursor = 'grabbing';
            document.addEventListener('mousemove', dragImage);
            document.addEventListener('mouseup', stopDrag);
        });
    }

    function dragImage(event) {
        if (!isDragging) return;
        offsetX = event.clientX - startX;
        offsetY = event.clientY - startY;
        modalImage.style.transform = `scale(${zoomLevel}) translate(${offsetX}px, ${offsetY}px)`;
    }

    function stopDrag() {
        isDragging = false;
        modalImage.style.cursor = 'grab';
        document.removeEventListener('mousemove', dragImage);
        document.removeEventListener('mouseup', stopDrag);
    }

    function resetModal() {
        zoomLevel = 1;
        offsetX = 0;
        offsetY = 0;
        modalImage.style.transform = 'scale(1) translate(0px, 0px)';
    }

    modalImage.addEventListener('touchstart', function(e) {
        if (e.touches.length === 1) {
            // Désactive le déplacement d'image pour un seul doigt sur mobile
            if(enableTouchDrag) {
                isDragging = true;
                startX = e.touches[0].clientX - offsetX;
                startY = e.touches[0].clientY - offsetY;
            }
        } else if (e.touches.length === 2) {
            initialDistance = getDistance(e.touches[0], e.touches[1]);
            initialZoom = zoomLevel;
        }
    }, {passive: false});

    modalImage.addEventListener('touchmove', function(e) {
        e.preventDefault();
        if (e.touches.length === 1 && isDragging) {
            offsetX = e.touches[0].clientX - startX;
            offsetY = e.touches[0].clientY - startY;
            modalImage.style.transform = `scale(${zoomLevel}) translate(${offsetX}px, ${offsetY}px)`;
        } else if (e.touches.length === 2) {
            let currentDistance = getDistance(e.touches[0], e.touches[1]);
            if (initialDistance) {
                let scaleChange = currentDistance / initialDistance;
                zoomLevel = initialZoom * scaleChange;
                zoomLevel = Math.min(Math.max(zoomLevel, 1), 5);
                modalImage.style.transform = `scale(${zoomLevel}) translate(${offsetX}px, ${offsetY}px)`;
            }
        }
    }, {passive: false});

    modalImage.addEventListener('touchend', function(e) {
        if (e.touches.length < 2) {
            initialDistance = null;
        }
        if (e.touches.length === 0) {
            isDragging = false;
        }
    });

    function getDistance(touch1, touch2) {
        let dx = touch2.clientX - touch1.clientX;
        let dy = touch2.clientY - touch1.clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    closeBtn.addEventListener('click', closeModal);
    prevBtn.addEventListener('click', () => changeImage(-1));
    nextBtn.addEventListener('click', () => changeImage(1));

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal(event);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (modal.style.display === "flex") {
            switch(event.key) {
                case 'ArrowRight':
                    changeImage(1);
                    break;
                case 'ArrowLeft':
                    changeImage(-1);
                    break;
                case 'Escape':
                    closeModal(event);
                    break;
            }
        }
    });
});
</script>
