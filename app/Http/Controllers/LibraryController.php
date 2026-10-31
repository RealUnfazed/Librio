<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\ReadingProgress;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string) $r->input('q', ''));
        $cat = (string) $r->input('cat', '');

        $books = Book::query()
            ->select(['id', 'title', 'author', 'category', 'pages', 'created_at'])
            ->where('is_published', true)
            ->when($q !== '', fn ($qb) => $qb->where(fn ($w) => $w
                ->where('title', 'like', '%' . $q . '%')
                ->orWhere('author', 'like', '%' . $q . '%')))
            ->when($cat !== '', fn ($qb) => $qb->where('category', $cat))
            ->latest()
            ->simplePaginate(24)
            ->withQueryString();

        $categories = Book::where('is_published', true)->whereNotNull('category')
            ->distinct()->orderBy('category')->pluck('category');

        $continue = collect();
        if ($q === '' && $cat === '') {
            $continue = ReadingProgress::with(['book:id,title,author,category,pages,is_published'])
                ->where('user_id', $r->user()->id)
                ->latest('updated_at')->limit(6)->get()
                ->filter(fn ($p) => $p->book && $p->book->is_published);
        }

        return view('library.index', compact('books', 'categories', 'continue'));
    }

    public function show(Request $r, Book $book)
    {
        abort_unless($book->is_published || $r->user()->is_admin, 404);
        $page = ReadingProgress::where(['user_id' => $r->user()->id, 'book_id' => $book->id])->value('page');

        return view('library.show', compact('book', 'page'));
    }

    public function cover(Request $r, Book $book)
    {
        abort_unless($book->is_published || $r->user()->is_admin, 404);
        $data = $book->getRawOriginal('cover_data');
        abort_unless($data && preg_match('#^data:(image/(?:webp|jpeg|png));base64,(.+)$#', $data, $m), 404);

        return response(base64_decode($m[2]), 200, [
            'Content-Type' => $m[1],
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
