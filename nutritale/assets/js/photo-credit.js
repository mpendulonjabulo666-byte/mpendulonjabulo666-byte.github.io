// Recipe page: the photo credit stays hidden until the visitor clicks
// (or taps / presses Enter on) the photo. Click again to hide it.
// If this script doesn't run, the credit is just left visible, so the
// attribution is never lost.
(function () {
    var photo = document.querySelector('.recipe-detail-image');
    var credit = document.querySelector('.recipe-photo-credit');
    if (!photo || !credit) return;

    credit.hidden = true;
    photo.classList.add('has-credit');
    photo.setAttribute('role', 'button');
    photo.setAttribute('tabindex', '0');
    photo.setAttribute('aria-expanded', 'false');
    photo.setAttribute('aria-label', 'Show photo credit');

    function toggle() {
        credit.hidden = !credit.hidden;
        photo.setAttribute('aria-expanded', String(!credit.hidden));
    }

    photo.addEventListener('click', toggle);
    photo.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    });
})();
