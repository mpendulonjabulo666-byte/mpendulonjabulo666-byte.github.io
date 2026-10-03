// Profile photo: with JS, picking a file submits straight away so the tap
// on the camera badge is the whole interaction. Without it the file input
// and its "Upload photo" button stay visible and work as a normal form -
// which is why they are hidden from here rather than in the markup.
(function () {
    var form = document.getElementById('avatar-form');
    if (!form) return;
    var input = document.getElementById('avatar-input');
    var submit = form.querySelector('.profile-avatar-submit');
    if (!input || !submit) return;

    input.classList.add('is-enhanced');
    submit.classList.add('is-enhanced');

    input.addEventListener('change', function () {
        if (input.files && input.files.length) form.submit();
    });
})();
