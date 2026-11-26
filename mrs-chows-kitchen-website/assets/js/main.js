/*!
 * Ink & Wok — Chinese Restaurant & Takeaway Template
 * main.js · v1.0.0
 *
 * Everything here is progressive enhancement: the page reads and works without it.
 * Order, search and map features added in 1.1: the order is remembered between pages,
 * slides up as a panel on phones and tablets, and the menu can be searched and filtered.
 *
 * Options are read from data attributes on <body> (see the documentation):
 *   data-currency="$"            symbol used for order totals
 *   data-order-url="https://…"   where the Checkout button sends people
 *   data-prep-time="20–30"       collection estimate (minutes)
 *   data-delivery-time="40–50"   delivery estimate (minutes)
 *   data-cursor="on|off"         chopstick cursor
 *   data-music="on|off"          background music button
 */
(function () {
  'use strict';

  var body = document.body;
  var opt = function (name, fallback) {
    var v = body.getAttribute('data-' + name);
    return v === null || v === '' ? fallback : v;
  };
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* ------------------------------------------------------------------
   * 1. Mobile navigation
   * ------------------------------------------------------------------ */
  var toggle = $('.nav-toggle');
  var nav = $('#site-nav');
  if (toggle && nav) {
    var setNav = function (open) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      nav.classList.toggle('open', open);
    };
    toggle.addEventListener('click', function () { setNav(toggle.getAttribute('aria-expanded') !== 'true'); });
    $$('a', nav).forEach(function (a) { a.addEventListener('click', function () { setNav(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setNav(false); });
  }

  /* ------------------------------------------------------------------
   * 2. Menu category tabs
   * ------------------------------------------------------------------ */
  var tabs = $$('[data-tab]');
  var showTab = function (id, focus) {
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-tab') === id;
      t.classList.toggle('is-active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      var panel = document.getElementById(t.getAttribute('aria-controls'));
      if (panel) panel.hidden = !on;
      if (on) {
        // On phones the tabs scroll sideways: bring the chosen one into view.
        var strip = t.parentNode;
        if (strip && strip.scrollWidth > strip.clientWidth) strip.scrollLeft = t.offsetLeft - (strip.clientWidth - t.offsetWidth) / 2;
      }
      if (on && focus) t.focus();
    });
  };
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () { showTab(t.getAttribute('data-tab')); });
    t.addEventListener('keydown', function (e) {
      var d = e.key === 'ArrowDown' || e.key === 'ArrowRight' ? 1 : e.key === 'ArrowUp' || e.key === 'ArrowLeft' ? -1 : 0;
      if (!d) return;
      e.preventDefault();
      var next = tabs[(i + d + tabs.length) % tabs.length];
      showTab(next.getAttribute('data-tab'), true);
    });
  });
  // menu.html#beef opens the Beef tab (links from the home page use this).
  var fromHash = function () {
    var id = decodeURIComponent(location.hash.replace('#', ''));
    if (!id || !document.querySelector('[data-tab="' + id.replace(/"/g, '') + '"]')) return;
    showTab(id);
    var section = document.getElementById('full-menu');
    if (section) section.scrollIntoView();
  };
  fromHash();
  window.addEventListener('hashchange', fromHash);
  $$('[data-show-tab]').forEach(function (a) {
    a.addEventListener('click', function () { showTab(a.getAttribute('data-show-tab')); });
  });

  /* ------------------------------------------------------------------
   * 3. Order slip (cart)
   *    Any button with data-add="id" data-name="…" data-price="12.50" adds a dish.
   *    Prices that are not numbers (e.g. "[00]") are allowed: the total then shows "—".
   * ------------------------------------------------------------------ */
  var currency = opt('currency', '$');
  var cart = {};
  var order = [];
  var mode = 'collection';
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var money = function (n) { return currency + n.toFixed(2); };

  var itemsBox = $('[data-cart="items"]');
  var emptyMsg = itemsBox ? itemsBox.innerHTML : '';

  var renderCart = function () {
    var count = 0, total = 0, priced = true;
    order.forEach(function (id) {
      var it = cart[id];
      count += it.qty;
      if (isNaN(it.price)) priced = false; else total += it.price * it.qty;
    });

    if (itemsBox) {
      itemsBox.innerHTML = count ? order.map(function (id) {
        var it = cart[id];
        return '<div class="t-item"><span class="t-name">' + esc(it.name) + '</span>' +
          '<span class="qty">' +
          '<button class="qbtn" type="button" data-dec="' + esc(id) + '" aria-label="Remove one ' + esc(it.name) + '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg></button>' +
          '<span style="min-width:20px;text-align:center">' + it.qty + '</span>' +
          '<button class="qbtn" type="button" data-inc="' + esc(id) + '" aria-label="Add one ' + esc(it.name) + '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></button>' +
          '</span></div>';
      }).join('') : emptyMsg;
    }

    $$('[data-cart="count"]').forEach(function (el) { el.textContent = count; });
    $$('[data-cart="total"]').forEach(function (el) { el.textContent = count ? (priced ? money(total) : '—') : money(0); });
    $$('[data-cart-badge]').forEach(function (el) { el.textContent = count; el.hidden = !count; });

    $$('[data-add]').forEach(function (b) {
      var q = cart[b.getAttribute('data-add')] ? cart[b.getAttribute('data-add')].qty : 0;
      var round = b.classList.contains('add-round');
      b.textContent = q ? (round ? '×' + q : 'Added · ' + q) : (round ? '+' : b.getAttribute('data-label') || 'Add to Order');
    });
  };

  var add = function (id, name, price) {
    if (!cart[id]) { cart[id] = { name: name, price: parseFloat(price), qty: 0 }; order.push(id); }
    cart[id].qty += 1;
    renderCart();
  };
  var dec = function (id) {
    if (!cart[id]) return;
    cart[id].qty -= 1;
    if (cart[id].qty <= 0) { delete cart[id]; order = order.filter(function (k) { return k !== id; }); }
    renderCart();
  };

  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-add],[data-inc],[data-dec],[data-mode]') : null;
    if (!t) return;
    if (t.hasAttribute('data-add')) {
      if (!t.getAttribute('data-label')) t.setAttribute('data-label', t.textContent.trim());
      add(t.getAttribute('data-add'), t.getAttribute('data-name'), t.getAttribute('data-price'));
    } else if (t.hasAttribute('data-inc')) {
      var id = t.getAttribute('data-inc'); add(id, cart[id].name, cart[id].price);
    } else if (t.hasAttribute('data-dec')) {
      dec(t.getAttribute('data-dec'));
    } else if (t.hasAttribute('data-mode')) {
      setMode(t.getAttribute('data-mode'));
    }
  });

  var setMode = function (m) {
    mode = m;
    var col = m === 'collection';
    $$('[data-mode]').forEach(function (b) {
      var on = b.getAttribute('data-mode') === m;
      b.classList.toggle('on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    $$('[data-cart="mode-label"]').forEach(function (el) { el.textContent = col ? 'Ready for collection' : 'Estimated delivery'; });
    $$('[data-cart="mode-time"]').forEach(function (el) { el.textContent = '~' + (col ? opt('prep-time', '20–30') : opt('delivery-time', '40–50')) + ' min'; });
    $$('[data-cart="mode-short"]').forEach(function (el) { el.textContent = col ? 'Collection' : 'Delivery'; });
  };

  // Remember the order between the home and menu pages (and the next visit).
  var STORE = 'inkwok-order-v1';
  var save = function () {
    try { localStorage.setItem(STORE, JSON.stringify({ cart: cart, order: order, mode: mode, t: Date.now() })); } catch (e) { /* private mode: fine */ }
  };
  try {
    var saved = JSON.parse(localStorage.getItem(STORE) || 'null');
    if (saved && saved.cart && Array.isArray(saved.order) && Date.now() - (saved.t || 0) < 864e5) {
      saved.order.forEach(function (id) {
        var it = saved.cart[id];
        if (it && it.qty > 0) { cart[id] = { name: String(it.name), price: parseFloat(it.price), qty: Math.min(99, it.qty | 0) }; order.push(id); }
      });
      if (saved.mode === 'delivery') setMode('delivery');
    }
  } catch (e) { /* storage unavailable or corrupt: start empty */ }

  var clearBtn = $('[data-cart-clear]');
  var baseRender = renderCart;
  renderCart = function () {
    baseRender();
    var count = order.reduce(function (n, id) { return n + cart[id].qty; }, 0);
    if (clearBtn) clearBtn.hidden = !count;
    save();
  };
  var baseMode = setMode;
  setMode = function (m) { baseMode(m); save(); };
  if (clearBtn) clearBtn.addEventListener('click', function () { cart = {}; order = []; renderCart(); });

  var cta = $('[data-cart="cta"]');
  if (cta && opt('order-url', '')) cta.setAttribute('href', opt('order-url', '#'));
  renderCart();

  /* ------------------------------------------------------------------
   * 4. Background music — a soft guzheng-style melody on the Chinese
   *    pentatonic scale, synthesised in the browser (plucked-string model
   *    + hall reverb). No audio file, nothing to license. Plays automatically
   *    (on the first tap/click/key press — browsers allow nothing sooner);
   *    see motion.js. The Music button turns it off and that choice is kept.
   * ------------------------------------------------------------------ */
  var musicBtn = $('.music-btn');
  if (musicBtn && opt('music', 'on') === 'off') musicBtn.hidden = true;
  var engine = null, playing = false;
  var stored = function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } };
  var store = function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} };

  var makeEngine = function () {
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    var ctx = new AC(), sr = ctx.sampleRate;
    var master = ctx.createGain(); master.gain.value = 0; master.connect(ctx.destination);
    var dry = ctx.createGain(); dry.gain.value = 0.75; dry.connect(master);
    var verb = ctx.createConvolver(), wet = ctx.createGain(); wet.gain.value = 0.45;
    verb.connect(wet); wet.connect(master);
    var ir = ctx.createBuffer(2, sr * 3.2, sr);
    for (var c = 0; c < 2; c++) { var d = ir.getChannelData(c); for (var i = 0; i < d.length; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / d.length, 3.2); }
    verb.buffer = ir;

    var cache = {};
    var pluck = function (hz) {
      if (cache[hz]) return cache[hz];
      var len = Math.floor(sr * 3.4), buf = ctx.createBuffer(1, len, sr), y = buf.getChannelData(0);
      var N = Math.max(2, Math.round(sr / hz)), last = 0, k;
      for (k = 0; k < N; k++) { last = last * 0.55 + (Math.random() * 2 - 1) * 0.45; y[k] = last; }
      var decay = hz > 600 ? 0.9965 : 0.998;
      for (k = N; k < len; k++) y[k] = decay * 0.5 * (y[k - N] + y[Math.max(0, k - N - 1)]);
      return (cache[hz] = buf);
    };
    var play = function (hz, t, vel, bend) {
      var s = ctx.createBufferSource(); s.buffer = pluck(hz);
      if (bend) { s.playbackRate.setValueAtTime(1, t + 0.25); s.playbackRate.linearRampToValueAtTime(1.0595, t + 0.55); s.playbackRate.linearRampToValueAtTime(1, t + 1.1); }
      var g = ctx.createGain(); g.gain.value = vel;
      var tone = ctx.createBiquadFilter(); tone.type = 'lowpass'; tone.frequency.value = piece.tone;
      s.connect(tone); tone.connect(g); g.connect(dry); g.connect(verb);
      s.start(t); s.stop(t + 3.4);
    };
    // Four pieces in the same plucked-string voice. Skip moves to the next one.
    var PIECES = [
      { name: 'Morning Tea',   // D gong pentatonic, unhurried
        scale: [293.66, 329.63, 369.99, 440, 493.88, 587.33, 659.25, 739.99, 880, 987.77],
        roots: [146.83, 110, 123.47], durs: [0.5, 0.5, 0.75, 1, 1, 1.5], pace: 1.15, rest: [1.8, 2.4], tone: 3200, wet: 0.45 },
      { name: 'Lantern Night', // A yu (minor) pentatonic, slow and dark
        scale: [220, 261.63, 293.66, 329.63, 392, 440, 523.25, 587.33, 659.25, 783.99],
        roots: [110, 98, 130.81], durs: [0.75, 1, 1, 1.5, 2], pace: 1.35, rest: [2.4, 3.2], tone: 2400, wet: 0.6 },
      { name: 'River Market',  // G gong pentatonic, flowing and bright
        scale: [392, 440, 493.88, 587.33, 659.25, 783.99, 880, 987.77, 1174.66, 1318.51],
        roots: [196, 146.83, 164.81], durs: [0.25, 0.5, 0.5, 0.5, 0.75, 1], pace: 0.95, rest: [1.2, 1.8], tone: 3800, wet: 0.38 },
      { name: 'Mountain Mist', // E shang pentatonic, low register
        scale: [164.81, 185, 220, 246.94, 277.18, 329.63, 370, 440, 493.88, 554.37],
        roots: [82.41, 110, 123.47], durs: [0.75, 1, 1, 1.5], pace: 1.25, rest: [2, 3], tone: 2000, wet: 0.55 }
    ];
    var pieceAt = Math.min(PIECES.length - 1, Math.max(0, parseInt(stored('mck-piece'), 10) || 0));
    var piece = PIECES[pieceAt];
    var scale = piece.scale, roots = piece.roots, durs = piece.durs;
    var timer = 0, next = 0, idx = 4, running = false;
    var pick = function (a) { return a[Math.floor(Math.random() * a.length)]; };
    var phrase = function () {
      if (!running) return;
      var t = Math.max(next, ctx.currentTime + 0.05);
      play(pick(roots), t, 0.32, false);
      var n = 4 + Math.floor(Math.random() * 4);
      for (var k = 0; k < n; k++) {
        idx = Math.min(scale.length - 1, Math.max(0, idx + pick([-2, -1, -1, 1, 1, 2])));
        if (Math.random() < 0.2 && idx > 0) play(scale[idx - 1], t - 0.07, 0.16, false); // grace note
        play(scale[idx], t, 0.26 + Math.random() * 0.1, Math.random() < 0.18);
        t += pick(durs) * piece.pace;
      }
      next = t + piece.rest[0] + Math.random() * piece.rest[1]; // breathe between phrases
      timer = setTimeout(phrase, (next - ctx.currentTime - 0.4) * 1000);
    };
    var vol = parseFloat(stored('mck-volume'));
    if (isNaN(vol)) vol = 0.7;
    var level = function () { return 0.46 * vol; };          // 70% ≈ the original loudness
    var setPiece = function (i) {
      pieceAt = (i + PIECES.length) % PIECES.length; piece = PIECES[pieceAt];
      scale = piece.scale; roots = piece.roots; durs = piece.durs; idx = 4; cache = {};
      wet.gain.setTargetAtTime(piece.wet, ctx.currentTime, 0.3);
      store('mck-piece', String(pieceAt));
    };
    wet.gain.value = piece.wet;
    return {
      start: function () { ctx.resume(); running = true; master.gain.cancelScheduledValues(ctx.currentTime); master.gain.setTargetAtTime(level(), ctx.currentTime, 0.8); next = 0; phrase(); },
      stop: function () { running = false; clearTimeout(timer); master.gain.cancelScheduledValues(ctx.currentTime); master.gain.setTargetAtTime(0, ctx.currentTime, 0.4); },
      volume: function (v) {
        vol = Math.min(1, Math.max(0, v)); store('mck-volume', vol.toFixed(2));
        if (running) { master.gain.cancelScheduledValues(ctx.currentTime); master.gain.setTargetAtTime(level(), ctx.currentTime, 0.08); }
      },
      getVolume: function () { return vol; },
      // Skip: fade out, change piece, fade the new one in.
      next: function () {
        clearTimeout(timer);
        master.gain.cancelScheduledValues(ctx.currentTime);
        master.gain.setTargetAtTime(0, ctx.currentTime, 0.15);
        setPiece(pieceAt + 1);
        timer = setTimeout(function () {
          if (!running) return;
          next = 0; master.gain.setTargetAtTime(level(), ctx.currentTime, 0.6); phrase();
        }, 550);
        return piece.name;
      },
      pieceName: function () { return piece.name; }
    };
  };

  if (musicBtn) {
    var label = $('.music-text', musicBtn);
    // Skip + volume: a small pill beside the seal, shown while music plays.
    var ctl = document.createElement('div');
    ctl.className = 'music-ctl';
    ctl.setAttribute('role', 'group');
    ctl.setAttribute('aria-label', 'Music controls');
    ctl.hidden = true;
    ctl.innerHTML = '<button type="button" class="music-skip" aria-label="Next piece">'
      + '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 5l9 7-9 7z"/><path d="M18 5v14"/></svg></button>'
      + '<label class="music-vol"><span class="sr-only">Music volume</span>'
      + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9z"/><path d="M16.5 8.5a5 5 0 0 1 0 7"/></svg>'
      + '<input type="range" min="0" max="100" step="1" value="70"></label>'
      + '<span class="music-name" aria-live="polite"></span>';
    musicBtn.insertAdjacentElement('afterend', ctl);
    var range = $('input', ctl), nameEl = $('.music-name', ctl), skipBtn = $('.music-skip', ctl);
    var paint = function () { range.style.setProperty('--fill', range.value + '%'); };
    var showName = function (n) {
      nameEl.textContent = n; skipBtn.setAttribute('aria-label', 'Next piece (now playing: ' + n + ')');
      ctl.classList.add('show-name'); clearTimeout(showName.t);
      showName.t = setTimeout(function () { ctl.classList.remove('show-name'); }, 2600);
    };
    range.addEventListener('input', function () { paint(); if (engine) engine.volume(range.value / 100); });
    skipBtn.addEventListener('click', function () { if (engine && playing) showName(engine.next()); });

    // The pill fades away when you click or tap anywhere else; hover/focus the seal (or tap it on a phone) to bring it back.
    var openedAt = 0, closeTimer = 0, lastPointer = '';
    var openCtl = function () {
      if (!playing) return;
      clearTimeout(closeTimer);
      ctl.classList.remove('is-closing');
      if (ctl.hidden) { ctl.hidden = false; openedAt = performance.now(); }
    };
    var closeCtl = function () {
      if (ctl.hidden || ctl.classList.contains('is-closing')) return;
      ctl.classList.add('is-closing');
      closeTimer = setTimeout(function () { ctl.hidden = true; ctl.classList.remove('is-closing'); }, 650);
    };
    document.addEventListener('pointerdown', function (e) {
      if (ctl.hidden || performance.now() - openedAt < 300) return;     // ignore the tap that just opened it
      if (ctl.contains(e.target) || musicBtn.contains(e.target)) return;
      closeCtl();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeCtl(); });
    musicBtn.addEventListener('pointerenter', function (e) { if (e.pointerType === 'mouse') openCtl(); });
    musicBtn.addEventListener('focus', function () { if (musicBtn.matches(':focus-visible')) openCtl(); });
    musicBtn.addEventListener('pointerdown', function (e) { lastPointer = e.pointerType; });

    musicBtn.addEventListener('click', function () {
      engine = engine || makeEngine();
      if (!engine) return;
      // Phones: while playing with the controls tucked away, the first tap brings them back instead of stopping.
      var touch = lastPointer === 'touch' || lastPointer === 'pen'; lastPointer = '';
      if (playing && touch && (ctl.hidden || ctl.classList.contains('is-closing'))) { openCtl(); return; }
      playing = !playing;
      playing ? engine.start() : engine.stop();
      musicBtn.classList.toggle('on', playing);
      musicBtn.setAttribute('aria-pressed', playing ? 'true' : 'false');
      musicBtn.setAttribute('aria-label', playing ? 'Turn background music off' : 'Play soft traditional music');
      if (label) label.textContent = playing ? 'Music on' : 'Play music';
      if (playing) { openCtl(); range.value = Math.round(engine.getVolume() * 100); paint(); showName(engine.pieceName()); }
      else { clearTimeout(closeTimer); ctl.classList.remove('is-closing'); ctl.hidden = true; }
    });
  }

  /* ------------------------------------------------------------------
   * 5. Chopstick cursor — follows the mouse, pinches over clickable things
   *    and on press, and after 2 seconds of stillness drifts and clacks.
   *    Mouse only: touch and pen keep the system behaviour.
   * ------------------------------------------------------------------ */
  if (opt('cursor', 'on') !== 'off' && window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    var el = document.createElement('div');
    el.id = 'chop-cursor';
    el.setAttribute('aria-hidden', 'true');
    el.innerHTML = '<svg viewBox="0 0 32 32">' +
      '<polygon points="3.5,1.5 29.3,26.8 26.7,29.2 2.5,2.5" fill="#B8291E" stroke="#1E1B18" stroke-width=".7" stroke-linejoin="round"/>' +
      '<polygon points="23.6,21.2 25.6,23.3 23.4,25.5 21.4,23.4" fill="#C9A15B"/>' +
      '<g class="lower"><polygon points="2.5,4.6 24.4,28.8 21.6,31.2 1.5,5.4" fill="#B8291E" stroke="#1E1B18" stroke-width=".7" stroke-linejoin="round"/>' +
      '<polygon points="19.6,23.5 21.3,25.5 18.9,27.5 17.2,25.5" fill="#C9A15B"/></g>' +
      '<ellipse class="rice" cx="3.6" cy="3.4" rx="2.6" ry="1.6" fill="#FBF6EC" stroke="#1E1B18" stroke-width=".7"/>' +
      '</svg>';
    body.appendChild(el);
    var CLICKABLE = 'a,button,[role="button"],[role="tab"],summary,select,label';
    var TYPING = 'input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]),textarea';
    var idleTimer = 0;
    var goIdle = function () { el.classList.add('idle'); };
    document.addEventListener('pointermove', function (e) {
      if (e.pointerType && e.pointerType !== 'mouse') { el.classList.remove('show'); return; }
      document.documentElement.classList.add('chop-on');
      el.style.transform = 'translate(' + (e.clientX - 2.3) + 'px,' + (e.clientY - 3.4) + 'px)';
      var t = e.target instanceof Element ? e.target : null;
      var typing = !!(t && t.closest(TYPING));
      el.classList.toggle('show', !typing);
      el.classList.toggle('hold', !!(t && !typing && t.closest(CLICKABLE)));
      el.classList.remove('idle');
      clearTimeout(idleTimer);
      idleTimer = setTimeout(goIdle, 2000);
    }, { passive: true });
    document.addEventListener('pointerdown', function (e) { if (e.pointerType === 'mouse') { el.classList.add('pinch'); el.classList.remove('idle'); } }, { passive: true });
    document.addEventListener('pointerup', function () { el.classList.remove('pinch'); clearTimeout(idleTimer); idleTimer = setTimeout(goIdle, 2000); }, { passive: true });
    document.documentElement.addEventListener('mouseleave', function () { el.classList.remove('show', 'idle'); clearTimeout(idleTimer); });
  }

  /* ------------------------------------------------------------------
   * 6. Order panel on phones and tablets: the slip slides up from the
   *    bottom bar instead of sitting below the whole menu.
   * ------------------------------------------------------------------ */
  var ticket = $('.ticket');
  var sheetMQ = window.matchMedia ? window.matchMedia('(max-width: 1180px)') : { matches: false };
  var backdrop = null, lastFocus = null;
  // The menu section is its own layer, so on phones and tablets the slip moves to the top level of the
  // page (above the bottom bar and the dimmed backdrop) and goes back into its column on desktop.
  var home = ticket ? document.createComment('order slip') : null;
  var place = function () {
    if (!ticket) return;
    if (sheetMQ.matches && ticket.parentNode !== body) {
      ticket.parentNode.insertBefore(home, ticket);
      body.appendChild(ticket);
    } else if (!sheetMQ.matches && home.parentNode) {
      home.parentNode.insertBefore(ticket, home);
      home.remove();
    }
  };
  place();
  var closeSheet = function () {
    if (!ticket || !ticket.classList.contains('open')) return;
    ticket.classList.remove('open');
    ticket.removeAttribute('role'); ticket.removeAttribute('aria-modal');
    document.documentElement.classList.remove('sheet-open');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  };
  var openSheet = function () {
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'sheet-backdrop';
      backdrop.addEventListener('click', closeSheet);
      body.appendChild(backdrop);
    }
    lastFocus = document.activeElement;
    ticket.classList.add('open');
    ticket.setAttribute('role', 'dialog'); ticket.setAttribute('aria-modal', 'true');
    document.documentElement.classList.add('sheet-open');
    var c = $('.t-close', ticket); if (c) c.focus();
  };
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-open-order], .t-close') : null;
    if (!t || !ticket) return;
    if (t.classList.contains('t-close')) { closeSheet(); return; }
    if (sheetMQ.matches) { e.preventDefault(); openSheet(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSheet(); });
  if (sheetMQ.addEventListener) sheetMQ.addEventListener('change', function (q) { if (!q.matches) closeSheet(); place(); });

  /* ------------------------------------------------------------------
   * 7. Menu search and filters (Vegetarian, Spicy). While searching,
   *    matching dishes from every category are shown together.
   * ------------------------------------------------------------------ */
  var search = $('[data-menu-search]');
  var chips = $$('[data-filter]');
  var results = $('[data-menu-results]');
  var panels = $$('[role="tabpanel"]');
  var filter = '';
  var activeTab = function () { var a = $('[data-tab].is-active'); return a ? a.getAttribute('data-tab') : ''; };
  var norm = function (v) { return (v || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
  var applyFilter = function () {
    var q = norm(search ? search.value.trim() : '');
    var on = !!(q || filter);
    var shown = 0;
    panels.forEach(function (p) {
      var n = 0;
      $$('.dish', p).forEach(function (d) {
        var hit = !on || ((!q || norm(d.textContent).indexOf(q) > -1) && (!filter || d.querySelector('.' + filter)));
        d.hidden = !hit;
        if (hit) n++;
      });
      shown += n;
      p.hidden = on ? n === 0 : p.id !== 'panel-' + activeTab();
    });
    tabs.forEach(function (t) { t.classList.toggle('dimmed', on); });
    if (results) {
      results.hidden = !on;
      results.textContent = !on ? '' : shown ? shown + (shown === 1 ? ' dish matches' : ' dishes match') : 'No dishes match. Try another word, or clear the filters.';
    }
  };
  var resetFilter = function () {
    if (search) search.value = '';
    filter = '';
    chips.forEach(function (c) { var on = c.getAttribute('data-filter') === ''; c.classList.toggle('on', on); c.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    applyFilter();
  };
  if (search) {
    var timer = 0;
    search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(applyFilter, 120); });
    search.addEventListener('keydown', function (e) { if (e.key === 'Escape' && search.value) { e.stopPropagation(); resetFilter(); } });
  }
  chips.forEach(function (c) {
    c.addEventListener('click', function () {
      filter = c.getAttribute('data-filter');
      chips.forEach(function (o) { var on = o === c; o.classList.toggle('on', on); o.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      applyFilter();
    });
  });
  // Picking a category ends a search, so the tabs always mean what they say.
  tabs.forEach(function (t) { t.addEventListener('click', function () { if ((search && search.value) || filter) resetFilter(); }, true); });

  /* ------------------------------------------------------------------
   * 8. Map: a drawn map until someone asks for the live Google map,
   *    which saves about 1 MB on every visit.
   * ------------------------------------------------------------------ */
  $$('[data-map-src]').forEach(function (b) {
    b.addEventListener('click', function () {
      var f = document.createElement('iframe');
      f.className = 'map-embed';
      f.src = b.getAttribute('data-map-src');
      f.title = b.getAttribute('data-map-title') || 'Map';
      f.setAttribute('allowfullscreen', '');
      f.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
      b.parentNode.insertBefore(f, b);
      b.remove();
    });
  });

  /* ------------------------------------------------------------------
   * 9. Cart page (checkout.html): the order from the slip plus the guest's
   *    name, collection/delivery, time and notes, sent to the restaurant
   *    as a WhatsApp message. No online payment and no order server.
   * ------------------------------------------------------------------ */
  var coForm = $('[data-checkout-form]');
  if (coForm) {
    var coBox = $('[data-checkout]');
    var fee = parseFloat(coBox.getAttribute('data-fee')) || 0;
    var minDel = parseFloat(coBox.getAttribute('data-min')) || 0;
    var coErr = $('[data-checkout-error]');
    var coBtn = $('[data-checkout-submit]');
    var showErr = function (m) { coErr.textContent = m; coErr.hidden = !m; if (m) coErr.scrollIntoView({ block: 'center' }); };
    var modeNow = function () { var r = coForm.querySelector('input[name="mode"]:checked'); return r ? r.value : 'collection'; };
    var lineCount = function () { return order.reduce(function (n, id) { return n + cart[id].qty; }, 0); };

    var drawSummary = function () {
      if (!lineCount()) { coBox.hidden = true; $('[data-checkout-empty]').hidden = false; return; }
      var sub = 0, priced = true;
      $('[data-co-lines]').innerHTML = order.map(function (id) {
        var it = cart[id]; var line = it.price * it.qty;
        if (isNaN(line)) priced = false; else sub += line;
        return '<div class="co-line"><span class="co-name">' + esc(it.name) + '</span>'
          + '<span class="co-qty"><button type="button" class="qbtn" data-co-step="-1" data-id="' + esc(id) + '" aria-label="One less ' + esc(it.name) + '">−</button>'
          + '<b aria-live="polite">' + it.qty + '</b>'
          + '<button type="button" class="qbtn" data-co-step="1" data-id="' + esc(id) + '" aria-label="One more ' + esc(it.name) + '">+</button></span>'
          + '<span class="co-price">' + (isNaN(line) ? '—' : money(line)) + '</span></div>';
      }).join('');
      var del = modeNow() === 'delivery';
      var f = del ? fee : 0;
      $('[data-co-sub]').textContent = priced ? money(sub) : '—';
      $('[data-co-fee-row]').hidden = !(del && fee > 0);
      $('[data-co-fee]').textContent = money(fee);
      var total = priced ? money(sub + f) : '—';
      $('[data-co-grand]').textContent = total;
      var ct = $('[data-co-total]'); if (ct) ct.textContent = total;
      $$('[data-delivery-only]').forEach(function (el) { el.hidden = !del; var i = el.querySelector('textarea,input'); if (i) i.required = del; });
      if (del && minDel > 0 && priced && sub < minDel) showErr('The minimum order for delivery is ' + money(minDel) + '. Add a little more, or choose collection.');
      else if (coErr.textContent.indexOf('minimum order') > -1) showErr('');
    };

    // Collection / delivery follows the choice made in the order slip.
    var pre = coForm.querySelector('input[name="mode"][value="' + mode + '"]');
    if (pre) pre.checked = true;
    coForm.addEventListener('change', function (e) {
      if (e.target.name === 'mode') { setMode(e.target.value); drawSummary(); }
      if (e.target.name === 'when') { var later = e.target.value === 'later'; $$('[data-later-only]').forEach(function (el) { el.hidden = !later; el.querySelector('input').required = later; }); }
    });
    drawSummary();

    // Change quantities right in the cart; 0 removes the dish.
    $('[data-co-lines]').addEventListener('click', function (e) {
      var b = e.target.closest('[data-co-step]');
      if (!b) return;
      var id = b.getAttribute('data-id'), it = cart[id];
      if (!it) return;
      it.qty = Math.min(99, it.qty + parseInt(b.getAttribute('data-co-step'), 10));
      if (it.qty <= 0) { delete cart[id]; order = order.filter(function (x) { return x !== id; }); }
      renderCart();
      drawSummary();
      var again = $('[data-co-step="' + b.getAttribute('data-co-step') + '"][data-id="' + id + '"]');
      if (again) again.focus();
    });

    // Send the order as a WhatsApp message to the restaurant.
    coForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showErr('');
      if (!lineCount()) { drawSummary(); return; }
      if (!coForm.reportValidity()) return;
      var wa = coForm.getAttribute('data-wa');
      if (!wa) { showErr('WhatsApp ordering is not set up yet. Please call us to order.'); return; }
      var url = 'https://wa.me/' + wa + '?text=' + encodeURIComponent(waText(coForm, coForm.getAttribute('data-brand')));
      var w = window.open(url, '_blank', 'noopener');
      if (!w) window.location.href = url;
      var sent = $('[data-co-sent]'); if (sent) sent.hidden = false;
    });
  }

  /* ------------------------------------------------------------------
   * 10. "Send order on WhatsApp": the cart typed out in a chat with the
   *     restaurant, plus whatever the guest has filled in on the form.
   *     Also offered when online ordering is switched off.
   * ------------------------------------------------------------------ */
  function waText(form, brand) {
    var data = form ? new FormData(form) : null;
    var get = function (k) { return data ? String(data.get(k) || '').trim() : ''; };
    var hi = 'Hi ' + brand;
    if (!order.length) return hi + ', I would like to place an order.';
    var del = (get('mode') || mode) === 'delivery', sub = 0, priced = true;
    var lines = order.map(function (id) {
      var it = cart[id]; if (isNaN(it.price)) priced = false; else sub += it.price * it.qty;
      return it.qty + ' \u00d7 ' + it.name;
    });
    var grand = $('[data-co-grand]');
    var total = grand && form ? grand.textContent : (priced ? money(sub) : '');
    var msg = [hi + (get('name') ? ', this is ' + get('name') + '.' : ',') + ' I would like to order:', ''].concat(lines, ['']);
    if (total && total !== '\u2014') msg.push('Total: ' + total);
    msg.push((del ? 'Delivery' : 'Collection') + ', ' + (get('when') === 'later' && get('time') ? 'at ' + get('time') : 'as soon as possible'));
    if (del && get('address')) msg.push('Address: ' + get('address'));
    if (get('name')) msg.push('Name: ' + get('name'));
    if (get('notes')) msg.push('Notes: ' + get('notes'));
    return msg.join('\n');
  }
  $$('[data-wa-order]').forEach(function (waBtn) {
    waBtn.addEventListener('click', function () {
      waBtn.href = 'https://wa.me/' + waBtn.getAttribute('data-wa') + '?text=' + encodeURIComponent(waText(waBtn.closest('form'), waBtn.getAttribute('data-brand')));
    });
  });
})();

