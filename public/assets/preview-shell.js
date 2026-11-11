/* Small source-only progressive enhancement. No ARBNT API or account dependencies. */
(function () {
  'use strict';
  var root = document.documentElement;
  var key = 'one-engine-preview-theme';
  try {
    var saved = localStorage.getItem(key);
    if (saved === 'dark' || saved === 'light') root.dataset.theme = saved;
  } catch (_) {}
  var toggle = document.getElementById('one-theme-toggle');
  if (!toggle) return;
  function refresh() {
    var dark = root.dataset.theme === 'dark' || (!root.dataset.theme && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
    toggle.textContent = dark ? 'الوضع النهاري' : 'الوضع الليلي';
    toggle.setAttribute('aria-pressed', String(dark));
  }
  toggle.addEventListener('click', function () {
    var dark = root.dataset.theme === 'dark' || (!root.dataset.theme && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
    root.dataset.theme = dark ? 'light' : 'dark';
    try { localStorage.setItem(key, root.dataset.theme); } catch (_) {}
    refresh();
  });
  refresh();
}());
