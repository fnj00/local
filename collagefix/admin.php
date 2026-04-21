<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

$id = isset($_GET['id']) ? $_GET['id'] : '0';
$eventIdInt = is_numeric($id) ? (int)$id : 0;

$uploadMessage = '';
$uploadMessageType = 'success';

function esc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getEmptyTiles($mysqli, $eventId) {
    $eventId = (int)$eventId;
    $emptyTiles = array();

    $eventSql = "SELECT collagex, collagey FROM events WHERE ID = $eventId";
    $eventResult = $mysqli->query($eventSql);
    if (!$eventResult || $eventResult->num_rows === 0) {
        return $emptyTiles;
    }

    $eventRow = $eventResult->fetch_assoc();
    $collageX = isset($eventRow['collagex']) ? (int)$eventRow['collagex'] : 0;
    $collageY = isset($eventRow['collagey']) ? (int)$eventRow['collagey'] : 0;

    if ($collageX <= 0 || $collageY <= 0) {
        return $emptyTiles;
    }

    $totalTiles = $collageX * $collageY;

    $usedTiles = array();
    $usedSql = "SELECT photonum FROM photos WHERE eventId = $eventId AND approved = 1 AND photonum IS NOT NULL AND photonum > 0";
    $usedResult = $mysqli->query($usedSql);
    if ($usedResult) {
        while ($row = $usedResult->fetch_assoc()) {
            $usedTiles[] = (int)$row['photonum'];
        }
    }

    for ($i = 1; $i <= $totalTiles; $i++) {
        if (!in_array($i, $usedTiles, true)) {
            $emptyTiles[] = $i;
        }
    }

    return $emptyTiles;
}

