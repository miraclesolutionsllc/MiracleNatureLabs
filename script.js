/* ============================================================
   MIRACLE NATURE LABS — script.js
   • Ambient gold particle system
   • Countdown timer
   • Subscribe form (fetch → subscribe.php)
   • Intersection observer fade-ins
   ============================================================ */

/* ── 1. AMBIENT PARTICLES ───────────────────────────────────── */
(function () {
    const canvas = document.getElementById('particles-canvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let W, H, particles;
    const PARTICLE_COUNT = 55;

    /* Resize canvas to fill viewport */
    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resize);
    resize();

    /* Particle class */
    function Particle() { this.init(); }

    Particle.prototype.init = function () {
        this.x     = Math.random() * W;
        this.y     = H + Math.random() * 40;
        this.r     = Math.random() * 2.2 + 0.6;          // radius 0.6–2.8px
        this.speed = Math.random() * 0.55 + 0.15;         // upward speed
        this.alpha = Math.random() * 0.55 + 0.08;         // opacity
        this.wobble      = Math.random() * Math.PI * 2;
        this.wobbleSpeed = Math.random() * 0.022 + 0.008;
        /* Gold / warm tones */
        var hue = Math.random() * 20 + 38;                // 38–58° → gold range
        var sat = Math.random() * 30 + 60;
        var lit = Math.random() * 25 + 55;
        this.color = 'hsl(' + hue + ',' + sat + '%,' + lit + '%)';
    };

    Particle.prototype.update = function () {
        this.y -= this.speed;
        this.wobble += this.wobbleSpeed;
        this.x += Math.sin(this.wobble) * 0.6;
        if (this.y < -12 || this.x < -12 || this.x > W + 12) this.init();
    };

    Particle.prototype.draw = function () {
        ctx.save();
        ctx.globalAlpha = this.alpha;
        ctx.fillStyle   = this.color;
        ctx.shadowColor = this.color;
        ctx.shadowBlur  = this.r * 3;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    };

    /* Build initial pool */
    particles = [];
    for (var i = 0; i < PARTICLE_COUNT; i++) {
        var p = new Particle();
        /* Scatter existing particles at random heights so they're not
           all queued at the bottom on load */
        p.y = Math.random() * H;
        particles.push(p);
    }

    /* Animation loop */
    function loop() {
        ctx.clearRect(0, 0, W, H);
        for (var i = 0; i < particles.length; i++) {
            particles[i].update();
            particles[i].draw();
        }
        requestAnimationFrame(loop);
    }
    loop();
}());


/* ── 2. COUNTDOWN TIMER ─────────────────────────────────────── */
(function () {
    /* ★ Change this date to your actual planned launch date ★ */
    var LAUNCH_DATE = new Date('2026-09-01T00:00:00');

    var elDays    = document.getElementById('cd-days');
    var elHours   = document.getElementById('cd-hours');
    var elMinutes = document.getElementById('cd-minutes');
    var elSeconds = document.getElementById('cd-seconds');

    if (!elDays) return; /* countdown block not in DOM */

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function tick() {
        var now  = new Date();
        var diff = LAUNCH_DATE - now;

        if (diff <= 0) {
            /* Launched! */
            elDays.textContent    = '00';
            elHours.textContent   = '00';
            elMinutes.textContent = '00';
            elSeconds.textContent = '00';
            return;
        }

        var days    = Math.floor(diff / 86400000);
        var hours   = Math.floor((diff % 86400000) / 3600000);
        var minutes = Math.floor((diff % 3600000)  / 60000);
        var seconds = Math.floor((diff % 60000)    / 1000);

        elDays.textContent    = pad(days);
        elHours.textContent   = pad(hours);
        elMinutes.textContent = pad(minutes);
        elSeconds.textContent = pad(seconds);
    }

    tick();
    setInterval(tick, 1000);
}());


/* ── 3. SUBSCRIBE FORM ──────────────────────────────────────── */
(function () {
    var form    = document.getElementById('subscribe-form');
    var msgEl   = document.getElementById('form-msg');
    var btn     = document.getElementById('sub-btn');
    var nameIn  = document.getElementById('sub-name');
    var emailIn = document.getElementById('sub-email');

    if (!form) return;

    /* Simple client-side validation */
    function validate() {
        var ok = true;

        nameIn.classList.remove('input-error');
        emailIn.classList.remove('input-error');

        if (!nameIn.value.trim()) {
            nameIn.classList.add('input-error');
            ok = false;
        }
        var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRe.test(emailIn.value.trim())) {
            emailIn.classList.add('input-error');
            ok = false;
        }
        return ok;
    }

    function setLoading(loading) {
        btn.disabled = loading;
        if (loading) {
            btn.classList.add('is-loading');
        } else {
            btn.classList.remove('is-loading');
        }
    }

    function showMsg(text, type) {
        msgEl.textContent  = text;
        msgEl.className    = 'form-msg msg-' + type;   /* msg-success | msg-error */
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!validate()) {
            showMsg('Please fill in all fields correctly.', 'error');
            return;
        }

        setLoading(true);
        msgEl.className = 'form-msg'; /* hide previous message */

        var formData = new FormData();
        formData.append('name',  nameIn.value.trim());
        formData.append('email', emailIn.value.trim());

        fetch('subscribe.php', {
            method: 'POST',
            body:   formData
        })
        .then(function (res) {
            /* subscribe.php always returns JSON */
            return res.json();
        })
        .then(function (data) {
            setLoading(false);
            if (data.success) {
                showMsg(data.message, 'success');
                form.reset();
                nameIn.classList.remove('input-error');
                emailIn.classList.remove('input-error');
            } else {
                showMsg(data.message, 'error');
            }
        })
        .catch(function () {
            setLoading(false);
            showMsg('Something went wrong. Please try again later.', 'error');
        });
    });

    /* Clear error state on input */
    [nameIn, emailIn].forEach(function (input) {
        input.addEventListener('input', function () {
            input.classList.remove('input-error');
        });
    });
}());


/* ── 4. SCROLL FADE-IN (Intersection Observer) ──────────────── */
(function () {
    /* Add .animate-in to elements we want to reveal on scroll */
    var targets = [].slice.call(
        document.querySelectorAll('.pillar, .product-card, .subscribe-card, .section-title, .about-body, .section-sub')
    );

    targets.forEach(function (el) {
        el.classList.add('animate-in');
    });

    if (!('IntersectionObserver' in window)) {
        /* Fallback: just show everything immediately */
        targets.forEach(function (el) { el.classList.add('visible'); });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    targets.forEach(function (el) { observer.observe(el); });
}());
