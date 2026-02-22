package ca.arcticwolves.gameplan.tv

import android.annotation.SuppressLint
import android.app.Activity
import android.content.Intent
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
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
import ca.arcticwolves.gameplan.tv.api.ApiClient

/**
 * Viewer activity that displays the Game Plan TV web content inside a
 * full-screen WebView. Polls the REST API every [POLL_INTERVAL_MS] to
 * detect controller page changes and freeze/unfreeze state.
 *
 * The WebView loads the server-rendered pages from gameplan_tv.php. All
 * navigation is driven by the controller device — the TV simply follows.
 */
class ViewerActivity : Activity() {

    companion object {
        private const val POLL_INTERVAL_MS = 3000L
    }

    private lateinit var webView: WebView
    private lateinit var frozenBadge: TextView
    private lateinit var connectionStatus: FrameLayout

    private val handler = Handler(Looper.getMainLooper())
    private var pairId = 0
    private var apiKey = ""
    private var currentPage = "home"
    private var isFrozen = false
    private var isPolling = false

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Immersive full-screen
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        setContentView(R.layout.activity_viewer)

        webView = findViewById(R.id.webView)
        frozenBadge = findViewById(R.id.frozenBadge)
        connectionStatus = findViewById(R.id.connectionStatus)

        // Read persisted pair session
        val prefs = getSharedPreferences(MainActivity.PREFS_NAME, MODE_PRIVATE)
        pairId = prefs.getInt(MainActivity.KEY_PAIR_ID, 0)
        apiKey = prefs.getString(MainActivity.KEY_API_KEY, "") ?: ""

        if (pairId == 0 || apiKey.isBlank()) {
            navigateToPairing()
            return
        }

        // Configure WebView for TV
        webView.apply {
            settings.javaScriptEnabled = true
            settings.domStorageEnabled = true
            settings.mediaPlaybackRequiresUserGesture = false
            settings.mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
            settings.cacheMode = WebSettings.LOAD_DEFAULT
            settings.userAgentString = settings.userAgentString + " GamePlanTV-Android/1.0"
            webViewClient = WebViewClient()
            webChromeClient = WebChromeClient()
        }

        // Load the initial page
        loadPage(currentPage)

        // Start polling for controller state changes
        startPolling()
    }

    override fun onResume() {
        super.onResume()
        // Enter immersive full-screen mode
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
        if (!isPolling) startPolling()
    }

    override fun onPause() {
        super.onPause()
        stopPolling()
    }

    override fun onDestroy() {
        super.onDestroy()
        stopPolling()
        webView.destroy()
    }

    /**
     * Handle D-pad back button to disconnect and return to pairing.
     */
    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        if (keyCode == KeyEvent.KEYCODE_BACK) {
            disconnectAndReturn()
            return true
        }
        return super.onKeyDown(keyCode, event)
    }

    // ── Page loading ───────────────────────────────────────

    private fun loadPage(page: String) {
        val baseUrl = AppConfig.BASE_URL
        val url = "$baseUrl/gameplan_tv.php"
        webView.loadUrl(url)
        currentPage = page
    }

    // ── Polling ────────────────────────────────────────────

    private val pollRunnable = object : Runnable {
        override fun run() {
            pollPairState()
            handler.postDelayed(this, POLL_INTERVAL_MS)
        }
    }

    private fun startPolling() {
        isPolling = true
        handler.postDelayed(pollRunnable, POLL_INTERVAL_MS)
    }

    private fun stopPolling() {
        isPolling = false
        handler.removeCallbacks(pollRunnable)
    }

    private fun pollPairState() {
        Thread {
            val state = ApiClient.getPairState(apiKey, pairId)

            handler.post {
                if (!state.active) {
                    // Pair ended by controller
                    clearPairData()
                    navigateToPairing()
                    return@post
                }

                // Update frozen badge
                if (state.isFrozen != isFrozen) {
                    isFrozen = state.isFrozen
                    frozenBadge.visibility = if (isFrozen) View.VISIBLE else View.GONE
                }

                // Navigate to new page if controller changed it and not frozen
                if (!isFrozen && state.controllerPage != currentPage) {
                    loadPage(state.controllerPage)
                }
            }
        }.start()
    }

    // ── Disconnect ─────────────────────────────────────────

    private fun disconnectAndReturn() {
        Thread {
            ApiClient.unpair(apiKey, pairId)
            handler.post {
                clearPairData()
                navigateToPairing()
            }
        }.start()
    }

    private fun clearPairData() {
        getSharedPreferences(MainActivity.PREFS_NAME, MODE_PRIVATE)
            .edit()
            .remove(MainActivity.KEY_PAIR_ID)
            .remove(MainActivity.KEY_VIEWER_TOKEN)
            .apply()
    }

    private fun navigateToPairing() {
        startActivity(Intent(this, PairActivity::class.java))
        finish()
    }
}
