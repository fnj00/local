<?php
include("../includes/db.php");

function esc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function event_photo_counts($mysqli, $eventId) {
    $eventId = (int)$eventId;

    $sql = "
        SELECT
            COUNT(*) AS totalPhotos,
            SUM(CASE WHEN approved = 1 THEN 1 ELSE 0 END) AS approvedPhotos,
            SUM(CASE WHEN approved = 1 AND photonum IS NOT NULL AND photonum > 0 THEN 1 ELSE 0 END) AS visiblePhotos,
            SUM(CASE WHEN approved = 1 AND (photonum IS NULL OR photonum = 0) THEN 1 ELSE 0 END) AS hiddenApprovedPhotos
        FROM photos
        WHERE eventId = $eventId
    ";

    $result = $mysqli->query($sql);
    if (!$result) {
        return array(
            'totalPhotos' => 0,
            'approvedPhotos' => 0,
            'visiblePhotos' => 0,
            'hiddenApprovedPhotos' => 0
        );
    }

    $row = $result->fetch_assoc();
    return array(
        'totalPhotos' => isset($row['totalPhotos']) ? (int)$row['totalPhotos'] : 0,
        'approvedPhotos' => isset($row['approvedPhotos']) ? (int)$row['approvedPhotos'] : 0,
        'visiblePhotos' => isset($row['visiblePhotos']) ? (int)$row['visiblePhotos'] : 0,
        'hiddenApprovedPhotos' => isset($row['hiddenApprovedPhotos']) ? (int)$row['hiddenApprovedPhotos'] : 0
    );
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_event') {
        $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $date = isset($_POST['date']) ? trim($_POST['date']) : '';
        $collageX = isset($_POST['collagex']) ? max(1, (int)$_POST['collagex']) : 1;
        $collageY = isset($_POST['collagey']) ? max(1, (int)$_POST['collagey']) : 1;
        $autoapprove = isset($_POST['autoapprove']) ? 1 : 0;
        $numCollage = $collageX * $collageY;

        if ($title === '' || $date === '') {
            $message = 'Title and date are required.';
            $messageType = 'danger';
        } else {
            $safeTitle = $mysqli->real_escape_string($title);
            $safeDate = $mysqli->real_escape_string($date);

            if ($eventId > 0) {
                $sql = "
                    UPDATE events
                    SET
                        title = '$safeTitle',
                        date = '$safeDate',
                        collagex = '$collageX',
                        collagey = '$collageY',
                        numcollage = '$numCollage',
                        autoapprove = '$autoapprove'
                    WHERE ID = '$eventId'
                ";

                if ($mysqli->query($sql)) {
                    $message = 'Event updated successfully.';
                } else {
                    $message = 'Unable to update event: ' . $mysqli->error;
                    $messageType = 'danger';
                }
            } else {
                $sql = "
                    INSERT INTO events (title, date, collagex, collagey, numcollage, autoapprove)
                    VALUES ('$safeTitle', '$safeDate', '$collageX', '$collageY', '$numCollage', '$autoapprove')
                ";

                if ($mysqli->query($sql)) {
                    $message = 'Event created successfully. New event ID: ' . $mysqli->insert_id . '.';
                } else {
                    $message = 'Unable to create event: ' . $mysqli->error;
                    $messageType = 'danger';
                }
            }
        }
    }

    if ($action === 'delete_event') {
        $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;

        if ($eventId > 0) {
            $sql = "DELETE FROM events WHERE ID = '$eventId'";

            if ($mysqli->query($sql)) {
                $message = 'Event deleted successfully.';
            } else {
                $message = 'Unable to delete event: ' . $mysqli->error;
                $messageType = 'danger';
            }
        }
    }
}

$editingEvent = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $result = $mysqli->query("SELECT * FROM events WHERE ID = '$editId'");
    if ($result && $result->num_rows > 0) {
        $editingEvent = $result->fetch_assoc();
    }
}

$events = array();
$result = $mysqli->query("SELECT * FROM events ORDER BY date DESC, ID DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['counts'] = event_photo_counts($mysqli, $row['ID']);
        $events[] = $row;
    }
}

$formEventId = isset($editingEvent['ID']) ? $editingEvent['ID'] : 0;
$formTitle = isset($editingEvent['title']) ? $editingEvent['title'] : '';
$formDate = isset($editingEvent['date']) ? $editingEvent['date'] : date('Y-m-d');
$formCollageX = isset($editingEvent['collagex']) ? $editingEvent['collagex'] : 4;
$formCollageY = isset($editingEvent['collagey']) ? $editingEvent['collagey'] : 4;
$formAutoapprove = isset($editingEvent['autoapprove']) ? (int)$editingEvent['autoapprove'] : 0;
$formNumCollage = (int)$formCollageX * (int)$formCollageY;

