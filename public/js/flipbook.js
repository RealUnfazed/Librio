/* Librio flipbook: pages turn like a real book (CSS 3D, no library).
   Knows nothing about PDFs: the reader hands in a render() that fills a page with a canvas.
   Wide screens show a two-page spread with a front cover; narrow screens show one page at a time.
   rtl = right-to-left binding (Persian, Arabic, Hebrew books). */
(function (root) {
  const TURN_MS = 850;

  class FlipBook {
    /** o: { pages, ratio (page height / width), rtl, start, render(p, host, pw, ph, stillValid), release(p, host), onChange(visiblePages, atStart, atEnd) } */
    constructor(stage, o) {
      this.stage = stage; this.o = o;
      this.N = o.pages; this.ratio = o.ratio; this.rtl = !!o.rtl;
      this.f = 0; this.gen = 0;
      this.book = document.createElement('div');
      this.book.className = 'fb-book';
      stage.appendChild(this.book);
      this._bind();
      this.build(o.start || 1);
    }

    /* ---------- geometry ---------- */
    _measure() {
      const W = this.stage.clientWidth, H = this.stage.clientHeight;
      const pad = W < 600 ? 10 : 30;
      const spread = W >= 760 && W / H > 1.15;
      const aw = W - pad * 2, ah = H - pad * 2;
      const pw = Math.max(100, Math.floor(Math.min(spread ? aw / 2 : aw, ah / this.ratio)));
      return { spread, pw, ph: Math.floor(pw * this.ratio) };
    }

    build(page) {
      this.gen++;
      this.book.textContent = '';
      this.hosts = new Map();   // page number -> element that receives the canvas
      this.drawn = new Set();   // pages that currently hold a canvas
      const m = this._measure();
      this.spread = m.spread; this.pw = m.pw; this.ph = m.ph;
      this.book.style.width = (this.spread ? this.pw * 2 : this.pw) + 'px';
      this.book.style.height = this.ph + 'px';
      this.book.classList.toggle('spread', this.spread);
      this.book.classList.toggle('single', !this.spread);
      this.book.classList.toggle('rtl', this.rtl);
      this.L = this.spread ? Math.ceil(this.N / 2) : this.N;
      this.leaves = [];
      for (let i = 0; i < this.L; i++) {
        const el = document.createElement('div');
        el.className = 'fb-leaf';
        el.appendChild(this._face('front', this.spread ? 2 * i + 1 : i + 1));
        if (this.spread) el.appendChild(this._face('back', 2 * i + 2));
        this.book.appendChild(el);
        this.leaves.push(el);
      }
      this.f = this._fFor(page);
      this.book.classList.add('no-anim'); this._layout(); void this.book.offsetWidth; this.book.classList.remove('no-anim');
      this._ensure(); this._emit();
    }

    _face(side, p) {
      const face = document.createElement('div'); face.className = 'fb-face ' + side;
      const host = document.createElement('div'); host.className = 'fb-page';
      if (p <= this.N) { host.dataset.p = p; this.hosts.set(p, host); }
      face.appendChild(host);
      return face;
    }

    _fFor(page) {
      page = Math.min(Math.max(1, page | 0), this.N);
      return this.spread ? Math.min(this.L, Math.floor(page / 2)) : page - 1;
    }
    _maxF() { return this.spread ? this.L : this.N - 1; }

    /** page numbers on view right now */
    visible() {
      const f = this.f, out = [];
      if (this.spread) {
        if (f > 0 && 2 * f <= this.N) out.push(2 * f);
        if (f < this.L && 2 * f + 1 <= this.N) out.push(2 * f + 1);
      } else out.push(f + 1);
      return out;
    }
    get page() { return this.visible()[0] || 1; }

    /* ---------- state -> DOM ---------- */
    _layout() {
      const f = this.f, L = this.L;
      this.leaves.forEach((el, i) => {
        const flipped = i < f;
        el.style.transform = flipped ? 'rotateY(-180deg)' : 'rotateY(0deg)';
        el.classList.toggle('far', Math.abs(i - f) > 4);          // far-away leaves cost nothing
        if (!el.classList.contains('turning')) el.style.zIndex = flipped ? i : L - i;
      });
      let tx = 0;                                                  // a closed book sits in the middle
      if (this.spread) { if (f === 0) tx = -this.pw / 2; else if (f === L) tx = this.pw / 2; }
      if (this.rtl) tx = -tx;
      this.book.style.transform = 'translateX(' + tx + 'px) scaleX(' + (this.rtl ? -1 : 1) + ')';
    }

    _go(nf, animate) {
      nf = Math.min(Math.max(0, nf), this._maxF());
      if (nf === this.f) return;
      const from = this.f; this.f = nf;
      if (animate && Math.abs(nf - from) === 1) {
        const el = this.leaves[Math.min(from, nf)];                // the leaf that actually turns
        clearTimeout(el._t);
        el.classList.add('turning'); el.style.zIndex = this.L + 2;
        el._t = setTimeout(() => { el.classList.remove('turning'); this._layout(); }, TURN_MS);
        this._layout();
      } else {                                                     // long jumps: no animation
        this.book.classList.add('no-anim'); this._layout(); void this.book.offsetWidth; this.book.classList.remove('no-anim');
      }
      this._ensure(); this._emit();
    }

    /** draw pages close to the reader, free the ones far away */
    _ensure() {
      const c = this.page, gen = this.gen;
      for (const [p, host] of this.hosts) {
        if (p >= c - 4 && p <= c + 5) {
          if (!this.drawn.has(p)) { this.drawn.add(p); this.o.render(p, host, this.pw, this.ph, () => gen === this.gen); }
        } else if (this.drawn.has(p) && (p < c - 8 || p > c + 9)) {
          this.drawn.delete(p); this.o.release(p, host);
        }
      }
    }

    _emit() {
      if (this.o.onChange) this.o.onChange(this.visible(), this.f === 0, this.f === this._maxF());
    }

    /* ---------- public ---------- */
    next() { this._go(this.f + 1, true); }
    prev() { this._go(this.f - 1, true); }
    /** dir -1 = the page on the visual left side, +1 = visual right (swapped for right-to-left books) */
    visual(dir) { this._go(this.f + (this.rtl ? -dir : dir), true); }
    goTo(page) { this._go(this._fFor(page), true); }
    setRtl(v) { this.rtl = !!v; this.book.classList.toggle('rtl', this.rtl); this._layout(); this._emit(); }
    resize() {
      const m = this._measure();
      if (m.spread !== this.spread || Math.abs(m.pw - this.pw) > 2) this.build(this.page);
    }
    destroy() {
      this.gen++;
      document.removeEventListener('keydown', this._key);
      this.stage.removeEventListener('pointerdown', this._down);
      this.stage.removeEventListener('pointerup', this._up);
      this.book.remove();
    }

    /* ---------- input: swipe, tap, keys ---------- */
    _bind() {
      let d = null;
      this._down = (e) => { d = { x: e.clientX, y: e.clientY }; };
      this._up = (e) => {
        if (!d) return;
        const dx = e.clientX - d.x, dy = e.clientY - d.y; d = null;
        if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.2) return this.visual(dx < 0 ? 1 : -1);   // swipe
        if (Math.abs(dx) < 10 && Math.abs(dy) < 10) {                                                        // tap
          const r = this.stage.getBoundingClientRect(), x = (e.clientX - r.left) / r.width;
          this.visual(x < (this.spread ? 0.5 : 0.3) ? -1 : 1);
        }
      };
      this._key = (e) => {
        if (e.target && e.target.matches && e.target.matches('input,textarea')) return;
        const k = e.key;
        if (k === 'ArrowRight') this.visual(1);
        else if (k === 'ArrowLeft') this.visual(-1);
        else if (k === 'PageDown' || k === ' ') { e.preventDefault(); this.next(); }
        else if (k === 'PageUp') { e.preventDefault(); this.prev(); }
        else if (k === 'Home') this.goTo(1);
        else if (k === 'End') this.goTo(this.N);
      };
      this.stage.addEventListener('pointerdown', this._down);
      this.stage.addEventListener('pointerup', this._up);
      document.addEventListener('keydown', this._key);
    }
  }

  root.FlipBook = FlipBook;
})(typeof window !== 'undefined' ? window : globalThis);
