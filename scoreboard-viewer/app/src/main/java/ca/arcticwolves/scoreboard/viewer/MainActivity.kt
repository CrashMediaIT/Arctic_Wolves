package ca.arcticwolves.scoreboard.viewer

import android.app.Activity
import android.content.Intent
import android.content.SharedPreferences
import android.os.Bundle

/**
 * Main entry-point activity for the Scoreboard Viewer app.
 *
 * Checks for a persisted server URL and either launches the
 * [ScoreboardActivity] (URL configured) or [SetupActivity] (needs setup).
 */
class MainActivity : Activity() {

    companion object {
        const val PREFS_NAME = "scoreboard_viewer_prefs"
        const val KEY_SERVER_URL = "server_url"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val prefs: SharedPreferences = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
        val serverUrl = prefs.getString(KEY_SERVER_URL, null)

        if (!serverUrl.isNullOrBlank()) {
            // Server URL configured — go straight to scoreboard
            startActivity(Intent(this, ScoreboardActivity::class.java))
        } else {
            // Not configured — show setup screen
            startActivity(Intent(this, SetupActivity::class.java))
        }
        finish()
    }
}
