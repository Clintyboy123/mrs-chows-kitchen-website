/* =========================================================================
   Mrs Chow's Kitchen — motion layer (v1.7.0)
   1. Scroll reveals: adds .is-in once, CSS runs the 4 s ease-out.
   2. Layered parallax: one strength knob (PARALLAX_STRENGTH).
   Off entirely with prefers-reduced-motion.
   ========================================================================= */
/* Social rail: Facebook / Instagram / TikTok, centre left (desktop).
   Copies the footer's links so the admin / Customizer stays the one source.
   Fades out while the footer (which has its own icons) is on screen. */
(function () {
  'use strict';
  function build() {
    if (document.querySelector('.social-rail')) return;
    var links = Array.prototype.filter.call(document.querySelectorAll('.footer .social'), function (a) {
      return !/wa\.me|whatsapp/i.test(a.getAttribute('href') + ' ' + a.getAttribute('aria-label'));
    });
    if (!links.length) return;
    var nav = document.createElement('nav');
    nav.className = 'social-rail';
    nav.setAttribute('aria-label', 'Follow us');
    var label = document.createElement('span');
    label.className = 'rail-label';
    label.setAttribute('aria-hidden', 'true');
    label.textContent = '關注';
    nav.appendChild(label);
    links.forEach(function (a) {
      var c = a.cloneNode(true);
      c.className = '';
      nav.appendChild(c);
    });
    var end = document.createElement('span');
    end.className = 'rail-end';
    end.setAttribute('aria-hidden', 'true');
    nav.appendChild(end);
    (document.querySelector('.site') || document.body).appendChild(nav);
    var foot = document.querySelector('.footer');
    if (foot && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (e) {
        nav.classList.toggle('is-away', e[0].isIntersecting);
      }).observe(foot);
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build);
  else build();
})();

