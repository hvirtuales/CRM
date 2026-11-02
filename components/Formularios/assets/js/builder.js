/*
 * Constructor de campos (JS nativo). Serializa a JSON en un campo oculto; el servidor valida todo
 * (tipos, claves, opciones, mapeos). Aquí no se confía en nada: solo se arma la estructura.
 */
(function () {
  'use strict';
  var form = document.querySelector('[data-form-builder]');
  if (!form) return;
  var list = form.querySelector('[data-field-list]');
  var tpl = form.querySelector('[data-field-template]');
  var hidden = form.querySelector('[data-fields-json]');

  function slug(s) {
    return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^([0-9])/, 'c_$1').slice(0, 40);
  }
  function syncOptions(row) {
    var type = row.querySelector('[data-k=type]').value;
    row.querySelector('[data-k=options]').hidden = type !== 'select';
  }
  function addRow(f) {
    var row = tpl.content.firstElementChild.cloneNode(true);
    f = f || { type: 'text', maps_to: 'none', required: false, options: [] };
    row.querySelector('[data-k=label]').value = f.label || '';
    row.querySelector('[data-k=type]').value = f.type || 'text';
    row.querySelector('[data-k=maps_to]').value = f.maps_to || 'none';
    row.querySelector('[data-k=required]').checked = !!f.required;
    row.querySelector('[data-k=options]').value = (f.options || []).join(', ');
    row.querySelector('[data-k=key]').value = f.key || '';
    row.querySelector('[data-k=type]').addEventListener('change', function () { syncOptions(row); });
    row.querySelector('[data-remove]').addEventListener('click', function () { row.remove(); });
    row.querySelector('[data-up]').addEventListener('click', function () { if (row.previousElementSibling) list.insertBefore(row, row.previousElementSibling); });
    row.querySelector('[data-down]').addEventListener('click', function () { if (row.nextElementSibling) list.insertBefore(row.nextElementSibling, row); });
    syncOptions(row);
    list.appendChild(row);
    return row;
  }

  var initial = [];
  try { initial = JSON.parse(hidden.value || '[]'); } catch (e) { initial = []; }
  (Array.isArray(initial) ? initial : []).forEach(addRow);
  form.querySelector('[data-add-field]').addEventListener('click', function () {
    var r = addRow();
    r.querySelector('[data-k=label]').focus();
  });

  form.addEventListener('submit', function () {
    var used = {};
    var out = Array.prototype.map.call(list.querySelectorAll('[data-field-row]'), function (row, i) {
      var label = row.querySelector('[data-k=label]').value.trim();
      var key = row.querySelector('[data-k=key]').value || slug(label) || ('campo_' + (i + 1));
      while (used[key]) key = key.slice(0, 36) + '_' + (i + 1);
      used[key] = true;
      return {
        key: key,
        label: label,
        type: row.querySelector('[data-k=type]').value,
        maps_to: row.querySelector('[data-k=maps_to]').value,
        required: row.querySelector('[data-k=required]').checked,
        options: row.querySelector('[data-k=options]').value.split(',').map(function (s) { return s.trim(); }).filter(Boolean)
      };
    });
    hidden.value = JSON.stringify(out);
  });
})();
