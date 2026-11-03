@extends('layout')
@section('title', 'Books')
@section('content')
@include('admin._tabs', ['active' => 'books'])
<div class="page-head"><h1>Books</h1><a class="btn" href="{{ route('admin.books.create') }}">+ Add book</a></div>
<div class="panel scroll"><table>
  <tr><th>Title</th><th>Authors</th><th>Categories</th><th>Pages</th><th>Size</th><th>Status</th><th></th></tr>
  @forelse($books as $b)
  <tr>
    <td dir="auto"><strong>{{ $b->title }}</strong></td><td dir="auto">{{ $b->authorLine() }}</td><td>@foreach($b->categories as $c)<span class="chip sm" dir="auto">{{ $c->name }}</span> @endforeach</td><td>{{ $b->pages }}</td>
    <td>{{ number_format($b->plain_size / 1048576, 1) }} MB</td>
    <td><span class="pill {{ $b->is_published ? '' : 'off' }}">{{ $b->is_published ? 'Published' : 'Hidden' }}</span></td>
    <td class="actions">
      <a href="{{ route('admin.books.edit', $b) }}">Edit</a>
      <form method="post" action="{{ route('admin.books.destroy', $b) }}" onsubmit="return confirm('Delete this book and its file?')">@csrf @method('DELETE')<button class="link danger">Delete</button></form>
    </td>
  </tr>
  @empty
  <tr><td colspan="7" class="muted">No books yet. Add your first one.</td></tr>
  @endforelse
</table></div>
{{ $books->links('pagination::simple-default') }}
@endsection
