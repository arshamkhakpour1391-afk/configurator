/**
 * MobiCare shop filters UI
 */
(function () {
  'use strict';

  var filters = document.getElementById('mc-shop-filters');
  var toggle = document.getElementById('mc-filter-toggle');
  var closeBtn = document.getElementById('mc-filters-close');
  var applyBtn = document.getElementById('mc-filters-apply');

  function openFilters() {
    if (!filters) return;
    filters.classList.add('is-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }

  function closeFilters() {
    if (!filters) return;
    filters.classList.remove('is-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  if (toggle) toggle.addEventListener('click', openFilters);
  if (closeBtn) closeBtn.addEventListener('click', closeFilters);
  if (applyBtn) {
    applyBtn.addEventListener('click', function () {
      var form = filters && filters.querySelector('form.mc-filters-form');
      if (form) {
        form.submit();
      } else {
        closeFilters();
      }
    });
  }

  // Price filter auto-submit on desktop when using widget form
  document.addEventListener('change', function (e) {
    if (!e.target.closest('.mc-filter-group')) return;
    if (window.matchMedia('(min-width: 1024px)').matches) {
      var form = e.target.closest('form');
      if (form && form.classList.contains('mc-filters-form')) {
        // slight delay for checkbox UX
        setTimeout(function () { form.submit(); }, 120);
      }
    }
  });
})();
