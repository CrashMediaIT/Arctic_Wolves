package ca.arcticwolves.scoreboard.viewer

import android.app.Activity
import android.content.Intent
import android.os.Bundle
import android.view.KeyEvent
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.TextView

/**
 * Setup screen where the user enters their scoreboard server URL.
 *
 * The URL is persisted in SharedPreferences so it only needs to be
 * entered once. On successful save, the [ScoreboardActivity] is launched.
 */
class SetupActivity : Activity() {

    companion object {
        /** Matches http:// or https:// URLs. */
        private val URL_PATTERN = Regex("^https?://.+")
    }

    private lateinit var serverUrlInput: EditText
    private lateinit var connectButton: Button
    private lateinit var errorText: TextView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_setup)

        serverUrlInput = findViewById(R.id.serverUrlInput)
        connectButton = findViewById(R.id.connectButton)
        errorText = findViewById(R.id.errorText)

        // Pre-fill URL if previously saved
        val prefs = getSharedPreferences(MainActivity.PREFS_NAME, MODE_PRIVATE)
        val savedUrl = prefs.getString(MainActivity.KEY_SERVER_URL, "")
        if (!savedUrl.isNullOrBlank()) {
            serverUrlInput.setText(savedUrl)
        }

        connectButton.setOnClickListener { attemptConnect() }

        // Allow Enter key on URL field to submit
        serverUrlInput.setOnKeyListener { _, keyCode, event ->
            if (keyCode == KeyEvent.KEYCODE_ENTER && event.action == KeyEvent.ACTION_UP) {
                attemptConnect()
                true
            } else false
        }
    }

    private fun attemptConnect() {
        val rawUrl = serverUrlInput.text.toString().trim()

        // Remove trailing slash for consistency
        val serverUrl = rawUrl.trimEnd('/')

        // Validate URL
        if (serverUrl.isBlank()) {
            showError(getString(R.string.error_empty_url))
            serverUrlInput.requestFocus()
            return
        }
        if (!serverUrl.matches(URL_PATTERN)) {
            showError(getString(R.string.error_invalid_url))
            serverUrlInput.requestFocus()
            return
        }

        errorText.visibility = View.GONE

        // Save server URL
        getSharedPreferences(MainActivity.PREFS_NAME, MODE_PRIVATE)
            .edit()
            .putString(MainActivity.KEY_SERVER_URL, serverUrl)
            .apply()

        // Launch scoreboard viewer
        startActivity(Intent(this, ScoreboardActivity::class.java))
        finish()
    }

    private fun showError(msg: String) {
        errorText.text = msg
        errorText.visibility = View.VISIBLE
    }
}
