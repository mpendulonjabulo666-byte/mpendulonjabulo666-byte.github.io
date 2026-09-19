// Starts the landing hero photo's float/tilt animation only while it's
// actually on screen (adds .is-in-view, which flips animation-play-state
// to running - see .landing-hero-photo in style.css) and pauses it again
// once scrolled away, so it never spends battery/CPU animating something
// nobody can see.
//
// Skipped entirely under prefers-reduced-motion: the CSS media query
// already removes the animation unconditionally in that case, so setting
// up an observer just to toggle a class with no visible effect would be
// pure waste.
(function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var photo = document.querySelector('.landing-hero-photo');
    if (!photo || !('IntersectionObserver' in window)) return;

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            entry.target.classList.toggle('is-in-view', entry.isIntersecting);
        });
    }, { threshold: 0.1 });

    observer.observe(photo);
})();
