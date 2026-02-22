# Game Plan TV – Android TV Native Application

Native Android TV application for the Arctic Wolves Game Plan module viewer. Designed for Android TV devices, Fire TV, and other set-top boxes running Android.

## Overview

The Game Plan TV app is a **viewer-only display** that pairs with a controller device (phone, tablet, or laptop running the Game Plan web app). The controller navigates; the TV follows.

### Flow

1. Launch the app on your Android TV device
2. Enter your API key (persisted for future launches)
3. Enter the pair code generated from Game Plan on your controller device
4. The TV displays whatever page the controller navigates to
5. The controller can freeze the display to browse privately

## Architecture

```
┌──────────────┐         ┌──────────────────┐         ┌──────────────┐
│  Controller  │         │  Arctic Wolves   │         │  Android TV  │
│  (Phone/PC)  │ ──────▶ │  REST API        │ ◀────── │  Native App  │
│  gameplan.php│         │  /v1/tv/pair     │         │              │
└──────────────┘         └──────────────────┘         └──────────────┘
                                                             │
                                                             ▼
                                                      ┌──────────────┐
                                                      │   WebView    │
                                                      │ gameplan_tv  │
                                                      └──────────────┘
```

- **Native pairing screen** – D-pad/remote friendly with large inputs
- **REST API communication** – Uses `/v1/tv/pair` endpoints with API key auth
- **WebView content** – Renders the server-side Game Plan TV pages
- **Background polling** – Checks pair state every 3 seconds for page changes

## API Endpoints

| Method   | Endpoint            | Description                  |
|----------|---------------------|------------------------------|
| `POST`   | `/v1/tv/pair`       | Join as viewer (pair code)   |
| `GET`    | `/v1/tv/pair/{id}`  | Poll pair state              |
| `DELETE` | `/v1/tv/pair/{id}`  | Disconnect viewer            |

All endpoints require an API key via the `Authorization: Bearer <key>` header.

## Project Structure

```
android-tv/
├── app/
│   ├── build.gradle                    # App-level Gradle config
│   ├── proguard-rules.pro              # ProGuard rules for release
│   └── src/
│       ├── main/
│       │   ├── AndroidManifest.xml     # TV app manifest (Leanback)
│       │   ├── java/ca/arcticwolves/gameplan/tv/
│       │   │   ├── MainActivity.kt     # Entry point, session check
│       │   │   ├── PairActivity.kt     # Pairing screen (D-pad)
│       │   │   ├── ViewerActivity.kt   # WebView viewer + polling
│       │   │   └── api/
│       │   │       └── ApiClient.kt    # REST API client
│       │   └── res/
│       │       ├── layout/             # XML layouts
│       │       ├── values/             # Strings, colors, styles
│       │       └── drawable/           # Backgrounds, icons
│       └── test/                       # Unit tests
├── build.gradle                        # Root Gradle config
├── settings.gradle                     # Project settings
├── gradle.properties                   # Gradle properties
└── gradle/wrapper/                     # Gradle wrapper config
```

## Requirements

- Android Studio Hedgehog (2023.1) or later
- Android SDK 34 (API level 34)
- Minimum SDK: 21 (Android 5.0 Lollipop)
- Kotlin 1.9+

## Building

1. Open the `android-tv/` directory in Android Studio
2. Sync Gradle files
3. Select an Android TV emulator or connected device
4. Run the app

### Debug Build

Uses `http://10.0.2.2/Arctic_Wolves` as the base URL (Android emulator loopback to host).

### Release Build

Uses `https://gameplan.arcticwolves.ca` and `https://api.arcticwolves.ca/v1`.

```bash
cd android-tv
./gradlew assembleRelease
```

## Dependencies

| Library              | Version | Purpose                           |
|----------------------|---------|-----------------------------------|
| AndroidX Core KTX    | 1.12.0  | Kotlin extensions for Android     |
| AndroidX AppCompat   | 1.6.1   | Backward-compatible UI            |
| AndroidX Leanback    | 1.0.0   | Android TV UI components          |
| OkHttp               | 4.12.0  | HTTP client for REST API          |
| JSON                 | 20231013| JSON parsing                      |

## Testing

Unit tests are in `app/src/test/`. The companion Playwright test for the API endpoint is in the root `tests/android-tv-api.spec.js`.

```bash
# Unit tests
cd android-tv
./gradlew test

# API endpoint tests (from project root)
npx playwright test tests/android-tv-api.spec.js
```