(function () {
  'use strict';

  var PARALLAX_STRENGTH = 1;     // 0 = off, 1 = designed, 1.5 = stronger
  var PHONE_FACTOR = 0.6;        // calmer on small screens

  var root = document.documentElement;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce || !('IntersectionObserver' in window)) return;
  root.classList.add('js-motion');

  /* ---------- 1. Reveals ---------- */
  // Images float up from below. Cards in one row follow each other by 0.15 s.
  var FLOAT = '.story-media, .special-media, .frame.map, .gallery .work';
  function tagFloats() {
    var tagged = [];
    document.querySelectorAll(FLOAT).forEach(function (el) {
      if (el.closest('.hero')) return;
      el.style.transition = 'none';            // jump straight to the start position
      el.classList.add('float-in');
      tagged.push(el);
      if (el.classList.contains('work') && el.parentElement) {
        var cards = Array.prototype.filter.call(el.parentElement.children, function (c) { return c.classList.contains('work'); });
        var i = cards.indexOf(el);
        var cols = i < 2 ? 2 : 4;              // first row holds 2 large cards, then rows of 4
        var pos = i < 2 ? i : (i - 2) % cols;
        el.style.setProperty('--stagger', (pos * 0.15).toFixed(2) + 's');
      }
    });
    if (!tagged.length) return;
    void document.body.offsetHeight;           // commit the hidden start state
    tagged.forEach(function (el) { el.style.transition = ''; });
  }

  function initReveals() {
    tagFloats();
    var items = document.querySelectorAll('.reveal, .float-in, .ph-zoom, .rule.draw');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        e.target.classList.add('is-in');
        io.unobserve(e.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0 });

    // Siblings that enter together cascade by 0.15 s.
    items.forEach(function (el) {
      var p = el.parentElement, i = 0;
      if (p && el.classList.contains('reveal')) {
        var sibs = p.querySelectorAll(':scope > .reveal');
        i = Array.prototype.indexOf.call(sibs, el);
      }
      if (i > 0) el.style.setProperty('--stagger', Math.min(i, 4) * 0.15 + 's');
      io.observe(el);
    });

    // Safety net: anchor jumps / restored scroll positions can skip the
    // observer in some browsers, so also check on scroll and after load.
    var pending = Array.prototype.slice.call(items), busy = false;
    function sweep() {
      busy = false;
      var vh = window.innerHeight * 0.92;
      pending = pending.filter(function (el) {
        if (el.classList.contains('is-in')) return false;
        var r = el.getBoundingClientRect();
        if (r.top < vh && r.bottom > 0) { el.classList.add('is-in'); io.unobserve(el); return false; }
        return true;
      });
      if (!pending.length) {
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('hashchange', onScroll);
      }
    }
    function onScroll() { if (!busy) { busy = true; requestAnimationFrame(sweep); } }
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('hashchange', onScroll);
    window.addEventListener('load', onScroll);
  }

  /* ---------- 2. Parallax ---------- */
  // speed: share of the distance from viewport centre the layer travels.
  // Positive = moves slower than the page (sits behind), negative = in front.
  var LAYERS = [
    { sel: '.hero-img',                 speed: 0.10, max: 0.05 },
    { sel: '.frame-inner > .ph-zoom',   speed: 0.12, max: 0.07 },
    { sel: '.hero-sun',                 speed: 0.18 },
    { sel: '.hero-vertical',            speed: -0.10 },
    { sel: '.hero-stamp',               speed: -0.08 },
    { sel: '.story-seal',               speed: -0.10 },
    { sel: '.price-seal',               speed: -0.12 },
    { sel: '.ink-drift',                speed: 0.08 }
  ];

  var layers = [], visible = new Set(), ticking = false;

  function initParallax() {
    if (!PARALLAX_STRENGTH) return;
    LAYERS.forEach(function (l) {
      document.querySelectorAll(l.sel).forEach(function (el) {
        if (el.hasAttribute('data-px')) return;
        el.setAttribute('data-px', '');
        // Measure the parent so our own translate never feeds back in.
        layers.push({ el: el, ref: el.parentElement || el, speed: l.speed, max: l.max });
      });
    });
    if (!layers.length) return;

    var vio = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        var layer = e.target.__px;
        if (!layer) return;
        if (e.isIntersecting) visible.add(layer); else visible.delete(layer);
      });
      request();
    }, { rootMargin: '20% 0px 20% 0px' });

    layers.forEach(function (l) { l.ref.__px = l; vio.observe(l.ref); });

    window.addEventListener('scroll', request, { passive: true });
    window.addEventListener('resize', request, { passive: true });
    request();
  }

  function request() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(update);
  }

  function update() {
    ticking = false;
    var vh = window.innerHeight;
    var k = PARALLAX_STRENGTH * (window.innerWidth < 760 ? PHONE_FACTOR : 1);
    visible.forEach(function (l) {
      var r = l.ref.getBoundingClientRect();
      var d = (r.top + r.height / 2) - vh / 2;
      var y = -d * l.speed * k;
      if (l.max) {
        var lim = r.height * l.max;
        y = Math.max(-lim, Math.min(lim, y));
      }
      l.el.style.translate = '0 ' + y.toFixed(1) + 'px';
    });
  }

  function start() { initReveals(); initParallax(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();

/* =========================================================================
   Hero noodle bowl: scroll scrubs a 60-frame 3D render.
   Frame 0 = chopsticks in the bowl; scrolling down lifts the noodles out.
   Frames stream in after first paint; the nearest loaded frame is drawn,
   eased toward the scroll position on the house curve feel (no jitter).
   Reduced motion: a still of the noodles mid-lift, nothing moves.
   ========================================================================= */
(function () {
  'use strict';
  function init() {
    var box = document.querySelector('[data-noodle-seq]');
    if (!box) return;
    var count = parseInt(box.getAttribute('data-count'), 10) || 60;
    var poster = box.querySelector('.noodle-poster');
    var canvas = box.querySelector('.noodle-canvas');
    var hero = box.closest('.hero') || document.body;
    var size = (Math.min(window.innerWidth, 900) * (window.devicePixelRatio || 1) > 900 && window.innerWidth > 760) ? 'lg' : 'sm';
    var base = 'assets/img/noodles/' + size + '/frame_';
    var src = function (i) { return base + String(i).padStart(3, '0') + '.webp'; };
    if (window.INKWOK_NOODLES) base = window.INKWOK_NOODLES + size + '/frame_';   // WordPress passes its own path

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) { poster.srcset = ''; poster.src = src(Math.round(count * 0.35)); return; }

    var ctx = canvas.getContext('2d');
    var frames = new Array(count), loaded = new Array(count);
    var current = 0, target = 0, drawn = -1, running = false;

    function load(i) {
      return new Promise(function (res) {
        var im = new Image();
        im.decoding = 'async';
        im.onload = function () { frames[i] = im; loaded[i] = true; res(); if (Math.round(current) === i || drawn < 0) draw(true); };
        im.onerror = function () { res(); };
        im.src = src(i);
      });
    }
    // Load order: first frame, then a coarse pass (every 6th) so scrubbing works early, then the rest.
    function queue() {
      var order = [0], seen = { 0: true };
      [6, 3, 1].forEach(function (step) {
        for (var i = 0; i < count; i += step) if (!seen[i]) { seen[i] = true; order.push(i); }
      });
      var at = 0, live = 0, MAX = 4;
      (function pump() {
        while (live < MAX && at < order.length) {
          live++;
          load(order[at++]).then(function () { live--; pump(); });
        }
      })();
    }

    function nearest(i) {
      if (loaded[i]) return i;
      for (var d = 1; d < count; d++) {
        if (loaded[i - d]) return i - d;
        if (loaded[i + d]) return i + d;
      }
      return -1;
    }
    function resize() {
      var dpr = Math.min(window.devicePixelRatio || 1, 2);
      var w = box.clientWidth, h = box.clientHeight;
      canvas.width = Math.round(w * dpr); canvas.height = Math.round(h * dpr);
      drawn = -1; draw(true);
    }
    function paint(im, alpha, keep) {
      var cw = canvas.width, ch = canvas.height;
      if (!cw || !ch || !im) return;
      var s = Math.min(cw / im.naturalWidth, ch / im.naturalHeight);       // object-fit: contain
      var w = im.naturalWidth * s, h = im.naturalHeight * s;
      if (!keep) ctx.clearRect(0, 0, cw, ch);                               // transparent render
      ctx.globalAlpha = alpha == null ? 1 : alpha;
      ctx.drawImage(im, (cw - w) / 2, (ch - h) / 2, w, h);
      ctx.globalAlpha = 1;
    }
    function draw(force) {
      var i = nearest(Math.round(current));
      if (i < 0 || (!force && i === drawn)) return;
      paint(frames[i]);
      drawn = i;
      if (!box.classList.contains('is-live')) box.classList.add('is-live');
    }
    // Desktop: the hero pins (CSS sticky) while a scroll runway plays the lift.
    // Phones/tablets: the lift plays as the photo travels up through the screen.
    var pinMQ = window.matchMedia('(min-width: 1025px) and (min-height: 700px)');
    var grid = hero.querySelector('.hero-grid');
    var runwayEl = grid && grid.parentElement;
    // Desktop placement: the bowl's centre sits level with the middle of the headline,
    // just right of the column centre. Measured from the render: bowl centre at 42.5% / 68.25%,
    // chopstick tips 51.4% of the width above the bowl centre.
    var BOWL_X = 0.425, BOWL_Y = 0.6825, STICK_UP = 0.514, maskBase = null;
    function place() {
      if (!pinMQ.matches || !grid) {
        box.style.removeProperty('--nl'); box.style.removeProperty('--nt'); box.style.removeProperty('--nw');
        // Phones/tablets: the chopsticks fade out just below the buttons, so they never cross the text.
        var ctas = hero.querySelector('.hero-ctas');
        if (ctas) {
          var lay = function (el) { var y = 0, e = el; while (e) { y += e.offsetTop; e = e.offsetParent; } return y; };
          var m = lay(ctas) + ctas.offsetHeight + 8 - lay(box);
          maskBase = Math.max(0, Math.round(m));
          box.style.setProperty('--mtop', maskBase + 'px');
        }
        return;
      }
      box.style.removeProperty('--mtop'); maskBase = null;
      var h1 = hero.querySelector('h1'), art = box.parentElement;
      if (!h1 || !art) return;
      // Layout positions (offsetTop/Left ignore the hero's rise-in transforms, which would skew a mid-animation measure).
      var pos = function (el) { var x = 0, y = 0, e = el; while (e) { x += e.offsetLeft; y += e.offsetTop; e = e.offsetParent; } return { left: x, top: y, width: el.offsetWidth, height: el.offsetHeight }; };
      var g = pos(grid), a = pos(art), t = pos(h1);
      var cy = t.top + t.height * 0.62;                         // optical middle of the headline (the chopsticks add weight above)
      var cx = a.left + a.width * 0.58;                         // right of the column centre
      var W = Math.min(900, Math.max(560, a.width * 1.3));    // big from the start; tips may tuck behind the header
      if (cy - STICK_UP * W < 0) W = cy / STICK_UP;            // never above the top of the page
      box.style.setProperty('--nw', Math.round(W) + 'px');
      box.style.setProperty('--nl', Math.round(cx - BOWL_X * W - a.left) + 'px');   // relative to the art column
      box.style.setProperty('--nt', Math.round(cy - BOWL_Y * W - a.top) + 'px');
    }
    function setPin() {
      var on = pinMQ.matches && !!grid;
      document.documentElement.classList.toggle('noodle-pin', on);
      if (on) {
        var nav = document.querySelector('.nav');
        var navH = nav ? nav.getBoundingClientRect().height : 84;
        var top = Math.max(navH + 16, (window.innerHeight - grid.offsetHeight) / 2 + navH / 2);
        document.documentElement.style.setProperty('--pin-top', Math.round(top) + 'px');
      }
    }
    function progress() {
      var p;
      if (document.documentElement.classList.contains('noodle-pin')) {
        var run = parseFloat(getComputedStyle(runwayEl, '::after').height) || 1;
        p = window.scrollY / run;
      } else {
        var r = box.getBoundingClientRect(), vh = window.innerHeight;
        var start = vh - r.height * 0.35;          // photo mostly on screen
        var end = r.height * 0.15;                  // photo near the top
        p = (start - r.top) / Math.max(1, start - end);
      }
      return Math.min(1, Math.max(0, p)) * (count - 1);
    }
    // Zoom in as you scroll (no fade: the bowl stays fully visible and scrolls away with the hero).
    function stage() {
      var p = current / (count - 1);
      var zoom = 1 + 0.4 * Math.pow(p, 1.4);                 // 1 → 1.4, gathering pace
      box.style.setProperty('--nz', zoom.toFixed(4));
      // keep the phone fade line fixed under the buttons while the bowl scales about its origin (68% down)
      if (maskBase !== null) {
        var O = box.offsetHeight * 0.68;
        box.style.setProperty('--mtop', Math.round(O + (maskBase - O) / zoom) + 'px');
      }
    }
    function tick() {
      current += (target - current) * 0.14;                 // ease toward the scroll position
      if (Math.abs(target - current) < 0.02) current = target;
      draw(false);
      stage();
      if (current !== target) requestAnimationFrame(tick); else running = false;
    }
    function onScroll() {
      target = progress();
      if (!running) { running = true; requestAnimationFrame(tick); }
    }

    setPin();
    place();
    resize();
    window.addEventListener('resize', function () { setPin(); place(); resize(); onScroll(); }, { passive: true });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { place(); resize(); });
    window.addEventListener('scroll', onScroll, { passive: true });
    target = current = progress();
    stage();
    if (document.readyState === 'complete') queue(); else window.addEventListener('load', queue);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

/* =========================================================================
   Background music plays automatically and carries on from page to page.
   - First visit: starts at once if the browser allows sound, otherwise on the
     first tap, click or key press anywhere (browsers block sound before that).
   - Moving to another page: if it was playing, the next page starts it again
     by itself (Chrome/Edge usually allow this after the earlier click); where
     the browser still blocks it (Safari, Firefox) it resumes on the first tap.
   - Switching it off with the music seal is remembered on this device.
   - Pages where the seal is hidden never autoplay, so it can always be stopped.
   ========================================================================= */
(function () {
  'use strict';
  // Watch the AudioContext that main.js creates, so a blocked (suspended) one can be resumed on a tap.
  var contexts = [];
  ['AudioContext', 'webkitAudioContext'].forEach(function (name) {
    var Orig = window[name];
    if (!Orig || typeof Proxy === 'undefined') return;
    try {
      window[name] = new Proxy(Orig, { construct: function (t, args) { var c = Reflect.construct(t, args); contexts.push(c); return c; } });
    } catch (e) {}
  });

  var OFF = 'mck-music', ON = 'mck-music-on';
  var local = {
    get: function () { try { return localStorage.getItem(OFF); } catch (e) { return null; } },
    set: function (v) { try { v ? localStorage.setItem(OFF, v) : localStorage.removeItem(OFF); } catch (e) {} }
  };
  var session = {
    get: function () { try { return sessionStorage.getItem(ON) === '1'; } catch (e) { return false; } },
    set: function (on) { try { on ? sessionStorage.setItem(ON, '1') : sessionStorage.removeItem(ON); } catch (e) {} }
  };

  function init() {
    var btn = document.querySelector('.music-btn');
    if (!btn || btn.hidden) return;
    var visible = function () { return btn.getClientRects().length > 0 && getComputedStyle(btn).visibility !== 'hidden'; };   // works for position:fixed
    var isOn = function () { return btn.getAttribute('aria-pressed') === 'true'; };
    var suspended = function () { return contexts.some(function (c) { return c.state === 'suspended'; }); };
    var auto = false;

    // A deliberate choice with the seal: remember "off" on this device; track "playing" for this visit.
    btn.addEventListener('click', function () {
      setTimeout(function () {
        session.set(isOn());
        if (!auto) local.set(isOn() ? null : 'off');
      }, 0);
    });

    function start() {
      if (local.get() === 'off' || !visible()) return;
      if (isOn()) { contexts.forEach(function (c) { if (c.state === 'suspended') c.resume(); }); return; }
      auto = true; btn.click(); auto = false;
    }
    var events = ['pointerdown', 'keydown', 'touchend'];
    function off() { events.forEach(function (t) { window.removeEventListener(t, onFirst, true); }); }
    function onFirst(e) {
      if (e.target && e.target.closest && e.target.closest('.music-btn') && !isOn()) { off(); return; }   // their tap on the seal does the job
      start();
      if (isOn() && !suspended()) off();
    }
    function listen() { events.forEach(function (t) { window.addEventListener(t, onFirst, { capture: true, passive: true }); }); }

    if (local.get() === 'off' || !visible()) return;
    var policy = navigator.getAutoplayPolicy ? navigator.getAutoplayPolicy('audiocontext') : '';
    if (session.get() || policy === 'allowed') {
      start();                                                   // carry on from the last page
      setTimeout(function () { if (!isOn() || suspended()) listen(); }, 120);   // still blocked: resume on the first tap
    } else {
      listen();
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
