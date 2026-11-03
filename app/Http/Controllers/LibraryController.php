<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\ReadingProgress;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    private const CARD_COLUMNS = ['id', 'title', 'author', 'pages', 'created_at'];

    /** Home: "continue reading" + one shelf per category. */
    public function index(Request $r)
    {
        $books = Book::query()
            ->select(self::CARD_COLUMNS)
            ->where('is_published', true)
            ->with(['categories:id,name', 'authors:id,name'])
            ->latest()
            ->get();

        $shelves = [];
        foreach ($books as $b) {
            $cats = $b->categories->isEmpty() ? [null] : $b->categories->all();
            foreach ($cats as $c) {
                $key = $c ? $c->id : 0;
                $shelves[$key] ??= ['id' => $c?->id, 'name' => $c?->name ?? 'More books', 'total' => 0, 'books' => []];
                $shelves[$key]['total']++;
                if (count($shelves[$key]['books']) < 24) {
                    $shelves[$key]['books'][] = $b;
                }
            }
        }
        $shelves = collect($shelves)
            ->sortBy(fn ($s) => [$s['id'] === null ? 1 : 0, mb_strtolower($s['name'])])
            ->values();

        $categories = $this->categoriesWithCounts();

        $continue = ReadingProgress::with(['book:id,title,author,pages,is_published', 'book.authors:id,name'])
            ->where('user_id', $r->user()->id)
            ->latest('updated_at')->limit(10)->get()
            ->filter(fn ($p) => $p->book && $p->book->is_published);

        return view('library.index', compact('shelves', 'categories', 'continue'));
    }

    /** Search + category filter (several categories = books in ANY of them). */
    public function search(Request $r)
    {
        $q = trim((string) $r->input('q', ''));
        $cats = array_values(array_filter(array_map('intval', (array) $r->input('cat', []))));
        $author = $r->filled('author') ? Author::find((int) $r->input('author')) : null;

        $books = null;
        if ($q !== '' || $cats || $author) {
            $books = Book::query()
                ->select(self::CARD_COLUMNS)
                ->where('is_published', true)
                ->when($q !== '', fn ($qb) => $qb->where(fn ($w) => $w
                    ->where('title', 'like', '%' . $q . '%')
                    ->orWhere('author', 'like', '%' . $q . '%')
                    ->orWhereHas('authors', fn ($a) => $a->where('authors.name', 'like', '%' . $q . '%'))))
                ->when($author, fn ($qb) => $qb->whereHas('authors', fn ($a) => $a->where('authors.id', $author->id)))
                ->when($cats, fn ($qb) => $qb->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $cats)))
                ->with(['categories:id,name', 'authors:id,name'])
                ->latest()
                ->simplePaginate(24)
                ->withQueryString();
        }

        return view('library.search', [
            'q' => $q,
            'cats' => $cats,
            'author' => $author,
            'books' => $books,
            'categories' => $this->categoriesWithCounts(),
        ]);
    }

    public function show(Request $r, Book $book)
    {
        abort_unless($book->is_published || $r->user()->is_admin, 404);
        $book->load(['categories' => fn ($q) => $q->orderBy('categories.name'), 'authors']);
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

    private function categoriesWithCounts()
    {
        $published = fn ($b) => $b->where('is_published', true);

        return Category::whereHas('books', $published)
            ->withCount(['books' => $published])
            ->orderBy('name')
            ->get();
    }
}
