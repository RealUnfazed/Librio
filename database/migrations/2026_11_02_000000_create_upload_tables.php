<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Temporary staging for chunked uploads (Vercel instances share no disk, the database is the shared place).
        Schema::create('upload_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('file_name');
            $t->unsignedBigInteger('plain_size');
            $t->unsignedInteger('piece_size');
            $t->unsignedInteger('pieces');
            $t->string('nonce_prefix', 32);
            $t->text('key_enc');
            $t->timestamps();
        });

        Schema::create('upload_pieces', function (Blueprint $t) {
            $t->uuid('upload_id');
            $t->unsignedInteger('idx');
            $t->longText('data'); // base64 of the already-encrypted piece
            $t->primary(['upload_id', 'idx']);
            $t->foreign('upload_id')->references('id')->on('upload_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_pieces');
        Schema::dropIfExists('upload_sessions');
    }
};
