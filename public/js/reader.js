/* PDF reader: lazy page rendering, zoom/pinch, resume, bookmarks, contents, search, themes, watermark. */
(() => {
  const R = window.READER;
  const $ = (s) => document.querySelector(s);
  const viewer = $('#viewer'), pagesEl = $('#pages');
  const store = {
    get(k, d) { try { return localStorage.getItem(k) || d; } catch { return d; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch {} },
  };
  const S = {
    pdf: null, n: 0, scale: 1, fit: true, page: 1, gen: 0, wm: '',
    base: null, dims: {}, bookmarks: new Set(), text: {}, searchGen: 0, els: [],
  };
  pdfjsLib.GlobalWorkerOptions.workerSrc = R.workerSrc;

  /* ---------- loading ---------- */
  function setLoad(msg, p) { $('#loadMsg').textContent = msg; if (p != null) $('#loadBar').value = p; }
  function fail(msg, err) { console.error(err); setLoad(msg); $('#loadBar').hidden = true; }

  async function fetchCipher(url, expected) {
    const res = await fetch(url, { mode: 'cors', credentials: 'omit', cache: 'force-cache' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const reader = res.body.getReader(), parts = []; let got = 0;
    for (;;) {
      const { done, value } = await reader.read();
      if (done) break;
      parts.push(value); got += value.length;
      setLoad('Downloading…', (got / expected) * 0.6);
    }
    const out = new Uint8Array(got); let o = 0;
    for (const p of parts) { out.set(p, o); o += p.length; }
    return out;
  }

  async function openDocument(m) {
    if (m.mode === 'proxy') {
      setLoad('Opening…', 0.3);
      return pdfjsLib.getDocument({ url: m.url, withCredentials: true, disableAutoFetch: true, rangeChunkSize: 262144 }).promise;
    }
    let cipher;
    try { cipher = await fetchCipher(m.url, m.enc_size); }
    catch (e) { throw Object.assign(new Error('download'), { cause: e }); }
    setLoad('Unlocking…', 0.6);
    const data = await window.decryptChunked(cipher, m, (p) => setLoad('Unlocking…', 0.6 + p * 0.4));
    cipher = null;
    return pdfjsLib.getDocument({ data }).promise;
  }

  /* ---------- layout & rendering ---------- */
  function pageSize(n) {
    const d = S.dims[n] || S.base;
    return [d[0] * S.scale, d[1] * S.scale];
  }
  function layout() {
    S.gen++;
    if (S.fit) S.scale = Math.max(0.3, (viewer.clientWidth - 24) / S.base[0]);
    S.els.forEach((el, i) => {
      const [w, h] = pageSize(i + 1);
      el.style.width = w + 'px'; el.style.height = h + 'px';
      release(el);
    });
    S.els.forEach((el) => { if (el._vis) render(el); });
  }

  function release(el) {
    const c = el.querySelector('canvas');
    if (c) { c.width = 0; c.height = 0; c.remove(); }
    el._done = false;
  }

  function stamp(ctx, w, h, dpr) {
    const t = S.wm + '   ' + new Date().toISOString().slice(0, 16).replace('T', ' ');
    ctx.save();
    ctx.globalAlpha = 0.1; ctx.fillStyle = '#000';
    ctx.font = '600 ' + 14 * dpr + 'px system-ui,sans-serif'; ctx.textAlign = 'center';
    ctx.rotate(-Math.PI / 6);
    const sx = Math.max(320 * dpr, ctx.measureText(t).width + 90 * dpr), sy = 130 * dpr;
    let row = 0;
    for (let y = 0; y < h + w; y += sy, row++) {
      for (let x = -w + (row % 2) * sx / 2; x < w * 2; x += sx) ctx.fillText(t, x, y);
    }
    ctx.restore();
  }

  async function render(el) {
    if (el._done || el._busy) return;
    el._busy = true;
    const gen = S.gen, n = +el.dataset.n;
    try {
      const page = await S.pdf.getPage(n);
      const v1 = page.getViewport({ scale: 1 });
      if (!S.dims[n] || S.dims[n][0] !== v1.width) {
        S.dims[n] = [v1.width, v1.height];
        const [w, h] = pageSize(n); el.style.width = w + 'px'; el.style.height = h + 'px';
      }
      if (gen !== S.gen || !el._vis) return;
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const vp = page.getViewport({ scale: S.scale * dpr });
      const c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
      const ctx = c.getContext('2d');
      await page.render({ canvasContext: ctx, viewport: vp }).promise;
      stamp(ctx, vp.width, vp.height, dpr);        // baked into the pixels, can't be removed via devtools
      if (gen !== S.gen || !el._vis) { c.width = 0; return; }
      el.appendChild(c); el._done = true;
    } catch (e) { console.warn('page', n, e); }
    finally { el._busy = false; if (!el._done && el._vis && gen !== S.gen) render(el); }
  }

  function buildPages() {
    const io = new IntersectionObserver((entries) => {
      for (const e of entries) {
        const el = e.target; el._vis = e.isIntersecting;
        if (e.isIntersecting) render(el); else release(el);
      }
    }, { root: viewer, rootMargin: '150% 0px' });
    for (let i = 1; i <= S.n; i++) {
      const el = document.createElement('div');
      el.className = 'page'; el.dataset.n = i;
      pagesEl.appendChild(el); S.els.push(el); io.observe(el);
    }
  }

  /* ---------- navigation ---------- */
  function goTo(n) {
    n = Math.min(Math.max(1, n | 0), S.n);
    viewer.scrollTop = S.els[n - 1].offsetTop - 12;
    setPage(n, true);
  }
  function setPage(n, save) {
    S.page = n; $('#pageInput').value = n; updateBm();
    $('#prog').style.width = (S.n ? (n / S.n) * 100 : 0) + '%';
    if (save) queueSave();
  }
  let ticking = false;
  viewer.addEventListener('scroll', () => {
    if (ticking) return; ticking = true;
    requestAnimationFrame(() => {
      ticking = false;
      const y = viewer.scrollTop + viewer.clientHeight * 0.3;
      let lo = 0, hi = S.els.length - 1;
      while (lo < hi) { const mid = (lo + hi + 1) >> 1; if (S.els[mid].offsetTop <= y) lo = mid; else hi = mid - 1; }
      if (lo + 1 !== S.page) setPage(lo + 1, true);
    });
  }, { passive: true });

  $('#pageInput').addEventListener('change', (e) => goTo(+e.target.value));

  /* ---------- zoom ---------- */
  function setScale(s) {
    const keep = S.page;
    S.fit = false; S.scale = Math.min(4, Math.max(0.4, s));
    layout(); goTo(keep);
  }
  $('#zoomIn').onclick = () => setScale(S.scale * 1.2);
  $('#zoomOut').onclick = () => setScale(S.scale / 1.2);
  $('#fit').onclick = () => { const keep = S.page; S.fit = true; layout(); goTo(keep); };
  viewer.addEventListener('wheel', (e) => {
    if (!e.ctrlKey) return; e.preventDefault(); setScale(S.scale * (e.deltaY < 0 ? 1.1 : 1 / 1.1));
  }, { passive: false });
  let resizeT; window.addEventListener('resize', () => {
    clearTimeout(resizeT);
    resizeT = setTimeout(() => { if (S.fit && S.pdf) { const k = S.page; layout(); goTo(k); } }, 200);
  });
  // pinch to zoom (applied when the fingers lift)
  let pd = 0, last = 0;
  const dist = (t) => Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY);
  viewer.addEventListener('touchstart', (e) => { if (e.touches.length === 2) pd = last = dist(e.touches); }, { passive: true });
  viewer.addEventListener('touchmove', (e) => { if (e.touches.length === 2) { e.preventDefault(); last = dist(e.touches); } }, { passive: false });
  viewer.addEventListener('touchend', (e) => { if (pd && e.touches.length < 2) { setScale(S.scale * (last / pd)); pd = 0; } });

  /* ---------- progress ---------- */
  let saveT;
  function post(url, body, keepalive) {
    return fetch(url, {
      method: 'POST', keepalive: !!keepalive, credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': R.csrf, Accept: 'application/json' },
      body: JSON.stringify(body),
    });
  }
  function queueSave() { clearTimeout(saveT); saveT = setTimeout(() => post(R.progressUrl, { page: S.page }).catch(() => {}), 1500); }
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && S.pdf) { clearTimeout(saveT); post(R.progressUrl, { page: S.page }, true).catch(() => {}); }
  });

  /* ---------- bookmarks ---------- */
  function updateBm() { $('#bm').classList.toggle('on', S.bookmarks.has(S.page)); }
  function renderBms() {
    const box = $('#bms'); box.textContent = '';
    if (!S.bookmarks.size) { box.textContent = 'No bookmarks yet. Tap the bookmark icon to add one.'; return; }
    [...S.bookmarks].sort((a, b) => a - b).forEach((p) => {
      const b = document.createElement('button'); b.className = 'item'; b.textContent = 'Page ' + p;
      b.onclick = () => { goTo(p); closeSide(); }; box.appendChild(b);
    });
  }
  $('#bm').onclick = async () => {
    try {
      const res = await post(R.bookmarkUrl, { page: S.page });
      S.bookmarks = new Set(await res.json()); updateBm(); renderBms();
    } catch (e) { console.warn(e); }
  };

  /* ---------- side panel: contents / bookmarks / search ---------- */
  const side = $('#side');
  const closeSide = () => { if (innerWidth < 800) side.hidden = true; };
  $('#menu').onclick = () => { side.hidden = !side.hidden; };
  side.querySelectorAll('.tabs button').forEach((b) => b.onclick = () => {
    side.querySelectorAll('.tabs button').forEach((x) => x.classList.toggle('on', x === b));
    ['toc', 'bms', 'find'].forEach((id) => { $('#' + id).hidden = id !== b.dataset.tab; });
  });

  async function destPage(dest) {
    try {
      let d = typeof dest === 'string' ? await S.pdf.getDestination(dest) : dest;
      if (!d) return null;
      return typeof d[0] === 'object' ? (await S.pdf.getPageIndex(d[0])) + 1 : d[0] + 1;
    } catch { return null; }
  }
  async function loadOutline() {
    const box = $('#toc'), out = await S.pdf.getOutline().catch(() => null);
    if (!out || !out.length) { box.textContent = 'This book has no table of contents.'; return; }
    const walk = (items, depth) => items.forEach((it) => {
      const b = document.createElement('button'); b.className = 'item';
      b.style.paddingLeft = 10 + depth * 14 + 'px'; b.textContent = it.title;
      b.onclick = async () => { const p = await destPage(it.dest); if (p) { goTo(p); closeSide(); } };
      box.appendChild(b);
      if (it.items && it.items.length) walk(it.items, depth + 1);
    });
    walk(out, 0);
  }

  async function pageText(n) {
    if (S.text[n] == null) {
      const tc = await (await S.pdf.getPage(n)).getTextContent();
      S.text[n] = tc.items.map((i) => i.str).join(' ');
    }
    return S.text[n];
  }
  let searchT;
  $('#q').addEventListener('input', (e) => { clearTimeout(searchT); searchT = setTimeout(() => search(e.target.value), 350); });
  async function search(raw) {
    const box = $('#results'), q = raw.trim().toLowerCase(), token = ++S.searchGen;
    box.textContent = ''; if (q.length < 2) return;
    let found = 0;
    for (let n = 1; n <= S.n && found < 100; n++) {
      if (token !== S.searchGen) return;
      const t = await pageText(n), i = t.toLowerCase().indexOf(q);
      if (i < 0) continue;
      found++;
      const b = document.createElement('button'); b.className = 'item';
      b.textContent = '…' + t.slice(Math.max(0, i - 30), i + q.length + 50).trim() + '…';
      const sm = document.createElement('small'); sm.textContent = 'Page ' + n; b.appendChild(sm);
      b.onclick = () => { goTo(n); closeSide(); }; box.appendChild(b);
    }
    if (!found && token === S.searchGen) box.textContent = 'No matches (scanned books have no searchable text).';
  }

  /* ---------- theme & deterrents ---------- */
  const themes = ['light', 'sepia', 'dark'];
  function applyTheme(t) { document.documentElement.dataset.theme = t; store.set('reader-theme', t); }
  $('#theme').onclick = () => applyTheme(themes[(themes.indexOf(document.documentElement.dataset.theme) + 1) % 3]);
  applyTheme(themes.includes(store.get('reader-theme', 'light')) ? store.get('reader-theme', 'light') : 'light');

  // Deterrents only: they stop casual saving/printing, not a determined person.
  document.addEventListener('contextmenu', (e) => e.preventDefault());
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && ['s', 'p', 'u'].includes(e.key.toLowerCase())) e.preventDefault();
  });

  /* ---------- start ---------- */
  (async () => {
    let m;
    try {
      const res = await fetch(R.manifestUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (res.status === 401 || res.status === 419) { location.href = '/login'; return; }
      if (!res.ok) throw new Error('manifest ' + res.status);
      m = await res.json();
    } catch (e) { return fail('Could not open this book. Please try again.', e); }

    S.wm = m.watermark; S.bookmarks = new Set(m.bookmarks);
    try { S.pdf = await openDocument(m); }
    catch (e) {
      return fail(e.message === 'download'
        ? 'The file host could not be reached from your browser. Please try again later.'
        : 'This book could not be unlocked or read.', e.cause || e);
    }
    S.n = S.pdf.numPages;
    const first = await S.pdf.getPage(1), v = first.getViewport({ scale: 1 });
    S.base = [v.width, v.height]; S.dims[1] = S.base;
    $('#pageTotal').textContent = S.n; $('#pageInput').max = S.n;
    buildPages(); layout();
    $('#loader').hidden = true;
    goTo(Math.min(m.progress || 1, S.n));
    renderBms(); loadOutline();
  })();
})();
