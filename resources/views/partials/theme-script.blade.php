<script>
document.querySelectorAll('.js-theme').forEach(function (b) {
  b.addEventListener('click', function () {
    var d = document.documentElement, sysDark = matchMedia('(prefers-color-scheme: dark)').matches;
    var cur = d.dataset.theme || (sysDark ? 'dark' : 'light');
    var next = cur === 'dark' ? 'light' : 'dark';
    d.dataset.theme = next;
    try { localStorage.setItem('theme', next); } catch (e) {}
  });
});
</script>
