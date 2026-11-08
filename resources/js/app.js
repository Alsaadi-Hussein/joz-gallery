/* Joz Gallery: storefront behaviour (vanilla, no dependencies) */
(function () {
  'use strict';

  var CART_KEY = 'joz_cart';
  var CUSTOMER_KEY = 'joz_customer';
  var CUSTOMER_FIELDS = ['name', 'phone', 'governorate_id', 'area', 'landmark'];
  var CUSTOMER_DAYS = 30;
  var DAY_MS = 86400000;
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
  function forget(key) {
    delete memory[key];
    try { localStorage.removeItem(key); } catch (e) { /* private mode */ }
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
  // The drawer is on every page and the cart page repeats it, so each part can appear twice.
  function each(selector, fn) { document.querySelectorAll(selector).forEach(fn); }

  var cart = load(CART_KEY, []);
  if (!Array.isArray(cart)) cart = [];
  cart = cart.filter(function (l) { return l && l.product_id && l.qty > 0; });

  var changeListeners = [];

  /* A finished order empties the cart and remembers the delivery details for next time. */
  var placedEl = document.querySelector('[data-order-placed]');
  if (placedEl) {
    var placed = JSON.parse(placedEl.textContent);
    placed.saved_at = Date.now();
    save(CUSTOMER_KEY, placed);
    cart = [];
    save(CART_KEY, cart);
  }

  // Someone else may share this device, so the details do not stay for ever.
  var customer = load(CUSTOMER_KEY, null);
  if (customer && !(Date.now() - customer.saved_at < CUSTOMER_DAYS * DAY_MS)) {
    forget(CUSTOMER_KEY);
    customer = null;
  }

  function sameLine(a, productId, variantId) {
    return a.product_id === productId && (a.variant_id || null) === (variantId || null);
  }

  function setCount(count) {
    document.querySelectorAll('[data-cart-count]').forEach(function (b) {
      b.textContent = count; b.hidden = count === 0;
    });
    each('[data-cart-count-text]', function (el) { el.textContent = count ? '(' + count + ')' : ''; });
  }

  function render(data) {
    setCount(data.count);
    each('[data-cart-empty]', function (el) { el.hidden = data.count > 0; });
    each('[data-cart-foot]', function (el) { el.hidden = data.count === 0; });
    each('[data-cart-subtotal]', function (el) { el.textContent = data.subtotal_formatted; });

    var html = data.lines.map(function (l) {
      var name = l.variant ? l.name + ' (' + l.variant + ')' : l.name;
      var key = esc(l.product_id + ':' + (l.variant_id || ''));
      return '<li class="cart-line">' +
        '<img src="' + esc(l.image) + '" alt="" width="76" height="92">' +
        '<div class="cart-line__info"><a class="cart-line__name" href="' + esc(l.url) + '">' + esc(name) + '</a>' +
        '<span class="cart-line__price">' + esc(l.line_total_formatted) + '</span>' +
        '<div class="qty"><button type="button" data-qty="-1" data-key="' + key + '" aria-label="' + esc(i18n.minus) + '"><svg class="ico"><use href="#i-minus"></use></svg></button>' +
        '<output>' + esc(l.qty) + '</output>' +
        '<button type="button" data-qty="1" data-key="' + key + '" data-stock="' + esc(l.stock) + '" aria-label="' + esc(i18n.plus) + '"><svg class="ico"><use href="#i-plus"></use></svg></button></div></div>' +
        '<button class="icon-btn cart-line__remove" type="button" data-remove="' + key + '" aria-label="' + esc(i18n.remove) + '"><svg class="ico"><use href="#i-trash"></use></svg></button>' +
        '</li>';
    }).join('');
    each('[data-cart-lines]', function (el) { el.hidden = data.count === 0; el.innerHTML = html; });

    var msg = i18n.waIntro + '\n' + data.lines.map(function (l) {
      return '• ' + l.name + (l.variant ? ' (' + l.variant + ')' : '') + ' × ' + l.qty + ' = ' + l.line_total_formatted;
    }).join('\n') + '\n' + i18n.subtotal + ': ' + data.subtotal_formatted;
    each('[data-cart-whatsapp]', function (el) { el.setAttribute('href', config.whatsapp + encodeURIComponent(msg)); });
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
      changeListeners.forEach(function (fn) { fn(); });
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

  document.addEventListener('click', function (ev) {
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

  /* ---------- Checkout ---------- */
  (function () {
    var form = document.querySelector('[data-checkout]');
    if (!form) return;

    var governorate = form.querySelector('[data-checkout-governorate]');
    var couponInput = form.querySelector('[data-checkout-coupon]');
    var couponMessage = form.querySelector('[data-coupon-message]');
    var discountRow = form.querySelector('[data-summary-discount-row]');
    var delivery = form.querySelector('[data-summary-delivery]');
    var submit = form.querySelector('[data-checkout-submit]');
    var submitLabel = submit.textContent;
    var coupon = couponInput.value.trim();
    var run = 0;

    function text(selector, value) { form.querySelector(selector).textContent = value; }

    function showEmpty(isEmpty) {
      form.hidden = isEmpty;
      document.querySelector('[data-checkout-empty]').hidden = !isEmpty;
    }

    function show(data) {
      showEmpty(data.count === 0);
      form.querySelector('[data-summary-lines]').innerHTML = data.lines.map(function (l) {
        var name = l.variant ? l.name + ' (' + l.variant + ')' : l.name;
        return '<li><span>' + esc(name) + ' × ' + esc(l.qty) + '</span><b>' + esc(l.line_total_formatted) + '</b></li>';
      }).join('');
      text('[data-summary-subtotal]', data.subtotal_formatted);
      text('[data-summary-discount]', '-' + data.discount_formatted);
      text('[data-summary-total]', data.total_formatted);
      discountRow.hidden = data.discount === 0;
      delivery.textContent = data.delivery_fee_formatted || delivery.getAttribute('data-empty');
      couponMessage.hidden = !data.coupon_message;
      couponMessage.textContent = data.coupon_message || '';
      couponMessage.classList.toggle('is-success', data.coupon_applied);
      couponMessage.classList.toggle('is-error', !data.coupon_applied);
    }

    function quote() {
      var current = ++run;
      fetch(config.quoteUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ items: cart, governorate_id: governorate.value || null, coupon: coupon || null })
      }).then(function (res) {
        if (!res.ok) throw new Error(res.status);
        return res.json();
      }).then(function (data) {
        if (current === run) show(data);
      }).catch(function () {
        if (current === run) toast(i18n.error);
      });
    }

    function applyCoupon() {
      coupon = couponInput.value.trim();
      quote();
    }

    // Someone who has ordered before only needs to confirm their details.
    if (customer) {
      CUSTOMER_FIELDS.forEach(function (field) {
        var el = form.elements[field];
        if (el && !el.value && customer[field]) el.value = customer[field];
      });
    }

    governorate.addEventListener('change', quote);
    form.querySelector('[data-checkout-apply]').addEventListener('click', applyCoupon);
    couponInput.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') { ev.preventDefault(); applyCoupon(); }
    });

    form.addEventListener('submit', function () {
      form.querySelector('[data-checkout-cart]').value = JSON.stringify(cart);
      submit.disabled = true;
      submit.textContent = submit.getAttribute('data-busy');
    });

    // Coming back with the back button restores the page as it was left.
    window.addEventListener('pageshow', function () {
      submit.disabled = false;
      submit.textContent = submitLabel;
    });

    changeListeners.push(quote);
    if (!cart.length) showEmpty(true);
  })();

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

  /* ---------- Section pages: tabs, swipe and auto-advance to the next section ---------- */
  // The direction is read by /assets/js/view-transition.js on the next page.
  function rememberDirection(dir) {
    try { sessionStorage.setItem('joz_vt', dir); } catch (e) { /* ignore */ }
  }

  (function () {
    var page = document.querySelector('[data-section-page]');
    if (!page) return;
    var rtl = document.documentElement.getAttribute('dir') === 'rtl';
    var tabs = Array.prototype.slice.call(page.querySelectorAll('.sec-tab'));
    var currentIndex = tabs.findIndex(function (t) { return t.getAttribute('aria-current') === 'page'; });
    var current = tabs[currentIndex];

    // Bring the current tab into view without moving the page.
    if (current) {
      var list = current.closest('[data-tabs]');
      var r = current.getBoundingClientRect(), lr = list.getBoundingClientRect();
      list.scrollLeft += (r.left + r.width / 2) - (lr.left + lr.width / 2);
    }

    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { rememberDirection(i > currentIndex ? 'forward' : 'backward'); });
    });
    page.querySelectorAll('[data-nav-dir]').forEach(function (a) {
      a.addEventListener('click', function () { rememberDirection(a.getAttribute('data-nav-dir') === 'next' ? 'forward' : 'backward'); });
    });

    var area = page.querySelector('[data-swipe-area]');
    var startX = 0, startY = 0, startT = 0, tracking = false;
    if (area) {
      area.addEventListener('touchstart', function (ev) {
        if (ev.touches.length !== 1 || ev.target.closest('.offers__rail, input, select, textarea, dialog, details')) { tracking = false; return; }
        tracking = true;
        startX = ev.touches[0].clientX; startY = ev.touches[0].clientY; startT = Date.now();
      }, { passive: true });
      area.addEventListener('touchend', function (ev) {
        if (!tracking) return;
        tracking = false;
        var t = ev.changedTouches[0];
        var dx = t.clientX - startX, dy = t.clientY - startY;
        // A clear sideways flick: far enough, mostly horizontal, reasonably quick.
        if (Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy) * 1.8 || Date.now() - startT > 800) return;
        var forward = rtl ? dx > 0 : dx < 0;
        var target = page.getAttribute(forward ? 'data-next' : 'data-prev');
        if (!target) return;
        rememberDirection(forward ? 'forward' : 'backward');
        window.location.href = target;
      }, { passive: true });
    }

    // At the end of a section, scrolling on opens the next one: once the "next
    // section" button is fully on screen and the visitor keeps scrolling down,
    // its bar fills and the next section opens. Scrolling up cancels.
    var link = page.querySelector('[data-auto-next]');
    var hint = page.querySelector('.sec-auto-hint');
    if (!link) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
      if (hint) hint.hidden = true;
      return;
    }
    var AUTO_MS = 1400, visible = false, timer = null, lastY = window.scrollY, touchY = 0;
    link.style.setProperty('--auto-ms', AUTO_MS + 'ms');
    function cancel() {
      if (timer) { clearTimeout(timer); timer = null; }
      link.classList.remove('is-arming');
    }
    function arm() {
      if (timer || !visible) return;
      link.classList.add('is-arming');
      timer = setTimeout(function () { rememberDirection('forward'); window.location.href = link.href; }, AUTO_MS);
    }
    new IntersectionObserver(function (entries) {
      visible = entries[0].intersectionRatio >= 0.9;
      if (!visible) cancel();
    }, { threshold: [0, 0.9] }).observe(link);
    window.addEventListener('scroll', function () {
      var y = window.scrollY;
      if (y < lastY - 2) cancel(); else if (y > lastY) arm();
      lastY = y;
    }, { passive: true });
    // A page too short to scroll: a downward wheel or swipe still counts as "keep going".
    window.addEventListener('wheel', function (ev) { if (ev.deltaY > 0) arm(); else if (ev.deltaY < 0) cancel(); }, { passive: true });
    window.addEventListener('touchstart', function (ev) { touchY = ev.touches[0].clientY; }, { passive: true });
    window.addEventListener('touchmove', function (ev) {
      var dy = touchY - ev.touches[0].clientY;
      if (dy > 30) arm(); else if (dy < -30) cancel();
    }, { passive: true });
  })();

  /* ---------- Listing controls ---------- */
  document.querySelectorAll('[data-autosubmit] select').forEach(function (select) {
    select.addEventListener('change', function () { select.form.submit(); });
  });

  var filters = document.querySelector('[data-filters]');
  if (filters && window.matchMedia('(min-width: 1024px)').matches) filters.open = true;
})();
