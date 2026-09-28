// Pantry barcode scanner (Premium): camera via the vendored html5-qrcode
// library (Apache-2.0, assets/js/vendor/), lookup via barcode_lookup.php,
// then the user confirms/edits the name and it posts to the normal pantry
// "add" handler. Typing the number in works without a camera.
(function () {
    var openBtn = document.getElementById('scan-open');
    var panel = document.getElementById('scan-panel');
    if (!openBtn || !panel) return;
    var statusEl = document.getElementById('scan-status');
    var manual = document.getElementById('scan-manual');
    var codeInput = document.getElementById('scan-code');
    var result = document.getElementById('scan-result');
    var nameInput = document.getElementById('scan-name');
    var scanner = null;
    var busy = false;

    function setStatus(text) { statusEl.textContent = text; }

    function stopCamera() {
        if (scanner && scanner.isScanning) {
            scanner.stop().catch(function () {});
        }
    }

    function lookup(code) {
        if (busy) return;
        busy = true;
        result.hidden = true;
        setStatus('Looking up ' + code + '...');
        fetch('barcode_lookup.php?code=' + encodeURIComponent(code), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    setStatus('Found: ' + (data.product || data.name));
                    nameInput.value = data.name;
                    result.hidden = false;
                    nameInput.focus();
                    nameInput.select();
                } else {
                    setStatus(data.error || 'Could not look that up.');
                }
            })
            .catch(function () { setStatus('Could not reach the product database. Type the ingredient above instead.'); })
            .then(function () { busy = false; });
    }

    function startCamera() {
        if (typeof Html5Qrcode === 'undefined' || !navigator.mediaDevices) {
            setStatus('Camera scanning is not available in this browser. Type the barcode number below.');
            return;
        }
        scanner = scanner || new Html5Qrcode('scan-reader', {
            formatsToSupport: [
                Html5QrcodeSupportedFormats.EAN_13, Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A, Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.QR_CODE
            ],
            verbose: false
        });
        scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 260, height: 140 } },
            function (text) {
                stopCamera();
                var digits = (text || '').replace(/\s+/g, '');
                if (/^\d{6,14}$/.test(digits)) {
                    codeInput.value = digits;
                    lookup(digits);
                } else {
                    setStatus('That code is not a product barcode. Scan the barcode on the pack, or type its number below.');
                }
            },
            function () { /* no code in this frame - keep scanning */ }
        ).catch(function () {
            setStatus('Camera access was blocked or no camera was found. Type the barcode number below.');
        });
    }

    openBtn.addEventListener('click', function () {
        panel.hidden = false;
        result.hidden = true;
        setStatus('Point your camera at the barcode on the pack.');
        startCamera();
    });
    document.getElementById('scan-close').addEventListener('click', function () {
        stopCamera();
        panel.hidden = true;
    });
    manual.addEventListener('submit', function (e) {
        e.preventDefault();
        var code = codeInput.value.replace(/\s+/g, '');
        if (!/^\d{6,14}$/.test(code)) {
            setStatus('Barcodes are 6 to 14 digits. Check the number and try again.');
            return;
        }
        stopCamera();
        lookup(code);
    });
})();
