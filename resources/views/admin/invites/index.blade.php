@extends('layout')
@section('title', 'Invites')
@section('content')
<div class="row"><h1>Invites</h1><span class="pill">Registration: {{ config('library.registration') }}</span></div>
<p class="muted">Set <code>LIBRARY_REGISTRATION</code> to <code>open</code>, <code>invite</code> or <code>closed</code>.</p>
<div class="panel">
  <form class="inline-form" method="post" action="{{ route('admin.invites.store') }}">
    @csrf
    <input name="label" placeholder="Label (optional)">
    <input type="number" name="max_uses" value="1" min="1" title="How many people can use it">
    <input type="number" name="days" placeholder="Expires in days" min="1">
    <button class="btn">Create invite</button>
  </form>
  <div class="scroll"><table>
    <tr><th>Code / link</th><th>Label</th><th>Used</th><th>Expires</th><th></th></tr>
    @forelse($invites as $i)
    <tr>
      <td><code>{{ $i->code }}</code><br><small class="muted">{{ route('register', ['invite' => $i->code]) }}</small></td>
      <td>{{ $i->label }}</td><td>{{ $i->uses }} / {{ $i->max_uses }}</td><td>{{ $i->expires_at?->format('Y-m-d') ?? '—' }}</td>
      <td><form method="post" action="{{ route('admin.invites.destroy', $i) }}">@csrf @method('DELETE')<button class="link danger">Remove</button></form></td>
    </tr>
    @empty
    <tr><td colspan="5" class="muted">No invites yet.</td></tr>
    @endforelse
  </table></div>
</div>
@endsection
