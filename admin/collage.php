<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_admin();

$pageTitle = 'Collage';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function get_events(mysqli $mysqli): array
{
    $rows = [];
    $result = $mysqli->query("SELECT ID, title, date, collagex, collagey, numcollage, autoapprove, collageimg, imgWidth, imgHeight FROM events ORDER BY date DESC, ID DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function get_event(mysqli $mysqli, int $eventId): ?array
{
    if ($eventId <= 0) {
        return null;
    }

    $stmt = $mysqli->prepare("SELECT * FROM events WHERE ID = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $event ?: null;
}

function get_empty_tiles(mysqli $mysqli, int $eventId, int $totalTiles): array
{
    $usedTiles = [];

    $stmt = $mysqli->prepare("
        SELECT photonum
        FROM photos
        WHERE eventId = ? AND approved = 1 AND photonum IS NOT NULL AND photonum > 0
    ");

    if ($stmt) {
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $usedTiles[] = (int) $row['photonum'];
        }
        $stmt->close();
    }

    $emptyTiles = [];
    for ($i = 1; $i <= $totalTiles; $i++) {
        if (!in_array($i, $usedTiles, true)) {
            $emptyTiles[] = $i;
        }
    }

    return $emptyTiles;
}

function load_image_resource(string $tmpPath, int $imageType)
{
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            return imagecreatefromjpeg($tmpPath);
        case IMAGETYPE_PNG:
            return imagecreatefrompng($tmpPath);
        case IMAGETYPE_WEBP:
            return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmpPath) : false;
        default:
            return false;
    }
}

function photo_image_url(int $eventId, string $filename): string
{
    $base = '/photos/' . $eventId . '/' . rawurlencode($filename);
    $jpgFs = __DIR__ . '/../photos/' . $eventId . '/' . $filename . '.jpg';
    $pngFs = __DIR__ . '/../photos/' . $eventId . '/' . $filename . '.png';
    $webpFs = __DIR__ . '/../photos/' . $eventId . '/' . $filename . '.webp';

    if (is_file($jpgFs)) {
        return $base . '.jpg';
    }
    if (is_file($pngFs)) {
        return $base . '.png';
    }
    if (is_file($webpFs)) {
        return $base . '.webp';
    }

    return '';
}

function delete_photo_files(int $eventId, string $filename): void
{
    $paths = [
        __DIR__ . '/../photos/' . $eventId . '/' . $filename . '.jpg',
        __DIR__ . '/../photos/' . $eventId . '/' . $filename . '.png',
        __DIR__ . '/../photos/' . $eventId . '/' . $filename . '.webp',
        __DIR__ . '/../photos/' . $eventId . '/' . $filename . '_thumb.jpg',
        __DIR__ . '/../photos/' . $eventId . '/' . $filename . '_thumb.png',
        __DIR__ . '/../photos/' . $eventId . '/' . $filename . '_thumb.webp',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function get_settings_path(int $eventId): string
{
    return __DIR__ . '/../photos/' . $eventId . '/collage_settings.json';
}

function get_current_opacity(int $eventId): float
{
    $settingsPath = get_settings_path($eventId);
    $currentOpacity = 0.40;

    if (is_file($settingsPath)) {
        $settingsJson = json_decode((string) file_get_contents($settingsPath), true);
        if (is_array($settingsJson) && isset($settingsJson['opacity'])) {
            $currentOpacity = (float) $settingsJson['opacity'];
        }
    }

    if (!is_finite($currentOpacity)) {
        $currentOpacity = 0.40;
    }

    return max(0.0, min(1.0, $currentOpacity));
}

function save_opacity_setting(int $eventId, float $opacity): bool
{
    $opacity = max(0.0, min(1.0, $opacity));
    $settingsPath = get_settings_path($eventId);
    $settingsDir = dirname($settingsPath);

    if (!is_dir($settingsDir) && !mkdir($settingsDir, 0775, true) && !is_dir($settingsDir)) {
        return false;
    }

    $saved = file_put_contents($settingsPath, json_encode([
        'opacity' => $opacity
    ], JSON_PRETTY_PRINT));

    return $saved !== false;
}

$events = get_events($mysqli);
$eventId = isset($_GET['event']) ? (int) $_GET['event'] : 0;
if ($eventId <= 0 && !empty($events)) {
    $eventId = (int) $events[0]['ID'];
}
$event = get_event($mysqli, $eventId);

$message = '';
$messageClass = 'message-success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token. Please refresh and try again.';
        $messageClass = 'message-error';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $postEventId = (int) ($_POST['event_id'] ?? 0);

        if ($postEventId > 0) {
            $eventId = $postEventId;
            $event = get_event($mysqli, $eventId);
        }

        if (!$event) {
            $message = 'Please select a valid event.';
            $messageClass = 'message-error';
        } else {
            $totalTiles = max(1, (int) $event['numcollage']);

            if ($action === 'save_opacity') {
                $newOpacity = isset($_POST['opacity']) ? (float) $_POST['opacity'] : 0.40;
                if (save_opacity_setting($eventId, $newOpacity)) {
                    $message = 'Collage opacity updated.';
                    $messageClass = 'message-success';
                } else {
                    $message = 'Unable to save collage opacity.';
                    $messageClass = 'message-error';
                }
            }

            if ($action === 'upload_photo') {
                $approveNow = isset($_POST['approve_now']) ? 1 : 0;
                $assignMode = trim((string) ($_POST['assign_mode'] ?? 'hidden'));
                $specificTile = (int) ($_POST['specific_tile'] ?? 0);

                if (!isset($_FILES['photo_file']) || $_FILES['photo_file']['error'] !== UPLOAD_ERR_OK) {
                    $message = 'Please choose a photo to upload.';
                    $messageClass = 'message-error';
                } else {
                    $imageInfo = @getimagesize($_FILES['photo_file']['tmp_name']);

                    if ($imageInfo === false) {
                        $message = 'Uploaded file is not a valid image.';
                        $messageClass = 'message-error';
                    } else {
                        $imageType = (int) $imageInfo[2];

                        if (!in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                            $message = 'Only JPG, PNG, and WEBP images are supported.';
                            $messageClass = 'message-error';
                        } else {
                            $sourceImage = load_image_resource($_FILES['photo_file']['tmp_name'], $imageType);

                            if (!$sourceImage) {
                                $message = 'Unable to process the uploaded image on this server.';
                                $messageClass = 'message-error';
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
                                $photoDir = __DIR__ . '/../photos/' . $eventId . '/';

                                if (!is_dir($photoDir)) {
                                    @mkdir($photoDir, 0775, true);
                                }

                                $jpgPath = $photoDir . $storedBase . '.jpg';
                                $pngPath = $photoDir . $storedBase . '.png';

                                $savedJpg = imagejpeg($dest, $jpgPath, 92);
                                $savedPng = imagepng($dest, $pngPath);

                                imagedestroy($sourceImage);
                                imagedestroy($dest);

                                if (!$savedJpg || !$savedPng) {
                                    $message = 'Unable to save uploaded image files.';
                                    $messageClass = 'message-error';
                                } else {
                                    $approved = $approveNow ? 1 : 0;
                                    $photonum = null;

                                    if ($assignMode === 'random') {
                                        $emptyTiles = get_empty_tiles($mysqli, $eventId, $totalTiles);
                                        if ($emptyTiles) {
                                            $photonum = (int) $emptyTiles[array_rand($emptyTiles)];
                                            $approved = 1;
                                        }
                                    } elseif ($assignMode === 'specific') {
                                        $emptyTiles = get_empty_tiles($mysqli, $eventId, $totalTiles);

                                        if ($specificTile <= 0 || $specificTile > $totalTiles) {
                                            $message = 'Please choose a valid tile number.';
                                            $messageClass = 'message-error';
                                        } elseif (!in_array($specificTile, $emptyTiles, true)) {
                                            $message = 'That tile is not available.';
                                            $messageClass = 'message-warning';
                                        } else {
                                            $photonum = $specificTile;
                                            $approved = 1;
                                        }
                                    }

                                    if ($message === '') {
                                        $stmt = $mysqli->prepare("
                                            INSERT INTO photos (eventId, filename, approved, photonum)
                                            VALUES (?, ?, ?, ?)
                                        ");

                                        if ($stmt) {
                                            $stmt->bind_param('isii', $eventId, $storedBase, $approved, $photonum);
                                            $ok = $stmt->execute();
                                            $stmt->close();

                                            if ($ok) {
                                                $message = 'Photo uploaded successfully.';
                                                $messageClass = 'message-success';
                                            } else {
                                                $message = 'Photo files were saved, but the database insert failed.';
                                                $messageClass = 'message-error';
                                            }
                                        } else {
                                            $message = 'Unable to save photo record.';
                                            $messageClass = 'message-error';
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($action === 'remove_from_mosaic') {
                $photoId = (int) ($_POST['photo_id'] ?? 0);
                $stmt = $mysqli->prepare("UPDATE photos SET photonum = NULL WHERE ID = ? AND eventId = ?");
                if ($stmt) {
                    $stmt->bind_param('ii', $photoId, $eventId);
                    $ok = $stmt->execute();
                    $stmt->close();
                    $message = $ok ? 'Photo removed from the mosaic.' : 'Unable to update photo.';
                    $messageClass = $ok ? 'message-success' : 'message-error';
                }
            }

            if ($action === 'assign_hidden_random') {
                $photoId = (int) ($_POST['photo_id'] ?? 0);
                $emptyTiles = get_empty_tiles($mysqli, $eventId, $totalTiles);

                if (!$emptyTiles) {
                    $message = 'No empty tiles are available.';
                    $messageClass = 'message-warning';
                } else {
                    $tile = (int) $emptyTiles[array_rand($emptyTiles)];
                    $stmt = $mysqli->prepare("UPDATE photos SET approved = 1, photonum = ? WHERE ID = ? AND eventId = ?");
                    if ($stmt) {
                        $stmt->bind_param('iii', $tile, $photoId, $eventId);
                        $ok = $stmt->execute();
                        $stmt->close();
                        $message = $ok ? 'Photo assigned to tile #' . $tile . '.' : 'Unable to assign photo.';
                        $messageClass = $ok ? 'message-success' : 'message-error';
                    }
                }
            }

            if ($action === 'assign_hidden_specific') {
                $photoId = (int) ($_POST['photo_id'] ?? 0);
                $tile = (int) ($_POST['specific_tile'] ?? 0);
                $emptyTiles = get_empty_tiles($mysqli, $eventId, $totalTiles);

                if ($tile <= 0 || $tile > $totalTiles) {
                    $message = 'Please choose a valid tile.';
                    $messageClass = 'message-error';
                } elseif (!in_array($tile, $emptyTiles, true)) {
                    $message = 'That tile is not available.';
                    $messageClass = 'message-warning';
                } else {
                    $stmt = $mysqli->prepare("UPDATE photos SET approved = 1, photonum = ? WHERE ID = ? AND eventId = ?");
                    if ($stmt) {
                        $stmt->bind_param('iii', $tile, $photoId, $eventId);
                        $ok = $stmt->execute();
                        $stmt->close();
                        $message = $ok ? 'Photo assigned to tile #' . $tile . '.' : 'Unable to assign photo.';
                        $messageClass = $ok ? 'message-success' : 'message-error';
                    }
                }
            }

            if ($action === 'delete_photo') {
                $photoId = (int) ($_POST['photo_id'] ?? 0);

                $stmt = $mysqli->prepare("SELECT filename FROM photos WHERE ID = ? AND eventId = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('ii', $photoId, $eventId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $photo = $result ? $result->fetch_assoc() : null;
                    $stmt->close();

                    if ($photo) {
                        $deleteStmt = $mysqli->prepare("DELETE FROM photos WHERE ID = ? AND eventId = ?");
                        if ($deleteStmt) {
                            $deleteStmt->bind_param('ii', $photoId, $eventId);
                            $ok = $deleteStmt->execute();
                            $deleteStmt->close();

                            if ($ok) {
                                delete_photo_files($eventId, (string) $photo['filename']);
                                $message = 'Photo deleted.';
                                $messageClass = 'message-success';
                            } else {
                                $message = 'Unable to delete photo.';
                                $messageClass = 'message-error';
                            }
                        }
                    } else {
                        $message = 'Photo not found.';
                        $messageClass = 'message-error';
                    }
                }
            }
        }
    }
}

$event = get_event($mysqli, $eventId);
$currentOpacity = $event ? get_current_opacity($eventId) : 0.40;

$visiblePhotos = [];
$hiddenPhotos = [];
$emptyTiles = [];
$baseCollageImage = '';

if ($event) {
    $totalTiles = max(1, (int) $event['numcollage']);
    $emptyTiles = get_empty_tiles($mysqli, $eventId, $totalTiles);

    $visibleStmt = $mysqli->prepare("
        SELECT ID, eventId, filename, approved, photonum
        FROM photos
        WHERE eventId = ? AND approved = 1 AND photonum IS NOT NULL AND photonum > 0
        ORDER BY photonum ASC
    ");

    if ($visibleStmt) {
        $visibleStmt->bind_param('i', $eventId);
        $visibleStmt->execute();
        $result = $visibleStmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $row['image_url'] = photo_image_url((int) $row['eventId'], (string) $row['filename']);
            $visiblePhotos[] = $row;
        }
        $visibleStmt->close();
    }

    $hiddenStmt = $mysqli->prepare("
        SELECT ID, eventId, filename, approved, photonum
        FROM photos
        WHERE eventId = ? AND (approved = 0 OR photonum IS NULL OR photonum = 0)
        ORDER BY approved ASC, ID DESC
    ");

    if ($hiddenStmt) {
        $hiddenStmt->bind_param('i', $eventId);
        $hiddenStmt->execute();
        $result = $hiddenStmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $row['image_url'] = photo_image_url((int) $row['eventId'], (string) $row['filename']);
            $hiddenPhotos[] = $row;
        }
        $hiddenStmt->close();
    }

    $possibleBaseImages = [
        __DIR__ . '/../photos/' . $eventId . '/collage.png',
        __DIR__ . '/../photos/' . $eventId . '/collage.jpg',
        __DIR__ . '/../photos/' . $eventId . '/collage.webp',
        __DIR__ . '/../photos/' . $eventId . '/background.png',
        __DIR__ . '/../photos/' . $eventId . '/background.jpg',
        __DIR__ . '/../photos/' . $eventId . '/background.webp',
    ];

    foreach ($possibleBaseImages as $candidate) {
        if (is_file($candidate)) {
            $baseCollageImage = str_replace(__DIR__ . '/..', '', $candidate);
            break;
        }
    }
}

require __DIR__ . '/includes/admin_layout_header.php';
?>

<h1>Collage</h1>

<?php if ($message !== ''): ?>
    <div class="message <?= h($messageClass) ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <form method="get" class="row">
        <div style="min-width:280px; flex:1;">
            <label for="event">Event</label>
            <select id="event" name="event" onchange="this.form.submit()">
                <?php foreach ($events as $evt): ?>
                    <option value="<?= (int) $evt['ID'] ?>" <?= $eventId === (int) $evt['ID'] ? 'selected' : '' ?>>
                        <?= h($evt['title']) ?> — <?= h($evt['date']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button class="btn" type="submit">Load Event</button></noscript>
    </form>
</div>

<?php if (!$event): ?>
    <div class="card"><p class="muted">No event selected.</p></div>
<?php else: ?>
    <div class="card">
        <h2><?= h($event['title']) ?></h2>
        <div class="row">
            <span class="pill">Date <?= h($event['date']) ?></span>
            <span class="pill">Grid <?= (int) $event['collagex'] ?> × <?= (int) $event['collagey'] ?></span>
            <span class="pill">Total Tiles <?= (int) $event['numcollage'] ?></span>
            <span class="pill">Empty Tiles <?= count($emptyTiles) ?></span>
            <?php if (!empty($event['imgWidth']) && !empty($event['imgHeight'])): ?>
                <span class="pill">Background <?= (int) $event['imgWidth'] ?> × <?= (int) $event['imgHeight'] ?></span>
            <?php endif; ?>
        </div>

        <div class="row" style="margin-top:16px;">
	    <a class="btn" href="/admin/export.php?id=<?= (int) $eventId ?>&mode=original" target="_blank" rel="noopener">Export Original</a>
	    <a class="btn btn-secondary" href="/admin/export.php?id=<?= (int) $eventId ?>&mode=tilefull" target="_blank" rel="noopener">Export Tile Full</a>
            <?php if ($baseCollageImage !== ''): ?>
                <a class="btn btn-secondary" href="<?= h($baseCollageImage) ?>" target="_blank" rel="noopener">Open Background</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2>Collage View & Opacity</h2>

        <form id="opacityForm" method="post" class="row" style="margin-bottom:16px;align-items:end;">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_opacity">
            <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">

            <div style="min-width:260px;">
                <label for="opacity">Photo Overlay Opacity</label>
                <input
                    id="opacity"
                    type="range"
                    name="opacity"
                    min="0"
                    max="1"
                    step="0.01"
                    value="<?= h((string) $currentOpacity) ?>">
                <div class="muted">Current: <span id="opacityValue"><?= h((string) $currentOpacity) ?></span></div>
            </div>

            <div>
                <button class="btn" type="submit">Save Opacity</button>
            </div>
        </form>

        <div style="border:1px solid var(--border);border-radius:12px;overflow:hidden;background:#fff;">
            <iframe
                id="collagePreviewFrame"
                src="/collagefix/?id=<?= (int) $eventId ?>"
                style="width:100%;height:900px;border:0;"
                loading="lazy">
            </iframe>
        </div>
    </div>

    <div class="card">
        <h2>Upload Photo</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="upload_photo">
            <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">

            <div style="margin-bottom:12px;">
                <label for="photo_file">Choose Photo</label>
                <input id="photo_file" type="file" name="photo_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
            </div>

            <div style="margin-bottom:12px;">
                <label for="assign_mode">Assignment</label>
                <select id="assign_mode" name="assign_mode">
                    <option value="hidden">Leave Hidden</option>
                    <option value="random">Approve + Random Empty Tile</option>
                    <option value="specific">Approve + Specific Tile</option>
                </select>
            </div>

            <div style="margin-bottom:12px;">
                <label for="specific_tile">Specific Tile</label>
                <input id="specific_tile" type="number" name="specific_tile" min="1" max="<?= (int) $event['numcollage'] ?>" placeholder="Only used with specific tile mode">
            </div>

            <div style="margin-bottom:16px;">
                <label>
                    <input type="checkbox" name="approve_now" value="1">
                    Approve now
                </label>
                <div class="muted" style="margin-top:6px;">
                    Random and specific tile placement approve the photo automatically.
                </div>
            </div>

            <button class="btn" type="submit">Upload Photo</button>
        </form>
    </div>

    <div class="card">
        <h2>Visible Mosaic</h2>
        <p class="muted">Filled tiles show the assigned photo. Empty spaces show the next available slots directly in the grid.</p>

        <style>
            .mosaic-grid {
                display: grid;
                grid-template-columns: repeat(<?= max(1, (int) $event['collagex']) ?>, minmax(120px, 1fr));
                gap: 12px;
            }
            .mosaic-tile {
                border: 1px solid var(--border);
                border-radius: 12px;
                background: #fff;
                padding: 8px;
                min-height: 180px;
            }
            .mosaic-tile img {
                width: 100%;
                height: 120px;
                object-fit: contain;
                background: #f8fafc;
                border: 1px solid var(--border);
                border-radius: 8px;
            }
            .mosaic-empty {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 120px;
                border: 2px dashed var(--border);
                border-radius: 8px;
                color: var(--muted);
                font-weight: 700;
                background: #fafafa;
            }
            .hidden-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 16px;
            }
            .hidden-card {
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 12px;
                background: #fff;
            }
            .hidden-card img {
                width: 100%;
                height: 200px;
                object-fit: contain;
                background: #f8fafc;
                border: 1px solid var(--border);
                border-radius: 8px;
            }
            .action-stack form {
                display: inline-block;
                margin: 6px 6px 0 0;
            }
        </style>

        <?php
        $visibleByTile = [];
        foreach ($visiblePhotos as $photo) {
            $visibleByTile[(int) $photo['photonum']] = $photo;
        }
        ?>

        <div class="mosaic-grid">
            <?php for ($tile = 1; $tile <= (int) $event['numcollage']; $tile++): ?>
                <div class="mosaic-tile">
                    <div style="font-weight:700; margin-bottom:8px;">Tile #<?= $tile ?></div>

                    <?php if (isset($visibleByTile[$tile])): ?>
                        <?php $photo = $visibleByTile[$tile]; ?>
                        <?php if ($photo['image_url'] !== ''): ?>
                            <a href="<?= h($photo['image_url']) ?>" target="_blank" rel="noopener">
                                <img src="<?= h($photo['image_url']) ?>" alt="">
                            </a>
                        <?php else: ?>
                            <div class="mosaic-empty">Image Missing</div>
                        <?php endif; ?>

                        <div class="muted" style="margin-top:8px; font-size:13px;">
                            <?= h($photo['filename']) ?>
                        </div>

                        <form method="post" style="margin-top:8px;">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="remove_from_mosaic">
                            <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                            <input type="hidden" name="photo_id" value="<?= (int) $photo['ID'] ?>">
                            <button class="btn btn-secondary" type="submit">Remove</button>
                        </form>
                    <?php else: ?>
                        <div class="mosaic-empty">Empty</div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="card">
        <h2>Photos Not Currently Visible</h2>

        <?php if (!$hiddenPhotos): ?>
            <p class="muted">No hidden or pending photos for this event.</p>
        <?php else: ?>
            <div class="hidden-grid">
                <?php foreach ($hiddenPhotos as $photo): ?>
                    <?php $isPending = (int) $photo['approved'] === 0; ?>
                    <div class="hidden-card">
                        <?php if ($photo['image_url'] !== ''): ?>
                            <a href="<?= h($photo['image_url']) ?>" target="_blank" rel="noopener">
                                <img src="<?= h($photo['image_url']) ?>" alt="">
                            </a>
                        <?php else: ?>
                            <div class="mosaic-empty">Image Missing</div>
                        <?php endif; ?>

                        <div style="margin-top:10px;">
                            <div><strong>ID:</strong> <?= (int) $photo['ID'] ?></div>
                            <div><strong>Filename:</strong> <?= h($photo['filename']) ?></div>
                            <div><strong>Status:</strong> <?= $isPending ? 'Pending approval' : 'Approved, hidden' ?></div>
                            <div><strong>Empty tiles available:</strong> <?= count($emptyTiles) ?></div>
                        </div>

                        <div class="action-stack" style="margin-top:10px;">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="action" value="assign_hidden_random">
                                <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                                <input type="hidden" name="photo_id" value="<?= (int) $photo['ID'] ?>">
                                <button class="btn" type="submit">Assign Random Tile</button>
                            </form>

                            <form method="post" class="row">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="action" value="assign_hidden_specific">
                                <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                                <input type="hidden" name="photo_id" value="<?= (int) $photo['ID'] ?>">
                                <input type="number" name="specific_tile" min="1" max="<?= (int) $event['numcollage'] ?>" placeholder="Tile #" style="width:100px;">
                                <button class="btn btn-secondary" type="submit">Assign</button>
                            </form>

                            <form method="post" onsubmit="return confirm('Delete this photo?');">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_photo">
                                <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                                <input type="hidden" name="photo_id" value="<?= (int) $photo['ID'] ?>">
                                <button class="btn btn-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        var slider = document.getElementById('opacity');
        var valueEl = document.getElementById('opacityValue');
        var frame = document.getElementById('collagePreviewFrame');
        var form = document.getElementById('opacityForm');
        var saveTimer = null;

        if (!slider || !frame || !form) return;

        function previewOpacity(value) {
            if (!frame.contentWindow) return;
            frame.contentWindow.postMessage({
                type: 'collage-opacity-preview',
                opacity: parseFloat(value)
            }, '*');
        }

        function autoSaveOpacity(value) {
            var formData = new FormData();
            formData.append('csrf_token', form.querySelector('input[name="csrf_token"]').value);
            formData.append('action', 'save_opacity');
            formData.append('event_id', form.querySelector('input[name="event_id"]').value);
            formData.append('opacity', value);

            fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).catch(function (err) {
                console.warn('Unable to auto-save opacity', err);
            });
        }

        slider.addEventListener('input', function () {
            valueEl.textContent = slider.value;
            previewOpacity(slider.value);

            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () {
                autoSaveOpacity(slider.value);
            }, 250);
        });
    })();
    </script>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin_layout_footer.php'; ?>
