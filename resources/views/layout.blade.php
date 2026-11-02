<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title', 'Library') · {{ config('app.name') }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
@include('partials.theme-init')
@include('partials.pwa')
</head>
<body class="app">
@php($u = auth()->user())

{{-- desktop: sidebar --}}
<aside class="sidebar">
  <a class="brand" href="{{ route('library') }}">
    <span class="logo"><svg class="i" viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16z"/><path d="M8 7.5h8M8 11h6"/></svg></span>
    {{ config('app.name') }}
  </a>
  <nav class="navlist">
    <a class="navlink {{ request()->routeIs('library', 'books.show') ? 'on' : '' }}" href="{{ route('library') }}">@include('partials.icon', ['n' => 'home'])<span>Library</span></a>
    <a class="navlink {{ request()->routeIs('search') ? 'on' : '' }}" href="{{ route('search') }}">@include('partials.icon', ['n' => 'search'])<span>Search</span></a>
    @if($u->is_admin)
      <div class="navlabel">Manage</div>
      <a class="navlink {{ request()->routeIs('admin.books.*', 'admin.uploads.*') ? 'on' : '' }}" href="{{ route('admin.books.index') }}">@include('partials.icon', ['n' => 'books'])<span>Books</span></a>
      <a class="navlink {{ request()->routeIs('admin.invites.*') ? 'on' : '' }}" href="{{ route('admin.invites.index') }}">@include('partials.icon', ['n' => 'mail'])<span>Invites</span></a>
      <a class="navlink {{ request()->routeIs('admin.health') ? 'on' : '' }}" href="{{ route('admin.health') }}">@include('partials.icon', ['n' => 'pulse'])<span>Health</span></a>
    @endif
  </nav>
  <div class="sidefoot">
    <a class="usercard {{ request()->routeIs('account') ? 'on' : '' }}" href="{{ route('account') }}">
      <span class="avatar">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
      <span class="who"><strong dir="auto">{{ $u->name }}</strong><small>{{ $u->email }}</small></span>
    </a>
    @include('partials.theme-toggle')
  </div>
</aside>

{{-- mobile: slim top bar --}}
<header class="appbar">
  <a class="brand" href="{{ route('library') }}">
    <span class="logo"><svg class="i" viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16z"/><path d="M8 7.5h8M8 11h6"/></svg></span>
    {{ config('app.name') }}
  </a>
  <span class="grow"></span>
  @include('partials.theme-toggle')
</header>

<div class="main">
  <main class="view">
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="flash err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @yield('content')
  </main>
</div>

{{-- mobile: bottom tab bar --}}
<nav class="tabbar">
  <a class="{{ request()->routeIs('library', 'books.show') ? 'on' : '' }}" href="{{ route('library') }}"><b>@include('partials.icon', ['n' => 'home'])</b><span>Library</span></a>
  <a class="{{ request()->routeIs('search') ? 'on' : '' }}" href="{{ route('search') }}"><b>@include('partials.icon', ['n' => 'search'])</b><span>Search</span></a>
  @if($u->is_admin)
    <a class="{{ request()->routeIs('admin.*') ? 'on' : '' }}" href="{{ route('admin.books.index') }}"><b>@include('partials.icon', ['n' => 'shield'])</b><span>Manage</span></a>
  @endif
  <a class="{{ request()->routeIs('account') ? 'on' : '' }}" href="{{ route('account') }}"><b>@include('partials.icon', ['n' => 'user'])</b><span>Account</span></a>
</nav>

@stack('scripts')
@include('partials.theme-script')
@include('partials.app-js')
</body>
</html>
