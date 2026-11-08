/* Joz Gallery: the welcome page's background video. The page has no cart or
   search, so it loads this instead of app.js. */
(function () {
  'use strict';

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var saveData = navigator.connection && navigator.connection.saveData;
  if (reduce || saveData) return; // the poster stays

  document.querySelectorAll('[data-welcome-video]').forEach(function (v) {
    var portrait = window.matchMedia('(orientation: portrait)').matches;
    v.addEventListener('playing', function () { v.classList.add('is-playing'); });
    v.src = v.getAttribute(portrait ? 'data-portrait' : 'data-wide');
    var p = v.play();
    if (p && p.catch) p.catch(function () { /* autoplay refused: the poster stays */ });
  });
})();
