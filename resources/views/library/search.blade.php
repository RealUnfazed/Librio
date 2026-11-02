@extends('layout')
@section('title', 'Search')
@section('content')
<div class="page-head"><h1>Search</h1></div>

<form class="search big" method="get" action="{{ route('search') }}">
  <input type="search" name="q" value="{{ $q }}" placeholder="Title or author" dir="auto" autofocus>
  @foreach($cats as $cid)<input type="hidden" name="cat[]" value="{{ $cid }}">@endforeach
  <button class="btn">Search</button>
</form>

@if($categories->isNotEmpty())
<div class="chips strip">
  @foreach($categories as $c)
    @php($next = in_array($c->id, $cats) ? array_values(array_diff($cats, [$c->id])) : array_merge($cats, [$c->id]))
    <a class="chip {{ in_array($c->id, $cats) ? 'on' : '' }}" dir="auto" href="{{ route('search', array_filter(['q' => $q, 'cat' => $next])) }}">{{ $c->name }} <small>{{ $c->books_count }}</small></a>
  @endforeach
</div>
@endif

@if($books === null)
  <div class="empty" style="margin-top:24px">Type a title or author, or pick one or more categories.</div>
@elseif($books->isEmpty())
  <div class="empty" style="margin-top:24px">Nothing found.</div>
@else
  <div class="grid" style="margin-top:22px">@foreach($books as $book) @include('partials.card', ['book' => $book]) @endforeach</div>
  {{ $books->links('pagination::simple-default') }}
@endif
@endsection
