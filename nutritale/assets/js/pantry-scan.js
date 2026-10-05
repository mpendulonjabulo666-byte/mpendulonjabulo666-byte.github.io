// Pantry barcode scanner (Premium): live camera preview via the vendored
// html5-qrcode library (Apache-2.0, assets/js/vendor/), lookup via
// barcode_lookup.php, then the user confirms/edits the name and it posts to
// the normal pantry "add" handler. Typing the number in works without a
// camera.
//
// Lookup path: the server goes first (it enforces Premium). If the server
// cannot reach Open Food Facts - free hosts such as InfinityFree block
// outgoing requests - this falls back to asking Open Food Facts straight
// from the phone's browser, which allows cross-origin reads.
(function () {
    var openBtn = document.getElementById('scan-open');
    var panel = document.getElementById('scan-panel');
    if (!openBtn || !panel) return;
    var statusEl = document.getElementById('scan-status');
    var manual = document.getElementById('scan-manual');
    var codeInput = document.getElementById('scan-code');
    var result = document.getElementById('scan-result');
    var nameInput = document.getElementById('scan-name');
    var readerEl = document.getElementById('scan-reader');
    var scanner = null;
    var busy = false;

    function setStatus(text) { statusEl.textContent = text; }

    function stopCamera() {
        if (scanner && scanner.isScanning) {
            var done = function () { readerEl.classList.remove('is-live'); };
            scanner.stop().then(done, done);
        } else {
            readerEl.classList.remove('is-live');
        }
    }

    // Port of product_pantry_name() in includes/external_recipes.php.
    function pantryName(productName, brands, genericName) {
        var name = (productName || '').trim() !== '' ? productName : (genericName || '');
        (brands || '').split(',').map(function (b) { return b.trim(); }).filter(Boolean).forEach(function (b) {
            name = name.replace(new RegExp('\\b' + b.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'gi'), ' ');
        });
        name = name.replace(/\b\d+(?:[.,]\d+)?\s*(?:x\s*\d+\s*)?(?:kg|g|mg|l|ml|cl|oz|lb|pack|pk|s)\b/gi, ' ');
        name = name.replace(/\s+/g, ' ').replace(/^[\s\-,.]+|[\s\-,.]+$/g, '');
        return name.slice(0, 80);
    }

    function lookupInBrowser(code) {
        var url = 'https://world.openfoodfacts.org/api/v2/product/' + encodeURIComponent(code)
            + '.json?fields=product_name,generic_name,brands';
        return fetch(url).then(function (r) {
            if (!r.ok && r.status !== 404) throw new Error('http ' + r.status);
            return r.json();
        }).then(function (d) {
            var p = d && d.product;
            if (!d || Number(d.status) !== 1 || !p) {
                return { ok: false, error: "We couldn't find that product. Type what it is in the box above instead." };
            }
            var name = pantryName(p.product_name, p.brands, p.generic_name);
            return {
                ok: name !== '',
                name: name,
                product: ((p.brands || '') + ' ' + (p.product_name || '')).trim(),
                error: name === '' ? 'Found the product but it has no name listed. Type what it is above.' : null
            };
        });
    }

    function showResult(data) {
        if (data.ok) {
            setStatus('Found: ' + (data.product || data.name));
            nameInput.value = data.name;
            result.hidden = false;
            nameInput.focus();
            nameInput.select();
        } else {
            setStatus(data.error || 'Could not look that up.');
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
                if (data.ok || !data.unreachable) { showResult(data); return null; }
                return lookupInBrowser(code).then(showResult);
            }, function () {
                return lookupInBrowser(code).then(showResult);
            })
            .catch(function () {
                setStatus('Could not reach the product database. Type the ingredient above instead.');
            })
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
            // Android Chrome has a native barcode detector that is far more
            // reliable on retail barcodes than the JS decoder.
            useBarCodeDetectorIfSupported: true,
            experimentalFeatures: { useBarCodeDetectorIfSupported: true },
            verbose: false
        });
        // The preview box must be visible BEFORE start(): the library reads
        // its width when it starts, and an empty hidden box measures 0px.
        readerEl.classList.add('is-live');
        setStatus('Starting camera...');
        scanner.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: function (w, h) {
                    var width = Math.floor(Math.min(w * 0.86, 340));
                    return { width: width, height: Math.floor(Math.min(width * 0.55, h * 0.8)) };
                }
            },
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
        ).then(function () {
            // "Fill", not "inside": the library shrinks the box's region of
            // each frame down to the box's on-screen size before decoding,
            // so a barcode that is merely inside the box can be too small to
            // read. Measured with a real EAN-13: decodes at ~70% of the box
            // width and up, never at ~50%.
            setStatus('Move closer until the barcode fills the box.');
        }).catch(function (err) {
            readerEl.classList.remove('is-live');
            var why = err && (err.message || err.name || String(err));
            setStatus('Camera could not start' + (why ? ' (' + why + ')' : '') + '. Allow camera access for this site, or type the barcode number below.');
        });
    }

    openBtn.addEventListener('click', function () {
        panel.hidden = false;
        result.hidden = true;
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
