package ca.arcticwolves.gameplan.tv

import ca.arcticwolves.gameplan.tv.api.ApiClient
import org.junit.Assert.*
import org.junit.Test

/**
 * Unit tests for the ApiClient data classes and basic validation logic.
 * Network-dependent integration tests require a running backend.
 */
class ApiClientTest {

    @Test
    fun joinResult_success_containsPairId() {
        val result = ApiClient.JoinResult(
            success = true,
            pairId = 42,
            viewerToken = "abc123",
            controllerPage = "video_review"
        )
        assertTrue(result.success)
        assertEquals(42, result.pairId)
        assertEquals("abc123", result.viewerToken)
        assertEquals("video_review", result.controllerPage)
        assertNull(result.error)
    }

    @Test
    fun joinResult_failure_containsError() {
        val result = ApiClient.JoinResult(
            success = false,
            error = "Pair code not found"
        )
        assertFalse(result.success)
        assertEquals(0, result.pairId)
        assertEquals("Pair code not found", result.error)
    }

    @Test
    fun pairState_defaults() {
        val state = ApiClient.PairState(active = false)
        assertFalse(state.active)
        assertFalse(state.isFrozen)
        assertEquals("home", state.controllerPage)
    }

    @Test
    fun pairState_activeWithFrozen() {
        val state = ApiClient.PairState(
            active = true,
            isFrozen = true,
            controllerPage = "film_room"
        )
        assertTrue(state.active)
        assertTrue(state.isFrozen)
        assertEquals("film_room", state.controllerPage)
    }
}