/* Phones: the bottom order bar waits until the hero's own buttons have scrolled out of view */
(function () {
  var ctas = document.querySelector('.hero-ctas');
  if (!ctas) return;
  var root = document.documentElement, ticking = false;
  function check() {
    ticking = false;
    var r = ctas.getBoundingClientRect();
    root.classList.toggle('hero-cta-visible', r.bottom > 0 && r.top < window.innerHeight);
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; setTimeout(check, 60); } }, { passive: true });
  window.addEventListener('resize', check);
  check();
})();

/* Back to top: appears once the visitor has scrolled down a screen or so */
(function () {
  var b = document.createElement('button');
  b.type = 'button';
  b.className = 'to-top';
  b.setAttribute('aria-label', 'Back to top');
  b.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"></path><path d="M5 12l7-7 7 7"></path></svg>';
  var music = document.querySelector('.music-btn');
  if (!music || music.hidden || getComputedStyle(music).display === 'none') b.classList.add('solo');
  (document.querySelector('.site') || document.body).appendChild(b);
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  b.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: still ? 'auto' : 'smooth' });
    var skip = document.querySelector('.brand');
    if (skip) skip.focus({ preventScroll: true });
  });
  var ticking = false;
  var check = function () { ticking = false; b.classList.toggle('show', window.scrollY > Math.max(600, window.innerHeight)); };
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; setTimeout(check, 80); } }, { passive: true });
  check();
})();

