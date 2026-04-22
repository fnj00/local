<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_admin();

$pageTitle = 'Events';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function event_photo_counts(mysqli $mysqli, int $eventId): array
{
    $stmt = $mysqli->prepare("
        SELECT
            COUNT(*) AS totalPhotos,
            SUM(CASE WHEN approved = 1 THEN 1 ELSE 0 END) AS approvedPhotos,
            SUM(CASE WHEN approved = 1 AND photonum IS NOT NULL AND photonum > 0 THEN 1 ELSE 0 END) AS visiblePhotos,
            SUM(CASE WHEN approved = 1 AND (photonum IS NULL OR photonum = 0) THEN 1 ELSE 0 END) AS hiddenApprovedPhotos
        FROM photos
        WHERE eventId = ?
    ");

    if (!$stmt) {
        return [
            'totalPhotos' => 0,
            'approvedPhotos' => 0,
            'visiblePhotos' => 0,
            'hiddenApprovedPhotos' => 0,
        ];
    }

    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return [
        'totalPhotos' => isset($row['totalPhotos']) ? (int) $row['totalPhotos'] : 0,
        'approvedPhotos' => isset($row['approvedPhotos']) ? (int) $row['approvedPhotos'] : 0,
        'visiblePhotos' => isset($row['visiblePhotos']) ? (int) $row['visiblePhotos'] : 0,
        'hiddenApprovedPhotos' => isset($row['hiddenApprovedPhotos']) ? (int) $row['hiddenApprovedPhotos'] : 0,
    ];
}

function normalize_upload_extension(string $mimeType, string $originalName): ?string
{
    $mimeType = strtolower(trim($mimeType));

    $map = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (isset($map[$mimeType])) {
        return $map[$mimeType];
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    return null;
}

function save_collage_background_metadata(mysqli $mysqli, int $eventId, string $targetPath, string $collageFile): bool
{
    $imageSize = @getimagesize($targetPath);

    $imgWidth = 0;
    $imgHeight = 0;

    if (is_array($imageSize)) {
        $imgWidth = isset($imageSize[0]) ? (int) $imageSize[0] : 0;
        $imgHeight = isset($imageSize[1]) ? (int) $imageSize[1] : 0;
    }

    $stmt = $mysqli->prepare("
        UPDATE events
        SET collageimg = ?, imgWidth = ?, imgHeight = ?
        WHERE ID = ?
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('siii', $collageFile, $imgWidth, $imgHeight, $eventId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function remove_existing_collage_files(string $photoDir): void
{
    foreach (glob($photoDir . '/collage.*') ?: [] as $oldFile) {
        @unlink($oldFile);
    }
}

function handle_collage_background_upload(mysqli $mysqli, int $eventId, array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [
            'performed' => false,
            'success' => true,
            'message' => '',
            'class' => 'message-success',
        ];
    }

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [
            'performed' => true,
            'success' => false,
            'message' => 'Collage background upload failed.',
            'class' => 'message-warning',
        ];
    }

    $ext = normalize_upload_extension(
        (string) ($upload['type'] ?? ''),
        (string) ($upload['name'] ?? '')
    );

    if ($ext === null) {
        return [
            'performed' => true,
            'success' => false,
            'message' => 'Collage background must be a JPG, PNG, GIF, or WEBP image.',
            'class' => 'message-warning',
        ];
    }

    $photoDir = __DIR__ . '/../photos/' . $eventId;
    if (!is_dir($photoDir) && !mkdir($photoDir, 0755, true) && !is_dir($photoDir)) {
        return [
            'performed' => true,
            'success' => false,
            'message' => 'Could not create the event photo folder.',
            'class' => 'message-warning',
        ];
    }

    remove_existing_collage_files($photoDir);

    $targetBase = 'collage';
    $targetPath = $photoDir . '/' . $targetBase . '.' . $ext;

    if (!move_uploaded_file((string) $upload['tmp_name'], $targetPath)) {
        return [
            'performed' => true,
            'success' => false,
            'message' => 'Collage background upload could not be saved.',
            'class' => 'message-warning',
        ];
    }

    $collageFile = $targetBase . '.' . $ext;

    if (!save_collage_background_metadata($mysqli, $eventId, $targetPath, $collageFile)) {
        return [
            'performed' => true,
            'success' => false,
            'message' => 'Collage background saved, but metadata could not be updated.',
            'class' => 'message-warning',
        ];
    }

    return [
        'performed' => true,
        'success' => true,
        'message' => '',
        'class' => 'message-success',
    ];
}

$message = '';
$messageClass = 'message-success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token. Please refresh and try again.';
        $messageClass = 'message-error';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save_event') {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $date = trim((string) ($_POST['date'] ?? ''));
            $collageX = max(1, (int) ($_POST['collagex'] ?? 4));
            $collageY = max(1, (int) ($_POST['collagey'] ?? 4));
            $autoapprove = isset($_POST['autoapprove']) ? 1 : 0;
            $numCollage = $collageX * $collageY;

            if ($title === '' || $date === '') {
                $message = 'Title and date are required.';
                $messageClass = 'message-error';
            } elseif ($eventId > 0) {
                $stmt = $mysqli->prepare("
                    UPDATE events
                    SET title = ?, date = ?, collagex = ?, collagey = ?, numcollage = ?, autoapprove = ?
                    WHERE ID = ?
                ");

                if ($stmt) {
                    $stmt->bind_param('ssiiiii', $title, $date, $collageX, $collageY, $numCollage, $autoapprove, $eventId);
                    $ok = $stmt->execute();
                    $stmt->close();

                    if ($ok) {
                        $uploadResult = handle_collage_background_upload($mysqli, $eventId, $_FILES['collageimg'] ?? []);

                        if (!$uploadResult['success']) {
                            $message = 'Event updated, but ' . $uploadResult['message'];
                            $messageClass = $uploadResult['class'];
                        } else {
                            $message = 'Event updated successfully.';
                            $messageClass = 'message-success';
                        }
                    } else {
                        $message = 'Unable to update event.';
                        $messageClass = 'message-error';
                    }
                } else {
                    $message = 'Unable to prepare update statement.';
                    $messageClass = 'message-error';
                }
            } else {
                $stmt = $mysqli->prepare("
                    INSERT INTO events (title, date, collagex, collagey, numcollage, autoapprove)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                if ($stmt) {
                    $stmt->bind_param('ssiiii', $title, $date, $collageX, $collageY, $numCollage, $autoapprove);
                    $ok = $stmt->execute();
                    $newId = (int) $mysqli->insert_id;
                    $stmt->close();

                    if ($ok) {
                        $eventId = $newId;
                        $uploadResult = handle_collage_background_upload($mysqli, $eventId, $_FILES['collageimg'] ?? []);

                        if (!$uploadResult['success']) {
                            $message = 'Event created, but ' . $uploadResult['message'];
                            $messageClass = $uploadResult['class'];
                        } else {
                            $message = 'Event created successfully. New event ID: ' . $eventId . '.';
                            $messageClass = 'message-success';
                        }
                    } else {
                        $message = 'Unable to create event.';
                        $messageClass = 'message-error';
                    }
                } else {
                    $message = 'Unable to prepare insert statement.';
                    $messageClass = 'message-error';
                }
            }
        }

        if ($action === 'delete_event') {
            $eventId = (int) ($_POST['event_id'] ?? 0);

            if ($eventId > 0) {
                $stmt = $mysqli->prepare("DELETE FROM events WHERE ID = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $eventId);
                    $ok = $stmt->execute();
                    $stmt->close();

                    $message = $ok ? 'Event deleted successfully.' : 'Unable to delete event.';
                    $messageClass = $ok ? 'message-success' : 'message-error';
                } else {
                    $message = 'Unable to prepare delete statement.';
                    $messageClass = 'message-error';
                }
            }
        }
    }
}

$editingEvent = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = $mysqli->prepare("SELECT * FROM events WHERE ID = ?");
    if ($stmt) {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $result = $stmt->get_result();
        $editingEvent = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

$events = [];
$result = $mysqli->query("SELECT * FROM events ORDER BY date DESC, ID DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['counts'] = event_photo_counts($mysqli, (int) $row['ID']);
        $events[] = $row;
    }
}

$formEventId = isset($editingEvent['ID']) ? (int) $editingEvent['ID'] : 0;
$formTitle = isset($editingEvent['title']) ? (string) $editingEvent['title'] : '';
$formDate = isset($editingEvent['date']) ? (string) $editingEvent['date'] : date('Y-m-d');
$formCollageX = isset($editingEvent['collagex']) ? (int) $editingEvent['collagex'] : 4;
$formCollageY = isset($editingEvent['collagey']) ? (int) $editingEvent['collagey'] : 4;
$formAutoapprove = isset($editingEvent['autoapprove']) ? (int) $editingEvent['autoapprove'] : 0;
$formCollageImg = isset($editingEvent['collageimg']) ? (string) $editingEvent['collageimg'] : '';
$formImgWidth = isset($editingEvent['imgWidth']) ? (int) $editingEvent['imgWidth'] : 0;
$formImgHeight = isset($editingEvent['imgHeight']) ? (int) $editingEvent['imgHeight'] : 0;

require __DIR__ . '/includes/admin_layout_header.php';
?>

<h1>Events</h1>

<?php if ($message !== ''): ?>
    <div class="message <?= h($messageClass) ?>">
        <?= h($message) ?>
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <h2><?= $formEventId > 0 ? 'Edit Event' : 'New Event' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_event">
            <input type="hidden" name="event_id" value="<?= $formEventId ?>">

            <div style="margin-bottom: 12px;">
                <label for="title">Title</label>
                <input id="title" type="text" name="title" required value="<?= h($formTitle) ?>">
            </div>

            <div style="margin-bottom: 12px;">
                <label for="date">Date</label>
                <input id="date" type="date" name="date" required value="<?= h($formDate) ?>">
            </div>

            <div class="row" style="margin-bottom: 12px;">
                <div style="flex:1; min-width:140px;">
                    <label for="collagex">Collage X</label>
                    <input id="collagex" type="number" min="1" name="collagex" value="<?= $formCollageX ?>">
                </div>

                <div style="flex:1; min-width:140px;">
                    <label for="collagey">Collage Y</label>
                    <input id="collagey" type="number" min="1" name="collagey" value="<?= $formCollageY ?>">
                </div>
            </div>

            <div style="margin-bottom: 12px;">
                <label for="collageimg">Collage Background Image</label>
                <input id="collageimg" type="file" name="collageimg" accept="image/jpeg,image/png,image/gif,image/webp">

                <?php if ($formEventId > 0 && $formCollageImg !== ''): ?>
                    <div class="muted" style="margin-top: 8px;">
                        Current file: <?= h($formCollageImg) ?>
                    </div>

                    <div class="muted" style="margin-top: 4px;">
                        Dimensions: <?= $formImgWidth > 0 ? $formImgWidth : 0 ?> × <?= $formImgHeight > 0 ? $formImgHeight : 0 ?>
                    </div>

                    <div style="margin-top: 10px;">
                        <img
                            src="/photos/<?= $formEventId ?>/<?= h($formCollageImg) ?>"
                            alt="Current collage background"
                            style="max-width: 240px; border: 1px solid var(--border); border-radius: 8px;">
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-bottom: 16px;">
                <label>
                    <input type="checkbox" name="autoapprove" value="1" <?= $formAutoapprove ? 'checked' : '' ?>>
                    Auto-approve uploaded photos
                </label>
            </div>

            <div class="row">
                <button class="btn" type="submit"><?= $formEventId > 0 ? 'Save Changes' : 'Create Event' ?></button>
                <?php if ($formEventId > 0): ?>
                    <a class="btn btn-secondary" href="/admin/events.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Notes</h2>
        <p class="muted">Total tiles are saved as collage X × collage Y.</p>
        <p class="muted">Upload a collage background image here if the event uses a custom collage template.</p>
        <p class="muted">When a collage background is uploaded, the image width and height are stored on the event for crop ratio calculations.</p>
    </div>
</div>

<div class="card">
    <h2>All Events</h2>

    <?php if (!$events): ?>
        <p class="muted">No events found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Collage</th>
                    <th>Photos</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= (int) $event['ID'] ?></td>
                        <td>
                            <strong><?= h((string) $event['title']) ?></strong><br>
                            <span class="muted">Auto-approve: <?= !empty($event['autoapprove']) ? 'Yes' : 'No' ?></span><br>
                            <span class="muted">Background: <?= !empty($event['collageimg']) ? h((string) $event['collageimg']) : 'None' ?></span><br>
                            <span class="muted">Image size: <?= isset($event['imgWidth']) ? (int) $event['imgWidth'] : 0 ?> × <?= isset($event['imgHeight']) ? (int) $event['imgHeight'] : 0 ?></span>
                        </td>
                        <td><?= h((string) $event['date']) ?></td>
                        <td>
                            <span class="pill"><?= (int) $event['collagex'] ?> × <?= (int) $event['collagey'] ?></span>
                            <span class="pill"><?= (int) $event['numcollage'] ?> tiles</span>
                        </td>
                        <td>
                            <span class="pill">Total <?= (int) $event['counts']['totalPhotos'] ?></span>
                            <span class="pill">Approved <?= (int) $event['counts']['approvedPhotos'] ?></span>
                            <span class="pill">Visible <?= (int) $event['counts']['visiblePhotos'] ?></span>
                            <span class="pill">Hidden <?= (int) $event['counts']['hiddenApprovedPhotos'] ?></span>
                        </td>
                        <td>
                            <div class="row">
                                <a class="btn btn-secondary" href="/admin/events.php?edit=<?= (int) $event['ID'] ?>">Edit</a>
                                <a class="btn btn-secondary" href="/admin/photos.php?event=<?= (int) $event['ID'] ?>">Photos</a>
                                <a class="btn btn-secondary" href="/admin/collage.php?event=<?= (int) $event['ID'] ?>">Collage</a>

                                <form method="post" onsubmit="return confirm('Delete this event?');" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_event">
                                    <input type="hidden" name="event_id" value="<?= (int) $event['ID'] ?>">
                                    <button class="btn btn-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin_layout_footer.php'; ?>
