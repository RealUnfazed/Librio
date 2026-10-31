@extends('layout')
@section('content')
<section class="hero">
  <h1>Hello, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}.</h1>
  <p>What will you read today?</p>
  <form class="search" method="get" action="{{ route('library') }}">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by title or author" dir="auto">
    @if(request('cat'))<input type="hidden" name="cat" value="{{ request('cat') }}">@endif
    <button class="btn">Search</button>
  </form>
</section>

@if($categories->isNotEmpty())
<div class="chips">
  <a class="chip {{ request('cat') ? '' : 'on' }}" href="{{ route('library', array_filter(['q' => request('q')])) }}">All</a>
  @foreach($categories as $c)
    <a class="chip {{ request('cat') === $c ? 'on' : '' }}" dir="auto" href="{{ route('library', array_filter(['q' => request('q'), 'cat' => $c])) }}">{{ $c }}</a>
  @endforeach
</div>
@endif

@if($continue->isNotEmpty())
  <h2>Continue reading</h2>
  <div class="grid">
    @foreach($continue as $p) @include('partials.card', ['book' => $p->book, 'page' => $p->page]) @endforeach
  </div>
@endif

<h2>{{ request()->hasAny(['q', 'cat']) ? 'Results' : 'All books' }}</h2>
@if($books->isEmpty())
  <div class="empty">No books found.</div>
@else
  <div class="grid">@foreach($books as $book) @include('partials.card', ['book' => $book]) @endforeach</div>
@endif
{{ $books->links('pagination::simple-default') }}
@endsection
