package ca.arcticwolves.gameplan.tv

import android.content.Intent
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.KeyEvent
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import androidx.fragment.app.FragmentActivity
import ca.arcticwolves.gameplan.tv.api.ApiClient

/**
 * D-pad-friendly pairing screen for Android TV.
 *
 * 1. User enters their API key (persisted for future launches).
 * 2. User enters the pair code displayed on the controller device.
 * 3. On success, the pair ID and viewer token are saved and the
 *    [ViewerActivity] is launched.
 */
class PairActivity : FragmentActivity() {

    private lateinit var apiKeyInput: EditText
    private lateinit var pairCodeInput: EditText
    private lateinit var connectButton: Button
    private lateinit var errorText: TextView
    private lateinit var statusText: TextView

    private val handler = Handler(Looper.getMainLooper())

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_pair)

        apiKeyInput = findViewById(R.id.apiKeyInput)
        pairCodeInput = findViewById(R.id.pairCodeInput)
        connectButton = findViewById(R.id.connectButton)
        errorText = findViewById(R.id.errorText)
        statusText = findViewById(R.id.statusText)

        // Pre-fill API key if previously saved
        val prefs = getSharedPreferences(MainActivity.PREFS_NAME, MODE_PRIVATE)
        val savedKey = prefs.getString(MainActivity.KEY_API_KEY, "")
        if (!savedKey.isNullOrBlank()) {
            apiKeyInput.setText(savedKey)
            pairCodeInput.requestFocus()
        }

        connectButton.setOnClickListener { attemptPair() }

        // Allow Enter key on pair code field to submit
        pairCodeInput.setOnKeyListener { _, keyCode, event ->
            if (keyCode == KeyEvent.KEYCODE_ENTER && event.action == KeyEvent.ACTION_UP) {
                attemptPair()
                true
            } else false
        }
    }

    private fun attemptPair() {
        val apiKey = apiKeyInput.text.toString().trim()
        val pairCode = pairCodeInput.text.toString().trim().uppercase()

        // Validate inputs
        if (apiKey.isBlank()) {
            showError("Please enter your API key.")
            apiKeyInput.requestFocus()
            return
        }
        if (pairCode.isBlank() || pairCode.length > 10 || !pairCode.matches(Regex("^[A-Z0-9]+$"))) {
            showError("Invalid pair code. Enter the code from your controller device.")
            pairCodeInput.requestFocus()
            return
        }

        errorText.visibility = View.GONE
        statusText.text = getString(R.string.connecting)
        statusText.visibility = View.VISIBLE
        connectButton.isEnabled = false

        // Save API key for future use
        getSharedPreferences(MainActivity.PREFS_NAME, MODE_PRIVATE)
            .edit()
            .putString(MainActivity.KEY_API_KEY, apiKey)
            .apply()

        // Call the REST API in a background thread
        Thread {
            val result = ApiClient.joinPair(apiKey, pairCode)

            handler.post {
                connectButton.isEnabled = true
                statusText.visibility = View.GONE

                if (result.success) {
                    // Save pair info
                    getSharedPreferences(MainActivity.PREFS_NAME, MODE_PRIVATE)
                        .edit()
                        .putInt(MainActivity.KEY_PAIR_ID, result.pairId)
                        .putString(MainActivity.KEY_VIEWER_TOKEN, result.viewerToken)
                        .apply()

                    // Launch viewer
                    startActivity(Intent(this, ViewerActivity::class.java))
                    finish()
                } else {
                    showError(result.error ?: getString(R.string.pair_failed))
                }
            }
        }.start()
    }

    private fun showError(msg: String) {
        errorText.text = msg
        errorText.visibility = View.VISIBLE
    }
}
