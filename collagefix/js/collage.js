;(function ($, window) {
    'use strict';

    let id = '0';
    let collageImg = '';
    let collageX = 1;
    let collageY = 1;
    let imgWidth = 0;
    let imgHeight = 0;
    let newImgWidth = 0;
    let newImgHeight = 0;
    let opacity = 0.40;
    let eventId = null;
    const gap = 2;

    function getEventIdFromUrl() {
        const origUrl = new URL(window.location.href);
        const params = origUrl.searchParams;
        const value = params.get('id');
        return value !== null ? value : '0';
    }

    function formatDateParts(dateString) {
        const parts = dateString.split('-').map(v => parseInt(v, 10));
        const year = parts[0];
        const month = parts[1];
        const day = parts[2];
        const monthname = 'January,February,March,April,May,June,July,August,September,October,November,December'.split(',')[month - 1];

        function nth(d) {
            if (d > 3 && d < 21) return 'th';
            switch (d % 10) {
                case 1: return 'st';
                case 2: return 'nd';
                case 3: return 'rd';
                default: return 'th';
            }
        }

        return day + nth(day) + ' ' + monthname + ' ' + year;
    }

    function getPhotoThumb(photo) {
        if (!photo) return '';
        if (typeof photo === 'string') return photo;
        return photo.thumbUrl || photo.image || photo.fullUrl || photo.url || '';
    }

    function getPhotoFull(photo) {
        if (!photo) return '';
        if (typeof photo === 'string') {
            return photo
                .replace(/_thumb(?=\.[^.]+$)/i, '')
                .replace(/\.(webp|png)$/i, '.jpg');
        }
        return photo.fullUrl || photo.image || photo.url || '';
    }

    function buildTileImage(photo) {
        const thumbSrc = getPhotoThumb(photo);
        const fullSrc = getPhotoFull(photo);
        if (!thumbSrc || !fullSrc) return '';

        return '<img'
            + ' class="pop"'
            + ' data-photo-url="' + fullSrc + '"'
            + ' data-thumb-url="' + thumbSrc + '"'
            + ' src="' + thumbSrc + '"'
            + ' alt="Event photo"'
            + ' style="width:100%;height:100%;object-fit:cover;display:block;opacity:' + opacity + ';">';
    }

    function buildEmptyTile(tileId) {
        return '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#ffffff;font-weight:700;font-size:20px;background:transparent;">'
            + tileId
            + '</div>';
    }

    function applyOpacityToVisibleTiles() {
        $('#collage img.pop').css('opacity', opacity);
    }

    function fetchSettings() {
        return fetch('/data/collageSettings.php?id=' + encodeURIComponent(id))
            .then(res => {
                if (!res.ok) {
                    throw new Error('Settings request failed: ' + res.status);
                }
                return res.json();
            })
            .then(settings => {
                if (typeof settings.opacity !== 'undefined') {
                    const parsed = parseFloat(settings.opacity);
                    if (!Number.isNaN(parsed)) {
                        opacity = parsed;
                        applyOpacityToVisibleTiles();
                    }
                }
            })
            .catch(err => {
                console.warn('Unable to load collage settings', err);
            });
    }

    function sizeCollage() {
        const collage = document.getElementById('collage');
        const topEl = document.getElementById('top');
        const headerHeight = topEl ? topEl.offsetHeight : 0;
        const maxWidth = Math.max(100, window.innerWidth - 8);
        const maxHeight = Math.max(100, window.innerHeight - headerHeight - 8);
        const scale = Math.min(maxWidth / imgWidth, maxHeight / imgHeight);

        newImgWidth = Math.floor(imgWidth * scale);
        newImgHeight = Math.floor(imgHeight * scale);

        collage.style.position = 'relative';
        collage.style.overflow = 'hidden';
        collage.style.width = newImgWidth + 'px';
        collage.style.height = newImgHeight + 'px';
    }

    function getTileMetrics() {
        const width = newImgWidth;
        const height = newImgHeight;
        const tileWidth = (width - ((collageX - 1) * gap)) / collageX;
        const tileHeight = (height - ((collageY - 1) * gap)) / collageY;
        return { width, height, tileWidth, tileHeight };
    }

    function updateTileLayout() {
        const $container = $('#collage');
        const $img = $container.find('#mainimg');
        const $tiles = $container.find('.tile');
        if ($tiles.length === 0) return;

        const metrics = getTileMetrics();

        $tiles.each(function () {
            const tileId = parseInt(this.id, 10);
            const index = tileId - 1;
            const col = index % collageX;
            const row = Math.floor(index / collageX);

            const rawLeft = col * (metrics.tileWidth + gap);
            const rawTop = row * (metrics.tileHeight + gap);
            const left = Math.round(rawLeft);
            const top = Math.round(rawTop);

            let width;
            let height;

            if (col === collageX - 1) {
                width = metrics.width - left;
            } else {
                const nextLeft = Math.round((col + 1) * (metrics.tileWidth + gap));
                width = nextLeft - left - gap;
            }

            if (row === collageY - 1) {
                height = metrics.height - top;
            } else {
                const nextTop = Math.round((row + 1) * (metrics.tileHeight + gap));
                height = nextTop - top - gap;
            }

            $(this).css({
                position: 'absolute',
                left: left + 'px',
                top: top + 'px',
                width: width + 'px',
                height: height + 'px',
                margin: '0',
                padding: '0',
                overflow: 'hidden',
                backgroundImage: 'url(' + $img.attr('src') + ')',
                backgroundRepeat: 'no-repeat',
                backgroundSize: metrics.width + 'px ' + metrics.height + 'px',
                backgroundPosition: (-left) + 'px ' + (-top) + 'px'
            });
        });

        applyOpacityToVisibleTiles();
    }

    function rebuildTiles(photosByTile) {
        const $container = $('#collage');
        const $img = $container.find('#mainimg');
        const nTiles = collageX * collageY;

        $container.find('.tile').remove();

        const wraps = [];
        for (let i = 1; i <= nTiles; i++) {
            wraps.push('<div class="tile" id="' + i + '"></div>');
        }

        const $wraps = $(wraps.join(''));
        $img.hide().after($wraps);

        updateTileLayout();

        $wraps.each(function () {
            const tileId = parseInt(this.id, 10);
            const photo = photosByTile[tileId];

            if (photo) {
                $(this).removeClass('tile-empty').html(buildTileImage(photo));
            } else {
                $(this).addClass('tile-empty').html(buildEmptyTile(tileId));
            }
        });
    }

    function refreshExistingTiles(photosByTile) {
        $('#collage .tile').each(function () {
            const tileId = parseInt(this.id, 10);
            const $existing = $(this).find('img.pop');
            const photo = photosByTile[tileId];

            if (photo) {
                const thumbSrc = getPhotoThumb(photo);
                const fullSrc = getPhotoFull(photo);
                const currentFullSrc = $existing.attr('data-photo-url');

                if (!$existing.length || currentFullSrc !== fullSrc) {
                    $(this).removeClass('tile-empty').html(buildTileImage(photo));
                } else {
                    $(this).removeClass('tile-empty');
                    $existing
                        .attr('data-photo-url', fullSrc)
                        .attr('data-thumb-url', thumbSrc)
                        .attr('src', thumbSrc)
                        .css('opacity', opacity);
                }
            } else {
                $(this).addClass('tile-empty').html(buildEmptyTile(tileId));
            }
        });
    }

    $.fn.extend({
        getStyle: function (prop) {
            const elem = this[0];
            const actuallySetStyles = {};
            for (let i = 0; i < elem.style.length; i++) {
                const key = elem.style[i];
                if (prop === key) return elem.style[key];
                actuallySetStyles[key] = elem.style[key];
            }
            if (!prop) return actuallySetStyles;
        },
        quickfitText: function (options) {
            options = options || {};
            return this.each(function () {
                const $elem = jQuery(this);
                const elem = $elem[0];
                const maxHeight = options.maxHeight || parseInt($elem.attr('maxheight'), 10) || parseInt($elem.css('min-height'), 10) || 50;
                const maxFontSize = options.minFontSize || parseInt($elem.attr('maxfont'), 10) || 300;
                const minFontSize = options.maxFontSize || parseInt($elem.attr('minfont'), 10) || 7;
                let fontSize = maxFontSize;
                const style = $elem.getStyle();

                style['line-height'] = elem.style.lineHeight = 'normal';
                elem.style.transition = 'none';
                elem.style.display = 'inline';
                elem.style.minHeight = '0';
                elem.style.fontSize = fontSize + 'px';

                while (elem.getBoundingClientRect().height > maxHeight && fontSize > minFontSize) {
                    fontSize--;
                    elem.style.fontSize = fontSize + 'px';
                }

                elem.style.transition = style.transition || '';
                elem.style.display = style.display || '';
                elem.style.minHeight = style['min-height'] || '';
            });
        }
    });

    function openPhotoModal(photoUrl) {
        const parts = photoUrl.split('/');
        const eventNum = parts.length >= 4 ? parts[2] : '';
        const fileName = parts.length >= 4 ? parts[3] : '';
        const jpgFileName = fileName.replace(/_thumb(?=\.[^.]+$)/i, '').replace(/\.[^.]+$/, '.jpg');
        const fullPhotoUrl = '/photos/' + eventNum + '/' + jpgFileName;

        $('.imagepreview').attr('src', fullPhotoUrl);

        const wifiQrEl = document.getElementById('wifiqrcode');
        const downloadQrEl = document.getElementById('downloadqrcode');

        if (wifiQrEl && window.QRCode) {
            wifiQrEl.innerHTML = '';
            new QRCode(wifiQrEl, {
                text: 'WIFI:T:nopass;S:joedeejay-guest;;',
                width: 220,
                height: 220
            });
        }

        if (downloadQrEl && window.QRCode) {
            downloadQrEl.innerHTML = '';
            new QRCode(downloadQrEl, {
                text: 'http://local.joedeejay.com/download.php?num=' + eventNum + '&file=' + jpgFileName,
                width: 220,
                height: 220
            });
        }

        $('#imagemodal').modal('show');
    }

    function fetchEvent() {
        return fetch('/data/boothnext.php?eventId=' + encodeURIComponent(id))
            .then(response => response.json())
            .then(obj => {
                collageImg = '../photos/' + obj.id + '/' + obj.collageImg;
                collageX = parseInt(obj.collageX, 10);
                collageY = parseInt(obj.collageY, 10);
                imgWidth = parseInt(obj.imgWidth, 10);
                imgHeight = parseInt(obj.imgHeight, 10);
                eventId = obj.id;
                window.eventId = obj.id;

                document.getElementById('eventName').innerHTML = obj.title + ' - ' + formatDateParts(obj.date);

                const mainImg = document.getElementById('mainimg');
                mainImg.src = collageImg;

                sizeCollage();
            });
    }

    $.fn.splitInTiles = function (photosByTile) {
        return this.each(function () {
            const hasTiles = $(this).find('.tile').length > 0;
            if (!hasTiles) {
                rebuildTiles(photosByTile);
            } else {
                updateTileLayout();
                refreshExistingTiles(photosByTile);
            }
        });
    };

    function dofetch() {
        if (!window.eventId) return;

        Promise.all([
            fetchSettings(),
            fetch('/data/collagePhotos.php?id=' + encodeURIComponent(window.eventId)).then(res => res.json())
        ])
        .then(([, list]) => {
            const mapped = {};

            if (Array.isArray(list)) {
                list.forEach(function (item) {
                    if (item && item.photonum) {
                        mapped[parseInt(item.photonum, 10)] = item;
                    }
                });
            }

            $('#collage').splitInTiles(mapped);
        })
        .catch(err => {
            console.error('Unable to refresh collage', err);
        });
    }

    function waitForIt() {
        if (window.eventId) {
            dofetch();
        } else {
            setTimeout(waitForIt, 250);
        }
    }

    $(document).on('pointerup click', '.pop', function (e) {
        e.preventDefault();
        const photoUrl = $(this).attr('data-photo-url') || $(this).attr('src');
        if (photoUrl) {
            openPhotoModal(photoUrl);
        }
    });

    window.addEventListener('message', function (event) {
        if (!event || !event.data || event.data.type !== 'collage-opacity-preview') {
            return;
        }

        const nextOpacity = parseFloat(event.data.opacity);
        if (!Number.isNaN(nextOpacity)) {
            opacity = nextOpacity;
            applyOpacityToVisibleTiles();
        }
    });

    $(window).on('resize', function () {
        if (!imgWidth || !imgHeight) return;
        sizeCollage();
        updateTileLayout();
        dofetch();
    });

    jQuery(function () {
        jQuery('.quickfit').quickfitText();
    });

    id = getEventIdFromUrl();

    fetchEvent()
        .then(fetchSettings)
        .then(waitForIt)
        .catch(err => {
            console.error('Unable to initialize collage', err);
        });

    setInterval(dofetch, 5000);
    setInterval(fetchSettings, 3000);

}(jQuery, window));
