/*
 * HV CRM — interacciones de la interfaz (JavaScript nativo, sin frameworks).
 * La autorización vive en el servidor; aquí solo se mejora la experiencia.
 */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  // ------------------------------------------------------------ CSRF para fetch()
  window.HV = window.HV || {};
  HV.csrf = function () {
    var m = $('meta[name="csrf-token"]');
    var h = $('meta[name="csrf-header"]');
    return { header: h ? h.content : 'X-CSRF-TOKEN', token: m ? m.content : '' };
  };
  HV.post = function (url, data) {
    var c = HV.csrf();
    var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[c.header] = c.token;
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: headers, body: JSON.stringify(data || {}) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (b) { return { ok: r.ok, status: r.status, body: b }; }); });
  };
  HV.toast = function (text, type) {
    var box = $('#toasts');
    if (!box) return;
    var el = document.createElement('div');
    el.className = 'pointer-events-auto flex max-w-lg items-start gap-2 rounded-xl border bg-surface px-4 py-3 text-sm shadow-lg ' + (type === 'error' ? 'border-danger text-danger' : 'border-ok');
    el.textContent = text; // textContent: nunca HTML de la respuesta
    box.appendChild(el);
    while (box.children.length > 3) box.removeChild(box.firstElementChild);
    setTimeout(function () { el.remove(); }, type === 'error' ? 7000 : 3500);
  };

  // ------------------------------------------------------------ Toasts del servidor
  $$('[data-toast]').forEach(function (el) {
    setTimeout(function () { el.remove(); }, parseInt(el.getAttribute('data-toast'), 10) || 3500);
  });

  // ------------------------------------------------------------ Tema claro/oscuro
  $$('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var root = document.documentElement;
      var current = root.getAttribute('data-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      var next = current === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('hv-theme', next); } catch (e) { /* sin almacenamiento */ }
    });
  });

  // ------------------------------------------------------------ Sidebar móvil y grupos plegables
  var sidebar = $('#sidebar');
  var veil = $('#sidebar-veil');
  function setSidebar(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('hidden', !open);
    sidebar.classList.toggle('flex', open);
    if (veil) veil.classList.toggle('hidden', !open);
  }
  $$('[data-open-sidebar]').forEach(function (b) { b.addEventListener('click', function () { setSidebar(true); }); });
  $$('[data-close-sidebar]').forEach(function (b) { b.addEventListener('click', function () { setSidebar(false); }); });

  $$('[data-nav-group]').forEach(function (btn) {
    var key = 'hv-nav-' + btn.getAttribute('data-nav-group');
    var list = document.getElementById(btn.getAttribute('aria-controls'));
    var hasActive = list && list.querySelector('[aria-current="page"]');
    var collapsed = false;
    try { collapsed = localStorage.getItem(key) === '1'; } catch (e) { /* */ }
    function apply(c) {
      btn.setAttribute('aria-expanded', c ? 'false' : 'true');
      if (list) list.hidden = c;
      var svg = btn.querySelector('svg');
      if (svg) svg.style.transform = c ? 'rotate(-90deg)' : '';
    }
    apply(collapsed && !hasActive);
    btn.addEventListener('click', function () {
      var c = btn.getAttribute('aria-expanded') === 'true';
      apply(c);
      try { localStorage.setItem(key, c ? '1' : '0'); } catch (e) { /* */ }
    });
  });

  // ------------------------------------------------------------ Dialogs (Drawer / Modal)
  function closeDialog(d) {
    d.close();
    var to = d.getAttribute('data-close-to');
    if (to) history.replaceState(null, '', to); // quita ?edit=… de la URL al cerrar
  }
  $$('[data-open-dialog]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var d = document.getElementById(btn.getAttribute('data-open-dialog'));
      if (d && !d.open) {
        d.showModal();
        var first = d.querySelector('input:not([type=hidden]),select,textarea');
        if (first) first.focus();
      }
    });
  });
  $$('dialog').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) closeDialog(d); }); // clic en el velo
    d.addEventListener('cancel', function (e) { e.preventDefault(); closeDialog(d); }); // Esc
    $$('[data-close-dialog]', d).forEach(function (b) { b.addEventListener('click', function () { closeDialog(d); }); });
  });
  $$('dialog[data-open-on-load]').forEach(function (d) { if (!d.open) d.showModal(); });

  // ------------------------------------------------------------ Confirmaciones
  var confirmDialog = $('#confirm-dialog');
  var pendingForm = null;
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') return;
    if (!confirmDialog) return; // sin diálogo, se envía normalmente
    e.preventDefault();
    pendingForm = form;
    $('#confirm-message', confirmDialog).textContent = form.getAttribute('data-confirm');
    confirmDialog.showModal();
  }, true);
  if (confirmDialog) {
    $('[data-confirm-cancel]', confirmDialog).addEventListener('click', function () { pendingForm = null; confirmDialog.close(); });
    $('[data-confirm-accept]', confirmDialog).addEventListener('click', function () {
      confirmDialog.close();
      if (pendingForm) { pendingForm.dataset.confirmed = '1'; pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit(); }
    });
  }

  // ------------------------------------------------------------ Botón en curso (gerundio, evita doble envío)
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var btn = e.target.querySelector('button[type=submit][data-busy]');
    if (btn) { btn.disabled = true; btn.textContent = btn.getAttribute('data-busy'); }
  });

  // ------------------------------------------------------------ Filtros que se aplican al instante
  $$('form[data-autosubmit] select').forEach(function (s) {
    s.addEventListener('change', function () { s.form.requestSubmit ? s.form.requestSubmit() : s.form.submit(); });
  });

  // ------------------------------------------------------------ Copiar al portapapeles
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var text = b.getAttribute('data-copy');
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () { HV.toast(b.getAttribute('data-copied') || 'OK'); });
      }
    });
  });
})();