function loadImageResource($tmpPath, $imageType) {
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            return imagecreatefromjpeg($tmpPath);
        case IMAGETYPE_PNG:
            return imagecreatefrompng($tmpPath);
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                return imagecreatefromwebp($tmpPath);
            }
            return false;
        default:
            return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    $postEventId = isset($_POST['eventId']) && is_numeric($_POST['eventId']) ? (int)$_POST['eventId'] : 0;
    $approveNow = isset($_POST['approve_now']) ? 1 : 0;
    $assignMode = isset($_POST['assign_mode']) ? trim($_POST['assign_mode']) : 'hidden';
    $specificTile = isset($_POST['specific_tile']) && is_numeric($_POST['specific_tile']) ? (int)$_POST['specific_tile'] : 0;

    if ($postEventId <= 0) {
        $uploadMessage = 'Invalid event selected.';
        $uploadMessageType = 'danger';
    } elseif (!isset($_FILES['photo_file']) || $_FILES['photo_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadMessage = 'Please choose a photo to upload.';
        $uploadMessageType = 'danger';
    } else {
        $eventSql = "SELECT ID, collagex, collagey FROM events WHERE ID = $postEventId";
        $eventResult = $mysqli->query($eventSql);

        if (!$eventResult || $eventResult->num_rows === 0) {
            $uploadMessage = 'Event not found.';
            $uploadMessageType = 'danger';
        } else {
            $imageInfo = getimagesize($_FILES['photo_file']['tmp_name']);

            if ($imageInfo === false) {
                $uploadMessage = 'Uploaded file is not a valid image.';
                $uploadMessageType = 'danger';
            } else {
                $imageType = $imageInfo[2];

                if (!in_array($imageType, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP), true)) {
                    $uploadMessage = 'Only JPG, PNG, and WEBP images are supported.';
                    $uploadMessageType = 'danger';
                } else {
                    $sourceImage = loadImageResource($_FILES['photo_file']['tmp_name'], $imageType);

                    if (!$sourceImage) {
                        $uploadMessage = 'Unable to process uploaded image on this server.';
                        $uploadMessageType = 'danger';
                    } else {
                        $width = imagesx($sourceImage);
                        $height = imagesy($sourceImage);

                        $dest = imagecreatetruecolor($width, $height);
                        $white = imagecolorallocate($dest, 255, 255, 255);
                        imagefill($dest, 0, 0, $white);
                        imagecopy($dest, $sourceImage, 0, 0, 0, 0, $width, $height);

                        $baseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($_FILES['photo_file']['name'], PATHINFO_FILENAME));
                        if ($baseName === '') {
                            $baseName = 'upload';
                        }
                        $storedBase = $baseName . '_' . time();

                        $photoDir = __DIR__ . '/../photos/' . $postEventId . '/';
                        if (!is_dir($photoDir)) {
                            mkdir($photoDir, 0775, true);
                        }

                        $jpgPath = $photoDir . $storedBase . '.jpg';
                        $pngPath = $photoDir . $storedBase . '.png';

                        $savedJpg = imagejpeg($dest, $jpgPath, 92);
                        $savedPng = imagepng($dest, $pngPath);

                        imagedestroy($sourceImage);
                        imagedestroy($dest);

                        if (!$savedJpg || !$savedPng) {
                            $uploadMessage = 'Unable to save uploaded image files.';
                            $uploadMessageType = 'danger';
                        } else {
                            $approved = $approveNow ? 1 : 0;
                            $photonum = 'NULL';

                            if ($assignMode === 'random') {
                                $emptyTiles = getEmptyTiles($mysqli, $postEventId);
                                if (count($emptyTiles) > 0) {
                                    $randomIndex = array_rand($emptyTiles);
                                    $photonum = (int)$emptyTiles[$randomIndex];
                                    $approved = 1;
                                }
                            } elseif ($assignMode === 'specific' && $specificTile > 0) {
                                $emptyTiles = getEmptyTiles($mysqli, $postEventId);
                                if (in_array($specificTile, $emptyTiles, true)) {
                                    $photonum = (int)$specificTile;
                                    $approved = 1;
                                } else {
                                    $uploadMessage = 'That tile is not available.';
                                    $uploadMessageType = 'danger';
                                }
                            }

                            if ($uploadMessage === '') {
                                $safeFilename = $mysqli->real_escape_string($storedBase);

                                $insertSql = "
                                    INSERT INTO photos (eventId, filename, approved, photonum)
                                    VALUES ($postEventId, '$safeFilename', $approved, $photonum)
                                ";

                                if ($mysqli->query($insertSql)) {
                                    $uploadMessage = 'Photo uploaded successfully.';
                                    $uploadMessageType = 'success';
                                } else {
                                    $uploadMessage = 'Photo files were saved, but database insert failed: ' . $mysqli->error;
                                    $uploadMessageType = 'danger';
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Collage Admin</title>
    <link rel="stylesheet" href="bootstrap.min.css">

    <style>
        body {
            background: #111;
            color: #fff;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 20px;
        }

        h1, h2, h3 {
            margin-top: 0;
        }

        .admin-wrap {
            max-width: 1800px;
            margin: 0 auto;
        }

        .panel {
            background: #1b1b1b;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 20px;
            box-shadow: 0 0 12px rgba(0,0,0,0.25);
        }

        .upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .upload-grid label {
            display: block;
            margin-bottom: 6px;
            font-weight: 700;
        }

        .upload-grid input[type="file"],
        .upload-grid select,
        .upload-grid input[type="number"] {
            width: 100%;
        }

        .upload-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            align-items: center;
            margin-top: 12px;
        }

        .upload-inline label {
            margin-bottom: 0;
            font-weight: 700;
        }

        #mosaicAdmin {
            position: relative;
            margin: 0 auto;
            overflow: hidden;
            background: #000;
            border: 1px solid #333;
        }

        #mosaicAdmin #mainimg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .admin-tile {
            position: absolute;
            overflow: hidden;
            background-repeat: no-repeat;
            box-sizing: border-box;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .admin-tile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            cursor: pointer;
        }

        .admin-tile.empty {
            background: #000 !important;
            border: 1px dashed #555;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            text-align: center;
            font-size: 14px;
        }

        .toolbar {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: center;
        }

        .toolbar label {
            margin: 0;
            font-weight: 700;
        }

        .toolbar input[type="range"] {
            width: 240px;
        }

        .toolbar .value {
            min-width: 55px;
            display: inline-block;
            font-weight: 700;
        }

        .hidden-table {
            width: 100%;
            border-collapse: collapse;
            background: #151515;
        }

        .hidden-table th,
        .hidden-table td {
            border: 1px solid #333;
            padding: 10px;
            vertical-align: middle;
        }

        .hidden-table th {
            background: #202020;
        }

        .hidden-thumb {
            width: 90px;
            height: 90px;
            object-fit: cover;
            display: block;
            border-radius: 6px;
        }

        .empty-badge {
            display: inline-block;
            margin: 3px;
            padding: 6px 10px;
            background: #222;
            border: 1px solid #444;
            border-radius: 6px;
            font-size: 13px;
        }

        .modal-content {
            background: #111;
            color: #fff;
        }

        .modal-photo {
            width: 100%;
            max-height: 60vh;
            object-fit: contain;
            display: block;
            margin-bottom: 16px;
            background: #000;
        }

        .tile-meta {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .table-actions .btn {
            margin-right: 8px;
            margin-bottom: 6px;
        }

        @media (max-width: 900px) {
            .hidden-table {
                font-size: 14px;
            }

            .hidden-thumb {
                width: 64px;
                height: 64px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrap">
        <?php if ($uploadMessage !== ''): ?>
            <div class="alert alert-<?php echo esc($uploadMessageType); ?>">
                <?php echo esc($uploadMessage); ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h1>Collage Admin</h1>

            <div class="toolbar">
                <label for="opacityRange">Opacity</label>
                <input id="opacityRange" type="range" min="0" max="100" step="1" value="40">
                <span id="opacityValue" class="value">40%</span>

                <a id="downloadVisible" class="btn btn-primary" href="#" target="_blank" rel="noopener">Download Current Canvas</a>
                <a id="downloadOriginal" class="btn btn-primary" href="#" target="_blank" rel="noopener">Download Base Resolution</a>
                <a id="downloadTileFull" class="btn btn-primary" href="#" target="_blank" rel="noopener">Download Full Tile Resolution</a>
            </div>
        </div>

        <div class="panel">
            <h2>Upload Photo</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_photo">
                <input type="hidden" name="eventId" value="<?php echo esc($eventIdInt); ?>">

                <div class="upload-grid">
                    <div>
                        <label for="photo_file">Choose Photo</label>
                        <input id="photo_file" type="file" name="photo_file" accept=".jpg,.jpeg,.png,.webp" required class="form-control-file">
                    </div>

                    <div>
                        <label for="assign_mode">Assignment</label>
                        <select id="assign_mode" name="assign_mode" class="form-control">
                            <option value="hidden">Leave Hidden</option>
                            <option value="random">Approve + Random Empty Tile</option>
                            <option value="specific">Approve + Specific Tile</option>
                        </select>
                    </div>

                    <div>
                        <label for="specific_tile">Specific Tile</label>
                        <input id="specific_tile" type="number" min="1" name="specific_tile" class="form-control" placeholder="Only used for Specific Tile">
                    </div>

                    <div>
                        <button type="submit" class="btn btn-success">Upload Photo</button>
                    </div>
                </div>

                <div class="upload-inline">
                    <label>
                        <input type="checkbox" name="approve_now" value="1">
                        Approve Now
                    </label>
                    <span class="text-muted">If you choose Random or Specific tile, the photo will be approved automatically.</span>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2>Visible Mosaic</h2>
            <p>Click any visible photo to manage it. Empty spaces are shown directly in the mosaic.</p>
            <div id="mosaicAdmin">
                <img id="mainimg" src="" alt="Base collage image">
            </div>
        </div>

        <div class="panel">
            <h2>Photos Not Currently Visible</h2>
            <div id="emptySpotSummary" style="margin-bottom:12px;"></div>

            <table class="hidden-table">
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>Filename</th>
                        <th>Empty Spots</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="hiddenPhotosBody">
                    <tr>
                        <td colspan="4">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="photoAdminModal" tabindex="-1" role="dialog" aria-labelledby="photoAdminModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="photoAdminModalLabel" class="modal-title">Manage Mosaic Photo</h3>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity:1;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <img id="adminModalPhoto" class="modal-photo" src="" alt="Selected mosaic photo">
                    <div id="adminModalMeta" class="tile-meta"></div>

                    <div class="d-flex flex-wrap">
                        <button id="removeFromMosaicBtn" class="btn btn-danger mr-2 mb-2">Remove From Mosaic</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        const eventId = <?php echo json_encode($id); ?>;
        const gap = 2;
        const adminRefreshMs = 5000;

        let adminRefreshTimer = null;
        let adminModalOpen = false;
        let opacity = 0.40;
        let collageX = 1;
        let collageY = 1;
        let imgWidth = 1;
        let imgHeight = 1;
        let collageImg = '';
        let selectedTileId = null;
        let currentAdminData = null;

        function updateDownloadLinks() {
            document.getElementById('downloadVisible').href = `export.php?id=${encodeURIComponent(eventId)}&mode=visible`;
            document.getElementById('downloadOriginal').href = `export.php?id=${encodeURIComponent(eventId)}&mode=original`;
            document.getElementById('downloadTileFull').href = `export.php?id=${encodeURIComponent(eventId)}&mode=tilefull`;
        }

        function loadSettings() {
            return fetch('data/collageSettings.php')
                .then(res => res.json())
                .then(settings => {
                    opacity = typeof settings.opacity !== 'undefined' ? parseFloat(settings.opacity) : 0.40;
                    const percent = Math.round(opacity * 100);
                    document.getElementById('opacityRange').value = percent;
                    document.getElementById('opacityValue').textContent = `${percent}%`;
                });
        }

        function saveSettings(newOpacity) {
            const body = new URLSearchParams();
            body.append('opacity', newOpacity.toFixed(2));

            return fetch('data/collageSettings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body.toString()
            });
        }

        function fitAdminMosaic() {
            const container = document.getElementById('mosaicAdmin');
            const maxWidth = Math.min(window.innerWidth - 80, 1400);
            const maxHeight = Math.min(window.innerHeight * 0.6, 850);

            const scale = Math.min(maxWidth / imgWidth, maxHeight / imgHeight);

            const renderWidth = Math.floor(imgWidth * scale);
            const renderHeight = Math.floor(imgHeight * scale);

            container.style.width = `${renderWidth}px`;
            container.style.height = `${renderHeight}px`;

            return { renderWidth, renderHeight };
        }

        function buildAdminTile(tileId, photoUrl, renderWidth, renderHeight) {
            const tileWidth = (renderWidth - ((collageX - 1) * gap)) / collageX;
            const tileHeight = (renderHeight - ((collageY - 1) * gap)) / collageY;

            const index = tileId - 1;
            const col = index % collageX;
            const row = Math.floor(index / collageX);

            const left = Math.round(col * (tileWidth + gap));
            const top = Math.round(row * (tileHeight + gap));

            let width;
            let height;

            if (col === collageX - 1) {
                width = renderWidth - left;
            } else {
                const nextLeft = Math.round((col + 1) * (tileWidth + gap));
                width = nextLeft - left - gap;
            }

            if (row === collageY - 1) {
                height = renderHeight - top;
            } else {
                const nextTop = Math.round((row + 1) * (tileHeight + gap));
                height = nextTop - top - gap;
            }

            const tile = document.createElement('div');
            tile.className = 'admin-tile';
            tile.style.left = `${left}px`;
            tile.style.top = `${top}px`;
            tile.style.width = `${width}px`;
            tile.style.height = `${height}px`;

            if (photoUrl) {
                tile.style.backgroundImage = `url(${collageImg})`;
                tile.style.backgroundRepeat = 'no-repeat';
                tile.style.backgroundSize = `${renderWidth}px ${renderHeight}px`;
                tile.style.backgroundPosition = `${-left}px ${-top}px`;

                const img = document.createElement('img');
                img.src = photoUrl;
                img.style.opacity = opacity;
                img.dataset.tileId = tileId;
                img.dataset.photoUrl = photoUrl;
                tile.appendChild(img);
            } else {
                tile.classList.add('empty');
                tile.style.backgroundImage = 'none';
                tile.style.background = '#000';
                tile.innerHTML = `<div>Empty Spot<br>Tile ${tileId}</div>`;
            }

            return tile;
        }

        function renderAdminMosaic(data) {
            currentAdminData = data;

            collageX = parseInt(data.event.collageX, 10);
            collageY = parseInt(data.event.collageY, 10);
            imgWidth = parseInt(data.event.imgWidth, 10);
            imgHeight = parseInt(data.event.imgHeight, 10);
            collageImg = data.event.collageImg;

            const mainImg = document.getElementById('mainimg');
            const mosaicAdmin = document.getElementById('mosaicAdmin');
            const dims = fitAdminMosaic();

            mainImg.src = collageImg;
            mainImg.style.width = `${dims.renderWidth}px`;
            mainImg.style.height = `${dims.renderHeight}px`;
            mainImg.style.display = 'none';

            mosaicAdmin.querySelectorAll('.admin-tile').forEach(el => el.remove());

            for (let tileId = 1; tileId <= (collageX * collageY); tileId++) {
                const visible = data.visible.find(v => parseInt(v.tileId, 10) === tileId);
                const tile = buildAdminTile(tileId, visible ? visible.photoUrl : null, dims.renderWidth, dims.renderHeight);
                mosaicAdmin.appendChild(tile);
            }
        }

        function renderHiddenPhotos(data) {
            const hiddenBody = document.getElementById('hiddenPhotosBody');
            const emptySummary = document.getElementById('emptySpotSummary');
            const emptyTiles = data.emptyTiles || [];

            if (!emptyTiles.length) {
                emptySummary.innerHTML = '<strong>No empty spots available in the mosaic.</strong>';
            } else {
                emptySummary.innerHTML = '<strong>Empty Spots:</strong> ' + emptyTiles.map(tile => {
                    return `<span class="empty-badge">Tile ${tile}</span>`;
                }).join('');
            }

            if (!data.hidden.length) {
                hiddenBody.innerHTML = '<tr><td colspan="4">No hidden photos found.</td></tr>';
                return;
            }

            hiddenBody.innerHTML = data.hidden.map(photo => {
                const actionButtons = emptyTiles.length
                    ? emptyTiles.map(tileId => {
                        return `<button class="btn btn-sm btn-success assign-photo-btn" data-photo-id="${photo.photoId}" data-tile-id="${tileId}">Add to Tile ${tileId}</button>`;
                    }).join('')
                    : '<span class="text-muted">No empty spots</span>';

                const statusBits = [];
                if (parseInt(photo.approved, 10) === 0) {
                    statusBits.push('<span class="empty-badge">Unapproved</span>');
                }
                if (!photo.photonum || parseInt(photo.photonum, 10) <= 0) {
                    statusBits.push('<span class="empty-badge">No Tile</span>');
                }

                return `
                    <tr>
                        <td><img class="hidden-thumb" src="${photo.photoUrl}" alt=""></td>
                        <td>
                            ${photo.fileName}
                            <div style="margin-top:6px;">${statusBits.join(' ')}</div>
                        </td>
                        <td>${emptyTiles.length ? emptyTiles.map(t => `<span class="empty-badge">${t}</span>`).join('') : '—'}</td>
                        <td class="table-actions">${actionButtons}</td>
                    </tr>
                `;
            }).join('');
        }

        function loadAdminData() {
            return fetch(`data/collageAdminPhotos.php?id=${encodeURIComponent(eventId)}&_=${Date.now()}`)
                .then(res => res.json())
                .then(data => {
                    renderAdminMosaic(data);
                    renderHiddenPhotos(data);
                });
        }

        function refreshAdminDataIfIdle() {
            if (adminModalOpen) {
                return;
            }

            loadAdminData().catch(function (err) {
                console.error('Unable to refresh admin data', err);
            });
        }

        function removePhotoFromMosaic(tileId) {
            const body = new URLSearchParams();
            body.append('action', 'remove');
            body.append('eventId', eventId);
            body.append('tileId', tileId);

            return fetch('data/collageAdminAction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(res => res.json());
        }

        function assignPhotoToTile(photoId, tileId) {
            const body = new URLSearchParams();
            body.append('action', 'assign');
            body.append('eventId', eventId);
            body.append('photoId', photoId);
            body.append('tileId', tileId);

            return fetch('data/collageAdminAction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(res => res.json());
        }

        $(document).on('click', '#mosaicAdmin .admin-tile img', function () {
            const tileId = this.dataset.tileId;
            const photoUrl = this.dataset.photoUrl;

            selectedTileId = tileId;
            $('#adminModalPhoto').attr('src', photoUrl);
            $('#adminModalMeta').text(`Tile ${tileId}`);
            $('#photoAdminModal').modal('show');
        });

        $(document).on('click', '#removeFromMosaicBtn', function () {
            if (!selectedTileId) return;

            removePhotoFromMosaic(selectedTileId).then(result => {
                if (!result.success) {
                    alert(result.message || 'Unable to remove photo.');
                    return;
                }

                $('#photoAdminModal').modal('hide');
                selectedTileId = null;
                loadAdminData();
            });
        });

        $(document).on('click', '.assign-photo-btn', function () {
            const photoId = this.dataset.photoId;
            const tileId = this.dataset.tileId;

            assignPhotoToTile(photoId, tileId).then(result => {
                if (!result.success) {
                    alert(result.message || 'Unable to assign photo.');
                    return;
                }

                loadAdminData();
            });
        });

        $('#photoAdminModal').on('shown.bs.modal', function () {
            adminModalOpen = true;
        });

        $('#photoAdminModal').on('hidden.bs.modal', function () {
            adminModalOpen = false;
        });

        document.getElementById('opacityRange').addEventListener('input', function () {
            const percent = parseInt(this.value, 10);
            const newOpacity = percent / 100;
            document.getElementById('opacityValue').textContent = `${percent}%`;

            saveSettings(newOpacity).then(() => {
                opacity = newOpacity;
                if (currentAdminData) {
                    renderAdminMosaic(currentAdminData);
                }
            });
        });

        window.addEventListener('resize', function () {
            if (currentAdminData) {
                renderAdminMosaic(currentAdminData);
            }
        });

        updateDownloadLinks();

        Promise.all([loadSettings(), loadAdminData()]).then(function () {
            if (adminRefreshTimer) {
                clearInterval(adminRefreshTimer);
            }

            adminRefreshTimer = setInterval(function () {
                refreshAdminDataIfIdle();
            }, adminRefreshMs);
        });
    </script>
</body>
</html>
