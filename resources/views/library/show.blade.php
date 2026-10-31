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
    @if($book->author)<p class="muted" style="font-size:1.1rem;margin:6px 0 0" dir="auto">{{ $book->author }}</p>@endif
    <div class="facts">
      @if($book->category)<span class="fact" dir="auto">{{ $book->category }}</span>@endif
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
