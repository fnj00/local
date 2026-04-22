<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_admin();

$pageTitle = 'Photos';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function get_selected_event(mysqli $mysqli, int $eventId): ?array
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

function get_all_events(mysqli $mysqli): array
{
    $rows = [];
    $result = $mysqli->query("SELECT ID, title, date, collagex, collagey, numcollage FROM events ORDER BY date DESC, ID DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function get_empty_tiles(mysqli $mysqli, int $eventId, int $maxTiles): array
{
    $used = [];
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
            $used[] = (int) $row['photonum'];
        }
        $stmt->close();
    }

    $empty = [];
    for ($i = 1; $i <= $maxTiles; $i++) {
        if (!in_array($i, $used, true)) {
            $empty[] = $i;
        }
    }

    return $empty;
}

function photo_image_url(int $eventId, string $filename): string
{
    $base = '/photos/' . $eventId . '/' . rawurlencode($filename);
    $jpgFs = __DIR__ . '/../photos/' . $eventId . '/' . $filename . '.jpg';
    $pngFs = __DIR__ . '/../photos/' . $eventId . '/' . $filename . '.png';

    if (is_file($jpgFs)) {
        return $base . '.jpg';
    }

    if (is_file($pngFs)) {
        return $base . '.png';
    }

    return '';
}

