<?php
require_once __DIR__ . '/auth.php';
$id = isset($_GET['id']) ? $_GET['id'] : '0';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'visible';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Export Mosaic</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #000;
            color: #fff;
            font-family: Arial, Helvetica, sans-serif;
        }

        #status {
            padding: 12px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
        }

        #exportPreview {
            padding: 12px;
            text-align: center;
        }

        #exportPreview canvas {
            max-width: 100%;
            height: auto;
            border: 1px solid #333;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.4);
            background: #000;
        }
    </style>
</head>
<body>
    <div id="status">Preparing export…</div>
    <div id="exportPreview"></div>

    <script>
        const eventId = <?php echo json_encode($id); ?>;
        const mode = <?php echo json_encode($mode); ?>;
        const gap = 2;

        function setStatus(message) {
            document.getElementById('status').textContent = message;
        }

        function loadImage(src) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => resolve(img);
                img.onerror = () => reject(new Error('Failed to load image: ' + src));
                img.src = src;
            });
        }

        function drawImageCover(ctx, img, x, y, w, h, alpha) {
            const imgRatio = img.width / img.height;
            const boxRatio = w / h;

            let sx = 0;
            let sy = 0;
            let sWidth = img.width;
            let sHeight = img.height;

            if (imgRatio > boxRatio) {
                sWidth = img.height * boxRatio;
                sx = (img.width - sWidth) / 2;
            } else {
                sHeight = img.width / boxRatio;
                sy = (img.height - sHeight) / 2;
            }

            ctx.save();
            ctx.globalAlpha = alpha;
            ctx.drawImage(img, sx, sy, sWidth, sHeight, x, y, w, h);
            ctx.restore();
        }

        async function detectTileNativeSize(photoMap) {
            for (let i = 1; i <= Object.keys(photoMap).length; i++) {
                if (!photoMap[i]) continue;
                try {
                    const sample = await loadImage(photoMap[i]);
                    return { width: sample.width, height: sample.height };
                } catch (e) {}
            }
            return { width: 1200, height: 1800 };
        }

        async function buildAndDownloadMosaic() {
            try {
                setStatus('Loading event data…');

                const [eventData, settings, photos] = await Promise.all([
                    fetch(`/data/boothnext.php?eventId=${encodeURIComponent(eventId)}`).then(r => {
                        if (!r.ok) throw new Error('Failed to load boothnext.php');
                        return r.json();
                    }),
                    fetch(`data/collageSettings.php`).then(r => {
                        if (!r.ok) throw new Error('Failed to load collageSettings.php');
                        return r.json();
                    }),
                    fetch(`/data/collagePhotos.php?id=${encodeURIComponent(eventId)}`).then(r => {
                        if (!r.ok) throw new Error('Failed to load collagePhotos.php');
                        return r.json();
                    })
                ]);

                const opacity = typeof settings.opacity !== 'undefined'
                    ? parseFloat(settings.opacity)
                    : 0.40;

                const collageX = parseInt(eventData.collageX, 10);
                const collageY = parseInt(eventData.collageY, 10);
                const imgWidth = parseInt(eventData.imgWidth, 10);
                const imgHeight = parseInt(eventData.imgHeight, 10);
                const collageImg = '../photos/' + eventData.collageImg;

                if (!collageX || !collageY || !imgWidth || !imgHeight || !collageImg) {
                    throw new Error('Missing required collage metadata.');
                }

                const baseImage = await loadImage(collageImg);

                let tileWidth;
                let tileHeight;
                let exportWidth;
                let exportHeight;

                if (mode === 'tilefull') {
                    setStatus('Detecting original tile resolution…');
                    const nativeTile = await detectTileNativeSize(photos);
                    tileWidth = nativeTile.width;
                    tileHeight = nativeTile.height;
                    exportWidth = (collageX * tileWidth) + ((collageX - 1) * gap);
                    exportHeight = (collageY * tileHeight) + ((collageY - 1) * gap);
                } else if (mode === 'original') {
                    exportWidth = imgWidth;
                    exportHeight = imgHeight;
                    tileWidth = (exportWidth - ((collageX - 1) * gap)) / collageX;
                    tileHeight = (exportHeight - ((collageY - 1) * gap)) / collageY;
                } else {
                    const maxWidth = Math.max(800, window.innerWidth - 20);
                    const aspect = imgWidth / imgHeight;
                    exportWidth = Math.round(maxWidth);
                    exportHeight = Math.round(exportWidth / aspect);
                    tileWidth = (exportWidth - ((collageX - 1) * gap)) / collageX;
                    tileHeight = (exportHeight - ((collageY - 1) * gap)) / collageY;
                }

                const canvas = document.createElement('canvas');
                canvas.width = Math.round(exportWidth);
                canvas.height = Math.round(exportHeight);

                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    throw new Error('Unable to get 2D canvas context.');
                }

                const preview = document.getElementById('exportPreview');
                preview.innerHTML = '';
                preview.appendChild(canvas);

                // Black background first so seams stay black
                ctx.fillStyle = '#000';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                setStatus('Rendering mosaic…');

                const baseTileSrcW = baseImage.width / collageX;
                const baseTileSrcH = baseImage.height / collageY;

                for (let i = 1; i <= collageX * collageY; i++) {
                    const row = Math.floor((i - 1) / collageX);
                    const col = (i - 1) % collageX;

                    const x = Math.round(col * (tileWidth + gap));
                    const y = Math.round(row * (tileHeight + gap));
                    const w = Math.round(tileWidth);
                    const h = Math.round(tileHeight);

                    // Draw only the tile slice of the base image into the tile box.
                    // This keeps the seams black instead of showing the background through.
                    const srcX = col * baseTileSrcW;
                    const srcY = row * baseTileSrcH;

                    ctx.drawImage(
                        baseImage,
                        srcX, srcY, baseTileSrcW, baseTileSrcH,
                        x, y, w, h
                    );

                    if (photos[i]) {
                        try {
                            const photoImg = await loadImage(photos[i]);
                            drawImageCover(ctx, photoImg, x, y, w, h, opacity);
                        } catch (tileErr) {
                            console.warn(`Failed to load tile photo ${i}:`, photos[i], tileErr);
                        }
                    }
                }

                setStatus('Preparing download…');

                const link = document.createElement('a');
                let fileLabel = 'visible';
                if (mode === 'original') fileLabel = 'original';
                if (mode === 'tilefull') fileLabel = 'tilefull';

                link.download = `mosaic-${fileLabel}-${eventId}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();

                setStatus('Download started.');
            } catch (err) {
                console.error(err);
                setStatus('Export failed: ' + err.message);
            }
        }

        buildAndDownloadMosaic();
    </script>
</body>
</html>
