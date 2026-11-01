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
</head>
<body>
<header class="top">
  <a class="brand" href="{{ route('library') }}">
    <span class="logo"><svg class="i" viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16z"/><path d="M8 7.5h8M8 11h6"/></svg></span>
    {{ config('app.name') }}
  </a>
  @auth
  <nav>
    <a href="{{ route('library') }}">Library</a>
    @if(auth()->user()->is_admin)<a href="{{ route('admin.books.index') }}">Books</a><a href="{{ route('admin.invites.index') }}">Invites</a><a href="{{ route('admin.health') }}">Health</a>@endif
    <form method="post" action="{{ route('logout') }}">@csrf<button>Log out</button></form>
    @include('partials.theme-toggle')
    <span class="avatar" title="{{ auth()->user()->email }}">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
  </nav>
  @endauth
</header>
<main class="wrap">
  @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="flash err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
  @yield('content')
</main>
@stack('scripts')
</body>
</html>
