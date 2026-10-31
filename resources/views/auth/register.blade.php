@extends('auth.layout')
@section('title', 'Register')
@section('content')
<h1>Create your account</h1>
<p class="sub">@if($mode === 'invite')You need an invite code to join.@else Start reading in a minute.@endif</p>
<form method="post" action="{{ url('/register') }}">
  @csrf
  <label>Name<input name="name" value="{{ old('name') }}" required></label>
  <label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
  <label>Password<input type="password" name="password" minlength="8" required></label>
  <label>Repeat password<input type="password" name="password_confirmation" required></label>
  @if($mode === 'invite')
    <label>Invite code<input name="invite" value="{{ old('invite', request('invite')) }}" required autocapitalize="characters"></label>
  @endif
  <button class="btn">Create account</button>
</form>
<p class="sub" style="margin-top:16px">Already registered? <a href="{{ route('login') }}">Log in</a></p>
@endsection