function delete_photo_files(int $eventId, string $filename): void
{
    $paths = [
        __DIR__ . '/../photos/' . $eventId . '/' . $filename . '.jpg',
        __DIR__ . '/../photos/' . $eventId . '/' . $filename . '.png',
        __DIR__ . '/../photos/' . $eventId . '/' . $filename . '_thumb.jpg',
        __DIR__ . '/../photos/' . $eventId . '/' . $filename . '_thumb.png',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function photo_counts(mysqli $mysqli, int $eventId): array
{
    $counts = [
        'total' => 0,
        'approved' => 0,
        'visible' => 0,
        'hidden' => 0,
        'pending' => 0,
    ];

    $stmt = $mysqli->prepare("
        SELECT
            COUNT(*) AS totalPhotos,
            SUM(CASE WHEN approved = 1 THEN 1 ELSE 0 END) AS approvedPhotos,
            SUM(CASE WHEN approved = 1 AND photonum IS NOT NULL AND photonum > 0 THEN 1 ELSE 0 END) AS visiblePhotos,
            SUM(CASE WHEN approved = 1 AND (photonum IS NULL OR photonum = 0) THEN 1 ELSE 0 END) AS hiddenPhotos,
            SUM(CASE WHEN approved = 0 THEN 1 ELSE 0 END) AS pendingPhotos
        FROM photos
        WHERE eventId = ?
    ");

    if ($stmt) {
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            $counts['total'] = (int) ($row['totalPhotos'] ?? 0);
            $counts['approved'] = (int) ($row['approvedPhotos'] ?? 0);
            $counts['visible'] = (int) ($row['visiblePhotos'] ?? 0);
            $counts['hidden'] = (int) ($row['hiddenPhotos'] ?? 0);
            $counts['pending'] = (int) ($row['pendingPhotos'] ?? 0);
        }
    }

    return $counts;
}

$events = get_all_events($mysqli);
$eventId = isset($_GET['event']) ? (int) $_GET['event'] : 0;
if ($eventId <= 0 && !empty($events)) {
    $eventId = (int) $events[0]['ID'];
}

$event = get_selected_event($mysqli, $eventId);

$message = '';
$messageClass = 'message-success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token. Please refresh and try again.';
        $messageClass = 'message-error';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $postEventId = (int) ($_POST['event_id'] ?? 0);
        $postPhotoId = (int) ($_POST['photo_id'] ?? 0);

        if ($postEventId > 0) {
            $eventId = $postEventId;
            $event = get_selected_event($mysqli, $eventId);
        }

        if (!$event) {
            $message = 'Please select a valid event first.';
            $messageClass = 'message-error';
        } else {
            $maxTiles = max(1, (int) $event['numcollage']);

            if ($action === 'approve_photo') {
                $stmt = $mysqli->prepare("UPDATE photos SET approved = 1 WHERE ID = ? AND eventId = ?");
                if ($stmt) {
                    $stmt->bind_param('ii', $postPhotoId, $eventId);
                    $ok = $stmt->execute();
                    $stmt->close();
                    $message = $ok ? 'Photo approved.' : 'Unable to approve photo.';
                    $messageClass = $ok ? 'message-success' : 'message-error';
                }
            }

            if ($action === 'unapprove_photo') {
                $stmt = $mysqli->prepare("UPDATE photos SET approved = 0, photonum = NULL WHERE ID = ? AND eventId = ?");
                if ($stmt) {
                    $stmt->bind_param('ii', $postPhotoId, $eventId);
                    $ok = $stmt->execute();
                    $stmt->close();
                    $message = $ok ? 'Photo moved back to pending.' : 'Unable to update photo.';
                    $messageClass = $ok ? 'message-success' : 'message-error';
                }
            }

            if ($action === 'hide_photo') {
                $stmt = $mysqli->prepare("UPDATE photos SET photonum = NULL WHERE ID = ? AND eventId = ?");
                if ($stmt) {
                    $stmt->bind_param('ii', $postPhotoId, $eventId);
                    $ok = $stmt->execute();
                    $stmt->close();
                    $message = $ok ? 'Photo removed from the mosaic.' : 'Unable to hide photo.';
                    $messageClass = $ok ? 'message-success' : 'message-error';
                }
            }

            if ($action === 'assign_random') {
                $emptyTiles = get_empty_tiles($mysqli, $eventId, $maxTiles);

                if (!$emptyTiles) {
                    $message = 'No empty collage tiles are available.';
                    $messageClass = 'message-warning';
                } else {
                    $tile = (int) $emptyTiles[array_rand($emptyTiles)];
                    $stmt = $mysqli->prepare("UPDATE photos SET approved = 1, photonum = ? WHERE ID = ? AND eventId = ?");
                    if ($stmt) {
                        $stmt->bind_param('iii', $tile, $postPhotoId, $eventId);
                        $ok = $stmt->execute();
                        $stmt->close();
                        $message = $ok ? 'Photo assigned to tile #' . $tile . '.' : 'Unable to assign photo.';
                        $messageClass = $ok ? 'message-success' : 'message-error';
                    }
                }
            }

            if ($action === 'assign_specific') {
                $tile = (int) ($_POST['specific_tile'] ?? 0);
                $emptyTiles = get_empty_tiles($mysqli, $eventId, $maxTiles);

                if ($tile <= 0 || $tile > $maxTiles) {
                    $message = 'Please select a valid tile number.';
                    $messageClass = 'message-error';
                } elseif (!in_array($tile, $emptyTiles, true)) {
                    $message = 'That tile is already in use.';
                    $messageClass = 'message-warning';
                } else {
                    $stmt = $mysqli->prepare("UPDATE photos SET approved = 1, photonum = ? WHERE ID = ? AND eventId = ?");
                    if ($stmt) {
                        $stmt->bind_param('iii', $tile, $postPhotoId, $eventId);
                        $ok = $stmt->execute();
                        $stmt->close();
                        $message = $ok ? 'Photo assigned to tile #' . $tile . '.' : 'Unable to assign photo.';
                        $messageClass = $ok ? 'message-success' : 'message-error';
                    }
                }
            }

            if ($action === 'delete_photo') {
                $stmt = $mysqli->prepare("SELECT filename FROM photos WHERE ID = ? AND eventId = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('ii', $postPhotoId, $eventId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $photo = $result ? $result->fetch_assoc() : null;
                    $stmt->close();

                    if ($photo) {
                        $deleteStmt = $mysqli->prepare("DELETE FROM photos WHERE ID = ? AND eventId = ?");
                        if ($deleteStmt) {
                            $deleteStmt->bind_param('ii', $postPhotoId, $eventId);
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

$event = get_selected_event($mysqli, $eventId);
$counts = $event ? photo_counts($mysqli, $eventId) : ['total'=>0,'approved'=>0,'visible'=>0,'hidden'=>0,'pending'=>0];
$photos = [];

if ($event) {
    $stmt = $mysqli->prepare("
        SELECT ID, eventId, filename, approved, photonum
        FROM photos
        WHERE eventId = ?
        ORDER BY approved ASC, photonum IS NULL DESC, photonum ASC, ID DESC
    ");

    if ($stmt) {
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $row['image_url'] = photo_image_url((int) $row['eventId'], (string) $row['filename']);
            $photos[] = $row;
        }
        $stmt->close();
    }
}

require __DIR__ . '/includes/admin_layout_header.php';
?>

<h1>Photos</h1>

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
    <div class="card">
        <p class="muted">No event selected.</p>
    </div>
<?php else: ?>
    <div class="card">
        <h2><?= h($event['title']) ?></h2>
        <div class="row">
            <span class="pill">Date <?= h($event['date']) ?></span>
            <span class="pill">Grid <?= (int) $event['collagex'] ?> × <?= (int) $event['collagey'] ?></span>
            <span class="pill">Total <?= $counts['total'] ?></span>
            <span class="pill">Pending <?= $counts['pending'] ?></span>
            <span class="pill">Approved <?= $counts['approved'] ?></span>
            <span class="pill">Visible <?= $counts['visible'] ?></span>
            <span class="pill">Hidden <?= $counts['hidden'] ?></span>
        </div>
    </div>

    <div class="card">
        <h2>All Photos</h2>

        <?php if (!$photos): ?>
            <p class="muted">No photos have been uploaded for this event yet.</p>
        <?php else: ?>
            <style>
                .photo-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                    gap: 16px;
                }
                .photo-card {
                    border: 1px solid var(--border);
                    border-radius: 12px;
                    padding: 12px;
                    background: #fff;
                }
                .photo-card img {
                    width: 100%;
                    height: 220px;
                    object-fit: contain;
                    border-radius: 8px;
                    background: #f8fafc;
                    border: 1px solid var(--border);
                }
                .photo-meta {
                    margin-top: 10px;
                    font-size: 14px;
                }
                .photo-actions form {
                    display: inline-block;
                    margin: 6px 6px 0 0;
                }
            </style>

            <div class="photo-grid">
                <?php foreach ($photos as $photo): ?>
                    <?php
                    $isApproved = (int) $photo['approved'] === 1;
                    $tile = isset($photo['photonum']) ? (int) $photo['photonum'] : 0;
                    ?>
                    <div class="photo-card">
                        <?php if ($photo['image_url'] !== ''): ?>
                            <a href="<?= h($photo['image_url']) ?>" target="_blank" rel="noopener">
                                <img src="<?= h($photo['image_url']) ?>" alt="">
                            </a>
                        <?php else: ?>
                            <div class="muted">Image file missing</div>
                        <?php endif; ?>

                        <div class="photo-meta">
                            <div><strong>ID:</strong> <?= (int) $photo['ID'] ?></div>
                            <div><strong>Filename:</strong> <?= h($photo['filename']) ?></div>
                            <div><strong>Status:</strong>
                                <?php if (!$isApproved): ?>
                                    Pending approval
                                <?php elseif ($tile > 0): ?>
                                    Visible on tile #<?= $tile ?>
                                <?php else: ?>
                                    Approved, hidden
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="photo-actions">
                            <?php if (!$isApproved): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="approve_photo">
                                    <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['ID'] ?>">
                                    <button class="btn" type="submit">Approve</button>
                                </form>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="unapprove_photo">
                                    <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['ID'] ?>">
                                    <button class="btn btn-secondary" type="submit">Move to Pending</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($tile > 0): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="hide_photo">
                                    <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['ID'] ?>">
                                    <button class="btn btn-secondary" type="submit">Remove From Mosaic</button>
                                </form>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="assign_random">
                                    <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['ID'] ?>">
                                    <button class="btn btn-secondary" type="submit">Assign Random Tile</button>
                                </form>

                                <form method="post" class="row">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="assign_specific">
                                    <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['ID'] ?>">
                                    <input type="number" name="specific_tile" min="1" max="<?= (int) $event['numcollage'] ?>" placeholder="Tile #" style="width:100px;">
                                    <button class="btn btn-secondary" type="submit">Assign</button>
                                </form>
                            <?php endif; ?>

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
<?php endif; ?>

<?php require __DIR__ . '/includes/admin_layout_footer.php'; ?>
