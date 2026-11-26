/* Ink & Wok admin: tabs, menu editor (add / reorder / delete), unsaved-changes guard. */
(function () {
  'use strict';
  var form = document.getElementById('editor');
  if (!form) return;

  /* ---------- tabs (kept in the URL hash so Save returns to the same tab) ---------- */
  var links = Array.prototype.slice.call(document.querySelectorAll('[data-tab-link]'));
  var panes = Array.prototype.slice.call(document.querySelectorAll('[data-pane]'));
  var extras = Array.prototype.slice.call(document.querySelectorAll('[data-pane-extra]'));
  var show = function (id) {
    if (!document.getElementById(id)) id = document.getElementById('orders') ? 'orders' : 'brand';
    panes.forEach(function (p) { p.hidden = p.id !== id; });
    links.forEach(function (a) { a.classList.toggle('on', a.getAttribute('data-tab-link') === id); a.setAttribute('aria-current', a.getAttribute('data-tab-link') === id ? 'page' : 'false'); });
    extras.forEach(function (x) { x.hidden = x.getAttribute('data-pane-extra') !== id; });
    form.elements.tab.value = id;
  };
  links.forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); history.replaceState(null, '', '#' + a.getAttribute('data-tab-link')); show(a.getAttribute('data-tab-link')); });
  });
  show(location.hash.replace('#', '') || (document.getElementById('orders') ? 'orders' : 'brand'));

  /* ---------- status message: success fades, problems stay until closed ---------- */
  var toast = document.querySelector('.page-msg');
  if (toast) {
    var close = document.createElement('button');
    close.type = 'button'; close.className = 'toast-close'; close.setAttribute('aria-label', 'Dismiss'); close.textContent = '✕';
    close.addEventListener('click', function () { toast.remove(); });
    toast.appendChild(close);
    if (toast.classList.contains('ok')) setTimeout(function () { toast.classList.add('fade'); setTimeout(function () { toast.remove(); }, 400); }, 6000);
  }

  /* ---------- menu editor ---------- */
  var uid = function (p) { return p + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); };
  var fromTemplate = function (id, cuid, duid) {
    var html = document.getElementById(id).innerHTML.split('__C__').join(cuid).split('__D__').join(duid || '');
    var box = document.createElement('div');
    box.innerHTML = html.trim();
    return box.firstElementChild;
  };
  var cats = document.getElementById('cats');

  document.getElementById('add-cat').addEventListener('click', function () {
    var el = fromTemplate('tpl-cat', uid('c'));
    cats.appendChild(el);
    el.querySelector('input').focus();
    dirty = true;
  });

  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!(t instanceof Element)) return;
    var add = t.closest('[data-add-dish]');
    if (add) {
      var cat = add.closest('[data-cat]');
      var row = fromTemplate('tpl-dish', cat.getAttribute('data-cuid'), uid('d'));
      cat.querySelector('[data-dishes]').appendChild(row);
      row.querySelector('input').focus();
      dirty = true;
      return;
    }
    var move = t.closest('[data-move]');
    if (move) {
      var item = move.closest('[data-dish]') || move.closest('[data-cat]');
      if (move.getAttribute('data-move') === 'up' && item.previousElementSibling) item.parentNode.insertBefore(item, item.previousElementSibling);
      if (move.getAttribute('data-move') === 'down' && item.nextElementSibling) item.parentNode.insertBefore(item.nextElementSibling, item);
      move.focus();
      dirty = true;
      return;
    }
    var rm = t.closest('[data-remove]');
    if (rm) {
      var target = rm.closest('[data-dish]') || rm.closest('[data-cat]');
      var isCat = target.hasAttribute('data-cat');
      var name = (target.querySelector('input') || {}).value || (isCat ? 'this category' : 'this dish');
      if (confirm('Delete "' + name + '"' + (isCat ? ' and all its dishes' : '') + '? This happens when you press Save & publish.')) {
        target.remove();
        dirty = true;
      }
    }
  });

  /* ---------- photo previews ---------- */
  form.addEventListener('change', function (e) {
    var input = e.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.files[0]) return;
    var host = input.closest('[data-dish]') ? input.closest('[data-dish]').querySelector('.thumb') : input.closest('.image-row');
    if (!host) return;
    var url = URL.createObjectURL(input.files[0]);
    var img = host.querySelector('img');
    if (!img) { img = document.createElement('img'); img.alt = ''; var old = host.querySelector('.noimg, .ph-mini'); if (old) old.replaceWith(img); else host.prepend(img); }
    img.src = url;
  });

  /* ---------- unsaved changes ---------- */
  var dirty = false;
  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function (e) {
    var preview = e.submitter && (e.submitter.getAttribute('formaction') || '').indexOf('?action=preview') === 0;
    if (!preview) { dirty = false; var b = e.submitter; if (b) { b.disabled = true; b.textContent = 'Publishing…'; } }
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* ---------- orders: confirm cancels, notice new orders ---------- */
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !confirm(msg)) e.preventDefault();
  });
  var ordersBox = document.querySelector('[data-orders]');
  if (ordersBox) {
    var latest = ordersBox.getAttribute('data-latest') || '';
    var title = document.title;
    setInterval(function () {
      fetch('?action=orders-check', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
        if (!d || !d.latest || d.latest <= latest) return;
        document.title = '(' + d.new + ') New order · ' + title;
        var note = ordersBox.querySelector('[data-new-orders]'); if (note) note.hidden = false;
        latest = d.latest;
        // Refresh by itself when the Orders tab is open and nothing is being typed.
        var typing = document.activeElement && /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName);
        if (!ordersBox.hidden && !typing && !dirty) location.reload();
      }).catch(function () {});
    }, 20000);
  }
})();
