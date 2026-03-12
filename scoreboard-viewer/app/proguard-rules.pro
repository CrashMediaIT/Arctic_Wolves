# Scoreboard Viewer – ProGuard rules
# Keep WebView JavaScript interface if added in future
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}
