<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('book_category', function (Blueprint $t) {
            $t->foreignId('book_id')->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->primary(['book_id', 'category_id']);
        });

        // Move the old single "category" text of every book into the new tables
        // (the old column stays in the database, it is simply not used any more).
        foreach (DB::table('books')->whereNotNull('category')->where('category', '<>', '')->get(['id', 'category']) as $book) {
            foreach (preg_split('/[,،;\n]+/u', (string) $book->category) as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                $existing = DB::table('categories')->where('name', $name)->first();
                $id = $existing
                    ? $existing->id
                    : DB::table('categories')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('book_category')->insertOrIgnore(['book_id' => $book->id, 'category_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('book_category');
        Schema::dropIfExists('categories');
    }
};
