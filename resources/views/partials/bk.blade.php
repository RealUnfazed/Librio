@php
  $h = crc32((string) $book->title) % 360;
  $pagesN = (int) ($book->pages ?: 220);
  $w = max(30, min(68, 24 + intdiv($pagesN, 7)));   // thicker book = more pages
  $ht = 168 + (crc32('h' . $book->id) % 5) * 12;     // slightly different heights, like real books
  $by = $book->authorLine();
@endphp
<a class="bk" href="{{ route('books.show', $book) }}" title="{{ $book->title }}{{ $by !== '' ? ' — ' . $by : '' }}" style="--h:{{ $h }};--w:{{ $w }}px;--ht:{{ $ht }}px">
  <span class="bk-spine">
    <b><span dir="auto">{{ $book->title }}</span></b>
    @if($by !== '')<i><span dir="auto">{{ $by }}</span></i>@endif
  </span>
  <span class="bk-cover">
    <span class="ft" dir="auto">{{ $book->title }}</span>
    <img loading="lazy" alt="" src="{{ route('books.cover', $book) }}" onerror="this.remove()">
  </span>
</a>
