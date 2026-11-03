<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex,nofollow">
<title>{{ $book->title }} · {{ config('app.name') }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
@include('partials.theme-init')
@include('partials.pwa')
</head>
<body class="reader">
<div id="bar">
  <a id="back" href="{{ route('books.show', $book) }}" title="Back"><svg class="i" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></a>
  <span id="title" dir="auto">{{ $book->title }}</span>
  <span id="pager"><input id="pageInput" type="number" min="1" value="1" inputmode="numeric"> / <span id="pageTotal">–</span></span>
  <button id="zoomOut" title="Zoom out"><svg class="i" viewBox="0 0 24 24"><path d="M5 12h14"/></svg></button>
  <button id="zoomIn" title="Zoom in"><svg class="i" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
  <button id="fit" title="Fit to width"><svg class="i" viewBox="0 0 24 24"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg></button>
  <button id="mode" title="Switch between book and scrolling view"><svg class="i" viewBox="0 0 24 24"><path d="M2 5.5C4 4.5 7 4.5 12 7c5-2.5 8-2.5 10-1.5V19c-2-1-5-1-10 1.5C7 18 4 18 2 19zM12 7v13"/></svg></button>
  <button id="rtl" title="Right-to-left book (Persian, Arabic, Hebrew)"><svg class="i" viewBox="0 0 24 24"><path d="M7 7h12M15 3l4 4-4 4M17 17H5M9 13l-4 4 4 4"/></svg></button>
  <button id="theme" title="Light / sepia / dark"><svg class="i" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor"/></svg></button>
  <button id="bm" title="Bookmark this page"><svg class="i" viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z"/></svg></button>
  <button id="menu" title="Contents, bookmarks, search"><svg class="i" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
  <div id="prog"></div>
</div>

<aside id="side" hidden>
  <div class="tabs">
    <button data-tab="toc" class="on">Contents</button>
    <button data-tab="bms">Bookmarks</button>
    <button data-tab="find">Search</button>
  </div>
  <div id="toc" class="pane"></div>
  <div id="bms" class="pane" hidden></div>
  <div id="find" class="pane" hidden>
    <input id="q" type="search" placeholder="Search in this book" dir="auto">
    <div id="results"></div>
  </div>
</aside>

<div id="viewer"><div id="pages"></div></div>

<div id="book" hidden>
  <div class="fb-stage" id="fbStage"></div>
  <button id="fbPrev" class="fb-nav left" aria-label="Previous page">&#8249;</button>
  <button id="fbNext" class="fb-nav right" aria-label="Next page">&#8250;</button>
</div>

<div id="loader"><div><p id="loadMsg">Opening…</p><progress id="loadBar" max="1" value="0"></progress></div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>window.READER = @json($cfg);</script>
<script src="{{ asset('js/chunk-decrypt.js') }}"></script>
<script src="{{ asset('js/flipbook.js') }}"></script>
<script src="{{ asset('js/reader.js') }}"></script>
</body>
</html>
