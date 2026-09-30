# Daily Targets Android

Lightweight Android wrapper for the ECFHL Daily Targets page.

## App behavior

- Opens https://ecfhl.win/ai-tips
- Keeps ecfhl.win navigation inside the app
- Opens external links such as Fantrax and Daily Faceoff in the device browser
- Pull down to refresh
- Android back button navigates WebView history first
- Uses HTTPS only

## Build locally

Requires JDK 17 and Android SDK 35.

```bash
gradle :app:assembleDebug
```

APK output:

```
app/build/outputs/apk/debug/app-debug.apk
```
