// "Share this recipe" (recipe.php): the Web Share API where it exists -
// including the recipe photo, when the browser can actually share files -
// with a manual fallback menu (WhatsApp / Facebook / copy link) on
// browsers (mainly desktop) that don't implement navigator.share() at all.
(function () {
    var shareBtn = document.getElementById('share-btn');
    var menu = document.getElementById('share-menu');
    var toast = document.getElementById('share-toast');
    if (!shareBtn || !menu || !toast) return;

    var whatsappLink = document.getElementById('share-whatsapp');
    var facebookLink = document.getElementById('share-facebook');
    var copyBtn = document.getElementById('share-copy');
    var menuItems = [whatsappLink, facebookLink, copyBtn];

    function showToast(text) {
        toast.textContent = text;
        toast.hidden = false;
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () { toast.hidden = true; }, 2500);
    }

    function copyLink(url) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url)
                .then(function () { showToast('Link copied!'); })
                .catch(function () { showToast(url); });
        } else {
            showToast(url);
        }
    }

    function shareData() {
        return {
            title: shareBtn.getAttribute('data-title'),
            text: shareBtn.getAttribute('data-text'),
            url: window.location.href,
        };
    }

    // Attaches the recipe's own photo to a Web Share payload when - and
    // only when - this browser both supports sharing files at all and is
    // willing to share this specific one (canShare's file check, not just
    // navigator.share existing - some Web Share implementations support
    // text/links but not files). No server-side resizing happens here:
    // this app has no image-processing utility (nothing like PHP's GD/
    // Imagick is used anywhere else in it), so it reuses the same
    // Unsplash-served, already-width-capped image (?w=800 in the URL,
    // matching the size the page itself displays) rather than re-encoding
    // a new copy just for sharing. If the fetch fails for any reason
    // (offline, a CORS-restricted image host, an unsupported type) sharing
    // still proceeds with just title/text/url - a missing photo is never
    // worth blocking the share itself.
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

    function openMenu() {
        var data = shareData();
        whatsappLink.href = 'https://wa.me/?text=' + encodeURIComponent(data.title + ' ' + data.url);
        facebookLink.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(data.url);
        menu.hidden = false;
        shareBtn.setAttribute('aria-expanded', 'true');
        menuItems.forEach(function (item) { item.tabIndex = 0; });
        whatsappLink.focus();
        document.addEventListener('click', onOutsideClick);
        document.addEventListener('keydown', onMenuKeydown);
    }

    function closeMenu(returnFocus) {
        menu.hidden = true;
        shareBtn.setAttribute('aria-expanded', 'false');
        menuItems.forEach(function (item) { item.tabIndex = -1; });
        document.removeEventListener('click', onOutsideClick);
        document.removeEventListener('keydown', onMenuKeydown);
        if (returnFocus) shareBtn.focus();
    }

    function onOutsideClick(event) {
        if (!menu.contains(event.target) && event.target !== shareBtn) {
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
        if (navigator.share) {
            withImageIfShareable(shareData(), shareBtn.getAttribute('data-image')).then(function (data) {
                navigator.share(data).catch(function () { /* user cancelled - no-op */ });
            });
            return;
        }
        if (menu.hidden) {
            openMenu();
        } else {
            closeMenu(false);
        }
    });

    copyBtn.addEventListener('click', function () {
        copyLink(shareData().url);
        closeMenu(true);
    });
    whatsappLink.addEventListener('click', function () { closeMenu(false); });
    facebookLink.addEventListener('click', function () { closeMenu(false); });
})();
