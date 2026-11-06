# Librio

Librio is an online PDF library for phone and desktop (Laravel 12 + Postfile). It has login/invites, library with search and categories, mobile + desktop PDF reader, admin panel.
Books are stored on Postfile **encrypted**, so their public CDN links are useless on their own.

## Run locally (2 minutes)

Needs PHP 8.2+ and Composer.

```bash
composer setup                                  # installs, creates .env + SQLite DB + master key, migrates
# put your Postfile key in .env  ->  POSTFILE_API_KEY=...
php artisan library:check --upload              # tests Postfile (CORS / Range / uploads) - run this once
php artisan library:make-admin you@example.com  # asks for a password
php artisan serve                               # http://localhost:8000  (local mode = server proxy)
```
Add books at **Books -> Add book** (PDF up to 50 MB; cover + page count are generated in your browser).
Local PHP needs `upload_max_filesize=50M` and `post_max_size=55M`.

## Deploy on Vercel

**Read `VERCEL-SETUP.md`** (6 steps, no terminal, ready-made `.env.vercel`).

How books reach the reader (`LIBRARY_DELIVERY`, default `proxy`, also on Vercel): your server fetches the encrypted file from Postfile,
decrypts it and streams plain PDF bytes to the browser in small pieces (max 1 MiB per response, so Vercel's response limit never applies).
The Postfile link never reaches the browser. `encrypted` (browser downloads ciphertext directly) only works with file hosts that send CORS headers; Postfile does not.

## Categories

A book can have several categories (tick existing ones or type new ones, comma separated, in Add/Edit book). The home page shows one shelf per category; Search can filter by several at once. Books you already had keep their old category automatically on the next deploy.

## Writers

A book can have several writers: type them comma separated in Add/Edit book (click a known name to add it). Order is kept, each name on the book page links to all books by that writer, and search also matches writers. Old single-author text is converted automatically on the next deploy.

## Bookshelf view

The Library and Search pages show books standing on wooden shelves: **Spines** (thickness follows the page count), **Covers**, or plain **Cards**. The choice is remembered per device.

## Reader: book view

The reader opens like a real book with a **page curl** (like FlipHTML5): a two-page spread with a front cover on wide screens, one page at a time on phones.
Drag any page corner and the paper folds under your finger or mouse (let go past the middle, or flick, to finish the turn; otherwise it springs back).
Hover a corner and it peeks. Click or tap a side, swipe, use the arrow keys, PageUp/PageDown, Home/End, or type a page number.
The book button switches to continuous scrolling, where you can **pinch to zoom freely** (the page follows your fingers, the sharp re-draw happens once you let go), **double-tap** to zoom in and back to fit, or use ctrl+wheel and the +/- buttons on a computer, and the arrows button marks a book as **right-to-left** (Persian, Arabic, Hebrew). Both choices are remembered.

## Android

See `ANDROID.md`: install from Chrome in 30 seconds, or build a real APK (screenshots blocked) with GitHub Actions.

## Security (honest version)
A watermark (your site's domain by default, `LIBRARY_WATERMARK=domain-id` adds the reader's number for tracing leaks) is drawn into every page, no download/print button, no text layer, rate limits, login required.
A logged-in reader can still screenshot what they see.
This stops link sharing, scraping and casual downloading and makes leaks traceable. It cannot make copying impossible.

## Important
* `LIBRARY_MASTER_KEY` wraps every book key. Lose it = every book unreadable. Back it up.
* Postfile free plan: 50 MB per file, monthly upload quota, 2 GB storage. One upload per book.
* Big uploads go in 3 MB pieces (works around Vercel's 4.5 MB request limit); pieces are staged in the database, then sent to Postfile in one request.
* Every page view passes through your server, so on Vercel it counts toward Vercel's bandwidth and function-time limits.
* Optional: run `composer install` once and commit `composer.lock` for reproducible builds.
* Tests: `node js-tests/chunk-decrypt.test.js`, `node js-tests/range-sim.test.js`.

## License

[MIT](LICENSE) © 2026 Alireza Asakareh (RealUnfazed)
