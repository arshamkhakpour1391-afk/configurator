/**
 * MobiCare main frontend JS
 */
(function () {
  'use strict';

  var data = window.mobicareData || {};
  var i18n = data.i18n || {};

  function $(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function $all(sel, ctx) {
    return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
  }

  function on(el, evt, fn, opts) {
    if (el) el.addEventListener(evt, fn, opts || false);
  }

  /* ---------- Theme (dark/light) ---------- */
  function getTheme() {
    try {
      return localStorage.getItem('mc-theme') || '';
    } catch (e) {
      return '';
    }
  }

  function setTheme(mode) {
    var root = document.documentElement;
    if (mode === 'dark') {
      root.setAttribute('data-theme', 'dark');
      root.classList.add('mc-dark');
    } else {
      root.setAttribute('data-theme', 'light');
      root.classList.remove('mc-dark');
    }
    try {
      localStorage.setItem('mc-theme', mode);
    } catch (e) { /* ignore */ }
  }

  function toggleTheme() {
    var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    setTheme(current === 'dark' ? 'light' : 'dark');
  }

  on($('#mc-theme-toggle'), 'click', toggleTheme);

  /* ---------- Announcement dismiss ---------- */
  on(document, 'click', function (e) {
    var btn = e.target.closest('[data-mc-dismiss-announce]');
    if (!btn) return;
    var bar = btn.closest('.mc-announce');
    if (bar) {
      bar.hidden = true;
      try { localStorage.setItem('mc-announce-dismissed', '1'); } catch (err) { /* */ }
    }
  });

  (function restoreAnnounce() {
    try {
      if (localStorage.getItem('mc-announce-dismissed') === '1') {
        var a = $('.mc-announce');
        if (a) a.hidden = true;
      }
    } catch (e) { /* */ }
  })();

  /* ---------- Mobile drawer ---------- */
  var drawer = $('#mc-mobile-nav');
  var navToggle = $('#mc-nav-toggle');

  function openDrawer() {
    if (!drawer) return;
    drawer.hidden = false;
    drawer.setAttribute('aria-hidden', 'false');
    if (navToggle) navToggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.hidden = true;
    drawer.setAttribute('aria-hidden', 'true');
    if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  on(navToggle, 'click', openDrawer);
  on(document, 'click', function (e) {
    if (e.target.closest('[data-mc-close-drawer]')) closeDrawer();
  });
  on(document, 'keydown', function (e) {
    if (e.key === 'Escape') {
      closeDrawer();
      closeModal();
    }
  });

  /* ---------- Modal (model finder) ---------- */
  var modal = $('#mc-model-finder');
  var brandsCache = null;

  function openModal() {
    if (!modal) return;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    loadPhoneModels();
  }

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  on(document, 'click', function (e) {
    if (e.target.closest('[data-mc-open-model-finder]')) {
      e.preventDefault();
      closeDrawer();
      openModal();
    }
    if (e.target.closest('[data-mc-close-modal]')) {
      closeModal();
    }
  });

  function loadPhoneModels() {
    var brandsEl = $('#mc-model-brands');
    var modelsEl = $('#mc-model-models');
    if (!brandsEl) return;

    if (brandsCache) {
      renderBrands(brandsCache);
      return;
    }

    brandsEl.innerHTML = '<p class="mc-muted">' + (i18n.loading || '...') + '</p>';

    var url = (data.ajaxUrl || '/wp-admin/admin-ajax.php') +
      '?action=mobicare_phone_models&nonce=' + encodeURIComponent(data.nonce || '');

    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success) {
          brandsEl.innerHTML = '<p class="mc-muted">' + (i18n.error || 'Error') + '</p>';
          return;
        }
        brandsCache = (res.data && res.data.brands) || [];
        renderBrands(brandsCache);
      })
      .catch(function () {
        brandsEl.innerHTML = '<p class="mc-muted">' + (i18n.error || 'Error') + '</p>';
      });

    function renderBrands(brands) {
      if (!brands.length) {
        brandsEl.innerHTML = '<p class="mc-muted">هنوز مدل گوشی‌ای تعریف نشده است. از پیشخوان وردپرس مدل‌ها را اضافه کنید.</p>';
        if (modelsEl) modelsEl.hidden = true;
        return;
      }
      brandsEl.innerHTML = '';
      brands.forEach(function (b) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mc-chip mc-chip--lg';
        btn.textContent = b.name;
        btn.addEventListener('click', function () {
          $all('.mc-chip.is-active', brandsEl).forEach(function (c) { c.classList.remove('is-active'); });
          btn.classList.add('is-active');
          renderModels(b.models || []);
        });
        brandsEl.appendChild(btn);
      });
      // Auto-select first
      if (brands[0]) {
        var first = brandsEl.querySelector('button');
        if (first) first.click();
      }
    }

    function renderModels(models) {
      if (!modelsEl) return;
      modelsEl.hidden = false;
      modelsEl.innerHTML = '';
      if (!models.length) {
        modelsEl.innerHTML = '<p class="mc-muted">مدلی برای این برند ثبت نشده.</p>';
        return;
      }
      models.forEach(function (m) {
        var a = document.createElement('a');
        a.href = m.url;
        a.className = 'mc-chip mc-chip--lg';
        a.textContent = m.name + (m.count ? ' (' + m.count + ')' : '');
        modelsEl.appendChild(a);
      });
    }
  }

  /* ---------- Toasts ---------- */
  function toast(message, type, actionHtml) {
    var box = $('#mc-toasts');
    if (!box) return;
    var el = document.createElement('div');
    el.className = 'mc-toast' + (type ? ' mc-toast--' + type : '');
    el.innerHTML = '<span>' + message + '</span>' + (actionHtml || '');
    box.appendChild(el);
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transition = 'opacity 200ms';
      setTimeout(function () { el.remove(); }, 220);
    }, 4200);
  }

  window.mobicareToast = toast;

  /* ---------- Search autocomplete ---------- */
  var searchInput = $('#mc-search-input');
  var suggestBox = $('#mc-search-suggest');
  var searchTimer = null;
  var activeIndex = -1;

  function hideSuggest() {
    if (!suggestBox) return;
    suggestBox.hidden = true;
    if (searchInput) searchInput.setAttribute('aria-expanded', 'false');
    activeIndex = -1;
  }

  function showSuggest() {
    if (!suggestBox) return;
    suggestBox.hidden = false;
    if (searchInput) searchInput.setAttribute('aria-expanded', 'true');
  }

  function renderSuggest(items) {
    if (!suggestBox) return;
    if (!items.length) {
      suggestBox.innerHTML = '<div class="mc-search__suggest-empty">' + (i18n.noResults || 'No results') + '</div>';
      showSuggest();
      return;
    }
    suggestBox.innerHTML = items.map(function (it, idx) {
      var img = it.image
        ? '<img src="' + escapeAttr(it.image) + '" alt="" width="40" height="40" loading="lazy">'
        : '<span style="width:40px;height:40px;border-radius:8px;background:var(--mc-bg-muted);display:inline-block"></span>';
      var price = it.price ? '<div class="mc-search__suggest-price">' + escapeHtml(it.price) + '</div>' : '';
      return (
        '<a href="' + escapeAttr(it.url) + '" class="mc-search__suggest-item" role="option" data-index="' + idx + '">' +
          img +
          '<div class="mc-search__suggest-meta">' +
            '<div class="mc-search__suggest-title">' + escapeHtml(it.title) + '</div>' +
            price +
          '</div>' +
        '</a>'
      );
    }).join('');
    showSuggest();
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escapeAttr(s) {
    return escapeHtml(s).replace(/'/g, '&#39;');
  }

  on(searchInput, 'input', function () {
    var q = (searchInput.value || '').trim();
    clearTimeout(searchTimer);
    if (q.length < 2) {
      hideSuggest();
      return;
    }
    searchTimer = setTimeout(function () {
      var url = (data.ajaxUrl || '') +
        '?action=mobicare_search&nonce=' + encodeURIComponent(data.nonce || '') +
        '&q=' + encodeURIComponent(q);
      fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.success) {
            renderSuggest((res.data && res.data.items) || []);
          }
        })
        .catch(function () { /* silent */ });
    }, 220);
  });

  on(searchInput, 'keydown', function (e) {
    if (!suggestBox || suggestBox.hidden) return;
    var items = $all('.mc-search__suggest-item', suggestBox);
    if (!items.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = Math.min(activeIndex + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = Math.max(activeIndex - 1, 0);
    } else if (e.key === 'Enter' && activeIndex >= 0) {
      e.preventDefault();
      items[activeIndex].click();
      return;
    } else if (e.key === 'Escape') {
      hideSuggest();
      return;
    } else {
      return;
    }
    items.forEach(function (it, i) {
      it.classList.toggle('is-active', i === activeIndex);
    });
  });

  on(document, 'click', function (e) {
    if (!e.target.closest('.mc-search')) hideSuggest();
  });

  /* ---------- Wishlist ---------- */
  function updateWishlistBadge(count) {
    var el = $('#mc-wishlist-count');
    if (!el) return;
    el.textContent = String(count);
    el.dataset.count = String(count);
    if (count > 0) el.removeAttribute('hidden');
    else el.setAttribute('hidden', '');
  }

  on(document, 'click', function (e) {
    var btn = e.target.closest('.mc-wishlist-btn');
    if (!btn) return;
    e.preventDefault();
    var pid = btn.getAttribute('data-product-id');
    if (!pid) return;
    btn.disabled = true;

    var body = new FormData();
    body.append('action', 'mobicare_wishlist_toggle');
    body.append('nonce', data.nonce || '');
    body.append('product_id', pid);

    fetch(data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        btn.disabled = false;
        if (!res || !res.success) {
          toast((res && res.data && res.data.message) || i18n.error || 'Error', 'error');
          return;
        }
        var active = !!(res.data && res.data.in_wishlist);
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        var svg = btn.querySelector('svg');
        if (svg) svg.setAttribute('fill', active ? 'currentColor' : 'none');
        if (typeof res.data.count === 'number') updateWishlistBadge(res.data.count);
        toast(active ? (i18n.addedToWishlist || 'Added') : (i18n.removedWishlist || 'Removed'), 'success');
      })
      .catch(function () {
        btn.disabled = false;
        toast(i18n.error || 'Error', 'error');
      });
  });

  /* ---------- Compare ---------- */
  on(document, 'click', function (e) {
    var btn = e.target.closest('.mc-compare-btn');
    if (!btn) return;
    e.preventDefault();
    var pid = btn.getAttribute('data-product-id');
    if (!pid) return;

    var body = new FormData();
    body.append('action', 'mobicare_compare_toggle');
    body.append('nonce', data.nonce || '');
    body.append('product_id', pid);

    fetch(data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success) {
          toast((res && res.data && res.data.message) || i18n.error || 'Error', 'error');
          return;
        }
        var msg = res.data && res.data.in_compare ? 'به مقایسه اضافه شد' : 'از مقایسه حذف شد';
        var action = '<a href="' + (window.location.origin + '/compare/') + '">مشاهده</a>';
        toast(msg, 'success', action);
      })
      .catch(function () {
        toast(i18n.error || 'Error', 'error');
      });
  });

  /* ---------- Cart count sync from WC events ---------- */
  function setCartCount(n) {
    ['#mc-cart-count', '#mc-cart-count-mobile'].forEach(function (sel) {
      var el = $(sel);
      if (!el) return;
      el.textContent = String(n);
      if (n > 0) el.removeAttribute('hidden');
      else el.setAttribute('hidden', '');
    });
  }

  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart removed_from_cart updated_cart_totals', function () {
      // Fragments usually update badges; toast on add.
    });
    window.jQuery(document.body).on('added_to_cart', function () {
      toast(i18n.addedToCart || 'Added to cart', 'success',
        '<a href="' + (data.cartUrl || '/cart/') + '">' + (i18n.viewCart || 'Cart') + '</a>');
    });
  }

  /* Expose */
  window.MobiCare = {
    toast: toast,
    openModelFinder: openModal,
    setCartCount: setCartCount,
    setTheme: setTheme,
  };
})();