$uploadMessage = isset($_GET['upload_message']) ? $_GET['upload_message'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Event Admin</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body {
            background: #111;
            color: #f5f5f5;
            font-family: Arial, Helvetica, sans-serif;
            padding: 20px;
        }

        .page-wrap {
            max-width: 1700px;
            margin: 0 auto;
        }

        .panel {
            background: #1b1b1b;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 18px rgba(0,0,0,.25);
        }

        .form-control,
        .custom-select {
            background: #222;
            color: #fff;
            border-color: #444;
        }

        .form-control:focus,
        .custom-select:focus {
            background: #222;
            color: #fff;
            border-color: #66afe9;
            box-shadow: none;
        }

        .form-control[readonly] {
            background: #181818;
            color: #ddd;
        }

        .table {
            color: #fff;
        }

        .table thead th {
            border-bottom-color: #444;
            border-top: none;
            background: #202020;
        }

        .table td,
        .table th {
            border-top-color: #333;
            vertical-align: middle;
        }

        .badge-darkish {
            background: #333;
            color: #fff;
            padding: .45rem .65rem;
            border-radius: 999px;
            display: inline-block;
            margin-right: 6px;
            margin-bottom: 6px;
        }

        .thumb {
            max-width: 220px;
            max-height: 140px;
            object-fit: contain;
            background: #000;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 4px;
        }

        .action-group .btn {
            margin-right: 6px;
            margin-bottom: 6px;
        }

        .small-note {
            font-size: .95rem;
            color: #bbb;
        }

        .stats-wrap div {
            margin-bottom: 4px;
        }

        .header-actions .btn {
            margin-left: 8px;
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap header-actions">
            <div>
                <h1 class="mb-1">Event Admin</h1>
                <div class="small-note">
                    Total tiles are calculated automatically from X × Y and saved into <code>numcollage</code>.
                </div>
            </div>
            <div>
                <a class="btn btn-outline-light" href="index.php">New Event Form</a>
            </div>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo esc($messageType); ?>">
            <?php echo esc($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($uploadMessage !== ''): ?>
        <div class="alert alert-info">
            <?php echo esc($uploadMessage); ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <h2 class="mb-3"><?php echo $formEventId > 0 ? 'Edit Event' : 'Create New Event'; ?></h2>

        <form method="post">
            <input type="hidden" name="action" value="save_event">
            <input type="hidden" name="event_id" value="<?php echo esc($formEventId); ?>">

            <div class="form-row">
                <div class="form-group col-md-5">
                    <label for="title">Event Title</label>
                    <input id="title" name="title" type="text" class="form-control" required value="<?php echo esc($formTitle); ?>">
                </div>

                <div class="form-group col-md-3">
                    <label for="date">Event Date</label>
                    <input id="date" name="date" type="date" class="form-control" required value="<?php echo esc($formDate); ?>">
                </div>

                <div class="form-group col-md-2">
                    <label for="collagex">Tiles Across (X)</label>
                    <input id="collagex" name="collagex" type="number" min="1" class="form-control" required value="<?php echo esc($formCollageX); ?>">
                </div>

                <div class="form-group col-md-2">
                    <label for="collagey">Tiles Down (Y)</label>
                    <input id="collagey" name="collagey" type="number" min="1" class="form-control" required value="<?php echo esc($formCollageY); ?>">
                </div>
            </div>

            <div class="form-row align-items-center">
                <div class="form-group col-md-3">
                    <label>Total Tiles</label>
                    <input id="numcollage_display" type="text" class="form-control" readonly value="<?php echo esc($formNumCollage); ?>">
                </div>

                <div class="form-group col-md-3">
                    <div class="custom-control custom-checkbox mt-4">
                        <input id="autoapprove" name="autoapprove" type="checkbox" class="custom-control-input" <?php echo $formAutoapprove ? 'checked' : ''; ?>>
                        <label class="custom-control-label" for="autoapprove">Auto-approve uploads</label>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap">
                <button type="submit" class="btn btn-success mr-2 mb-2">
                    <?php echo $formEventId > 0 ? 'Save Event' : 'Create Event'; ?>
                </button>

                <?php if ($formEventId > 0): ?>
                    <a class="btn btn-primary mr-2 mb-2" href="../collagefix/admin.php?id=<?php echo esc($formEventId); ?>">Open Mosaic Admin</a>
                    <a class="btn btn-outline-light mr-2 mb-2" href="../collagefix/index.php?id=<?php echo esc($formEventId); ?>" target="_blank" rel="noopener">Open Live Mosaic</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($formEventId > 0): ?>
        <div class="panel">
            <h2 class="mb-3">Collage Image</h2>

            <form method="post" action="upload.php" enctype="multipart/form-data">
                <input type="hidden" name="eventId" value="<?php echo esc($formEventId); ?>">

                <div class="form-row align-items-end">
                    <div class="form-group col-md-6">
                        <label for="fileToUpload">Upload / Replace Collage Image</label>
                        <input id="fileToUpload" type="file" name="fileToUpload" class="form-control-file" required>
                    </div>

                    <div class="form-group col-md-3">
                        <button type="submit" class="btn btn-info">Upload Collage Image</button>
                    </div>
                </div>
            </form>

            <?php
            if (!empty($editingEvent['collageimg'])):
                $collagePath = '../photos/' . $editingEvent['collageimg'];
            ?>
                <div class="mt-3">
                    <div class="mb-2"><strong>Current Image</strong></div>
                    <img class="thumb" src="<?php echo esc($collagePath); ?>" alt="Current collage image">
                    <div class="small-note mt-2">
                        <?php echo esc(isset($editingEvent['imgWidth']) ? $editingEvent['imgWidth'] : ''); ?> × <?php echo esc(isset($editingEvent['imgHeight']) ? $editingEvent['imgHeight'] : ''); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="small-note">No collage image uploaded for this event yet.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <h2 class="mb-3">Existing Events</h2>

        <div class="table-responsive">
            <table class="table table-dark table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Grid</th>
                        <th>Total Tiles</th>
                        <th>Auto-Approve</th>
                        <th>Collage Image</th>
                        <th>Photo Counts</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($events) === 0): ?>
                    <tr>
                        <td colspan="9">No events found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <?php
                            $eventId = (int)$event['ID'];
                            $counts = $event['counts'];
                            $tileCount = ((int)$event['collagex']) * ((int)$event['collagey']);
                        ?>
                        <tr>
                            <td><?php echo esc($eventId); ?></td>
                            <td><strong><?php echo esc($event['title']); ?></strong></td>
                            <td><?php echo esc($event['date']); ?></td>
                            <td><?php echo esc($event['collagex']); ?> × <?php echo esc($event['collagey']); ?></td>
                            <td><?php echo esc($tileCount); ?></td>
                            <td><?php echo !empty($event['autoapprove']) ? 'Yes' : 'No'; ?></td>
                            <td>
                                <?php if (!empty($event['collageimg'])): ?>
                                    <span class="badge-darkish">Uploaded</span>
                                    <div class="small-note">
                                        <?php echo esc(isset($event['imgWidth']) ? $event['imgWidth'] : ''); ?> × <?php echo esc(isset($event['imgHeight']) ? $event['imgHeight'] : ''); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge-darkish">Missing</span>
                                <?php endif; ?>
                            </td>
                            <td class="stats-wrap">
                                <div><span class="badge-darkish">Total <?php echo esc($counts['totalPhotos']); ?></span></div>
                                <div><span class="badge-darkish">Approved <?php echo esc($counts['approvedPhotos']); ?></span></div>
                                <div><span class="badge-darkish">Visible <?php echo esc($counts['visiblePhotos']); ?></span></div>
                                <div><span class="badge-darkish">Hidden <?php echo esc($counts['hiddenApprovedPhotos']); ?></span></div>
                            </td>
                            <td class="action-group">
                                <a class="btn btn-sm btn-warning" href="index.php?edit=<?php echo esc($eventId); ?>">Edit</a>
                                <a class="btn btn-sm btn-primary" href="../collagefix/admin.php?id=<?php echo esc($eventId); ?>">Mosaic Admin</a>
                                <a class="btn btn-sm btn-outline-light" href="../collagefix/index.php?id=<?php echo esc($eventId); ?>" target="_blank" rel="noopener">Live View</a>

                                <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete this event? This does not automatically delete event photos.');">
                                    <input type="hidden" name="action" value="delete_event">
                                    <input type="hidden" name="event_id" value="<?php echo esc($eventId); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function updateTileCount() {
        var x = parseInt(document.getElementById('collagex').value || '0', 10);
        var y = parseInt(document.getElementById('collagey').value || '0', 10);
        var total = Math.max(1, x) * Math.max(1, y);
        document.getElementById('numcollage_display').value = total;
    }

    document.getElementById('collagex').addEventListener('input', updateTileCount);
    document.getElementById('collagey').addEventListener('input', updateTileCount);
    updateTileCount();
</script>
</body>
</html>
