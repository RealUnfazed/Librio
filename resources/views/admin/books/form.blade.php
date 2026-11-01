@extends('layout')
@section('title', $book->exists ? 'Edit book' : 'Add book')
@section('content')
<form id="bookForm" class="panel form-wide" method="post" enctype="multipart/form-data"
      action="{{ $book->exists ? route('admin.books.update', $book) : route('admin.books.store') }}">
  @csrf
  @if($book->exists) @method('PUT') @endif
  <h1 style="font-size:1.6rem">{{ $book->exists ? 'Edit book' : 'Add book' }}</h1>

  @unless($book->exists)
    <label>PDF file (max 50 MB)<input type="file" id="pdf" name="pdf" accept="application/pdf" required></label>
    <p class="muted" id="pdfInfo" style="margin:-6px 0 0;font-size:.86rem">Cover and page count are read from the PDF in your browser.</p>
  @endunless

  <label>Title<input name="title" id="title" value="{{ old('title', $book->title) }}" required dir="auto"></label>
  <label>Author<input name="author" value="{{ old('author', $book->author) }}" dir="auto"></label>
  <label>Category<input name="category" value="{{ old('category', $book->category) }}" dir="auto"></label>
  <label>Description<textarea name="description" rows="5" dir="auto">{{ old('description', $book->description) }}</textarea></label>
  <label>Pages<input type="number" name="pages" id="pages" value="{{ old('pages', $book->pages) }}" min="1"></label>
  <input type="hidden" name="cover_data" id="cover_data">
  <img id="coverPreview" alt="" class="preview" hidden>
  <label class="inline"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $book->is_published))> Published (visible to readers)</label>
  <div id="upErr" class="flash err" hidden></div>
  <progress id="upBar" max="1" value="0" hidden></progress>
  <p id="upMsg" class="muted" style="margin:6px 0 12px" hidden></p>
  <button class="btn" id="save">{{ $book->exists ? 'Save changes' : 'Encrypt & upload' }}</button>
  @unless($book->exists)<p class="muted" style="font-size:.86rem">Big files are sent in small pieces, then encrypted and passed to Postfile. Keep this page open until it finishes.</p>@endunless
</form>

@unless($book->exists)
@php
$urls = [
    'init' => route('admin.uploads.init'),
    'piece' => route('admin.uploads.piece', ['upload' => 'UPLOAD_ID', 'index' => 0]),
    'finish' => route('admin.uploads.finish', ['upload' => 'UPLOAD_ID']),
];
@endphp
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
const URLS = @json($urls);
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

/* ---- cover + page count, read from the PDF in the browser ---- */
document.getElementById('pdf').addEventListener('change', async (e) => {
  const f = e.target.files[0]; if (!f) return;
  const info = document.getElementById('pdfInfo'); info.textContent = 'Reading PDF…';
  try {
    const pdf = await pdfjsLib.getDocument({ data: new Uint8Array(await f.arrayBuffer()) }).promise;
    document.getElementById('pages').value = pdf.numPages;
    const page = await pdf.getPage(1);
    const vp1 = page.getViewport({ scale: 1 });
    const vp = page.getViewport({ scale: 360 / vp1.width });
    const c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
    await page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
    const url = c.toDataURL('image/webp', 0.8);
    document.getElementById('cover_data').value = url;
    const img = document.getElementById('coverPreview'); img.src = url; img.hidden = false;
    const t = document.getElementById('title');
    if (!t.value) t.value = f.name.replace(/\.pdf$/i, '').replace(/[_-]+/g, ' ');
    info.textContent = pdf.numPages + ' pages · cover generated.';
  } catch (err) { info.textContent = 'Could not read this PDF in the browser (you can still upload it, without a cover).'; }
});

/* ---- chunked upload: 3 MB pieces -> server encrypts + stages them -> server sends the file to Postfile ---- */
const form = document.getElementById('bookForm');
let session = null;   // kept after a failed "finish", so a retry does not re-upload every piece

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const file = document.getElementById('pdf').files[0];
  const btn = document.getElementById('save'), bar = document.getElementById('upBar');
  const msg = document.getElementById('upMsg'), err = document.getElementById('upErr');
  err.hidden = true;
  if (!file) return;
  if (file.size > 50 * 1024 * 1024) { err.textContent = 'This file is larger than 50 MB (the Postfile free-plan limit).'; err.hidden = false; return; }

  const H = { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' };
  const json = async (r) => {
    const j = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(j.message || ('Server error ' + r.status));
    return j;
  };
  btn.disabled = true; bar.hidden = false; msg.hidden = false; bar.value = 0;

  try {
    if (!session || session.name !== file.name || session.size !== file.size) {
      msg.textContent = 'Preparing…';
      const j = await json(await fetch(URLS.init, { method: 'POST', headers: { ...H, 'Content-Type': 'application/json' }, body: JSON.stringify({ name: file.name, size: file.size }) }));
      session = { ...j, name: file.name, size: file.size, sent: false };
    }

    if (!session.sent) {
      for (let i = 0; i < session.pieces; i++) {
        const blob = file.slice(i * session.piece_size, Math.min(file.size, (i + 1) * session.piece_size));
        const url = URLS.piece.replace('UPLOAD_ID', session.id).replace(/\/pieces\/0$/, '/pieces/' + i);
        let done = false, last;
        for (let t = 0; t < 4 && !done; t++) {          // each piece gets 4 tries
          try { await json(await fetch(url, { method: 'POST', headers: { ...H, 'Content-Type': 'application/octet-stream' }, body: blob })); done = true; }
          catch (x) { last = x; await new Promise((r) => setTimeout(r, 900 * (t + 1))); }
        }
        if (!done) throw last;
        bar.value = ((i + 1) / session.pieces) * 0.85;
        msg.textContent = 'Uploading… ' + Math.round(((i + 1) / session.pieces) * 100) + '%';
      }
      session.sent = true;
    }

    msg.textContent = 'Sending to Postfile… this can take a minute, keep this page open.';
    bar.removeAttribute('value');
    const f = form.elements;
    const meta = {
      title: f['title'].value, author: f['author'].value, category: f['category'].value, description: f['description'].value,
      pages: f['pages'].value || null, cover_data: f['cover_data'].value || null, is_published: f['is_published'].checked,
    };
    const j = await json(await fetch(URLS.finish.replace('UPLOAD_ID', session.id), { method: 'POST', headers: { ...H, 'Content-Type': 'application/json' }, body: JSON.stringify(meta) }));
    location.href = j.redirect;
  } catch (x) {
    err.textContent = x.message + ' Press the button again to retry.'; err.hidden = false;
    btn.disabled = false; bar.hidden = true; msg.hidden = true;
  }
});
</script>
@endpush
@endunless
@endsection
