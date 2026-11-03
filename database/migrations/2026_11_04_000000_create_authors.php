<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('author_book', function (Blueprint $t) {
            $t->foreignId('book_id')->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position')->default(0); // keeps the order: first author first
            $t->primary(['book_id', 'author_id']);
        });

        // Move the old single "author" text of every book into the new tables.
        // "A, B", "A & B", "A and B" become two authors. The old column is cleared afterwards.
        foreach (DB::table('books')->whereNotNull('author')->where('author', '<>', '')->get(['id', 'author']) as $book) {
            $position = 0;
            foreach (preg_split('/\s*(?:[,،;&]|\s+and\s+|\s+و\s+)\s*/iu', (string) $book->author) as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                $existing = DB::table('authors')->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();
                $id = $existing
                    ? $existing->id
                    : DB::table('authors')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('author_book')->insertOrIgnore(['book_id' => $book->id, 'author_id' => $id, 'position' => $position++]);
            }
            DB::table('books')->where('id', $book->id)->update(['author' => null]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('author_book');
        Schema::dropIfExists('authors');
    }
};