/* Hero slideshow: crossfades every 6 seconds; markers jump to a photo; pauses on hover, keyboard focus,
   the pause button, a hidden tab, and (from the start) for visitors who prefer reduced motion */
(function () {
  var box = document.querySelector('[data-hero-slides]');
  if (!box) return;
  var frame = box.parentNode;
  var slides = box.querySelectorAll('.hero-img');
  var dots = frame.querySelectorAll('.hero-dot');
  var pauseBtn = frame.querySelector('.hero-pause');
  var cur = 0, timer = 0, hover = false;
  var stopped = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var show = function (n) {
    cur = (n + slides.length) % slides.length;
    for (var i = 0; i < slides.length; i++) {
      slides[i].classList.toggle('is-on', i === cur);
      if (i === cur) slides[i].removeAttribute('aria-hidden'); else slides[i].setAttribute('aria-hidden', 'true');
      if (dots[i]) { if (i === cur) dots[i].setAttribute('aria-current', 'true'); else dots[i].removeAttribute('aria-current'); }
    }
  };
  var run = function () {
    clearInterval(timer);
    if (!stopped && !hover && !document.hidden) timer = setInterval(function () { show(cur + 1); }, 6000);
  };
  var setStopped = function (v) {
    stopped = v;
    if (pauseBtn) {
      pauseBtn.setAttribute('aria-pressed', v ? 'true' : 'false');
      pauseBtn.setAttribute('aria-label', v ? 'Play the slideshow' : 'Pause the slideshow');
    }
    run();
  };
  for (var i = 0; i < dots.length; i++) {
    dots[i].addEventListener('click', function () { show(parseInt(this.getAttribute('data-slide'), 10)); run(); });
  }
  if (pauseBtn) pauseBtn.addEventListener('click', function () { setStopped(!stopped); });
  frame.addEventListener('mouseenter', function () { hover = true; run(); });
  frame.addEventListener('mouseleave', function () { hover = false; run(); });
  frame.addEventListener('focusin', function () { hover = true; run(); });
  frame.addEventListener('focusout', function () { hover = false; run(); });
  document.addEventListener('visibilitychange', run);
  setStopped(stopped);
})();

