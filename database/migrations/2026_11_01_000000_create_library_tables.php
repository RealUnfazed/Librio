<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false);
        });

        Schema::create('books', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('author')->nullable();
            $t->string('category')->nullable()->index();
            $t->text('description')->nullable();
            $t->unsignedInteger('pages')->nullable();
            $t->mediumText('cover_data')->nullable();
            $t->string('postfile_id');
            $t->string('postfile_url', 500);
            $t->unsignedBigInteger('plain_size');
            $t->unsignedBigInteger('enc_size');
            $t->unsignedInteger('chunk_size');
            $t->string('nonce_prefix', 32);
            $t->text('key_enc');
            $t->boolean('is_published')->default(true);
            $t->timestamps();
        });

        Schema::create('invites', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('label')->nullable();
            $t->unsignedInteger('max_uses')->default(1);
            $t->unsignedInteger('uses')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('bookmarks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('book_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('page');
            $t->timestamps();
            $t->unique(['user_id', 'book_id', 'page']);
        });

        Schema::create('reading_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('book_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('page')->default(1);
            $t->timestamps();
            $t->unique(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_progress');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('invites');
        Schema::dropIfExists('books');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('is_admin');
        });
    }
};
