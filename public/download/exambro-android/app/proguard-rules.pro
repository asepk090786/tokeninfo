# ProGuard rules for ExambroClient
-keepattributes *Annotation*
-keepattributes SourceFile,LineNumberTable
-keep public class * extends java.lang.Exception

# WebView JavaScript interface
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

# Keep BuildConfig
-keep class com.exambro.client.BuildConfig { *; }