/* Phones: the signature dishes slide one at a time; arrows and a 01 / 10 counter sit under the slider */
(function () {
  var g = document.querySelector('.gallery');
  if (!g) return;
  var cards = g.querySelectorAll('.work');
  if (cards.length < 2) return;
  var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
  var arrow = function (d) { return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + d + '"></path></svg>'; };
  var nav = document.createElement('div');
  nav.className = 'gallery-nav';
  nav.innerHTML = '<button type="button" class="g-prev" aria-label="Previous dish">' + arrow('M15 5l-7 7 7 7') + '</button>'
    + '<span class="g-count" aria-live="polite"><b>01</b> / ' + pad(cards.length) + '</span>'
    + '<button type="button" class="g-next" aria-label="Next dish">' + arrow('M9 5l7 7-7 7') + '</button>';
  g.parentNode.insertBefore(nav, g.nextSibling);
  var prev = nav.querySelector('.g-prev'), next = nav.querySelector('.g-next'), num = nav.querySelector('.g-count b');
  var current = function () {
    var mid = g.getBoundingClientRect().left + g.clientWidth / 2, best = 0, dist = Infinity;
    for (var i = 0; i < cards.length; i++) {
      var r = cards[i].getBoundingClientRect(), d = Math.abs(r.left + r.width / 2 - mid);
      if (d < dist) { dist = d; best = i; }
    }
    return best;
  };
  var update = function () {
    var i = current();
    num.textContent = pad(i + 1);
    prev.disabled = i === 0;
    next.disabled = i === cards.length - 1;
  };
  var go = function (i) {
    i = Math.max(0, Math.min(cards.length - 1, i));
    var r = cards[i].getBoundingClientRect(), box = g.getBoundingClientRect();
    g.scrollLeft += r.left + r.width / 2 - (box.left + g.clientWidth / 2);
    setTimeout(update, 400);
  };
  prev.addEventListener('click', function () { go(current() - 1); });
  next.addEventListener('click', function () { go(current() + 1); });
  var ticking = false;
  g.addEventListener('scroll', function () { if (!ticking) { ticking = true; setTimeout(function () { ticking = false; update(); }, 80); } }, { passive: true });
  update();
})();
