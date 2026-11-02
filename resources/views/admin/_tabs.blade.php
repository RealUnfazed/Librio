<div class="seg">
  <a class="{{ $active === 'books' ? 'on' : '' }}" href="{{ route('admin.books.index') }}">Books</a>
  <a class="{{ $active === 'invites' ? 'on' : '' }}" href="{{ route('admin.invites.index') }}">Invites</a>
  <a class="{{ $active === 'health' ? 'on' : '' }}" href="{{ route('admin.health') }}">Health</a>
</div>
