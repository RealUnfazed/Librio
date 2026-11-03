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
    mode: null, fb: null, rtl: false, io: null,
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

  /* Proxy delivery: the server fetches the encrypted file from Postfile, decrypts it and hands out plain PDF bytes.
     We never ask for more than 1 MiB per request (Vercel caps response sizes), so every response stays small. */
  const PIECE = 1024 * 1024;
  async function fetchRange(url, begin, end) {          // end is exclusive
    let last;
    for (let t = 0; t < 3; t++) {
      try {
        const res = await fetch(url, { headers: { Range: 'bytes=' + begin + '-' + (end - 1) }, credentials: 'same-origin', cache: 'no-store' });
        if (res.status !== 206 && res.status !== 200) throw new Error('HTTP ' + res.status);
        return new Uint8Array(await res.arrayBuffer());
      } catch (e) { last = e; await new Promise((r) => setTimeout(r, 500 * (t + 1))); }
    }
    throw last;
  }

  // Streaming: PDF.js asks for the byte ranges it needs (xref, then page after page), nothing else is downloaded.
  async function openStreaming(m) {
    const total = m.plain_size;
    const initial = await fetchRange(m.url, 0, Math.min(total, 4 * 262144));
    class Transport extends pdfjsLib.PDFDataRangeTransport {
      requestDataRange(begin, end) {
        for (let b = begin; b < end; b += PIECE) {
          const e = Math.min(end, b + PIECE);
          fetchRange(m.url, b, e).then((buf) => this.onDataRange(b, buf)).catch((err) => console.error('range', b, err));
        }
      }
      abort() {}
    }
    return pdfjsLib.getDocument({ range: new Transport(total, initial), length: total, rangeChunkSize: 262144, disableAutoFetch: true, disableStream: true }).promise;
  }

  // Fallback: download the whole PDF through the server in 1 MiB pieces, then open it from memory.
  async function openWhole(m) {
    const out = new Uint8Array(m.plain_size);
    for (let off = 0; off < m.plain_size; off += PIECE) {
      out.set(await fetchRange(m.url, off, Math.min(m.plain_size, off + PIECE)), off);
      setLoad('Downloading…', Math.min(0.95, (off + PIECE) / m.plain_size));
    }
    return pdfjsLib.getDocument({ data: out }).promise;
  }

  async function openDocument(m) {
    if (m.mode === 'proxy') {
      setLoad('Opening…', 0.3);
      try { return await openStreaming(m); }
      catch (e) { console.warn('streaming failed, downloading the whole file instead', e); setLoad('Downloading…', 0.3); return openWhole(m); }
    }
    // "encrypted" mode (only for file hosts that allow browser downloads, i.e. send CORS headers)
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
    const t = S.wm || location.hostname;
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
    S.io = io;
    for (let i = 1; i <= S.n; i++) {
      const el = document.createElement('div');
      el.className = 'page'; el.dataset.n = i;
      pagesEl.appendChild(el); S.els.push(el); io.observe(el);
    }
  }

  /* ---------- navigation ---------- */
  function goTo(n) {
    n = Math.min(Math.max(1, n | 0), S.n);
    if (S.mode === 'book') { if (S.fb) S.fb.goTo(n); return; }
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
    resizeT = setTimeout(() => {
      if (!S.pdf) return;
      if (S.mode === 'book') { if (S.fb) S.fb.resize(); return; }
      if (S.fit) { const k = S.page; layout(); goTo(k); }
    }, 200);
  });
  // pinch to zoom (applied when the fingers lift)
  let pd = 0, last = 0;
  const dist = (t) => Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY);
  viewer.addEventListener('touchstart', (e) => { if (e.touches.length === 2) pd = last = dist(e.touches); }, { passive: true });
  viewer.addEventListener('touchmove', (e) => { if (e.touches.length === 2) { e.preventDefault(); last = dist(e.touches); } }, { passive: false });
  viewer.addEventListener('touchend', (e) => { if (pd && e.touches.length < 2) { setScale(S.scale * (last / pd)); pd = 0; } });


  /* ---------- book mode: pages turn like a real book (see flipbook.js) ---------- */
  const bookEl = $('#book');
  function renderBook(p, host, pw, ph, valid) {
    const tok = host._tok = (host._tok || 0) + 1;       // a newer render / a release cancels an older one
    (async () => {
      try {
        const page = await S.pdf.getPage(p);
        const v1 = page.getViewport({ scale: 1 });
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const vp = page.getViewport({ scale: (pw * dpr) / v1.width });
        const c = document.createElement('canvas'); c.width = Math.ceil(vp.width); c.height = Math.ceil(vp.height);
        const ctx = c.getContext('2d');
        await page.render({ canvasContext: ctx, viewport: vp }).promise;
        stamp(ctx, c.width, c.height, dpr);               // the watermark is part of the pixels
        if (!valid() || host._tok !== tok) { c.width = 0; return; }
        host.textContent = ''; host.appendChild(c);
      } catch (e) { console.warn('book page', p, e); }
    })();
  }
  function releaseBook(p, host) {
    host._tok = (host._tok || 0) + 1;
    const c = host.querySelector('canvas');
    if (c) { c.width = 0; c.height = 0; c.remove(); }
  }
  function startBook(page) {
    S.mode = 'book';
    viewer.hidden = true; bookEl.hidden = false;
    document.body.classList.add('mode-book'); document.body.classList.remove('mode-scroll');
    S.fb = new window.FlipBook($('#fbStage'), {
      pages: S.n, ratio: S.base[1] / S.base[0], rtl: S.rtl, start: page,
      render: renderBook, release: releaseBook,
      onChange: (vis, atStart, atEnd) => {
        if (vis.length) setPage(vis[vis.length - 1], true);   // show the right-hand page of a spread
        $('#fbPrev').disabled = S.rtl ? atEnd : atStart;   // left arrow
        $('#fbNext').disabled = S.rtl ? atStart : atEnd;   // right arrow
      },
    });
  }
  function startScroll(page) {
    if (S.fb) { S.fb.destroy(); S.fb = null; }
    S.mode = 'scroll';
    bookEl.hidden = true; viewer.hidden = false;
    document.body.classList.add('mode-scroll'); document.body.classList.remove('mode-book');
    buildPages(); layout(); goTo(page);
  }
  function startMode(m, page) {
    if (S.mode === 'scroll') { if (S.io) S.io.disconnect(); pagesEl.textContent = ''; S.els = []; }
    (m === 'book' && window.FlipBook ? startBook : startScroll)(page);
  }
  $('#mode').onclick = () => { const m = S.mode === 'book' ? 'scroll' : 'book'; startMode(m, S.page); store.set('reader-mode', m); };
  $('#rtl').onclick = () => {
    S.rtl = !S.rtl; store.set('rtl:' + R.bookId, S.rtl ? '1' : '0');
    $('#rtl').classList.toggle('on', S.rtl);
    if (S.fb) S.fb.setRtl(S.rtl);
  };
  $('#fbPrev').onclick = () => { if (S.fb) S.fb.visual(-1); };
  $('#fbNext').onclick = () => { if (S.fb) S.fb.visual(1); };

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
  function applyTheme(t) { document.documentElement.dataset.theme = t; store.set('theme', t); }
  $('#theme').onclick = () => applyTheme(themes[(themes.indexOf(document.documentElement.dataset.theme) + 1) % 3]);
  {
    const saved = store.get('theme', '');
    // no choice saved yet: follow the device setting (and don't save it until the reader taps the button)
    document.documentElement.dataset.theme = themes.includes(saved) ? saved
      : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  }

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
    S.rtl = store.get('rtl:' + R.bookId, '') === '1';
    $('#rtl').classList.toggle('on', S.rtl);
    startMode(store.get('reader-mode', 'book'), Math.min(m.progress || 1, S.n));
    $('#loader').hidden = true;
    renderBms(); loadOutline();
  })();
})();
