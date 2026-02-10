/**
 * Arctic Wolves Sports Stopwatch
 * Full-featured stopwatch with lap/split timing, time history, and athlete assignment
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
    }

    start() {
        if (this.running) return;
        this.running = true;
        this.startTime = performance.now() - this.elapsed;
        this.intervalId = requestAnimationFrame(() => this._tick());
    }

    stop() {
        if (!this.running) return;
        this.running = false;
        this.elapsed = performance.now() - this.startTime;
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
        this.elapsed = 0;
        this.lastLapTime = 0;
        this.laps = [];
        this._updateDisplay();
    }

    getElapsed() {
        if (this.running) {
            return performance.now() - this.startTime;
        }
        return this.elapsed;
    }

    getLaps() {
        return this.laps;
    }

    _tick() {
        if (!this.running) return;
        this.elapsed = performance.now() - this.startTime;
        this._updateDisplay();
        this.intervalId = requestAnimationFrame(() => this._tick());
    }

    _updateDisplay() {
        if (this.displayEl) {
            this.displayEl.textContent = Stopwatch.formatTime(this.elapsed);
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
