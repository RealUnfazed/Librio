@extends('layout')
@section('title', 'Health')
@section('content')
@include('admin._tabs', ['active' => 'health'])
<div class="page-head"><h1>Health check</h1></div>
<div class="panel">
  @foreach($results as $r)
    <p style="margin:.5em 0">
      @if($r['ok'] === true)<span class="pill">OK</span>@elseif($r['ok'] === false)<span class="pill" style="background:var(--err-bg);color:var(--err-ink)">FIX</span>@else<span class="pill off">INFO</span>@endif
      {{ $r['text'] }}
    </p>
  @endforeach
  <form method="post" action="{{ route('admin.health') }}" style="margin-top:20px">
    @csrf
    <button class="btn">Run upload test (CORS / Range, uses 1 Postfile upload)</button>
  </form>
</div>
@endsection
