package ca.arcticwolves.gameplan.tv

import android.content.Intent
import android.content.SharedPreferences
import android.os.Bundle
import androidx.fragment.app.FragmentActivity

/**
 * Main entry-point activity for the Game Plan TV app.
 *
 * Checks for a persisted pair session and either launches the
 * [ViewerActivity] (already paired) or [PairActivity] (needs pairing).
 */
class MainActivity : FragmentActivity() {

    companion object {
        const val PREFS_NAME = "gameplan_tv_prefs"
        const val KEY_PAIR_ID = "pair_id"
        const val KEY_VIEWER_TOKEN = "viewer_token"
        const val KEY_API_KEY = "api_key"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val prefs: SharedPreferences = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
        val pairId = prefs.getInt(KEY_PAIR_ID, 0)
        val apiKey = prefs.getString(KEY_API_KEY, null)

        if (pairId > 0 && !apiKey.isNullOrBlank()) {
            // Already paired — go straight to viewer
            startActivity(Intent(this, ViewerActivity::class.java))
        } else {
            // Not paired — show pairing screen
            startActivity(Intent(this, PairActivity::class.java))
        }
        finish()
    }
}
