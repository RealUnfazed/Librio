<script>
(function () {
  var root = document.documentElement;
  addEventListener('pageshow', function () { root.classList.remove('nav-loading'); });
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button || a.target || a.hasAttribute('download')) return;
    if (a.origin === location.origin && a.pathname + a.search !== location.pathname + location.search) root.classList.add('nav-loading');
  });
  document.querySelectorAll('.shelf-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      var s = document.getElementById(b.dataset.target);
      if (s) s.scrollBy({ left: (+b.dataset.dir) * s.clientWidth * 0.85, behavior: 'smooth' });
    });
  });
})();
</script>
