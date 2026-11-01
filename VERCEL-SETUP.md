# Librio on Vercel: 6 steps, no terminal

Why you got a **500**: Vercel had no settings for the app (no `APP_KEY`, no database).
`.env.vercel` contains everything; you only edit 5 lines.

## 1. Get a Postgres database (2 min)
Pick ONE:
* **Easiest:** Vercel -> your project -> **Storage** -> **Create Database** -> **Neon (Postgres)** -> connect it to the project.
  Vercel adds the connection variables for you. In `.env.vercel` **delete the `DB_URL` line**.
* **Or** make a free database on neon.tech / supabase.com, copy its **connection string**
  (`postgresql://user:password@host/dbname?sslmode=require`) and paste it after `DB_URL=` in `.env.vercel`.

## 2. Edit `.env.vercel` (5 lines at the top)
`APP_URL`, `DB_URL` (see step 1), `POSTFILE_API_KEY`, `LIBRARY_ADMIN_EMAIL`, `LIBRARY_ADMIN_PASSWORD`.
Leave everything under "READY - DO NOT CHANGE" exactly as it is.

## 3. Put it into Vercel
Vercel -> your project -> **Settings** -> **Environment Variables** -> click the **Key** box -> paste the whole file ->
make sure **Production** is ticked -> **Save**.
(Fallback if paste doesn't split the lines: add each `NAME=value` as its own variable.)

## 4. Redeploy (important!)
Variables only apply to NEW deployments: **Deployments** -> latest one -> **...** -> **Redeploy**.

## 5. Open your site
Go to your Vercel address, log in with `LIBRARY_ADMIN_EMAIL` + `LIBRARY_ADMIN_PASSWORD`.
The first visit creates the tables and your admin automatically (takes a few seconds).

## 6. Check everything
Top menu -> **Health**. Every line should say OK. Then press **Run upload test**:
if it says the CDN sends no CORS header, tell me, the delivery mode must change.

Then **Books -> Add book**. People join through **Invites** (or set `LIBRARY_REGISTRATION=open`).

---
## If it still shows an error
The page now tells you what's wrong in plain words. For technical details: Vercel -> project -> **Logs**.

| It says / log says | Fix |
|---|---|
| `APP_KEY is missing` / `Unsupported cipher` | Variables not saved or not redeployed (step 3-4). Value must be exactly `base64:...` from `.env.vercel`. |
| `Database file at path [/app/database/database.sqlite] does not exist` | The app got NO database settings and fell back to SQLite (impossible on Vercel). Add `DB_CONNECTION` = `pgsql` and `DB_URL` = your Postgres connection string, then redeploy. |
| `Cannot use the database` | `DB_URL` wrong or still the PASTE text. Copy the full string from Neon/Supabase. |
| `tables do not exist` | `LIBRARY_AUTO_SETUP=true` missing. Add it, redeploy, open the site. |
| `LIBRARY_MASTER_KEY ...` | Copy it exactly from `.env.vercel`. **Save that value somewhere safe: if you lose it every book is unreadable.** |
| `blocked by CORS policy ... cdn.postfile.download` | Old setting. Postfile does not allow browser downloads, so Librio streams books through your server instead: in Vercel set `LIBRARY_DELIVERY` = `proxy` (or delete that variable) and redeploy. |
| Book won't open / stuck on "Opening" | Open Health and press Run upload test. Check Vercel > Logs for the failing `/books/.../stream` request. |
| `413 FUNCTION_PAYLOAD_TOO_LARGE` on upload | Fixed: Librio now uploads in 3 MB pieces. If you still see it, you are running an old build, redeploy the latest one. |
| Upload stops at "Sending to Postfile" | The last step sends the whole encrypted file to Postfile from the server and can hit Vercel's time limit. Press the button again (your pieces are kept). If it keeps failing, run Librio on your PC for uploads (same `DB_URL`, `LIBRARY_MASTER_KEY`, `POSTFILE_API_KEY`). |

Also: `APP_DEBUG` must be `false` in Vercel (true shows technical details to everyone) and `APP_URL` must be your real address, e.g. `https://librio.unfazed.ir`.

After your admin works you may delete `LIBRARY_ADMIN_PASSWORD` from Vercel and redeploy.
