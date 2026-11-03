@extends('layout')
@section('title', $book->title)
@section('content')
@php($h = crc32((string) $book->title) % 360)
<div class="detail">
  <div class="cover" style="--h:{{ $h }}">
    <span class="ft" dir="auto">{{ $book->title }}</span>
    <img alt="" src="{{ route('books.cover', $book) }}" onerror="this.remove()">
  </div>
  <div>
    <h1 dir="auto">{{ $book->title }}</h1>
    @if($book->authors->isNotEmpty() || $book->authorLine() !== '')
    <p class="byline">
      @forelse($book->authors->sortBy('pivot.position') as $a)<a href="{{ route('search', ['author' => $a->id]) }}" dir="auto">{{ $a->name }}</a>@if(! $loop->last)<span class="sep">,</span> @endif
      @empty<span dir="auto">{{ $book->authorLine() }}</span>
      @endforelse
    </p>
    @endif
    <div class="facts">
      @foreach($book->categories as $c)<a class="chip" dir="auto" href="{{ route('search', ['cat' => [$c->id]]) }}">{{ $c->name }}</a>@endforeach
      @if($book->pages)<span class="fact">{{ $book->pages }} pages</span>@endif
      <span class="fact">{{ number_format($book->plain_size / 1048576, 1) }} MB</span>
    </div>
    @if($book->description)<p class="desc" dir="auto">{!! nl2br(e($book->description)) !!}</p>@endif
    <div class="actions-row">
      <a class="btn" href="{{ route('books.read', $book) }}">{{ $page ? 'Continue · page '.$page : 'Start reading' }}</a>
      @if(auth()->user()->is_admin)<a class="btn ghost" href="{{ route('admin.books.edit', $book) }}">Edit</a>@endif
    </div>
  </div>
</div>
@endsection
