# Librio on Android

Two ways. You can use both.

## A) Install straight from Chrome (30 seconds, no build)
Open your Librio address in Chrome on the phone -> menu (three dots) -> **Install app** (or "Add to Home screen").
You get an icon and a full-screen app. Screenshots are still possible here (a website cannot block them).

## B) Real Android app (APK) with screenshot protection
Inside this app, **screenshots, screen recording and the recent-apps preview are blocked** (Android's FLAG_SECURE).
It is a full-screen window for your site: same login, same reader, same admin. PDF upload works too.

GitHub builds it for you, no Android Studio needed:
1. Push this project to GitHub (the same repo you use for Vercel).
2. GitHub -> **Actions** tab -> **Build Android app** -> **Run workflow** (it also runs by itself when `android/` changes).
3. When it turns green, open the run -> **Artifacts** -> download **librio-apk** (a zip, the file inside is `app-release.apk`).
4. Send the APK to your phone, tap it, allow "install unknown apps" when Android asks.
5. New version later: build again and install over the old one (same signing key, nothing is lost).

Settings (`android/gradle.properties`):
* `librioUrl=https://librio.unfazed.ir`  your address
* `librioSecure=true`  set `false` if you want screenshots allowed

Notes
* The app needs internet (it shows a "Try again" page when offline).
* Links to other websites open in the phone's browser.
* The signing key is committed on purpose so updates install over each other. It is meant for your own phone/people you trust.
  For the Play Store you would need your own private key, a developer account and a newer target SDK; ask me if you want that.
* If the GitHub build fails: open the failed run and send me the first red error line.
