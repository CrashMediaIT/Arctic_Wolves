#!/usr/bin/env bash
# ──────────────────────────────────────────────────────────────
# build-apk.sh — Build the Game Plan TV APK without Gradle
#
# Uses the Android SDK command-line tools directly:
#   aapt2, kotlinc, d8, zipalign, apksigner
#
# Requirements:
#   - ANDROID_HOME set to the Android SDK path
#   - kotlinc on PATH
#   - Java 17+
#
# Usage:
#   cd android-tv && ./build-apk.sh
# ──────────────────────────────────────────────────────────────
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BUILD_DIR="$SCRIPT_DIR/build-cmdline"
SDK="${ANDROID_HOME:?ANDROID_HOME is not set}"
BT="$SDK/build-tools/34.0.0"
PLATFORM="$SDK/platforms/android-34/android.jar"
SRC="$SCRIPT_DIR/app/src/main/java"
RES="$SCRIPT_DIR/app/src/main/res"
MANIFEST="$SCRIPT_DIR/app/src/main/AndroidManifest.xml"
OUTPUT="$SCRIPT_DIR/gameplan-tv-debug.apk"

echo "╔══════════════════════════════════════════╗"
echo "║  Game Plan TV — APK Build (no Gradle)    ║"
echo "╚══════════════════════════════════════════╝"

# Clean previous build
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"/{deps,classes,dex,res-compiled,apk,gen}

# ── Download dependencies from Maven Central ────────────────
echo "» Downloading dependencies…"
DEPS="$BUILD_DIR/deps"
curl -sL -o "$DEPS/okhttp-4.12.0.jar" \
  "https://repo.maven.apache.org/maven2/com/squareup/okhttp3/okhttp/4.12.0/okhttp-4.12.0.jar"
curl -sL -o "$DEPS/okio-jvm-3.6.0.jar" \
  "https://repo.maven.apache.org/maven2/com/squareup/okio/okio-jvm/3.6.0/okio-jvm-3.6.0.jar"
curl -sL -o "$DEPS/kotlin-stdlib-1.9.22.jar" \
  "https://repo.maven.apache.org/maven2/org/jetbrains/kotlin/kotlin-stdlib/1.9.22/kotlin-stdlib-1.9.22.jar"

# ── Compile resources ────────────────────────────────────────
echo "» Compiling resources…"
"$BT/aapt2" compile --dir "$RES" -o "$BUILD_DIR/res-compiled/resources.zip"

# ── Link resources & generate R.java ─────────────────────────
echo "» Linking resources…"
"$BT/aapt2" link \
  -o "$BUILD_DIR/apk/base.apk" \
  --manifest "$MANIFEST" \
  -I "$PLATFORM" \
  --java "$BUILD_DIR/gen" \
  --auto-add-overlay \
  --min-sdk-version 21 \
  --target-sdk-version 34 \
  --version-code 1 \
  --version-name "1.0.0" \
  "$BUILD_DIR/res-compiled/resources.zip"

# ── Compile Kotlin + R.java ──────────────────────────────────
echo "» Compiling Kotlin sources…"
CLASSPATH="$PLATFORM:$DEPS/okhttp-4.12.0.jar:$DEPS/okio-jvm-3.6.0.jar:$DEPS/kotlin-stdlib-1.9.22.jar"
# shellcheck disable=SC2046
kotlinc \
  $(find "$SRC" -name "*.kt" -type f) \
  "$BUILD_DIR/gen/ca/arcticwolves/gameplan/tv/R.java" \
  -classpath "$CLASSPATH" \
  -d "$BUILD_DIR/classes" \
  -jvm-target 17 \
  -no-stdlib \
  -no-reflect

# ── DEX ──────────────────────────────────────────────────────
echo "» Converting to DEX…"
# shellcheck disable=SC2046
"$BT/d8" \
  --release \
  --min-api 21 \
  --output "$BUILD_DIR/dex" \
  --lib "$PLATFORM" \
  $(find "$BUILD_DIR/classes" -name "*.class") \
  "$DEPS/okhttp-4.12.0.jar" \
  "$DEPS/okio-jvm-3.6.0.jar" \
  "$DEPS/kotlin-stdlib-1.9.22.jar"

# ── Package APK ──────────────────────────────────────────────
echo "» Packaging APK…"
cp "$BUILD_DIR/apk/base.apk" "$BUILD_DIR/apk/unsigned.apk"
( cd "$BUILD_DIR" && zip -j "$BUILD_DIR/apk/unsigned.apk" "$BUILD_DIR/dex/classes.dex" )

# ── Zipalign ─────────────────────────────────────────────────
echo "» Aligning…"
"$BT/zipalign" -p 4 "$BUILD_DIR/apk/unsigned.apk" "$BUILD_DIR/apk/aligned.apk"

# ── Sign ─────────────────────────────────────────────────────
echo "» Signing (debug key)…"
KEYSTORE="$BUILD_DIR/debug.keystore"
if [ ! -f "$KEYSTORE" ]; then
  keytool -genkeypair \
    -keystore "$KEYSTORE" \
    -storepass android \
    -alias androiddebugkey \
    -keypass android \
    -keyalg RSA -keysize 2048 -validity 10000 \
    -dname "CN=Android Debug,O=Android,C=US" 2>/dev/null
fi

"$BT/apksigner" sign \
  --ks "$KEYSTORE" \
  --ks-pass pass:android \
  --ks-key-alias androiddebugkey \
  --key-pass pass:android \
  --out "$OUTPUT" \
  "$BUILD_DIR/apk/aligned.apk"

# ── Verify ───────────────────────────────────────────────────
echo "» Verifying…"
"$BT/apksigner" verify "$OUTPUT"

SIZE=$(du -h "$OUTPUT" | cut -f1)
echo ""
echo "✅  APK built successfully: $OUTPUT ($SIZE)"
echo "    Package:  ca.arcticwolves.gameplan.tv"
echo "    Version:  1.0.0 (1)"
echo "    Min SDK:  21"
