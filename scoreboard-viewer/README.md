# Scoreboard Viewer – Android Application

Native Android application for the Arctic Wolves Scoreboard module viewer. Connects to the scoreboard server and displays only the scoreboard — no operator controls.

## Overview

The Scoreboard Viewer app is a **display-only client** that loads the scoreboard web page in a full-screen WebView and injects CSS to hide all operator controls (top bar, control panels, modals), leaving only the professional arena scoreboard visible.

### Flow

1. Launch the app on your Android device
2. Enter the scoreboard server URL (e.g. `https://scoreboard.arcticwolves.ca`)
3. Log in with your staff PIN or credentials (via the built-in scoreboard login)
4. The scoreboard displays in full-screen immersive mode

## Architecture

```
┌──────────────────┐         ┌──────────────────┐
│  Scoreboard      │         │  Android App     │
│  Server          │ ◀────── │  (WebView)       │
│  scoreboard.php  │         │                  │
└──────────────────┘         └──────────────────┘
                                    │
                                    ▼
                              ┌──────────────┐
                              │   WebView    │
                              │ + CSS inject │
                              │ (board only) │
                              └──────────────┘
```

- **Setup screen** – Enter scoreboard server URL (persisted for future launches)
- **WebView viewer** – Loads `scoreboard.php` and injects CSS to hide controls
- **Full-screen mode** – Immersive display with screen-on lock for arena use
- **Authentication** – Uses the built-in scoreboard PIN / email login within the WebView

## What is Hidden

The injected CSS hides all operator-only elements:

| Hidden Element         | CSS Selector              |
|------------------------|---------------------------|
| Top navigation bar     | `.sb-topbar`              |
| Operator controls grid | `.sb-controls-grid`       |
| Control panels         | `.sb-ctrl-panel`          |
| New game modal         | `#sb-new-game-modal`      |
| Goal assignment modal  | `.sb-goal-assign-modal`   |
| Music library modal    | `.sb-music-library-modal` |

## What is Displayed

Only the professional arena scoreboard board (`.sb-board`) is visible:

- Team names and logos
- Score (large LED-style digits)
- Game clock (center display)
- Period indicator
- Penalty timer boxes (2 per team with player # and countdown)
- Power Play / Short Handed indicators
- Shots on Goal (SOG) per team
- Timeout indicators
- Strength display (5v5, 5v4, etc.)
- Game status (WARMUP, IN PROGRESS, INTERMISSION)
- Goal light flash animation

## Project Structure

```
scoreboard-viewer/
├── app/
│   ├── build.gradle                    # App-level Gradle config
│   ├── proguard-rules.pro              # ProGuard rules for release
│   └── src/
│       ├── main/
│       │   ├── AndroidManifest.xml     # App manifest
│       │   ├── java/ca/arcticwolves/scoreboard/viewer/
│       │   │   ├── MainActivity.kt     # Entry point, session check
│       │   │   ├── SetupActivity.kt    # Server URL configuration
│       │   │   └── ScoreboardActivity.kt # WebView viewer + CSS injection
│       │   └── res/
│       │       ├── layout/             # XML layouts
│       │       ├── values/             # Strings, colors, styles
│       │       ├── drawable/           # Backgrounds, icons
│       │       └── xml/                # Network security config
│       ├── debug/res/xml/              # Debug network config
│       └── test/                       # Unit tests
├── build.gradle                        # Root Gradle config
├── settings.gradle                     # Project settings
├── gradle.properties                   # Gradle properties
├── build-apk.sh                        # Command-line build script
└── gradle/wrapper/                     # Gradle wrapper config
```

## Requirements

- Android Studio Hedgehog (2023.1) or later
- Android SDK 34 (API level 34)
- Minimum SDK: 21 (Android 5.0 Lollipop)
- Kotlin 1.9+

## Building

### With Android Studio

1. Open the `scoreboard-viewer/` directory in Android Studio
2. Sync Gradle files
3. Select an emulator or connected device
4. Run the app

### Debug Build (Gradle)

Uses `http://10.0.2.2/Arctic_Wolves` as the default server URL (Android emulator loopback to host).

```bash
cd scoreboard-viewer
./gradlew assembleDebug
```

### Release Build (Gradle)

Uses `https://scoreboard.arcticwolves.ca` as the default server URL.

```bash
cd scoreboard-viewer
./gradlew assembleRelease
```

### Without Gradle (command-line tools)

Requires `ANDROID_HOME`, `kotlinc`, and Java 17+:

```bash
cd scoreboard-viewer
./build-apk.sh
```

## Testing

Unit tests are in `app/src/test/`. The companion Playwright test for the app file structure is in the root `tests/scoreboard-viewer.spec.js`.

```bash
# Unit tests (with Gradle)
cd scoreboard-viewer
./gradlew test

# File structure tests (from project root)
npx playwright test tests/scoreboard-viewer.spec.js
```

## Dependencies

| Library              | Version | Purpose                           |
|----------------------|---------|-----------------------------------|
| AndroidX (built-in)  | —       | Core Android framework            |
| WebView (built-in)   | —       | Renders scoreboard web content    |
| JUnit                | 4.13.2  | Unit testing                      |

No external HTTP libraries are required since all communication happens through the WebView.
