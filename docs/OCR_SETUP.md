# OCR Receipt Scanning Setup Guide

The OCR (Optical Character Recognition) feature allows automatic extraction of vendor, date, and amount data from receipt images and PDFs.

## Option 1: Paperless-NGX (Recommended for Docker)

**Paperless-NGX** is the recommended OCR solution, especially for Docker-based deployments. It includes built-in OCR — no need to install Tesseract or other tools in your application container.

### Setup

1. Deploy Paperless-NGX as a Docker container alongside your application:
   ```yaml
   # docker-compose.yml
   paperless:
     image: ghcr.io/paperless-ngx/paperless-ngx:latest
     restart: unless-stopped
     ports:
       - "8000:8000"
     volumes:
       - paperless_data:/usr/src/paperless/data
       - paperless_media:/usr/src/paperless/media
     environment:
       PAPERLESS_URL: http://paperless:8000
       PAPERLESS_SECRET_KEY: your-secret-key
       PAPERLESS_OCR_LANGUAGE: eng
   ```

2. Create an API token in Paperless-NGX:
   - Log in to Paperless-NGX web UI
   - Go to **Settings → API Tokens**
   - Create a new token

3. Configure in Arctic Wolves:
   - Go to **System Tools → Paperless-NGX** tab
   - Enter the Paperless-NGX URL (e.g. `http://paperless:8000`)
   - Enter the API token
   - Enable "Use for OCR"
   - Click **Test Connection** then **Save Settings**

### Paperless-NGX vs Nextcloud

| Feature | Paperless-NGX | Nextcloud |
|---------|--------------|-----------|
| **Primary purpose** | Document management & OCR | File sync & share |
| **Built-in OCR** | ✅ Yes (Tesseract included) | ❌ No (requires separate install) |
| **Full-text search** | ✅ Advanced search with tags | ⚠️ Basic (with plugins) |
| **Auto-categorization** | ✅ Automatic tagging & classification | ❌ No |
| **Docker-friendly OCR** | ✅ Self-contained | ❌ Need Tesseract in app container |
| **File storage** | Documents only | All file types |
| **Video storage** | ❌ Not suitable | ✅ Good |
| **Backup storage** | ❌ Not suitable | ✅ Good |
| **Collaboration** | ❌ Single-purpose | ✅ Full collaboration suite |

**Recommendation:** Use Paperless-NGX for receipt/document OCR and Nextcloud for general file storage (backups, videos, HR files).

## Option 2: Tesseract OCR (Direct Install)

If you prefer not to run Paperless-NGX, you can install Tesseract directly.

### Required Software

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install tesseract-ocr
```

**CentOS/RHEL:**
```bash
sudo yum install tesseract
```

**macOS (Homebrew):**
```bash
brew install tesseract
```

**Verify installation:**
```bash
tesseract --version
which tesseract
# Expected path: /usr/bin/tesseract
```

### PDF Support (Optional)

To scan PDF receipts, one of the following tools is needed to convert PDF pages to images before OCR processing:

**Option A — poppler-utils (Recommended):**
```bash
# Ubuntu/Debian
sudo apt-get install poppler-utils

# CentOS/RHEL
sudo yum install poppler-utils

# Verify
which pdftoppm
```

**Option B — ImageMagick:**
```bash
# Ubuntu/Debian
sudo apt-get install imagemagick

# CentOS/RHEL
sudo yum install ImageMagick

# Verify
which convert
```

> **Note:** If neither `pdftoppm` nor `convert` is installed, PDF receipts cannot be scanned. Image receipts (JPG, PNG) will still work with just Tesseract.

### Docker Setup (Tesseract only)

If running in Docker without Paperless-NGX, add these to your Dockerfile:

```dockerfile
RUN apt-get update && apt-get install -y \
    tesseract-ocr \
    poppler-utils \
    && rm -rf /var/lib/apt/lists/*
```

## How It Works

1. User uploads a receipt image (JPG/PNG) or PDF via the OCR modal
2. **If Paperless-NGX is configured:** the file is uploaded to its API, OCR runs inside Paperless, and the extracted text is returned
3. **If using Tesseract:** PDFs are converted to images first, then Tesseract extracts raw text
4. The system parses the text to identify:
   - **Vendor name** — first non-empty line
   - **Date** — date patterns (YYYY-MM-DD, MM/DD/YYYY, etc.)
   - **Amounts** — currency patterns ($X.XX); largest is assumed total
   - **Tax** — amounts following GST/HST/TAX keywords
   - **Line items** — quantity × price patterns
5. Extracted data is presented for review before creating an expense

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| "OCR not available" | Neither Paperless-NGX nor Tesseract configured | Configure Paperless-NGX in System Tools, or install Tesseract |
| "Paperless-NGX OCR returned no text" | Paperless couldn't extract text | Check image quality, check Paperless-NGX logs |
| "OCR failed - no text extracted" | Tesseract couldn't read the image | Try a clearer photo with good lighting |
| "PDF conversion failed" | Neither `pdftoppm` nor ImageMagick installed | Install poppler-utils or ImageMagick, or use Paperless-NGX |
| "Only JPG, PNG, and PDF files can be scanned" | Unsupported file format | Convert the file to JPG or PNG first |
| Poor extraction accuracy | Low image resolution or blurry text | Use higher resolution (300+ DPI), ensure text is legible |
