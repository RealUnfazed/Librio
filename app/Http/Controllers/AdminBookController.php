<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Services\ChunkCipher;
use App\Services\PostfileClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminBookController extends Controller
{
    public function index()
    {
        $books = Book::select(['id', 'title', 'author', 'pages', 'plain_size', 'is_published', 'created_at'])
            ->with(['categories:id,name', 'authors:id,name'])->latest()->paginate(30);

        return view('admin.books.index', compact('books'));
    }

    public function create()
    {
        return view('admin.books.form', [
            'book' => new Book(['is_published' => true]),
            'allCategories' => Category::orderBy('name')->get(),
            'selected' => [],
            'authorsText' => '',
            'authorSuggestions' => Author::orderBy('name')->limit(40)->pluck('name'),
        ]);
    }

    private function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'authors' => 'nullable|string|max:500',
            'categories' => 'nullable|array|max:30',
            'categories.*' => 'integer|exists:categories,id',
            'new_categories' => 'nullable|string|max:400',
            'description' => 'nullable|string|max:5000',
            'pages' => 'nullable|integer|min:1|max:100000',
            'cover_data' => ['nullable', 'string', 'max:400000', 'regex:/^data:image\/(webp|jpeg|png);base64,[A-Za-z0-9+\/=]+$/'],
        ];
    }

    public function store(Request $r, ChunkCipher $cipher, PostfileClient $postfile)
    {
        $data = $r->validate($this->rules() + ['pdf' => 'required|file|max:51200']); // 50 MB = Postfile free-plan limit

        $path = $r->file('pdf')->getRealPath();
        if (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw ValidationException::withMessages(['pdf' => 'That file is not a PDF.']);
        }

        $key = $cipher->newKey();
        $tmp = tempnam(sys_get_temp_dir(), 'lib');

        try {
            $meta = $cipher->encryptFile($path, $tmp, $key);
            $up = $postfile->upload($tmp, Str::uuid() . '.bin');

            try {
                DB::transaction(function () use ($data, $up, $meta, $cipher, $key, $r) {
                        $book = Book::create([
                        'title' => $data['title'],
                            'description' => $data['description'] ?? null,
                        'pages' => $data['pages'] ?? null,
                        'cover_data' => $data['cover_data'] ?? null,
                        'postfile_id' => $up['file_id'],
                        'postfile_url' => $up['url'],
                        'plain_size' => $meta['plain_size'],
                        'enc_size' => $meta['enc_size'],
                        'chunk_size' => $meta['chunk_size'],
                        'nonce_prefix' => $meta['nonce_prefix'],
                        'key_enc' => $cipher->wrap($key),
                        'is_published' => $r->boolean('is_published'),
                    ]);
                    $book->categories()->sync(Category::resolve($data['categories'] ?? [], $data['new_categories'] ?? null));
                    $book->setAuthors($data['authors'] ?? null);
                });
            } catch (\Throwable $e) {
                try { $postfile->delete($up['file_id']); } catch (\Throwable) {}
                throw $e;
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['pdf' => $e->getMessage()]);
        } finally {
            @unlink($tmp);
        }

        return redirect()->route('admin.books.index')->with('status', 'Book added.');
    }

    public function edit(Book $book)
    {
        return view('admin.books.form', [
            'book' => $book,
            'allCategories' => Category::orderBy('name')->get(),
            'selected' => $book->categories()->pluck('categories.id')->all(),
            'authorsText' => implode(', ', $book->load('authors')->authorNames()),
            'authorSuggestions' => Author::orderBy('name')->limit(40)->pluck('name'),
        ]);
    }

    public function update(Request $r, Book $book)
    {
        $data = $r->validate($this->rules());
        $book->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'pages' => $data['pages'] ?? $book->pages,
            'is_published' => $r->boolean('is_published'),
        ]);
        if (! empty($data['cover_data'])) {
            $book->cover_data = $data['cover_data'];
        }
        $book->save();
        $book->categories()->sync(Category::resolve($data['categories'] ?? [], $data['new_categories'] ?? null));
        $book->setAuthors($data['authors'] ?? null);

        return redirect()->route('admin.books.index')->with('status', 'Book updated.');
    }

    public function destroy(Book $book, PostfileClient $postfile)
    {
        try {
            $postfile->delete($book->postfile_id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['delete' => $e->getMessage() . ' The book was NOT removed.']);
        }
        $book->delete();

        return redirect()->route('admin.books.index')->with('status', 'Book deleted.');
    }
}
