<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_admin();

$pageTitle = 'Bulk Upload Photos';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$events = [];
$result = $mysqli->query("SELECT ID, title, date, collagex, collagey, numcollage, collageimg FROM events ORDER BY date DESC, ID DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

$defaultEventId = isset($_GET['event']) && is_numeric($_GET['event']) ? (int) $_GET['event'] : 0;

require __DIR__ . '/includes/admin_layout_header.php';
?>

<h1>Bulk Upload Photos</h1>

<div class="card">
    <p class="muted">Upload multiple photos, crop each in a lightbox editor, then upload them all to the selected event.</p>
</div>

<div class="card">
    <div style="margin-bottom:16px;">
        <label for="bulkEventSelect">Event</label>
        <select id="bulkEventSelect">
            <option value="">Select an event</option>
            <?php foreach ($events as $event): ?>
                <option
                    value="<?= (int) $event['ID'] ?>"
                    data-collagex="<?= (int) $event['collagex'] ?>"
                    data-collagey="<?= (int) $event['collagey'] ?>"
                    data-numcollage="<?= (int) $event['numcollage'] ?>"
                    data-collageimg="<?= h((string) ($event['collageimg'] ?? '')) ?>"
                    <?= $defaultEventId === (int) $event['ID'] ? 'selected' : '' ?>
                >
                    <?= h((string) $event['title']) ?> — <?= h((string) $event['date']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin-bottom:16px;">
        <label for="bulkPhotoInput">Choose Photos</label>
        <input
            id="bulkPhotoInput"
            type="file"
            accept="image/*"
            multiple
            style="display:block;width:100%;margin-top:8px;">
    </div>

    <div style="margin-bottom:16px;">
        <label>
            <input type="checkbox" id="bulkAssignOpenTiles" checked>
            Auto-assign uploaded photos to open collage tiles when possible
        </label>
    </div>

    <div id="bulkUploadNotice" class="message" style="display:none;"></div>

    <div class="card" id="bulkSummaryCard" style="display:none;">
        <div class="row">
            <span class="pill" id="bulkCountPill">0 photos</span>
            <span class="pill" id="bulkCroppedPill">0 cropped</span>
            <span class="pill" id="bulkRatioPill">Ratio 1:1</span>
        </div>
    </div>

    <div class="card" id="bulkQueueCard" style="display:none;">
        <h2>Selected Photos</h2>
        <div id="bulkQueueList" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;"></div>
    </div>

    <div class="card" id="bulkActionsCard" style="display:none;">
        <div class="row">
            <button class="btn" type="button" id="bulkUploadAllBtn">Upload All Cropped Photos</button>
            <button class="btn btn-secondary" type="button" id="bulkOpenFirstUncroppedBtn">Open First Uncropped</button>
            <button class="btn btn-secondary" type="button" id="bulkClearAllBtn">Clear All</button>
        </div>
    </div>
</div>

<div id="bulkCropModal" style="display:none;position:fixed;inset:0;background:rgba(17,24,39,.82);z-index:9999;padding:24px;overflow:auto;">
    <div style="max-width:1200px;margin:0 auto;background:#fff;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <div id="bulkModalTitle" style="font-size:22px;font-weight:700;">Crop Photo</div>
                <div id="bulkModalSubtitle" class="muted" style="margin-top:4px;"></div>
            </div>
            <button class="btn btn-secondary" type="button" id="bulkCloseModalBtn">Close</button>
        </div>

        <div style="padding:18px;">
            <div class="grid grid-2">
                <div class="card" style="margin-bottom:0;">
                    <h2 style="margin-top:0;">Crop Editor</h2>
                    <div style="max-width:100%;background:#fff;border:1px solid var(--border);border-radius:12px;padding:10px;">
                        <img id="bulkCropperTarget" alt="Crop target" style="display:block;max-width:100%;">
                    </div>

                    <div class="row" style="margin-top:14px;">
                        <button class="btn btn-secondary" type="button" id="bulkPrevBtn">Previous</button>
                        <button class="btn btn-secondary" type="button" id="bulkNextBtn">Next</button>
                        <button class="btn btn-secondary" type="button" id="bulkResetCropBtn">Reset Crop</button>
                        <button class="btn" type="button" id="bulkRefreshPreviewBtn">Refresh Preview</button>
                    </div>
                </div>

                <div class="card" style="margin-bottom:0;">
                    <h2 style="margin-top:0;">Preview</h2>
                    <div id="bulkRatioText" class="muted" style="margin-bottom:10px;"></div>
                    <img id="bulkPreviewImage" alt="Cropped preview" style="display:block;max-width:100%;border:1px solid var(--border);border-radius:12px;background:#fff;">
                    <div class="row" style="margin-top:14px;">
                        <button class="btn" type="button" id="bulkSaveCropBtn">Save Crop</button>
                        <button class="btn btn-secondary" type="button" id="bulkSaveAndNextBtn">Save + Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>

<script>
(function () {
    var eventSelect = document.getElementById('bulkEventSelect');
    var fileInput = document.getElementById('bulkPhotoInput');
    var assignOpenTiles = document.getElementById('bulkAssignOpenTiles');
    var notice = document.getElementById('bulkUploadNotice');

    var summaryCard = document.getElementById('bulkSummaryCard');
    var queueCard = document.getElementById('bulkQueueCard');
    var actionsCard = document.getElementById('bulkActionsCard');

    var countPill = document.getElementById('bulkCountPill');
    var croppedPill = document.getElementById('bulkCroppedPill');
    var ratioPill = document.getElementById('bulkRatioPill');

    var queueList = document.getElementById('bulkQueueList');

    var modal = document.getElementById('bulkCropModal');
    var closeModalBtn = document.getElementById('bulkCloseModalBtn');
    var modalTitle = document.getElementById('bulkModalTitle');
    var modalSubtitle = document.getElementById('bulkModalSubtitle');

    var cropperTarget = document.getElementById('bulkCropperTarget');
    var previewImage = document.getElementById('bulkPreviewImage');
    var ratioText = document.getElementById('bulkRatioText');

    var prevBtn = document.getElementById('bulkPrevBtn');
    var nextBtn = document.getElementById('bulkNextBtn');
    var resetCropBtn = document.getElementById('bulkResetCropBtn');
    var refreshPreviewBtn = document.getElementById('bulkRefreshPreviewBtn');
    var saveCropBtn = document.getElementById('bulkSaveCropBtn');
    var saveAndNextBtn = document.getElementById('bulkSaveAndNextBtn');

    var uploadAllBtn = document.getElementById('bulkUploadAllBtn');
    var openFirstUncroppedBtn = document.getElementById('bulkOpenFirstUncroppedBtn');
    var clearAllBtn = document.getElementById('bulkClearAllBtn');

    var cropper = null;
    var files = [];
    var currentIndex = -1;
    var cropAspectRatio = 1;
    var cropRatioLabel = '1:1';

    function showNotice(text, type) {
        notice.style.display = 'block';
        notice.className = 'message ' + (type || 'message-success');
        notice.textContent = text;
    }

    function hideNotice() {
        notice.style.display = 'none';
        notice.textContent = '';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getSelectedEventMeta() {
        var option = eventSelect.options[eventSelect.selectedIndex];
        if (!option || !option.value) return null;

        return {
            eventId: option.value,
            collageX: parseFloat(option.getAttribute('data-collagex') || '1'),
            collageY: parseFloat(option.getAttribute('data-collagey') || '1'),
            numCollage: parseFloat(option.getAttribute('data-numcollage') || '1'),
            collageImg: option.getAttribute('data-collageimg') || ''
        };
    }

    function destroyCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    function showModal() {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function hideModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        destroyCropper();
    }

    function updateSummary() {
        var croppedCount = files.filter(function (f) { return !!f.croppedDataUrl; }).length;

        countPill.textContent = files.length + ' photos';
        croppedPill.textContent = croppedCount + ' cropped';
        ratioPill.textContent = 'Ratio ' + cropRatioLabel;

        var show = files.length > 0;
        summaryCard.style.display = show ? 'block' : 'none';
        queueCard.style.display = show ? 'block' : 'none';
        actionsCard.style.display = show ? 'block' : 'none';
    }

    function loadCropRatioFromEvent(callback) {
        var meta = getSelectedEventMeta();
        if (!meta) {
            cropAspectRatio = 1;
            cropRatioLabel = '1:1';
            ratioText.textContent = 'Crop ratio: ' + cropRatioLabel;
            updateSummary();
            if (callback) callback();
            return;
        }

        fetch('/data/eventDetails.php?id=' + encodeURIComponent(meta.eventId))
            .then(function (r) { return r.json(); })
            .then(function (obj) {
                var collageX = parseFloat(obj.collageX || obj.collagex || meta.collageX || 1);
                var collageY = parseFloat(obj.collageY || obj.collagey || meta.collageY || 1);
                var imgWidth = parseFloat(obj.imgWidth || obj.imgwidth || 0);
                var imgHeight = parseFloat(obj.imgHeight || obj.imgheight || 0);
                var gap = 2;

                if (imgWidth > 0 && imgHeight > 0) {
                    var tileWidth = (imgWidth / collageX) - gap;
                    var tileHeight = (imgHeight / collageY) - gap;
                    cropAspectRatio = tileWidth / tileHeight;
                    cropRatioLabel = Math.round(tileWidth) + ' × ' + Math.round(tileHeight);
                } else {
                    cropAspectRatio = 1;
                    cropRatioLabel = '1:1 fallback';
                }

                if (!cropAspectRatio || !isFinite(cropAspectRatio) || cropAspectRatio <= 0) {
                    cropAspectRatio = 1;
                    cropRatioLabel = '1:1 fallback';
                }

                ratioText.textContent = 'Crop ratio: ' + cropRatioLabel;
                updateSummary();
                if (callback) callback();
            })
            .catch(function () {
                cropAspectRatio = 1;
                cropRatioLabel = '1:1 fallback';
                ratioText.textContent = 'Crop ratio: ' + cropRatioLabel;
                updateSummary();
                if (callback) callback();
            });
    }

    function renderQueue() {
        queueList.innerHTML = '';

        files.forEach(function (item, index) {
            var cropped = !!item.croppedDataUrl;
            var wrapper = document.createElement('div');
            wrapper.style.border = '1px solid var(--border)';
            wrapper.style.borderRadius = '12px';
            wrapper.style.padding = '10px';
            wrapper.style.background = '#fff';

            wrapper.innerHTML = ''
                + '<div style="font-weight:700;margin-bottom:8px;">' + escapeHtml(item.file.name) + '</div>'
                + '<div style="margin-bottom:8px;color:' + (cropped ? '#256029' : '#8a5200') + ';">'
                + (cropped ? 'Crop saved' : 'Needs crop')
                + '</div>'
                + '<img src="' + (item.croppedDataUrl || item.previewDataUrl) + '" style="width:100%;height:160px;object-fit:contain;border:1px solid var(--border);border-radius:8px;background:#fff;">'
                + '<div class="row" style="margin-top:10px;">'
                + '  <button type="button" class="btn btn-secondary" data-edit="' + index + '">Open Editor</button>'
                + '</div>';

            queueList.appendChild(wrapper);
        });

        Array.prototype.forEach.call(queueList.querySelectorAll('button[data-edit]'), function (btn) {
            btn.addEventListener('click', function () {
                openEditor(parseInt(btn.getAttribute('data-edit'), 10));
            });
        });

        updateSummary();
    }

    function refreshPreview() {
        if (!cropper || currentIndex < 0 || !files[currentIndex]) return;

        var canvas = cropper.getCroppedCanvas({
            maxWidth: 1200,
            maxHeight: 1200,
            fillColor: '#ffffff',
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });

        if (!canvas) return;
        previewImage.src = canvas.toDataURL('image/jpeg', 0.9);
    }

    function saveCurrentCrop() {
        if (!cropper || currentIndex < 0 || !files[currentIndex]) return false;

        var canvas = cropper.getCroppedCanvas({
            maxWidth: 1600,
            maxHeight: 1600,
            fillColor: '#ffffff',
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });

        if (!canvas) {
            showNotice('Unable to save crop for this photo.', 'message-error');
            return false;
        }

        files[currentIndex].croppedDataUrl = canvas.toDataURL('image/jpeg', 0.9);
        previewImage.src = files[currentIndex].croppedDataUrl;
        renderQueue();
        return true;
    }

    function updateModalHeader() {
        if (currentIndex < 0 || !files[currentIndex]) return;
        modalTitle.textContent = 'Crop Photo';
        modalSubtitle.textContent = files[currentIndex].file.name + ' — ' + (currentIndex + 1) + ' of ' + files.length;
    }

    function openEditor(index) {
        if (index < 0 || index >= files.length) return;

        currentIndex = index;
        updateModalHeader();
        showModal();
        destroyCropper();

        cropperTarget.src = files[index].previewDataUrl;
        previewImage.src = files[index].croppedDataUrl || files[index].previewDataUrl;

        setTimeout(function () {
            cropper = new Cropper(cropperTarget, {
                aspectRatio: cropAspectRatio || 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                responsive: true,
                background: false,
                guides: true,
                movable: true,
                zoomable: true,
                rotatable: false,
                scalable: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                ready: function () {
                    refreshPreview();
                },
                cropend: function () {
                    refreshPreview();
                },
                zoom: function () {
                    refreshPreview();
                }
            });
        }, 50);
    }

    function openFirstUncropped() {
        var index = files.findIndex(function (f) { return !f.croppedDataUrl; });
        if (index === -1) {
            if (files.length > 0) {
                openEditor(0);
            }
            return;
        }
        openEditor(index);
    }

    function readFiles(fileList) {
        files = [];
        currentIndex = -1;
        destroyCropper();
        hideNotice();

        if (!fileList || !fileList.length) {
            summaryCard.style.display = 'none';
            queueCard.style.display = 'none';
            actionsCard.style.display = 'none';
            return;
        }

        var pending = fileList.length;

        Array.prototype.forEach.call(fileList, function (file) {
            if (!/^image\//i.test(file.type)) {
                pending--;
                if (pending === 0) finalizeLoad();
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                files.push({
                    file: file,
                    previewDataUrl: e.target.result,
                    croppedDataUrl: ''
                });

                pending--;
                if (pending === 0) finalizeLoad();
            };
            reader.onerror = function () {
                pending--;
                if (pending === 0) finalizeLoad();
            };
            reader.readAsDataURL(file);
        });
    }

    function finalizeLoad() {
        if (!files.length) {
            showNotice('No valid image files were selected.', 'message-error');
            summaryCard.style.display = 'none';
            queueCard.style.display = 'none';
            actionsCard.style.display = 'none';
            return;
        }

        loadCropRatioFromEvent(function () {
            renderQueue();
            openFirstUncropped();
        });
    }

    function uploadOne(item, eventId, assignTiles) {
        var payload = {
            eventId: eventId,
            image: item.croppedDataUrl || item.previewDataUrl,
            assignOpenTiles: assignTiles ? 1 : 0
        };

        return fetch('/data/uploadPhonePhoto.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.json();
        });
    }

    function uploadAll() {
        var meta = getSelectedEventMeta();
        if (!meta) {
            showNotice('Select an event first.', 'message-error');
            return;
        }

        if (!files.length) {
            showNotice('No photos are loaded.', 'message-error');
            return;
        }

        var missing = files.filter(function (f) { return !f.croppedDataUrl; });
        if (missing.length) {
            showNotice('Please crop and save every photo before uploading.', 'message-warning');
            openFirstUncropped();
            return;
        }

        uploadAllBtn.disabled = true;
        openFirstUncroppedBtn.disabled = true;

        var completed = 0;
        var failures = 0;

        function next() {
            if (completed >= files.length) {
                uploadAllBtn.disabled = false;
                openFirstUncroppedBtn.disabled = false;

                if (failures === 0) {
                    showNotice('All photos uploaded successfully.', 'message-success');
                } else {
                    showNotice((files.length - failures) + ' uploaded, ' + failures + ' failed.', 'message-warning');
                }
                return;
            }

            uploadOne(files[completed], meta.eventId, assignOpenTiles.checked)
                .then(function (resp) {
                    if (!resp || !resp.success) {
                        failures++;
                    }
                })
                .catch(function () {
                    failures++;
                })
                .finally(function () {
                    completed++;
                    showNotice('Uploading ' + completed + ' of ' + files.length + '...', 'message-warning');
                    next();
                });
        }

        next();
    }

    fileInput.addEventListener('change', function () {
        if (!eventSelect.value) {
            showNotice('Select an event before choosing photos.', 'message-error');
            fileInput.value = '';
            return;
        }
        readFiles(fileInput.files);
    });

    closeModalBtn.addEventListener('click', hideModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            hideModal();
        }
    });

    prevBtn.addEventListener('click', function () {
        if (currentIndex > 0) {
            openEditor(currentIndex - 1);
        }
    });

    nextBtn.addEventListener('click', function () {
        if (currentIndex < files.length - 1) {
            openEditor(currentIndex + 1);
        }
    });

    resetCropBtn.addEventListener('click', function () {
        if (cropper) {
            cropper.reset();
            refreshPreview();
        }
    });

    refreshPreviewBtn.addEventListener('click', refreshPreview);

    saveCropBtn.addEventListener('click', function () {
        if (saveCurrentCrop()) {
            showNotice('Crop saved for ' + files[currentIndex].file.name + '.', 'message-success');
        }
    });

    saveAndNextBtn.addEventListener('click', function () {
        if (!saveCurrentCrop()) return;

        showNotice('Crop saved for ' + files[currentIndex].file.name + '.', 'message-success');

        if (currentIndex < files.length - 1) {
            openEditor(currentIndex + 1);
        } else {
            hideModal();
        }
    });

    uploadAllBtn.addEventListener('click', uploadAll);
    openFirstUncroppedBtn.addEventListener('click', openFirstUncropped);

    clearAllBtn.addEventListener('click', function () {
        destroyCropper();
        files = [];
        currentIndex = -1;
        queueList.innerHTML = '';
        previewImage.src = '';
        cropperTarget.src = '';
        fileInput.value = '';
        hideModal();
        hideNotice();
        updateSummary();
    });

    if (eventSelect.value) {
        loadCropRatioFromEvent();
    }
})();
</script>

<?php require __DIR__ . '/includes/admin_layout_footer.php'; ?>
