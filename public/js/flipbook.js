/* Librio flipbook: a real page CURL (like FlipHTML5), drawn on one canvas.
   - drag a page corner and the paper folds under your finger / mouse, with shadows
   - hover a corner and it peeks; click/tap a side, swipe, arrow keys, or the arrow buttons turn the page
   - wide screens: two-page spread with a front cover; narrow screens: one page at a time
   - rtl = right-to-left binding (Persian, Arabic, Hebrew)
   It knows nothing about PDFs: the reader gives it page canvases through o.render(). */
(function (root) {
  const ease = {
    inOut: (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2),
    out: (t) => 1 - Math.pow(1 - t, 3),
  };
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
  const PAPER = '#fdfcf8';

  /* keep the part of a convex polygon where (X - M) . n * keep >= 0 */
  function clipPoly(poly, M, n, keep) {
    const out = [];
    for (let i = 0; i < poly.length; i++) {
      const a = poly[i], b = poly[(i + 1) % poly.length];
      const da = ((a[0] - M[0]) * n[0] + (a[1] - M[1]) * n[1]) * keep;
      const db = ((b[0] - M[0]) * n[0] + (b[1] - M[1]) * n[1]) * keep;
      if (da >= 0) out.push(a);
      if ((da >= 0) !== (db >= 0)) {
        const t = da / (da - db);
        out.push([a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t]);
      }
    }
    return out;
  }
  function reflect(X, M, n) {
    const d = (X[0] - M[0]) * n[0] + (X[1] - M[1]) * n[1];
    return [X[0] - 2 * d * n[0], X[1] - 2 * d * n[1]];
  }
  function polyPath(ctx, poly) {
    ctx.beginPath();
    poly.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
    ctx.closePath();
  }

  class FlipBook {
    /** o: { pages, ratio (height/width), rtl, start, render(p, done(canvas), pw, ph, stillValid), release(p, canvas),
     *       filter() -> css filter string for the theme, onChange(visiblePages, atStart, atEnd) } */
    constructor(stage, o) {
      this.stage = stage; this.o = o;
      this.N = o.pages; this.ratio = o.ratio; this.rtl = !!o.rtl;
      this.f = 0; this.gen = 0;
      this.srcs = new Map(); this.imgs = new Map(); this.asked = new Set();
      this.turn = null; this.queue = []; this.hover = 0; this.hoverSpec = null; this.hoverGoal = 0;
      this.raf = 0;
      this.cv = document.createElement('canvas');
      this.cv.className = 'fb-canvas';
      this.ctx = this.cv.getContext('2d');
      stage.appendChild(this.cv);
      this._bind();
      this.build(o.start || 1);
    }

    /* ------------------------------------------------------------ geometry */
    _measure() {
      const W = this.stage.clientWidth, H = this.stage.clientHeight;
      const pad = W < 600 ? 10 : 34;
      const spread = W >= 760 && W / H > 1.15;
      const aw = W - pad * 2, ah = H - pad * 2;
      const pw = Math.max(100, Math.floor(Math.min(spread ? aw / 2 : aw, ah / this.ratio)));
      return { W, H, spread, pw, ph: Math.floor(pw * this.ratio) };
    }

    build(page) {
      this.gen++;
      this.srcs.forEach((c, p) => this.o.release && this.o.release(p, c));
      this.srcs.clear(); this.imgs.clear(); this.asked.clear();
      const m = this._measure();
      this.W = m.W; this.H = m.H; this.spread = m.spread; this.pw = m.pw; this.ph = m.ph;
      this.dpr = Math.min(window.devicePixelRatio || 1, 2);
      this.cv.width = Math.round(this.W * this.dpr); this.cv.height = Math.round(this.H * this.dpr);
      this.T = Math.round((this.H - this.ph) / 2);
      this.L = this.spread ? Math.ceil(this.N / 2) : this.N;
      this.f = this._fFor(page);
      this.stage.classList.toggle('rtl', this.rtl);
      this.turn = null; this.queue.length = 0; this.hover = 0; this.hoverGoal = 0; this.hoverSpec = null;
      this._ensure(); this._emit(); this._render();
    }

    _fFor(page) {
      page = clamp(page | 0, 1, this.N);
      return this.spread ? Math.min(this.L, Math.floor(page / 2)) : page - 1;
    }
    _maxF() { return this.spread ? this.L : this.N - 1; }

    /** x of the spine for a resting book at state f (a closed book sits in the middle) */
    _sx(f) {
      if (!this.spread) return Math.round((this.W - this.pw) / 2);
      if (f === 0) return Math.round(this.W / 2 - this.pw / 2);
      if (f === this.L) return Math.round(this.W / 2 + this.pw / 2);
      return Math.round(this.W / 2);
    }

    _left(f) { return this.spread && f > 0 ? 2 * f : null; }
    _right(f) { return this.spread ? (f < this.L ? 2 * f + 1 : null) : f + 1; }

    visible() {
      const out = [], l = this._left(this.f), r = this._right(this.f);
      if (l !== null && l <= this.N) out.push(l);
      if (r !== null && r <= this.N) out.push(r);
      return out;
    }
    get page() { return this.visible()[0] || 1; }

    /* ------------------------------------------------------------ what turns */
    /** page numbers involved in a turn. 0 = blank paper, null = nothing there. mirror = the turning leaf goes right -> left in a mirrored frame */
    _spec(dir) {
      const f = this.f;
      if (dir > 0 ? f >= this._maxF() : f <= 0) return null;
      let s;
      if (this.spread) {
        if (dir > 0) s = { front: 2 * f + 1, back: 2 * f + 2, under: f + 1 < this.L ? 2 * f + 3 : null, other: f > 0 ? 2 * f : null, mirror: false };
        else s = { front: 2 * f, back: 2 * f - 1, under: f - 1 > 0 ? 2 * f - 2 : null, other: f < this.L ? 2 * f + 1 : null, mirror: true };
      } else if (dir > 0) s = { front: f + 1, back: 0, under: f + 2, other: null, mirror: false };
      else s = { front: 0, back: f, under: null, other: f + 1, mirror: true };
      s.dir = dir; s.fFrom = f; s.fTo = f + dir;
      return s;
    }

    _img(n) {
      if (n === null) return null;
      if (n === 0 || n > this.N) return 'paper';
      return this.imgs.get(n) || 'paper';
    }

    /* ------------------------------------------------------------ pages -> canvases (windowed) */
    _ensure() {
      const c = this.page, gen = this.gen;
      for (let p = Math.max(1, c - 5); p <= Math.min(this.N, c + 6); p++) {
        if (this.asked.has(p)) continue;
        this.asked.add(p);
        this.o.render(p, (canvas) => this._got(p, canvas, gen), this.pw, this.ph, () => gen === this.gen);
      }
      for (const p of [...this.srcs.keys()]) {
        if (p < c - 9 || p > c + 10) {
          this.o.release && this.o.release(p, this.srcs.get(p));
          this.srcs.delete(p); this.imgs.delete(p); this.asked.delete(p);
        }
      }
    }
    _got(p, canvas, gen) {
      if (gen !== this.gen || !canvas) { if (canvas && this.o.release) this.o.release(p, canvas); return; }
      this.srcs.set(p, canvas); this.imgs.set(p, this._themed(canvas));
      this._kick();
    }
    _themed(src) {
      const f = this.o.filter ? this.o.filter() : '';
      if (!f || !('filter' in CanvasRenderingContext2D.prototype)) return src;
      const c = document.createElement('canvas'); c.width = src.width; c.height = src.height;
      const x = c.getContext('2d'); x.filter = f; x.drawImage(src, 0, 0);
      return c;
    }
    retheme() {
      this.imgs.clear();
      this.srcs.forEach((c, p) => this.imgs.set(p, this._themed(c)));
      this._kick();
    }

    _emit() {
      if (this.o.onChange) this.o.onChange(this.visible(), this.f === 0, this.f === this._maxF());
    }

    /* ------------------------------------------------------------ drawing */
    _render() {
      const ctx = this.ctx;
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.clearRect(0, 0, this.cv.width, this.cv.height);
      ctx.scale(this.dpr, this.dpr);
      if (this.turn) { this._drawTurn(this.turn.spec, this.turn.P); return; }
      const peek = this._peek();
      if (peek) { this._drawTurn(peek.spec, peek.P); return; }
      this._drawStatic();
    }

    _gutterShadow(ctx, x, y, w, h, side) {
      const gw = w * 0.14;
      const g = side === 'r' ? ctx.createLinearGradient(x, 0, x + gw, 0) : ctx.createLinearGradient(x + w, 0, x + w - gw, 0);
      g.addColorStop(0, 'rgba(0,0,0,.36)'); g.addColorStop(0.1, 'rgba(0,0,0,.22)');
      g.addColorStop(0.4, 'rgba(0,0,0,.08)'); g.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = g; ctx.fillRect(x, y, w, h);
    }
    /** one page at (x, y); side = which edge touches the spine ('l' page: right edge, 'r' page: left edge) */
    _paper(ctx, x, y, img, flip, side) {
      const pw = this.pw, ph = this.ph;
      ctx.save();
      ctx.translate(x, y);
      if (flip) { ctx.translate(pw, 0); ctx.scale(-1, 1); }
      if (img && img !== 'paper') {
        const k = Math.min(pw / img.width, ph / img.height), w = img.width * k, h = img.height * k;
        if (Math.abs(w - pw) > 1 || Math.abs(h - ph) > 1) { ctx.fillStyle = PAPER; ctx.fillRect(0, 0, pw, ph); }
        ctx.drawImage(img, (pw - w) / 2, (ph - h) / 2, w, h);
      } else { ctx.fillStyle = PAPER; ctx.fillRect(0, 0, pw, ph); }
      ctx.restore();
      if (side) this._gutterShadow(ctx, x, y, pw, ph, side);
    }
    _bookShadow(ctx, sx, hasLeft, hasRight) {
      const pw = this.pw, d = this.dpr;
      ctx.save();
      ctx.shadowColor = 'rgba(0,0,0,.42)'; ctx.shadowBlur = 30 * d; ctx.shadowOffsetY = 10 * d;
      ctx.fillStyle = PAPER;
      ctx.fillRect(hasLeft ? sx - pw : sx, this.T, (hasLeft ? pw : 0) + (hasRight ? pw : 0), this.ph);
      ctx.restore();
    }

    _drawStatic() {
      const ctx = this.ctx, f = this.f, sx = this._sx(f);
      const l = this._left(f), r = this._right(f);
      this._bookShadow(ctx, sx, l !== null, r !== null);
      if (l !== null) this._paper(ctx, sx - this.pw, this.T, this._img(l), this.rtl, 'l');
      if (r !== null) this._paper(ctx, sx, this.T, this._img(r), this.rtl, 'r');
    }

    /** keep the dragged corner where a real sheet could be: P is relative to the spine */
    _clampP(P, yc, ys) {
      const pw = this.pw, ph = this.ph;
      let x = Math.min(P[0], pw - 0.5), y = P[1];
      let dx = x, dy = y - yc, d = Math.hypot(dx, dy);
      if (d > pw) { x = (dx / d) * pw; y = yc + (dy / d) * pw; }
      dx = x; dy = y - ys; d = Math.hypot(dx, dy);
      const D = Math.hypot(pw, ph);
      if (d > D) { x = (dx / d) * D; y = ys + (dy / d) * D; }
      return [x, y];
    }

    /** the heart: draw the book with one leaf folded over, corner at P (relative to the spine, in the leaf's own frame) */
    _drawTurn(spec, P0) {
      const ctx = this.ctx, pw = this.pw, ph = this.ph, T = this.T, d = this.dpr;
      const yc = spec.corner === 'b' ? T + ph : T, ys = spec.corner === 'b' ? T : T + ph;
      const P = this._clampP(P0, yc, ys);
      const prog = clamp((pw - P[0]) / (2 * pw), 0, 1);                 // 0 = flat, 1 = turned
      const s0 = this._sx(spec.fFrom), s1 = this._sx(spec.fTo);
      const sx = s0 + (s1 - s0) * ease.inOut(prog);                       // the book re-centres while a cover opens/closes
      const fi = this.rtl !== spec.mirror;                                // pre-flip page images so they end up upright

      ctx.save();
      if (spec.mirror) { ctx.translate(sx, 0); ctx.scale(-1, 1); ctx.translate(-sx, 0); }

      this._bookShadow(ctx, sx, spec.other !== null, true);
      if (spec.other !== null) this._paper(ctx, sx - pw, T, this._img(spec.other), fi, 'l');
      if (spec.under !== null) this._paper(ctx, sx, T, this._img(spec.under), fi, 'r');

      const C = [sx + pw, yc], Pw = [sx + P[0], P[1]];
      const dx = Pw[0] - C[0], dy = Pw[1] - C[1], len = Math.hypot(dx, dy);
      if (len < 1) {
        this._paper(ctx, sx, T, this._img(spec.front), fi, 'r');
        ctx.restore();
        return;
      }
      const n = [dx / len, dy / len], M = [(C[0] + Pw[0]) / 2, (C[1] + Pw[1]) / 2];
      const rect = [[sx, T], [sx + pw, T], [sx + pw, T + ph], [sx, T + ph]];
      const folded = clipPoly(rect, M, n, -1);      // the part that has been lifted
      const unfolded = clipPoly(rect, M, n, 1);     // the part still lying flat
      const flap = folded.map((p) => reflect(p, M, n));
      const shade = pw * 0.55;
      const fade = clamp((1 - prog) / 0.3, 0, 1);                           // curl shading strength: 0 when the sheet is flat again

      // shadow of the bending sheet on the page underneath, where the sheet used to be
      if (folded.length > 2 && spec.under !== null) {
        ctx.save(); polyPath(ctx, folded); ctx.clip();
        const g = ctx.createLinearGradient(M[0], M[1], M[0] - n[0] * shade * 0.5, M[1] - n[1] * shade * 0.5);
        g.addColorStop(0, 'rgba(0,0,0,' + (0.22 * fade) + ')'); g.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = g; ctx.fillRect(-this.W, -this.H, this.W * 3, this.H * 3);
        ctx.restore();
      }

      // the part still flat
      if (unfolded.length > 2) {
        ctx.save(); polyPath(ctx, unfolded); ctx.clip();
        this._paper(ctx, sx, T, this._img(spec.front), fi, 'r');
        ctx.restore();
      }

      if (flap.length > 2) {
        // soft shadow the lifted sheet throws on everything below
        ctx.save();
        ctx.beginPath(); ctx.rect(sx - pw, T - 30, pw * 2, ph + 60); ctx.clip();
        ctx.shadowColor = 'rgba(0,0,0,' + (0.45 * fade) + ')'; ctx.shadowBlur = 26 * d; ctx.shadowOffsetX = -9 * d * (spec.mirror ? -1 : 1); ctx.shadowOffsetY = 4 * d;
        polyPath(ctx, flap); ctx.fillStyle = PAPER; ctx.fill();
        ctx.restore();

        // the lifted sheet: its back face, mirrored across the fold line
        const r11 = 1 - 2 * n[0] * n[0], r12 = -2 * n[0] * n[1], r22 = 1 - 2 * n[1] * n[1];
        const k = 2 * (M[0] * n[0] + M[1] * n[1]);
        const a = -r11, b = -r12, c = r12, dd = r22;
        const e = r11 * (sx + pw) + r12 * T + k * n[0];
        const fy = r12 * (sx + pw) + r22 * T + k * n[1];
        ctx.save(); polyPath(ctx, flap); ctx.clip();
        ctx.save();
        ctx.transform(a, b, c, dd, e, fy);
        this._paper(ctx, 0, 0, this._img(spec.back), fi, 'l');
        ctx.restore();
        const g2 = ctx.createLinearGradient(M[0], M[1], M[0] + n[0] * shade, M[1] + n[1] * shade);
        g2.addColorStop(0, 'rgba(0,0,0,' + (0.36 * fade) + ')'); g2.addColorStop(0.16, 'rgba(0,0,0,' + (0.15 * fade) + ')'); g2.addColorStop(0.7, 'rgba(255,255,255,' + (0.07 * fade) + ')'); g2.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = g2; ctx.fillRect(-this.W, -this.H, this.W * 3, this.H * 3);
        ctx.restore();
        ctx.save(); polyPath(ctx, flap); ctx.strokeStyle = 'rgba(0,0,0,' + (0.16 * fade) + ')'; ctx.lineWidth = 1; ctx.stroke(); ctx.restore();
      }
      ctx.restore();
    }

    /* ------------------------------------------------------------ animation loop */
    _kick() { if (!this.raf) this.raf = requestAnimationFrame((t) => this._tick(t)); }

    _tick(now) {
      this.raf = 0;
      let again = false;
      const t = this.turn;
      if (t && t.anim) {
        const k = clamp((now - t.anim.t0) / t.anim.dur, 0, 1), e = t.anim.ease(k);
        t.P = t.anim.at(e);
        if (k >= 1) { this._finish(t, t.anim.complete); } else again = true;
      }
      // corner peek eases in and out
      if (!this.turn) {
        const diff = this.hoverGoal - this.hover;
        if (Math.abs(diff) > 0.004) { this.hover += diff * 0.2; again = true; } else this.hover = this.hoverGoal;
      }
      this._render();
      if (again || (this.turn && this.turn.anim)) this._kick();
    }

    _finish(t, complete) {
      this.turn = null;
      if (complete) { this.f = t.spec.fTo; this._ensure(); this._emit(); }
      this._render();
      const nxt = this.queue.shift();
      if (nxt) this._start(nxt.dir, nxt.corner);
      else this._kick();
    }

    _begin(spec, P, anim) {
      this.hover = 0; this.hoverGoal = 0;
      this.turn = { spec, P, anim: anim || null };
      this._kick();
    }

    /** a whole page turn (click, tap, key, button): the corner travels in a lifted arc */
    _start(dir, corner) {
      const spec = this._spec(dir);
      if (!spec) return;
      spec.corner = corner || 'b';
      const pw = this.pw, T = this.T, ph = this.ph;
      const yc = spec.corner === 'b' ? T + ph : T, lift = (spec.corner === 'b' ? -1 : 1) * ph * 0.2;
      this._begin(spec, [pw, yc], {
        t0: performance.now(), dur: 820, ease: ease.inOut, complete: true,
        at: (e) => [pw - 2 * pw * e, yc + lift * Math.sin(Math.PI * e)],
      });
    }

    /** let go of a dragged page: finish the turn or put it back */
    _release(t, complete) {
      const pw = this.pw, T = this.T, ph = this.ph;
      const yc = t.spec.corner === 'b' ? T + ph : T;
      const from = t.P.slice(), to = complete ? [-pw, yc] : [pw, yc];
      const prog = clamp((pw - from[0]) / (2 * pw), 0, 1);
      const frac = complete ? 1 - prog : prog;
      t.anim = {
        t0: performance.now(), dur: 260 + 520 * frac, ease: complete ? ease.out : ease.inOut, complete,
        at: (e) => [from[0] + (to[0] - from[0]) * e, from[1] + (to[1] - from[1]) * e],
      };
      this._kick();
    }

    _enqueue(dir, corner) {
      if (this.turn) { if (this.queue.length < 3) this.queue.push({ dir, corner }); return; }
      this._start(dir, corner);
    }

    /* ------------------------------------------------------------ public */
    next() { this._enqueue(1); }
    prev() { this._enqueue(-1); }
    /** -1 = the visual left side, +1 = the visual right side (swapped for right-to-left books) */
    visual(dir) { this._enqueue(this.rtl ? -dir : dir); }
    goTo(page) {
      const nf = this._fFor(page);
      if (nf === this.f) return;
      if (Math.abs(nf - this.f) === 1 && !this.turn) { this._enqueue(nf > this.f ? 1 : -1); return; }
      this.turn = null; this.queue.length = 0;
      this.f = nf; this._ensure(); this._emit(); this._render();
    }
    setRtl(v) { this.rtl = !!v; this.stage.classList.toggle('rtl', this.rtl); this._render(); this._emit(); }
    resize() {
      const m = this._measure();
      if (m.spread !== this.spread || Math.abs(m.pw - this.pw) > 2 || m.W !== this.W || m.H !== this.H) this.build(this.page);
    }
    destroy() {
      this.gen++;
      cancelAnimationFrame(this.raf);
      document.removeEventListener('keydown', this._key);
      ['pointerdown', 'pointermove', 'pointerup', 'pointercancel', 'pointerleave'].forEach((n) => this.stage.removeEventListener(n, this._h[n]));
      this.srcs.forEach((c, p) => this.o.release && this.o.release(p, c));
      this.cv.remove();
    }

    /* ------------------------------------------------------------ input */
    _pt(e) {
      const r = this.cv.getBoundingClientRect();
      let x = e.clientX - r.left; const y = e.clientY - r.top;
      if (this.rtl) x = r.width - x;                    // the canvas is mirrored by CSS in right-to-left books
      return [x, y];
    }
    /** which way would a grab at (x, y) turn? returns { dir, corner } or null */
    _hit(x, y) {
      const sx = this._sx(this.f), pw = this.pw, ph = this.ph, T = this.T;
      if (y < T - 20 || y > T + ph + 20) return null;
      const corner = y < T + ph / 2 ? 't' : 'b';
      if (this.spread) {
        if (x >= sx && x <= sx + pw && this.f < this._maxF()) return { dir: 1, corner };
        if (x < sx && x >= sx - pw && this.f > 0) return { dir: -1, corner };
        return null;
      }
      if (x < sx - 30 || x > sx + pw + 30) return null;
      if (x < sx + pw * 0.3 && this.f > 0) return { dir: -1, corner };
      if (this.f < this._maxF()) return { dir: 1, corner };
      return null;
    }

    _bind() {
      let d = null;
      const h = (this._h = {});
      h.pointerdown = (e) => {
        if (this.turn && this.turn.anim) return;
        const [x, y] = this._pt(e), hit = this._hit(x, y);
        if (!hit) return;
        d = { x, y, t: performance.now(), dir: hit.dir, corner: hit.corner, drag: false, id: e.pointerId, samples: [[performance.now(), x]] };
        try { this.stage.setPointerCapture(e.pointerId); } catch (_) {}
      };
      h.pointermove = (e) => {
        const [x, y] = this._pt(e);
        if (!d) {                                                        // hovering: peek the corner
          if (e.pointerType !== 'mouse' || this.turn) return;
          const hit = this._hit(x, y);
          const sx = this._sx(this.f), pw = this.pw, ph = this.ph, T = this.T;
          this.stage.style.cursor = hit ? 'grab' : 'default';
          let goal = 0;
          if (hit) {
            const cx = hit.dir > 0 ? sx + pw : (this.spread ? sx - pw : sx);
            const cy = hit.corner === 'b' ? T + ph : T;
            const near = Math.hypot((x - cx) / (pw * 0.3), (y - cy) / (ph * 0.2));
            goal = near < 1 ? 1 - near * 0.4 : 0;
            if (goal > 0) this.hoverSpec = Object.assign(this._spec(hit.dir) || {}, { corner: hit.corner });
          }
          if (goal > 0 && this.hoverSpec && this.hoverSpec.front === undefined) goal = 0;
          this.hoverGoal = goal; this._kick();
          return;
        }
        if (!d.drag) {
          if (Math.hypot(x - d.x, y - d.y) < 7) return;
          const spec = this._spec(d.dir);
          if (!spec) { d = null; return; }
          spec.corner = d.corner;
          const s0 = this._sx(spec.fFrom), pw = this.pw, T = this.T, ph = this.ph;
          const fx = (px) => (spec.mirror ? 2 * s0 - px : px) - s0;
          const yc = spec.corner === 'b' ? T + ph : T;
          const rel0 = fx(d.x);
          // a grab near the outer corner follows the pointer 1:1, a grab in the middle is amplified so the whole sheet can be pulled over
          const gain = clamp((2 * pw) / Math.max(rel0 + pw * 0.75, pw * 0.5), 1, 2.6);
          d.drag = true; d.spec = spec; d.fx = fx; d.rel0 = rel0; d.gain = gain; d.yc = yc;
          this._begin(spec, [pw, yc], null);
          this.stage.style.cursor = 'grabbing';
        }
        const t = this.turn; if (!t) return;
        const rel = d.fx(x);
        t.P = [this.pw + (rel - d.rel0) * d.gain, d.yc + (y - d.y) * d.gain * 0.9];
        d.samples.push([performance.now(), rel]); if (d.samples.length > 6) d.samples.shift();
        this._kick();
      };
      h.pointerup = (e) => {
        const dd = d; d = null;
        this.stage.style.cursor = 'default';
        try { this.stage.releasePointerCapture(e.pointerId); } catch (_) {}
        if (!dd) return;
        if (!dd.drag) {                                                  // a tap
          if (performance.now() - dd.t < 600) this._enqueue(dd.dir, dd.corner);
          return;
        }
        const t = this.turn; if (!t) return;
        const P = this._clampP(t.P, dd.yc, dd.spec.corner === 'b' ? this.T : this.T + this.ph);
        t.P = P;
        const prog = clamp((this.pw - P[0]) / (2 * this.pw), 0, 1);
        const s = dd.samples, a = s[0], b = s[s.length - 1];
        const v = b[0] > a[0] ? -(b[1] - a[1]) / (b[0] - a[0]) : 0;       // px/ms towards "turned"
        const complete = (prog > 0.3 || (v > 0.5 && prog > 0.05)) && !(v < -0.4);
        this._release(t, complete);
      };
      h.pointercancel = (e) => { if (d && d.drag && this.turn) this._release(this.turn, false); d = null; };
      h.pointerleave = () => { if (!d) { this.hoverGoal = 0; this.stage.style.cursor = 'default'; this._kick(); } };
      for (const n in h) this.stage.addEventListener(n, h[n]);

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
      document.addEventListener('keydown', this._key);
    }

    /** corner peek shown while the mouse rests near a corner */
    _peek() {
      if (this.hover < 0.02 || !this.hoverSpec || this.hoverSpec.front === undefined) return null;
      const spec = this.hoverSpec, pw = this.pw, T = this.T, ph = this.ph;
      const yc = spec.corner === 'b' ? T + ph : T, peel = 66 * this.hover;
      return { spec, P: [pw - peel, yc + (spec.corner === 'b' ? -peel * 0.75 : peel * 0.75)] };
    }
  }

  root.FlipBook = FlipBook;
})(typeof window !== 'undefined' ? window : globalThis);
