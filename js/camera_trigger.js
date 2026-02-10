/**
 * Arctic Wolves Dual-Camera Trigger for Stopwatch
 * Uses two separate device cameras with motion detection to trigger start/stop
 * Camera 1 (Start Line) detects motion to start the timer
 * Camera 2 (Finish Line) detects motion to stop/lap the timer
 */
class CameraTrigger {
    constructor(options = {}) {
        this.sensitivity = options.sensitivity || 30;
        this.motionThreshold = options.motionThreshold || 8;
        this.detectionRegion = options.detectionRegion || 0.3;
        this.cooldownMs = options.cooldownMs || 500;

        this.startVideoEl = null;
        this.finishVideoEl = null;
        this.startCanvasEl = null;
        this.finishCanvasEl = null;
        this.startStream = null;
        this.finishStream = null;

        this.startPrevFrame = null;
        this.finishPrevFrame = null;
        this.startAnimId = null;
        this.finishAnimId = null;

        this.onStartTrigger = null;
        this.onFinishTrigger = null;
        this.onMotionLevel = null;

        this.startArmed = false;
        this.finishArmed = false;
        this.startLastTrigger = 0;
        this.finishLastTrigger = 0;

        this.active = false;
    }

    async getAvailableCameras() {
        try {
            await navigator.mediaDevices.getUserMedia({ video: true });
            const devices = await navigator.mediaDevices.enumerateDevices();
            return devices.filter(d => d.kind === 'videoinput');
        } catch (e) {
            return [];
        }
    }

    async setupCamera(videoEl, canvasEl, deviceId) {
        const constraints = {
            video: {
                deviceId: deviceId ? { exact: deviceId } : undefined,
                width: { ideal: 320 },
                height: { ideal: 240 },
                frameRate: { ideal: 30 }
            }
        };

        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        videoEl.srcObject = stream;
        await videoEl.play();

        canvasEl.width = 320;
        canvasEl.height = 240;

        return stream;
    }

    async startMonitoring(config) {
        const {
            startVideoEl, finishVideoEl,
            startCanvasEl, finishCanvasEl,
            startDeviceId, finishDeviceId,
            onStartTrigger, onFinishTrigger, onMotionLevel
        } = config;

        this.startVideoEl = startVideoEl;
        this.finishVideoEl = finishVideoEl;
        this.startCanvasEl = startCanvasEl;
        this.finishCanvasEl = finishCanvasEl;
        this.onStartTrigger = onStartTrigger;
        this.onFinishTrigger = onFinishTrigger;
        this.onMotionLevel = onMotionLevel || null;

        this.startStream = await this.setupCamera(startVideoEl, startCanvasEl, startDeviceId);
        this.finishStream = await this.setupCamera(finishVideoEl, finishCanvasEl, finishDeviceId);

        this.active = true;
        this.startArmed = true;
        this.finishArmed = false;
        this.startPrevFrame = null;
        this.finishPrevFrame = null;

        this._detectLoop('start');
        this._detectLoop('finish');
    }

    armStart() {
        this.startArmed = true;
    }

    armFinish() {
        this.finishArmed = true;
    }

    disarmAll() {
        this.startArmed = false;
        this.finishArmed = false;
    }

    _detectLoop(which) {
        if (!this.active) return;

        const isStart = which === 'start';
        const videoEl = isStart ? this.startVideoEl : this.finishVideoEl;
        const canvasEl = isStart ? this.startCanvasEl : this.finishCanvasEl;

        if (!videoEl || !canvasEl || videoEl.readyState < 2) {
            const animId = requestAnimationFrame(() => this._detectLoop(which));
            if (isStart) this.startAnimId = animId;
            else this.finishAnimId = animId;
            return;
        }

        const ctx = canvasEl.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);

        const w = canvasEl.width;
        const h = canvasEl.height;
        const regionH = Math.floor(h * this.detectionRegion);
        const startY = Math.floor((h - regionH) / 2);
        const imageData = ctx.getImageData(0, startY, w, regionH);
        const data = imageData.data;

        const prevFrame = isStart ? this.startPrevFrame : this.finishPrevFrame;

        if (prevFrame) {
            let changedPixels = 0;
            const totalPixels = w * regionH;

            for (let i = 0; i < data.length; i += 16) {
                const rDiff = Math.abs(data[i] - prevFrame[i]);
                const gDiff = Math.abs(data[i + 1] - prevFrame[i + 1]);
                const bDiff = Math.abs(data[i + 2] - prevFrame[i + 2]);
                if (rDiff + gDiff + bDiff > this.sensitivity) {
                    changedPixels++;
                }
            }

            const sampledPixels = Math.floor(totalPixels / 4);
            const motionPercent = (changedPixels / sampledPixels) * 100;

            if (this.onMotionLevel) {
                this.onMotionLevel(which, motionPercent);
            }

            const now = performance.now();

            if (isStart && this.startArmed && motionPercent > this.motionThreshold) {
                if (now - this.startLastTrigger > this.cooldownMs) {
                    this.startLastTrigger = now;
                    this.startArmed = false;
                    this.finishArmed = true;
                    if (this.onStartTrigger) this.onStartTrigger(now);
                }
            }

            if (!isStart && this.finishArmed && motionPercent > this.motionThreshold) {
                if (now - this.finishLastTrigger > this.cooldownMs) {
                    this.finishLastTrigger = now;
                    if (this.onFinishTrigger) this.onFinishTrigger(now);
                }
            }
        }

        const frameCopy = new Uint8ClampedArray(data);
        if (isStart) this.startPrevFrame = frameCopy;
        else this.finishPrevFrame = frameCopy;

        const animId = requestAnimationFrame(() => this._detectLoop(which));
        if (isStart) this.startAnimId = animId;
        else this.finishAnimId = animId;
    }

    stopMonitoring() {
        this.active = false;
        this.disarmAll();

        if (this.startAnimId) cancelAnimationFrame(this.startAnimId);
        if (this.finishAnimId) cancelAnimationFrame(this.finishAnimId);
        this.startAnimId = null;
        this.finishAnimId = null;

        if (this.startStream) {
            this.startStream.getTracks().forEach(t => t.stop());
            this.startStream = null;
        }
        if (this.finishStream) {
            this.finishStream.getTracks().forEach(t => t.stop());
            this.finishStream = null;
        }

        if (this.startVideoEl) this.startVideoEl.srcObject = null;
        if (this.finishVideoEl) this.finishVideoEl.srcObject = null;

        this.startPrevFrame = null;
        this.finishPrevFrame = null;
    }

    setSensitivity(value) {
        this.sensitivity = Math.max(5, Math.min(100, value));
    }

    setMotionThreshold(value) {
        this.motionThreshold = Math.max(1, Math.min(50, value));
    }

    isActive() {
        return this.active;
    }
}
