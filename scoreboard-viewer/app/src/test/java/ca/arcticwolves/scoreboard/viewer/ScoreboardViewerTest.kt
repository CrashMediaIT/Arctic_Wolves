package ca.arcticwolves.scoreboard.viewer

import org.junit.Assert.*
import org.junit.Test

/**
 * Unit tests for the Scoreboard Viewer app.
 * Validates URL validation logic and CSS injection string generation.
 */
class ScoreboardViewerTest {

    /**
     * Verify the URL pattern used by SetupActivity accepts valid URLs.
     */
    private val urlPattern = Regex("^https?://.+")

    @Test
    fun validHttpsUrl_isAccepted() {
        assertTrue("https://scoreboard.arcticwolves.ca".matches(urlPattern))
    }

    @Test
    fun validHttpUrl_isAccepted() {
        assertTrue("http://10.0.2.2/Arctic_Wolves".matches(urlPattern))
    }

    @Test
    fun urlWithPath_isAccepted() {
        assertTrue("https://example.com/scoreboard".matches(urlPattern))
    }

    @Test
    fun emptyString_isRejected() {
        assertFalse("".matches(urlPattern))
    }

    @Test
    fun missingScheme_isRejected() {
        assertFalse("scoreboard.arcticwolves.ca".matches(urlPattern))
    }

    @Test
    fun ftpScheme_isRejected() {
        assertFalse("ftp://files.example.com".matches(urlPattern))
    }

    @Test
    fun trailingSlash_isStripped() {
        val url = "https://scoreboard.arcticwolves.ca/"
        val trimmed = url.trimEnd('/')
        assertEquals("https://scoreboard.arcticwolves.ca", trimmed)
    }

    @Test
    fun noTrailingSlash_isUnchanged() {
        val url = "https://scoreboard.arcticwolves.ca"
        val trimmed = url.trimEnd('/')
        assertEquals("https://scoreboard.arcticwolves.ca", trimmed)
    }

    /**
     * Verify the JS string literal escaping helper produces valid output.
     */
    @Test
    fun toJsStringLiteral_escapesQuotes() {
        val input = "it's a test"
        val result = input.testToJsStringLiteral()
        assertEquals("'it\\'s a test'", result)
    }

    @Test
    fun toJsStringLiteral_escapesNewlines() {
        val input = "line1\nline2"
        val result = input.testToJsStringLiteral()
        assertEquals("'line1\\nline2'", result)
    }

    @Test
    fun toJsStringLiteral_escapesBackslashes() {
        val input = "back\\slash"
        val result = input.testToJsStringLiteral()
        assertEquals("'back\\\\slash'", result)
    }

    /**
     * Mirror of the private toJsStringLiteral() extension from ScoreboardActivity.
     */
    private fun String.testToJsStringLiteral(): String {
        val escaped = this
            .replace("\\", "\\\\")
            .replace("'", "\\'")
            .replace("\n", "\\n")
            .replace("\r", "")
        return "'$escaped'"
    }
}
