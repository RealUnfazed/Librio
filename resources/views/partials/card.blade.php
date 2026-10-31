@php($h = crc32((string) $book->title) % 360)
<a class="card" href="{{ route('books.show', $book) }}">
  <div class="cover" style="--h:{{ $h }}">
    <span class="ft" dir="auto">{{ $book->title }}</span>
    <img loading="lazy" alt="" src="{{ route('books.cover', $book) }}" onerror="this.remove()">
  </div>
  <div class="meta">
    <strong dir="auto">{{ $book->title }}</strong>
    @if($book->author)<span dir="auto">{{ $book->author }}</span>@endif
    @isset($page)
      @if($book->pages)<div class="bar"><i style="width:{{ min(100, round($page / $book->pages * 100)) }}%"></i></div>@endif
      <small>Page {{ $page }}@if($book->pages) of {{ $book->pages }}@endif</small>
    @endisset
  </div>
</a>
