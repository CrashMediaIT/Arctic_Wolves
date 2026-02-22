# Proguard rules for Game Plan TV
-keep class ca.arcticwolves.gameplan.tv.** { *; }
-keepattributes *Annotation*

# OkHttp
-dontwarn okhttp3.**
-dontwarn okio.**
-keep class okhttp3.** { *; }
-keep interface okhttp3.** { *; }

# Keep JSON classes
-keep class org.json.** { *; }
