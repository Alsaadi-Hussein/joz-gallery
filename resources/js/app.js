/* Joz Gallery: storefront behaviour (vanilla, no dependencies) */
(function () {
  'use strict';

  var CART_KEY = 'joz_cart';
  var SEARCH_DELAY = 220;

  var config = JSON.parse(document.querySelector('[data-config]').textContent);
  var i18n = config.i18n;
  var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  /* ---------- Safe storage ---------- */
  var memory = {};
  function load(key, fallback) {
    try { var v = localStorage.getItem(key); return v ? JSON.parse(v) : fallback; }
    catch (e) { return key in memory ? memory[key] : fallback; }
  }
  function save(key, value) {
    memory[key] = value;
    try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* private mode */ }
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- Toast ---------- */
  var toastEl = document.querySelector('[data-toast]');
  var toastTimer;
  function toast(msg) {
    toastEl.textContent = msg;
    toastEl.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.hidden = true; }, 2600);
  }

  /* ---------- Dialogs ---------- */
  function dialog(name) { return document.querySelector('[data-dialog="' + name + '"]'); }
  document.addEventListener('click', function (ev) {
    var opener = ev.target.closest('[data-open]');
    if (opener) {
      var d = dialog(opener.getAttribute('data-open'));
      if (d && !d.open) {
        d.showModal();
        if (d.matches('[data-dialog="search"]')) d.querySelector('input').focus();
      }
      return;
    }
    var closer = ev.target.closest('[data-close]');
    if (closer) { closer.closest('dialog').close(); return; }
    if (ev.target.tagName === 'DIALOG') { ev.target.close(); return; }
    var link = ev.target.closest('dialog a[href*="#"]');
    if (link) link.closest('dialog').close();
  });

  /* ---------- Cart: the browser keeps ids and quantities, the server keeps prices ---------- */
  var linesEl = document.querySelector('[data-cart-lines]');
  var emptyEl = document.querySelector('[data-cart-empty]');
  var footEl = document.querySelector('[data-cart-foot]');
  var subtotalEl = document.querySelector('[data-cart-subtotal]');
  var countTextEl = document.querySelector('[data-cart-count-text]');
  var waEl = document.querySelector('[data-cart-whatsapp]');

  var cart = load(CART_KEY, []);
  if (!Array.isArray(cart)) cart = [];
  cart = cart.filter(function (l) { return l && l.product_id && l.qty > 0; });

  function sameLine(a, productId, variantId) {
    return a.product_id === productId && (a.variant_id || null) === (variantId || null);
  }

  function setCount(count) {
    document.querySelectorAll('[data-cart-count]').forEach(function (b) {
      b.textContent = count; b.hidden = count === 0;
    });
    countTextEl.textContent = count ? '(' + count + ')' : '';
  }

  function render(data) {
    setCount(data.count);
    emptyEl.hidden = data.count > 0;
    footEl.hidden = data.count === 0;
    linesEl.hidden = data.count === 0;
    subtotalEl.textContent = data.subtotal_formatted;

    linesEl.innerHTML = data.lines.map(function (l) {
      var name = l.variant ? l.name + ' (' + l.variant + ')' : l.name;
      var key = l.product_id + ':' + (l.variant_id || '');
      return '<li class="cart-line">' +
        '<img src="' + esc(l.image) + '" alt="" width="76" height="92">' +
        '<div class="cart-line__info"><a class="cart-line__name" href="' + esc(l.url) + '">' + esc(name) + '</a>' +
        '<span class="cart-line__price">' + esc(l.line_total_formatted) + '</span>' +
        '<div class="qty"><button type="button" data-qty="-1" data-key="' + key + '" aria-label="' + esc(i18n.minus) + '"><svg class="ico"><use href="#i-minus"></use></svg></button>' +
        '<output>' + l.qty + '</output>' +
        '<button type="button" data-qty="1" data-key="' + key + '" data-stock="' + l.stock + '" aria-label="' + esc(i18n.plus) + '"><svg class="ico"><use href="#i-plus"></use></svg></button></div></div>' +
        '<button class="icon-btn cart-line__remove" type="button" data-remove="' + key + '" aria-label="' + esc(i18n.remove) + '"><svg class="ico"><use href="#i-trash"></use></svg></button>' +
        '</li>';
    }).join('');

    var msg = i18n.waIntro + '\n' + data.lines.map(function (l) {
      return '• ' + l.name + (l.variant ? ' (' + l.variant + ')' : '') + ' × ' + l.qty + ' = ' + l.line_total_formatted;
    }).join('\n') + '\n' + i18n.subtotal + ': ' + data.subtotal_formatted;
    waEl.setAttribute('href', config.whatsapp + encodeURIComponent(msg));
  }

  var previewRun = 0;
  function refresh() {
    var run = ++previewRun;
    fetch(config.previewUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ items: cart })
    }).then(function (res) {
      if (!res.ok) throw new Error(res.status);
      return res.json();
    }).then(function (data) {
      if (run !== previewRun) return; // a newer change is already on its way
      cart = data.lines.map(function (l) {
        return { product_id: l.product_id, variant_id: l.variant_id, qty: l.qty };
      });
      save(CART_KEY, cart);
      render(data);
    }).catch(function () {
      if (run === previewRun) toast(i18n.error);
    });
  }

  function setCart(next) {
    cart = next.filter(function (l) { return l.qty > 0; });
    save(CART_KEY, cart);
    refresh();
  }

  function parseKey(key) {
    var parts = key.split(':');
    return { product: +parts[0], variant: parts[1] ? +parts[1] : null };
  }

  function changeQty(key, delta) {
    var k = parseKey(key);
    setCart(cart.map(function (l) {
      return { product_id: l.product_id, variant_id: l.variant_id, qty: sameLine(l, k.product, k.variant) ? l.qty + delta : l.qty };
    }));
  }

  linesEl.addEventListener('click', function (ev) {
    var q = ev.target.closest('[data-qty]');
    if (q) {
      var line = cart.find(function (l) { var k = parseKey(q.getAttribute('data-key')); return sameLine(l, k.product, k.variant); });
      if (+q.getAttribute('data-qty') > 0 && line && line.qty >= +q.getAttribute('data-stock')) { toast(i18n.maxStock); return; }
      changeQty(q.getAttribute('data-key'), +q.getAttribute('data-qty'));
      return;
    }
    var r = ev.target.closest('[data-remove]');
    if (r) changeQty(r.getAttribute('data-remove'), -Infinity);
  });

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-add]');
    if (!btn || btn.disabled) return;
    var productId = +btn.getAttribute('data-add');
    var variantId = +btn.getAttribute('data-variant') || null;
    var next = cart.map(function (l) { return { product_id: l.product_id, variant_id: l.variant_id, qty: l.qty }; });
    var line = next.find(function (l) { return sameLine(l, productId, variantId); });
    if (line) line.qty += 1; else next.push({ product_id: productId, variant_id: variantId, qty: 1 });
    setCart(next);
    btn.classList.add('is-added');
    setTimeout(function () { btn.classList.remove('is-added'); }, 1200);
    toast(i18n.added + ': ' + btn.getAttribute('data-name'));
  });

  if (cart.length) refresh();

  /* ---------- Instant search ---------- */
  (function () {
    var input = document.querySelector('[data-search-input]');
    var results = document.querySelector('[data-search-results]');
    var empty = document.querySelector('[data-search-empty]');
    var popular = document.querySelector('[data-search-popular]');
    var timer, run = 0;

    function show(hits) {
      empty.hidden = hits.length > 0;
      results.innerHTML = hits.map(function (p) {
        return '<li><a href="' + esc(p.url) + '"><img src="' + esc(p.image) + '" alt="" width="56" height="66">' +
          '<span><small>' + esc(p.section) + '</small>' + esc(p.name) + '<b>' + esc(p.price) + '</b></span></a></li>';
      }).join('');
    }

    function runSearch() {
      var q = input.value.trim();
      var current = ++run;
      clearTimeout(timer);
      popular.hidden = q.length > 0;
      if (!q) { results.innerHTML = ''; empty.hidden = true; return; }
      timer = setTimeout(function () {
        fetch(config.suggestUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
          .then(function (res) { return res.ok ? res.json() : { results: [] }; })
          .then(function (data) { if (current === run) show(data.results); })
          .catch(function () { /* the form still submits to the search page */ });
      }, SEARCH_DELAY);
    }

    input.addEventListener('input', runSearch);
    document.querySelectorAll('[data-search-term]').forEach(function (chip) {
      chip.addEventListener('click', function () { input.value = chip.getAttribute('data-search-term'); runSearch(); input.focus(); });
    });
    document.querySelector('[data-search-form]').addEventListener('submit', function (ev) {
      if (!input.value.trim()) ev.preventDefault();
    });
  })();

  /* ---------- Hero slider ---------- */
  var slider = document.querySelector('[data-slider]');
  if (slider) initSlider(slider);

  function initSlider(slider) {
    var track = slider.querySelector('[data-track]');
    var slides = Array.prototype.slice.call(slider.querySelectorAll('[data-slide]'));
    var dots = Array.prototype.slice.call(slider.querySelectorAll('[data-dot]'));
    var current = 0, paused = false;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function go(i) {
      i = (i + slides.length) % slides.length;
      track.scrollTo({ left: slides[i].offsetLeft - slides[0].offsetLeft, behavior: reduce ? 'auto' : 'smooth' });
    }
    function setActive(i) {
      current = i;
      slides.forEach(function (s, k) { s.classList.toggle('is-active', k === i); });
      dots.forEach(function (d, k) { d.setAttribute('aria-current', k === i ? 'true' : 'false'); });
    }
    if (!slides.length) return;
    setActive(0);

    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting && en.intersectionRatio > 0.6) setActive(slides.indexOf(en.target));
        });
      }, { root: track, threshold: [0.6] });
      slides.forEach(function (s) { io.observe(s); });
    }

    slider.querySelector('[data-prev]').addEventListener('click', function () { go(current - 1); });
    slider.querySelector('[data-next]').addEventListener('click', function () { go(current + 1); });
    dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); }); });

    function tick() {
      if (!paused && document.visibilityState === 'visible') go(current + 1);
    }
    if (!reduce) setInterval(tick, 6000);
    ['mouseenter', 'focusin', 'touchstart'].forEach(function (t) {
      slider.addEventListener(t, function () { paused = true; }, { passive: true });
    });
    ['mouseleave', 'focusout'].forEach(function (t) {
      slider.addEventListener(t, function () { paused = false; });
    });
  }

  /* ---------- Countdowns ---------- */
  (function () {
    var els = document.querySelectorAll('[data-countdown]');
    if (!els.length) return;
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function update() {
      els.forEach(function (el) {
        var left = Math.max(0, Math.floor((new Date(el.getAttribute('data-countdown')) - Date.now()) / 1000));
        var v = { d: Math.floor(left / 86400), h: Math.floor(left % 86400 / 3600), m: Math.floor(left % 3600 / 60), s: left % 60 };
        el.querySelectorAll('[data-unit]').forEach(function (b) { b.textContent = pad(v[b.getAttribute('data-unit')]); });
      });
    }
    update();
    setInterval(update, 1000);
  })();

  /* ---------- Product gallery ---------- */
  (function () {
    var stage = document.querySelector('[data-gallery-stage]');
    if (!stage) return;
    var thumbs = document.querySelectorAll('[data-gallery-thumb]');
    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        stage.src = thumb.getAttribute('data-src');
        stage.srcset = thumb.getAttribute('data-srcset');
        thumbs.forEach(function (t) { t.classList.toggle('is-current', t === thumb); });
      });
    });
  })();

  /* ---------- Variant selector ---------- */
  (function () {
    var root = document.querySelector('[data-product]');
    if (!root) return;
    var add = root.querySelector('[data-add]');
    var now = root.querySelector('[data-price-now]');
    var old = root.querySelector('[data-price-old]');
    root.querySelectorAll('[data-variant-option]').forEach(function (option) {
      option.addEventListener('change', function () {
        add.setAttribute('data-variant', option.value);
        now.textContent = option.getAttribute('data-now');
        old.textContent = option.getAttribute('data-old');
        old.hidden = !option.getAttribute('data-old');
      });
    });
  })();

  /* ---------- Listing controls ---------- */
  document.querySelectorAll('[data-autosubmit] select').forEach(function (select) {
    select.addEventListener('change', function () { select.form.submit(); });
  });

  var filters = document.querySelector('[data-filters]');
  if (filters && window.matchMedia('(min-width: 1024px)').matches) filters.open = true;
})();
