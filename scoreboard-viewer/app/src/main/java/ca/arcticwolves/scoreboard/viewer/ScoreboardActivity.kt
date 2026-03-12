package ca.arcticwolves.scoreboard.viewer

import android.annotation.SuppressLint
import android.app.Activity
import android.content.Intent
import android.os.Build
import android.os.Bundle
import android.view.KeyEvent
import android.view.View
import android.view.WindowInsets
import android.view.WindowInsetsController
import android.view.WindowManager
import android.webkit.WebChromeClient
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.FrameLayout
import android.widget.TextView

/**
 * Full-screen scoreboard viewer that loads the scoreboard web page inside
 * a WebView and injects CSS to hide all operator controls, displaying
 * only the scoreboard board itself.
 *
 * The user authenticates via the built-in scoreboard login (PIN or email)
 * rendered within the WebView. Once logged in, the injected CSS hides
 * the top bar, control panels, modals, and any other non-display elements,
 * leaving only the professional arena scoreboard visible.
 */
class ScoreboardActivity : Activity() {

    private lateinit var webView: WebView
    private lateinit var statusOverlay: FrameLayout
    private lateinit var statusText: TextView

    private var serverUrl = ""

    /**
     * CSS injected after each page load to hide operator controls and
     * show only the scoreboard display board.
     */
    private val viewerCss = """
        /* Hide operator-only elements */
        .sb-topbar { display: none !important; }
        .sb-controls-grid { display: none !important; }
        .sb-ctrl-panel { display: none !important; }
        #sb-new-game-modal { display: none !important; }
        .sb-goal-assign-modal { display: none !important; }
        .sb-music-library-modal { display: none !important; }
        .sb-no-game .sb-btn { display: none !important; }

        /* Let the scoreboard board fill the viewport */
        .sb-main {
            min-height: 100vh !important;
            min-height: 100dvh !important;
            display: flex !important;
            flex-direction: column !important;
        }
        .sb-board {
            flex: 1 !important;
            align-content: center !important;
        }
    """.trimIndent()

    /**
     * JavaScript that injects the viewer CSS into the page via a <style> tag.
     */
    private val injectScript: String
        get() = """
            (function() {
                var styleId = 'sb-viewer-inject';
                if (document.getElementById(styleId)) return;
                var style = document.createElement('style');
                style.id = styleId;
                style.textContent = ${viewerCss.toJsStringLiteral()};
                document.head.appendChild(style);
            })();
        """.trimIndent()

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Keep the screen on for arena display use
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        setContentView(R.layout.activity_scoreboard)

        webView = findViewById(R.id.webView)
        statusOverlay = findViewById(R.id.statusOverlay)
        statusText = findViewById(R.id.statusText)

        // Read persisted server URL
        val prefs = getSharedPreferences(MainActivity.PREFS_NAME, MODE_PRIVATE)
        serverUrl = prefs.getString(MainActivity.KEY_SERVER_URL, "") ?: ""

        if (serverUrl.isBlank()) {
            navigateToSetup()
            return
        }

        // Configure WebView
        webView.apply {
            settings.javaScriptEnabled = true
            settings.domStorageEnabled = true
            settings.mediaPlaybackRequiresUserGesture = false
            settings.mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
            settings.cacheMode = WebSettings.LOAD_DEFAULT
            settings.userAgentString = settings.userAgentString + " ScoreboardViewer-Android/1.0"

            webViewClient = object : WebViewClient() {
                override fun onPageFinished(view: WebView?, url: String?) {
                    super.onPageFinished(view, url)
                    // Inject CSS to hide controls after every page load
                    view?.evaluateJavascript(injectScript, null)
                }
            }
            webChromeClient = WebChromeClient()
        }

        // Load the scoreboard page
        webView.loadUrl("$serverUrl/scoreboard.php")
    }

    override fun onResume() {
        super.onResume()
        enterImmersiveMode()
    }

    override fun onDestroy() {
        super.onDestroy()
        webView.destroy()
    }

    /**
     * Handle back button:
     * - If WebView can go back (e.g. login → scoreboard), go back in WebView.
     * - Otherwise return to setup screen.
     */
    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        if (keyCode == KeyEvent.KEYCODE_BACK) {
            if (webView.canGoBack()) {
                webView.goBack()
                return true
            }
            navigateToSetup()
            return true
        }
        return super.onKeyDown(keyCode, event)
    }

    // ── Helpers ────────────────────────────────────────────

    private fun enterImmersiveMode() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            window.insetsController?.let { controller ->
                controller.hide(WindowInsets.Type.statusBars() or WindowInsets.Type.navigationBars())
                controller.systemBarsBehavior =
                    WindowInsetsController.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
            }
        } else {
            @Suppress("DEPRECATION")
            window.decorView.systemUiVisibility = (
                View.SYSTEM_UI_FLAG_FULLSCREEN
                    or View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
                    or View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                )
        }
    }

    private fun navigateToSetup() {
        startActivity(Intent(this, SetupActivity::class.java))
        finish()
    }
}

/**
 * Converts a multi-line string into a JavaScript string literal
 * (single-quoted, with newlines and quotes escaped).
 */
private fun String.toJsStringLiteral(): String {
    val escaped = this
        .replace("\\", "\\\\")
        .replace("'", "\\'")
        .replace("\n", "\\n")
        .replace("\r", "")
    return "'$escaped'"
}
