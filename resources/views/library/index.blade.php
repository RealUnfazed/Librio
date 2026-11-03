@extends('layout')
@section('content')
<section class="hero">
  <h1>Hello, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}.</h1>
  <p>What will you read today?</p>
  <form class="search" method="get" action="{{ route('search') }}">
    <input type="search" name="q" placeholder="Search by title or author" dir="auto">
    <button class="btn">Search</button>
  </form>
</section>

@if($categories->isNotEmpty())
<div class="chips strip">
  @foreach($categories as $c)
    <a class="chip" dir="auto" href="{{ route('search', ['cat' => [$c->id]]) }}">{{ $c->name }} <small>{{ $c->books_count }}</small></a>
  @endforeach
</div>
@endif

@include('partials.view-toggle')

@if($continue->isNotEmpty())
  @include('partials.shelf-head', ['id' => 'shelf-continue', 'title' => 'Continue reading'])
  <div class="shelf" id="shelf-continue">
    @foreach($continue as $p) @include('partials.card', ['book' => $p->book, 'page' => $p->page]) @endforeach
  </div>
@endif

@forelse($shelves as $i => $shelf)
  @php($title = ($shelf['id'] === null && $shelves->count() === 1) ? 'All books' : $shelf['name'])
  @php($shelfLink = $shelf['id'] ? route('search', ['cat' => [$shelf['id']]]) : null)
  <div class="card-shelves">
    @include('partials.shelf-head', array_merge(['id' => 'shelf-'.$i, 'title' => $title, 'count' => $shelf['total']], $shelfLink ? ['link' => $shelfLink] : []))
    <div class="shelf" id="shelf-{{ $i }}">
      @foreach($shelf['books'] as $book) @include('partials.card', ['book' => $book]) @endforeach
    </div>
  </div>
  @include('partials.bookcase', array_merge(['books' => $shelf['books'], 'title' => $title, 'count' => $shelf['total']], $shelfLink ? ['link' => $shelfLink] : []))
@empty
  <div class="empty" style="margin-top:28px">No books yet.@if(auth()->user()->is_admin) <a href="{{ route('admin.books.create') }}">Add the first one</a>.@endif</div>
@endforelse
@endsection
