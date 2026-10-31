<?php

use App\Http\Controllers\AdminBookController;
use App\Http\Controllers\AdminInviteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\ReaderController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/library');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/library', [LibraryController::class, 'index'])->name('library');
    Route::get('/books/{book}', [LibraryController::class, 'show'])->name('books.show');
    Route::get('/books/{book}/cover', [LibraryController::class, 'cover'])->name('books.cover');

    Route::get('/books/{book}/read', [ReaderController::class, 'read'])->name('books.read');
    Route::get('/books/{book}/manifest', [ReaderController::class, 'manifest'])->middleware('throttle:20,1')->name('books.manifest');
    Route::get('/books/{book}/stream', [ReaderController::class, 'stream'])->middleware('throttle:900,1')->name('books.stream');
    Route::post('/books/{book}/progress', [ReaderController::class, 'progress'])->name('books.progress');
    Route::post('/books/{book}/bookmarks', [ReaderController::class, 'bookmark'])->name('books.bookmark');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::resource('books', AdminBookController::class)->except(['show']);
    Route::resource('invites', AdminInviteController::class)->only(['index', 'store', 'destroy']);
});
