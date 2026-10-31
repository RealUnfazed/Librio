@extends('auth.layout')
@section('title', 'Log in')
@section('content')
<h1>Welcome back</h1>
<p class="sub">Log in to continue reading.</p>
<form method="post" action="{{ url('/login') }}">
  @csrf
  <label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
  <label>Password<input type="password" name="password" required></label>
  <label class="inline"><input type="checkbox" name="remember" value="1"> Remember me</label>
  <button class="btn">Log in</button>
</form>
@if(config('library.registration') !== 'closed')<p class="sub" style="margin-top:16px">New here? <a href="{{ route('register') }}">Create an account</a></p>@endif
@endsection
