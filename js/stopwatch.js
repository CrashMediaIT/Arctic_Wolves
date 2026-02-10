/**
 * Arctic Wolves Sports Stopwatch
 * Full-featured stopwatch with lap/split timing, time history, and athlete assignment
 * Now includes countdown timer mode
 */
class Stopwatch {
    constructor(displayEl) {
        this.displayEl = displayEl;
        this.running = false;
        this.startTime = 0;
        this.elapsed = 0;
        this.lastLapTime = 0;
        this.laps = [];
        this.intervalId = null;
        // Countdown mode properties
        this.isCountdownMode = false;
        this.countdownDuration = 0; // in milliseconds
        this.countdownRemaining = 0;
        this.onCountdownComplete = null; // callback when countdown reaches zero
    }

    // Set countdown mode with duration in seconds
    setCountdownMode(durationSeconds, onComplete = null) {
        this.isCountdownMode = true;
        this.countdownDuration = durationSeconds * 1000;
        this.countdownRemaining = this.countdownDuration;
        this.onCountdownComplete = onComplete;
        this.elapsed = 0;
        this.lastLapTime = 0;
        this.laps = [];
        this._updateDisplay();
    }

    // Switch to stopwatch mode
    setStopwatchMode() {
        this.isCountdownMode = false;
        this.countdownDuration = 0;
        this.countdownRemaining = 0;
        this.onCountdownComplete = null;
        this.reset();
    }

    start() {
        if (this.running) return;
        this.running = true;
        
        if (this.isCountdownMode) {
            this.startTime = performance.now();
            // If countdown was paused, adjust start time
            if (this.countdownRemaining < this.countdownDuration) {
                this.startTime = performance.now() - (this.countdownDuration - this.countdownRemaining);
            }
        } else {
            this.startTime = performance.now() - this.elapsed;
        }
        
        this.intervalId = requestAnimationFrame(() => this._tick());
    }

    stop() {
        if (!this.running) return;
        this.running = false;
        
        if (this.isCountdownMode) {
            const now = performance.now();
            const elapsed = now - this.startTime;
            this.countdownRemaining = Math.max(0, this.countdownDuration - elapsed);
        } else {
            this.elapsed = performance.now() - this.startTime;
        }
        
        if (this.intervalId) {
            cancelAnimationFrame(this.intervalId);
            this.intervalId = null;
        }
        this._updateDisplay();
    }

    lap() {
        if (!this.running) return null;
        const now = performance.now() - this.startTime;
        const lapTime = now - this.lastLapTime;
        this.lastLapTime = now;
        const lapData = {
            number: this.laps.length + 1,
            lapTimeMs: Math.round(lapTime),
            totalTimeMs: Math.round(now),
            athleteId: null,
            athleteName: ''
        };
        this.laps.push(lapData);
        return lapData;
    }

    reset() {
        this.stop();
        if (this.isCountdownMode) {
            this.countdownRemaining = this.countdownDuration;
        } else {
            this.elapsed = 0;
        }
        this.lastLapTime = 0;
        this.laps = [];
        this._updateDisplay();
    }

    getElapsed() {
        if (this.isCountdownMode) {
            if (this.running) {
                const now = performance.now();
                const elapsed = now - this.startTime;
                return Math.max(0, this.countdownDuration - elapsed);
            }
            return this.countdownRemaining;
        } else {
            if (this.running) {
                return performance.now() - this.startTime;
            }
            return this.elapsed;
        }
    }

    getLaps() {
        return this.laps;
    }

    _tick() {
        if (!this.running) return;
        
        if (this.isCountdownMode) {
            const now = performance.now();
            const elapsed = now - this.startTime;
            this.countdownRemaining = Math.max(0, this.countdownDuration - elapsed);
            
            // Check if countdown complete
            if (this.countdownRemaining <= 0) {
                this.stop();
                this._updateDisplay();
                if (this.onCountdownComplete) {
                    this.onCountdownComplete();
                }
                return;
            }
        } else {
            this.elapsed = performance.now() - this.startTime;
        }
        
        this._updateDisplay();
        this.intervalId = requestAnimationFrame(() => this._tick());
    }

    _updateDisplay() {
        if (this.displayEl) {
            if (this.isCountdownMode) {
                this.displayEl.textContent = Stopwatch.formatTime(this.countdownRemaining);
            } else {
                this.displayEl.textContent = Stopwatch.formatTime(this.elapsed);
            }
        }
    }

    static formatTime(ms) {
        const totalMs = Math.floor(ms);
        const minutes = Math.floor(totalMs / 60000);
        const seconds = Math.floor((totalMs % 60000) / 1000);
        const centiseconds = Math.floor((totalMs % 1000) / 10);
        return String(minutes).padStart(2, '0') + ':' +
               String(seconds).padStart(2, '0') + '.' +
               String(centiseconds).padStart(2, '0');
    }

    static formatTimeMs(ms) {
        const totalMs = Math.floor(ms);
        const minutes = Math.floor(totalMs / 60000);
        const seconds = Math.floor((totalMs % 60000) / 1000);
        const milliseconds = totalMs % 1000;
        return String(minutes).padStart(2, '0') + ':' +
               String(seconds).padStart(2, '0') + '.' +
               String(milliseconds).padStart(3, '0');
    }
}
