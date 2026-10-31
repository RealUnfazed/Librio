@extends('layout')
@section('title', $book->exists ? 'Edit book' : 'Add book')
@section('content')
<form class="panel form-wide" method="post" enctype="multipart/form-data"
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
  <button class="btn">{{ $book->exists ? 'Save changes' : 'Encrypt & upload' }}</button>
  @unless($book->exists)<p class="muted" style="font-size:.86rem">Large files take a while: the PDF is encrypted, then sent to Postfile.</p>@endunless
</form>

@unless($book->exists)
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
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
</script>
@endpush
@endunless
@endsection
