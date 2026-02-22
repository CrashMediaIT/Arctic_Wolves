package ca.arcticwolves.gameplan.tv.api

import ca.arcticwolves.gameplan.tv.BuildConfig
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.util.concurrent.TimeUnit

/**
 * Lightweight REST API client for the Arctic Wolves TV pairing endpoints.
 *
 * All calls are **synchronous** and must be invoked from a background thread.
 *
 * Base URL comes from [BuildConfig.API_BASE_URL] which is set per build type
 * (debug → localhost emulator, release → production domain).
 */
object ApiClient {

    private val JSON_MEDIA = "application/json; charset=utf-8".toMediaType()

    private val client = OkHttpClient.Builder()
        .connectTimeout(10, TimeUnit.SECONDS)
        .readTimeout(15, TimeUnit.SECONDS)
        .writeTimeout(10, TimeUnit.SECONDS)
        .build()

    // ── Join pair ──────────────────────────────────────────

    data class JoinResult(
        val success: Boolean,
        val pairId: Int = 0,
        val viewerToken: String = "",
        val controllerPage: String = "home",
        val error: String? = null
    )

    /**
     * POST /v1/tv/pair — join as viewer with the given pair code.
     */
    fun joinPair(apiKey: String, pairCode: String): JoinResult {
        return try {
            val body = JSONObject().apply {
                put("pair_code", pairCode)
            }.toString().toRequestBody(JSON_MEDIA)

            val request = Request.Builder()
                .url("${BuildConfig.API_BASE_URL}/tv/pair")
                .addHeader("Authorization", "Bearer $apiKey")
                .post(body)
                .build()

            client.newCall(request).execute().use { response ->
                val json = JSONObject(response.body?.string() ?: "{}")
                if (json.optBoolean("success", false)) {
                    JoinResult(
                        success = true,
                        pairId = json.optInt("pair_id", 0),
                        viewerToken = json.optString("viewer_token", ""),
                        controllerPage = json.optString("controller_page", "home")
                    )
                } else {
                    JoinResult(success = false, error = json.optString("error", "Pairing failed"))
                }
            }
        } catch (e: Exception) {
            JoinResult(success = false, error = "Network error: ${e.message}")
        }
    }

    // ── Poll pair state ────────────────────────────────────

    data class PairState(
        val active: Boolean,
        val isFrozen: Boolean = false,
        val controllerPage: String = "home"
    )

    /**
     * GET /v1/tv/pair/{id} — poll current pair state.
     */
    fun getPairState(apiKey: String, pairId: Int): PairState {
        return try {
            val request = Request.Builder()
                .url("${BuildConfig.API_BASE_URL}/tv/pair/$pairId")
                .addHeader("Authorization", "Bearer $apiKey")
                .get()
                .build()

            client.newCall(request).execute().use { response ->
                val json = JSONObject(response.body?.string() ?: "{}")
                PairState(
                    active = json.optBoolean("active", false),
                    isFrozen = json.optBoolean("is_frozen", false),
                    controllerPage = json.optString("controller_page", "home")
                )
            }
        } catch (e: Exception) {
            PairState(active = false)
        }
    }

    // ── Unpair ─────────────────────────────────────────────

    /**
     * DELETE /v1/tv/pair/{id} — disconnect the viewer.
     */
    fun unpair(apiKey: String, pairId: Int): Boolean {
        return try {
            val request = Request.Builder()
                .url("${BuildConfig.API_BASE_URL}/tv/pair/$pairId")
                .addHeader("Authorization", "Bearer $apiKey")
                .delete()
                .build()

            client.newCall(request).execute().use { response ->
                response.isSuccessful
            }
        } catch (e: Exception) {
            false
        }
    }
}
