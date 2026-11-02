/*
 * Tablero kanban de oportunidades: arrastrar y soltar (HTML5) o teclado (Alt+←/→).
 * El servidor valida permiso, pertenencia y etapa; aquí solo se mueve la tarjeta si responde OK.
 */
(function () {
  'use strict';
  var board = document.querySelector('[data-kanban]');
  if (!board || !window.HV) return;
  var urlTpl = board.getAttribute('data-move-url');
  var lostPrompt = board.getAttribute('data-lost-prompt');
  var dragged = null;

  function cols() { return Array.prototype.slice.call(board.querySelectorAll('.kanban-col')); }

  function move(card, col) {
    var from = card.parentElement;
    if (from === col) return;
    var payload = { stage_id: parseInt(col.getAttribute('data-stage'), 10) };
    if (col.getAttribute('data-kind') === 'lost') {
      var reason = window.prompt(lostPrompt || '');
      if (!reason) return;
      payload.lost_reason = reason;
    }
    col.appendChild(card); // optimista; se revierte si el servidor rechaza
    HV.post(urlTpl.replace('__ID__', card.getAttribute('data-id')), payload).then(function (res) {
      if (!res.ok) {
        from.appendChild(card);
        HV.toast((res.body && res.body.messages && res.body.messages.error) || 'Error', 'error');
      } else if (res.body && res.body.message) {
        HV.toast(res.body.message);
      }
    }).catch(function () { from.appendChild(card); HV.toast('Error', 'error'); });
  }

  board.addEventListener('dragstart', function (e) {
    var card = e.target.closest('.kanban-card');
    if (!card) return;
    dragged = card;
    card.setAttribute('aria-grabbed', 'true');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', card.getAttribute('data-id'));
  });
  board.addEventListener('dragend', function () {
    if (dragged) dragged.setAttribute('aria-grabbed', 'false');
    dragged = null;
    cols().forEach(function (c) { c.classList.remove('is-over'); });
  });
  cols().forEach(function (col) {
    col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('is-over'); });
    col.addEventListener('dragleave', function () { col.classList.remove('is-over'); });
    col.addEventListener('drop', function (e) {
      e.preventDefault();
      col.classList.remove('is-over');
      if (dragged) move(dragged, col);
    });
  });

  // Accesibilidad: Alt + flecha mueve la tarjeta enfocada a la columna vecina.
  board.addEventListener('keydown', function (e) {
    var card = e.target.closest('.kanban-card');
    if (!card || !e.altKey || (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft')) return;
    e.preventDefault();
    var all = cols();
    var idx = all.indexOf(card.parentElement) + (e.key === 'ArrowRight' ? 1 : -1);
    if (idx >= 0 && idx < all.length) { move(card, all[idx]); card.focus(); }
  });
})();
