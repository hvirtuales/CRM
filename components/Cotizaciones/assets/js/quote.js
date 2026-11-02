/*
 * Editor de líneas de cotización. Calcula una VISTA PREVIA del total; el valor oficial lo calcula
 * el servidor (en centavos) al guardar. Al elegir un servicio se usa el precio del catálogo.
 */
(function () {
  'use strict';
  var form = document.querySelector('[data-quote-editor]');
  if (!form) return;
  var body = form.querySelector('[data-lines]');
  var catalog = {};
  try { JSON.parse(form.getAttribute('data-catalog') || '[]').forEach(function (s) { catalog[s.id] = s; }); } catch (e) { /* */ }
  var totalEl = form.querySelector('[data-preview-total]');
  var currency = (form.querySelector('[name=currency]') || {}).value || 'COP';
  var locale = document.documentElement.lang === 'en' ? 'en-US' : 'es-CO';

  function n(v) { var x = parseFloat(String(v || '0').replace(',', '.')); return isFinite(x) ? x : 0; }
  function recalc() {
    var cents = 0;
    body.querySelectorAll('[data-line]').forEach(function (tr) {
      var line = Math.round(n(tr.querySelector('[data-l=qty]').value) * n(tr.querySelector('[data-l=price]').value) * 100);
      cents += line + Math.round(line * n(tr.querySelector('[data-l=tax]').value) / 100);
    });
    try {
      totalEl.textContent = new Intl.NumberFormat(locale, { style: 'currency', currency: currency, maximumFractionDigits: currency === 'COP' ? 0 : 2 }).format(cents / 100);
    } catch (e) { totalEl.textContent = (cents / 100).toFixed(2); }
  }
  function renumber() {
    body.querySelectorAll('[data-line]').forEach(function (tr, i) {
      tr.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + i + ']'); });
    });
  }
  function wire(tr) {
    var sel = tr.querySelector('[data-l=service]');
    sel.addEventListener('change', function () {
      var s = catalog[sel.value];
      var price = tr.querySelector('[data-l=price]');
      var tax = tr.querySelector('[data-l=tax]');
      if (s) { price.value = s.price; tax.value = s.tax; price.readOnly = tax.readOnly = true; var d = tr.querySelector('[name$="[description]"]'); if (!d.value) d.value = s.name; }
      else { price.readOnly = tax.readOnly = false; }
      recalc();
    });
    if (catalog[sel.value]) { tr.querySelector('[data-l=price]').readOnly = tr.querySelector('[data-l=tax]').readOnly = true; }
    tr.querySelector('[data-remove-line]').addEventListener('click', function () {
      if (body.querySelectorAll('[data-line]').length > 1) { tr.remove(); renumber(); recalc(); }
    });
  }
  body.querySelectorAll('[data-line]').forEach(wire);
  form.querySelector('[data-add-line]').addEventListener('click', function () {
    var first = body.querySelector('[data-line]');
    var tr = first.cloneNode(true);
    tr.querySelectorAll('input').forEach(function (i) { i.value = i.getAttribute('data-l') === 'qty' ? '1' : (i.getAttribute('data-l') ? '0' : ''); i.readOnly = false; });
    tr.querySelector('select').value = '';
    body.appendChild(tr);
    wire(tr); renumber(); recalc();
  });
  form.addEventListener('input', recalc);
  recalc();
})();
