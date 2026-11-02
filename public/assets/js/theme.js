/* Aplica el tema guardado antes de pintar (evita parpadeo). Sin dependencias. */
(function () {
  try {
    var t = localStorage.getItem('hv-theme');
    if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
  } catch (e) { /* almacenamiento no disponible: se usa prefers-color-scheme */ }
})();
