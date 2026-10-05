// "Share this recipe" (recipe.php): one menu on every device - WhatsApp,
// Instagram (copies the link), X, Facebook, a QR code, Copy link and, where
// the browser has the Web Share API, the device's own share sheet.
//
// The share button used to skip this menu whenever navigator.share existed
// and open the OS share sheet instead. That is every phone, plus Chrome and
// Edge on Windows - so the QR code, which only lives in this menu, could
// not be reached on exactly the devices it was made for. The OS sheet is
// still here, one tap further, as the round button beside "Copy link".
(function () {
    var shareBtn = document.getElementById('share-btn');
    var menu = document.getElementById('share-menu');
    var toast = document.getElementById('share-toast');
    if (!shareBtn || !menu || !toast) return;

    var whatsappLink = document.getElementById('share-whatsapp');
    var instagramBtn = document.getElementById('share-instagram');
    var xLink = document.getElementById('share-x');
    var facebookLink = document.getElementById('share-facebook');
    var copyBtn = document.getElementById('share-copy');
    var nativeBtn = document.getElementById('share-native');
    var qrBtn = document.getElementById('share-qr');
    var qrPanel = document.getElementById('share-qr-panel');
    var qrCode = document.getElementById('share-qr-code');
    var qrClose = document.getElementById('share-qr-close');
    // Read now: document.currentScript is only set while this file first runs.
    var scriptSrc = document.currentScript ? document.currentScript.src : '';

    if (nativeBtn && navigator.share) nativeBtn.hidden = false;
    // Keyboard order follows the menu's visual order.
    var menuItems = [whatsappLink, instagramBtn, xLink, facebookLink, qrBtn, copyBtn, nativeBtn]
        .filter(function (item) { return item && !item.hidden; });

    function showToast(text) {
        toast.textContent = text;
        toast.hidden = false;
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () { toast.hidden = true; }, 2500);
    }

    function copyLink(url, successMessage) {
        var message = successMessage || 'Link copied!';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url)
                .then(function () { showToast(message); })
                .catch(function () { showToast(url); });
        } else {
            showToast(url);
        }
    }

    function shareData() {
        return {
            title: shareBtn.getAttribute('data-title'),
            text: shareBtn.getAttribute('data-text'),
            // Without the #ingredients/#method fragment the folder tabs add.
            url: window.location.href.split('#')[0],
        };
    }

    // Attaches the recipe's own photo to a Web Share payload when - and
    // only when - this browser both supports sharing files at all and is
    // willing to share this specific one (canShare's file check, not just
    // navigator.share existing - some Web Share implementations support
    // text/links but not files). It reuses the same already-width-capped
    // image the page displays rather than re-encoding a copy for sharing.
    // If the fetch fails for any reason (offline, a CORS-restricted image
    // host, an unsupported type) sharing still proceeds with just
    // title/text/url - a missing photo is never worth blocking the share.
    function withImageIfShareable(data, imageUrl) {
        if (!imageUrl || !navigator.canShare) {
            return Promise.resolve(data);
        }
        return fetch(imageUrl)
            .then(function (res) { return res.ok ? res.blob() : Promise.reject(); })
            .then(function (blob) {
                var extension = (blob.type.split('/')[1] || 'jpg').split('+')[0];
                var file = new File([blob], 'recipe.' + extension, { type: blob.type });
                var withFile = Object.assign({}, data, { files: [file] });
                return navigator.canShare(withFile) ? withFile : data;
            })
            .catch(function () { return data; });
    }

    // navigator.share() has to be called while the tap that asked for it
    // still counts as a user gesture, and Safari does not wait for a photo
    // download first - it rejects the share. So the photo is fetched when
    // the menu opens, and the button shares whatever is ready by then.
    var preparedShare = null;
    function prepareNativeShare() {
        if (!nativeBtn || nativeBtn.hidden || preparedShare) return;
        withImageIfShareable(shareData(), shareBtn.getAttribute('data-image'))
            .then(function (data) { preparedShare = data; });
    }

    function openMenu() {
        var data = shareData();
        whatsappLink.href = 'https://wa.me/?text=' + encodeURIComponent(data.title + ' ' + data.url);
        if (xLink) {
            xLink.href = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(data.title) + '&url=' + encodeURIComponent(data.url);
        }
        facebookLink.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(data.url);
        closeQr(false);
        menu.hidden = false;
        shareBtn.setAttribute('aria-expanded', 'true');
        menuItems.forEach(function (item) { item.tabIndex = 0; });
        whatsappLink.focus();
        document.addEventListener('click', onOutsideClick);
        document.addEventListener('keydown', onMenuKeydown);
        prepareNativeShare();
    }

    function closeMenu(returnFocus) {
        menu.hidden = true;
        shareBtn.setAttribute('aria-expanded', 'false');
        menuItems.forEach(function (item) { item.tabIndex = -1; });
        document.removeEventListener('click', onOutsideClick);
        document.removeEventListener('keydown', onMenuKeydown);
        if (returnFocus) shareBtn.focus();
    }

    // contains(), not ===: a click on the button's icon targets the <svg>
    // inside it, which would otherwise count as "outside" and close the
    // menu in the same click that opened it.
    function onOutsideClick(event) {
        if (!menu.contains(event.target) && !shareBtn.contains(event.target)) {
            closeMenu(false);
        }
    }

    function onMenuKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeMenu(true);
            return;
        }
        if (event.key !== 'Tab') return;
        var currentIndex = menuItems.indexOf(document.activeElement);
        if (currentIndex === -1) return;
        var nextIndex = currentIndex + (event.shiftKey ? -1 : 1);
        if (nextIndex >= 0 && nextIndex < menuItems.length) {
            event.preventDefault();
            menuItems[nextIndex].focus();
        } else {
            closeMenu(false);
        }
    }

    shareBtn.addEventListener('click', function () {
        if (menu.hidden) {
            openMenu();
        } else {
            closeMenu(false);
        }
    });

    if (nativeBtn && !nativeBtn.hidden) {
        nativeBtn.addEventListener('click', function () {
            var data = preparedShare || shareData();
            closeMenu(true);
            navigator.share(data).catch(function () { /* cancelled - no-op */ });
        });
    }

    // qrcode-generator (assets/js/vendor/) is loaded by recipe.php ahead of
    // this file. If it is missing anyway - a dropped script tag, a blocked
    // or failed request - it is fetched on first use instead of leaving an
    // empty box. A dropped tag is exactly how every recipe's QR went blank
    // before (a merge removed it), and nothing on the page said so.
    var qrLibPromise = null;
    function loadQrLib() {
        if (typeof qrcode === 'function') return Promise.resolve();
        if (!qrLibPromise) {
            qrLibPromise = new Promise(function (resolve, reject) {
                var script = document.createElement('script');
                script.src = (scriptSrc ? scriptSrc.replace(/[^/]*$/, '') : 'assets/js/') + 'vendor/qrcode-generator.min.js';
                script.onload = function () {
                    if (typeof qrcode === 'function') { resolve(); } else { reject(new Error('qrcode missing')); }
                };
                script.onerror = reject;
                document.head.appendChild(script);
            }).catch(function (err) {
                qrLibPromise = null; // let the next tap try again
                throw err;
            });
        }
        return qrLibPromise;
    }

    // QR of the recipe's own URL, drawn once and kept. 'M' error correction
    // is the usual trade for a screen-displayed code: still readable at an
    // angle or half-lit, without inflating the module count the way 'H'
    // would on a long recipe URL.
    function renderQr() {
        if (qrCode.firstChild) return Promise.resolve();
        return loadQrLib().then(function () {
            var qr = qrcode(0, 'M');
            qr.addData(shareData().url);
            qr.make();
            qrCode.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
            var svg = qrCode.firstChild;
            svg.setAttribute('role', 'img');
            svg.setAttribute('aria-label', 'QR code that opens this recipe');
        });
    }

    function onQrOutsideClick(event) {
        if (!qrPanel.contains(event.target) && !qrBtn.contains(event.target)) {
            closeQr(false);
        }
    }

    function onQrKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeQr(true);
        }
    }

    function closeQr(returnFocus) {
        if (!qrPanel || qrPanel.hidden) return;
        qrPanel.hidden = true;
        document.removeEventListener('click', onQrOutsideClick);
        document.removeEventListener('keydown', onQrKeydown);
        if (returnFocus) shareBtn.focus();
    }

    if (qrBtn && qrPanel && qrCode && qrClose) {
        qrBtn.addEventListener('click', function () {
            closeMenu(false);
            renderQr().then(function () {
                qrPanel.hidden = false;
                qrClose.focus();
                document.addEventListener('click', onQrOutsideClick);
                document.addEventListener('keydown', onQrKeydown);
            }, function () {
                showToast("Couldn't draw the QR code. Check your connection and try again.");
            });
        });
        qrClose.addEventListener('click', function () { closeQr(true); });
    }

    copyBtn.addEventListener('click', function () {
        copyLink(shareData().url);
        closeMenu(true);
    });
    if (instagramBtn) {
        instagramBtn.addEventListener('click', function () {
            copyLink(shareData().url, 'Link copied - paste it in Instagram');
            closeMenu(true);
        });
    }
    whatsappLink.addEventListener('click', function () { closeMenu(false); });
    if (xLink) xLink.addEventListener('click', function () { closeMenu(false); });
    facebookLink.addEventListener('click', function () { closeMenu(false); });
})();
