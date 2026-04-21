<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Photo Mosaic</title>

    <link rel="stylesheet" href="bootstrap.min.css">

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #000;
            color: #fff;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
        }

        #top {
            width: 100%;
            text-align: center;
            padding: 8px 12px;
            background: #000;
            position: relative;
            z-index: 10;
        }

        #eventName {
            margin: 0;
            font-size: clamp(20px, 3vw, 42px);
            line-height: 1.1;
            font-weight: 700;
        }

        #eventName,#eventdate {
            //font-size: 6vw;
	    //color: white;
            font-family: Arizonia;
        }

        #collage {
            position: relative;
            margin: 0 auto;
            overflow: hidden;
            line-height: 0;
            user-select: none;
            -webkit-user-select: none;
            background: #000;
        }

        #collage img#mainimg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .tile {
            position: absolute;
            overflow: hidden;
            background-repeat: no-repeat;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        .tile img.pop {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
            touch-action: manipulation;
            user-select: none;
            -webkit-user-drag: none;
        }

        .modal-content {
            background: #111;
            color: #fff;
        }

        .modal-body {
            text-align: center;
            padding: 25px;
	}

        .imagepreview {
            max-width: 100%;
            max-height: 45vh;
	    margin-bottom: 25px;
            border-radius: 8px;
        }

        .modal-title {
            font-size: 2rem;
            font-weight: 800;
        }

        @media (max-width: 768px) {
            .imagepreview {
                max-height: 30vh;
            }
        }

        #qrcode {
            display: inline-block;
            padding: 10px;
            background: #fff;
            margin-top: 10px;
        }

        .modal-instructions {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .tile.tile-empty {
            background: #000 !important;
        }

        .empty-tile-label {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: clamp(18px, 2vw, 42px);
            font-weight: 800;
            text-align: center;
            user-select: none;
        }
    </style>
</head>
<body>
    <div id="top">
        <h1 id="eventName" class="quickfit">Loading...</h1>
    </div>

    <div id="collage">
        <img id="mainimg" src="" alt="Base collage image">
    </div>

    <div class="modal fade" id="imagemodal" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="imageModalLabel" class="modal-title w-100 text-center">Download Your Photo</h2>
                    <button type="button" class="close text-white position-absolute" data-dismiss="modal" aria-label="Close" style="right:15px; top:15px; opacity:1;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body text-center">
                    <img class="imagepreview" src="" alt="Selected photo preview">

                    <div style="font-size:2rem; font-weight:700; margin-bottom:25px; line-height:1.3;">
                        Follow these 2 steps to download your photo
                    </div>

                    <div class="row justify-content-center align-items-start">
                        <div class="col-md-6 mb-4">
                            <div style="font-size:2.2rem; font-weight:800; margin-bottom:10px;">
                                ↓ STEP 1: JOIN WIFI ↓
                            </div>
                            <div style="font-size:1.6rem; font-weight:700; margin-bottom:15px; line-height:1.4;">
                                Scan this QR code to join<br>
                                <span style="color:#00d4ff;">joedeejay-guest</span>
                            </div>
                            <div id="wifiqrcode" style="display:inline-block; padding:14px; background:#fff; border-radius:8px;"></div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div style="font-size:2.2rem; font-weight:800; margin-bottom:10px;">
                                ↓ STEP 2: DOWNLOAD PHOTO ↓
                            </div>
                            <div style="font-size:1.6rem; font-weight:700; margin-bottom:15px; line-height:1.4;">
                                After joining WiFi,<br>
                                scan this QR code to download your image
                            </div>
                            <div id="downloadqrcode" style="display:inline-block; padding:14px; background:#fff; border-radius:8px;"></div>
                        </div>
                    </div>

                    <div style="font-size:1.3rem; font-weight:600; margin-top:10px; line-height:1.4;">
                        If your phone asks, connect to WiFi first, then scan the second QR code.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/qrcode.min.js"></script>
    <script src="js/collage.js"></script>
</body>
</html>
