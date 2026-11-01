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
| `Cannot use the database` | `DB_URL` wrong or still the PASTE text. Copy the full string from Neon/Supabase. |
| `tables do not exist` | `LIBRARY_AUTO_SETUP=true` missing. Add it, redeploy, open the site. |
| `LIBRARY_MASTER_KEY ...` | Copy it exactly from `.env.vercel`. **Save that value somewhere safe: if you lose it every book is unreadable.** |
| Book won't open / stuck on "Downloading" | Health -> Run upload test (CORS). |
| Uploading a big PDF fails | Serverless functions limit upload size. Run the project on your PC with the same `DB_URL`, `LIBRARY_MASTER_KEY`, `POSTFILE_API_KEY` and add books there (README). |

After your admin works you may delete `LIBRARY_ADMIN_PASSWORD` from Vercel and redeploy.
