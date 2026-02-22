package ca.arcticwolves.gameplan.tv

/**
 * Application configuration constants.
 *
 * When built with Gradle (AGP), these can be overridden via BuildConfig
 * fields per build type. For command-line builds these defaults are used.
 */
object AppConfig {
    /** Web content base URL for the Game Plan TV viewer. */
    const val BASE_URL = "https://gameplan.arcticwolves.ca"

    /** REST API base URL for TV pairing endpoints. */
    const val API_BASE_URL = "https://api.arcticwolves.ca/v1"
}
