<button type="button" id="themeBtn" class="theme-btn" title="Dark / light mode" aria-label="Toggle dark mode">
  <svg class="i i-moon" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
  <svg class="i i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
</button>
<script>
document.getElementById('themeBtn').addEventListener('click', function () {
  var d = document.documentElement, sysDark = matchMedia('(prefers-color-scheme: dark)').matches;
  var cur = d.dataset.theme || (sysDark ? 'dark' : 'light');
  var next = cur === 'dark' ? 'light' : 'dark';
  d.dataset.theme = next;
  try { localStorage.setItem('theme', next); } catch (e) {}
});
</script>
