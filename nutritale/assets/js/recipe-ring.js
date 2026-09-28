// Landing page recipe ring: cards seated edge-out around a tipped wheel,
// spinning slowly and thrown with momentum on drag. One rAF loop; the only
// trig per card is sin/cos of its single angle.
(function () {
    var stage = document.querySelector('.recipe-ring-stage');
    if (!stage) return;
    var cards = Array.prototype.slice.call(stage.querySelectorAll('.recipe-ring-card'));
    var N = cards.length;
    if (N < 3) return;

    var TAU = Math.PI * 2;
    // Reduced motion: no idle drift - the ring only moves when dragged.
    var IDLE = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 0.0045;
    var rotation = 0, vel = IDLE, R = 0;
    var dragging = false, lastX = 0, running = false, front = -1;
    var items = cards.map(function (el, i) { return { el: el, i: i, z: 0 }; });

    var capTitle = document.querySelector('.recipe-ring-caption-title');
    var capCredit = document.querySelector('.recipe-ring-caption-credit');
    var creditRows = document.querySelectorAll('.recipe-ring-credits li');

    stage.classList.add('is-live');

    function measure() {
        var r = stage.getBoundingClientRect();
        R = 0.62 * Math.min(r.width, r.height);
        stage.style.setProperty('--ring-card-w', Math.round(Math.max(96, Math.min(170, R * 0.52))) + 'px');
    }

    // The front card's title + linked photo credit, shown under the ring.
    function setCaption(i) {
        capTitle.textContent = cards[i].querySelector('figcaption').textContent;
        var credit = creditRows[i] && creditRows[i].querySelector('.recipe-ring-credit');
        capCredit.textContent = '';
        if (credit) capCredit.appendChild(credit.cloneNode(true));
    }

    function render() {
        if (R <= 0) return;
        for (var k = 0; k < N; k++) {
            var it = items[k];
            var a = rotation + (k / N) * TAU;
            var s = Math.sin(a), c = Math.cos(a);
            var x = s * R;
            var z = c * R;
            var y = -c * R * 0.42; // the tilt: derived from z, not separate
            var depth = (z / R + 1) / 2; // 0 at the back, 1 at the front
            it.z = z;
            var st = it.el.style;
            st.transform = 'translate(-50%,-50%) translate3d(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px,0) rotate('
                + (s * 14).toFixed(2) + 'deg) scale(' + (0.55 + depth * 0.55).toFixed(3) + ')';
            st.opacity = (0.30 + depth * 0.70).toFixed(3);
            st.filter = 'brightness(' + (0.5 + depth * 0.7).toFixed(3) + ')';
        }
        // Sort the depth ourselves: transforms alone can't resolve cards this
        // overlapped, so every frame the array index becomes the z-index.
        var sorted = items.slice();
        sorted.sort(function (p, q) { return p.z - q.z; });
        for (var j = 0; j < N; j++) sorted[j].el.style.zIndex = j;
        var f = sorted[N - 1].i;
        if (f !== front) {
            front = f;
            setCaption(f);
        }
    }

    function frame() {
        if (!running) return;
        if (!dragging) {
            rotation += vel;
            // A throw decays into the resting spin rather than stopping dead.
            vel += (IDLE - vel) * 0.02;
        }
        if (rotation > TAU || rotation < -TAU) rotation %= TAU;
        render();
        requestAnimationFrame(frame);
    }

    function start() {
        if (running) return;
        running = true;
        requestAnimationFrame(frame);
    }

    stage.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        dragging = true;
        vel = 0;
        lastX = e.clientX;
        stage.setPointerCapture(e.pointerId);
        stage.classList.add('is-dragging');
    });
    stage.addEventListener('pointermove', function (e) {
        if (!dragging) return;
        var d = (e.clientX - lastX) * 0.0045;
        lastX = e.clientX;
        rotation += d;
        vel = d;
    });
    function release() {
        dragging = false;
        stage.classList.remove('is-dragging');
    }
    stage.addEventListener('pointerup', release);
    stage.addEventListener('pointercancel', release);
    stage.addEventListener('lostpointercapture', release);

    stage.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
            vel += e.key === 'ArrowRight' ? 0.05 : -0.05;
            e.preventDefault();
        }
    });

    measure();
    render();
    if ('ResizeObserver' in window) new ResizeObserver(measure).observe(stage);
    else window.addEventListener('resize', measure);

    // Only animate while the ring is on screen.
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting) start();
            else running = false;
        }).observe(stage);
    } else {
        start();
    }
})();
