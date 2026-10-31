# PDF Library (Laravel 12 + Postfile)

Complete project: login/invites, library with search and categories, mobile + desktop PDF reader, admin panel.
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

1. Create a **Postgres** database (Neon, Supabase, Vercel Postgres...) and copy its connection URL.
2. Push this repo to GitHub, import it in Vercel (it builds the `Dockerfile` through `vercel.json`).
3. Vercel -> Settings -> Environment Variables:

| Name | Value |
|---|---|
| `APP_KEY` | output of `php artisan key:generate --show` |
| `APP_URL` | `https://your-project.vercel.app` |
| `DB_CONNECTION` | `pgsql` |
| `DB_URL` | the connection URL (add `?sslmode=require` if your host needs it) |
| `LIBRARY_MASTER_KEY` | **the same value as in your local `.env`** |
| `POSTFILE_API_KEY` | your Postfile key |
| `LIBRARY_REGISTRATION` | `invite`, `open` or `closed` |

4. Create the tables once, from your computer: `DB_CONNECTION=pgsql DB_URL="..." php artisan migrate --force`
5. Create your admin the same way: `DB_CONNECTION=pgsql DB_URL="..." php artisan library:make-admin you@example.com`
6. **Add books from your computer**, with the same `DB_URL` and `LIBRARY_MASTER_KEY` in your local `.env`.
   The Vercel site is for reading; pushing 50 MB uploads through a serverless function is risky.

Vercel mode (`LIBRARY_DELIVERY=encrypted`, already the Docker default): the browser downloads the encrypted file from the
Postfile CDN and decrypts it in memory with a key it only receives after login.
VPS mode (`LIBRARY_DELIVERY=proxy`): your server decrypts and streams every byte; the CDN link never reaches the browser.

## Security (honest version)
Per-user watermark is drawn into every page, no download/print button, no text layer, rate limits, login required.
A logged-in reader can still screenshot, and in Vercel mode a technical user can read the key in devtools.
This stops link sharing, scraping and casual downloading and makes leaks traceable. It cannot make copying impossible.

## Important
* `LIBRARY_MASTER_KEY` wraps every book key. Lose it = every book unreadable. Back it up.
* Postfile free plan: 50 MB per file, monthly upload quota, 2 GB storage. One upload per book.
* Vercel mode downloads the whole file before reading (progress bar). Page-by-page streaming is VPS mode only.
* Optional: run `composer install` once and commit `composer.lock` for reproducible builds.
* Tests: `node js-tests/chunk-decrypt.test.js`, `node js-tests/range-sim.test.js`.
