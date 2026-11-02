<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title') · {{ config('app.name') }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
@include('partials.theme-init')
@include('partials.pwa')
</head>
<body class="auth">
<div class="theme-corner">@include('partials.theme-toggle')</div>
<div class="panel">
  <div class="brand"><span class="logo"><svg class="i" viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16z"/><path d="M8 7.5h8M8 11h6"/></svg></span>{{ config('app.name') }}</div>
  @if($errors->any())<div class="flash err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
  @yield('content')
</div>
@include('partials.theme-script')
</body>
</html>
