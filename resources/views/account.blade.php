@extends('layout')
@section('title', 'Account')
@section('content')
<div class="page-head"><h1>Account</h1></div>

<div class="panel acct">
  <span class="avatar big">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
  <div><strong dir="auto" style="font-size:1.15rem">{{ $user->name }}</strong><div class="muted">{{ $user->email }}</div>@if($user->is_admin)<span class="pill" style="margin-top:6px">Admin</span>@endif</div>
</div>

<h2>Appearance</h2>
<div class="seg" id="themeSeg">
  <button type="button" data-theme-set="">Auto</button>
  <button type="button" data-theme-set="light">Light</button>
  <button type="button" data-theme-set="dark">Dark</button>
</div>

@if($user->is_admin)
<h2>Manage</h2>
<div class="panel list">
  <a href="{{ route('admin.books.index') }}">@include('partials.icon', ['n' => 'books'])<span>Books</span>@include('partials.icon', ['n' => 'right'])</a>
  <a href="{{ route('admin.invites.index') }}">@include('partials.icon', ['n' => 'mail'])<span>Invites</span>@include('partials.icon', ['n' => 'right'])</a>
  <a href="{{ route('admin.health') }}">@include('partials.icon', ['n' => 'pulse'])<span>Health check</span>@include('partials.icon', ['n' => 'right'])</a>
</div>
@endif

<form method="post" action="{{ route('logout') }}" style="margin-top:28px">@csrf<button class="btn ghost">@include('partials.icon', ['n' => 'logout']) Log out</button></form>

@push('scripts')
<script>
(function () {
  var seg = document.getElementById('themeSeg'), d = document.documentElement;
  function mark() {
    var cur = ''; try { cur = localStorage.getItem('theme') || ''; } catch (e) {}
    if (cur === 'sepia') cur = '';
    seg.querySelectorAll('button').forEach(function (b) { b.classList.toggle('on', b.dataset.themeSet === cur); });
  }
  seg.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    var v = b.dataset.themeSet;
    try { v ? localStorage.setItem('theme', v) : localStorage.removeItem('theme'); } catch (x) {}
    if (v) d.dataset.theme = v; else delete d.dataset.theme;
    mark();
  });
  mark();
})();
</script>
@endpush
@endsection
