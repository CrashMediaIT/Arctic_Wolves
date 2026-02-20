# OCR Receipt Scanning Setup Guide

The OCR (Optical Character Recognition) feature allows automatic extraction of vendor, date, and amount data from receipt images and PDFs. All OCR processing is performed by **Paperless-NGX**.

## Paperless-NGX Setup

**Paperless-NGX** is a purpose-built document management system with built-in OCR. It handles all file types (JPG, PNG, PDF) natively — no additional tools required.

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
   - Go to **Settings → System Tools → Paperless-NGX** tab
   - Enter the Paperless-NGX URL (e.g. `http://paperless:8000`)
   - Enter the API token
   - Enable "Use for OCR"
   - Click **Test Connection** then **Save Settings**

### Paperless-NGX vs Nextcloud

| Feature | Paperless-NGX | Nextcloud |
|---------|--------------|-----------|
| **Primary purpose** | Document management & OCR | File sync & share |
| **Built-in OCR** | ✅ Yes | ❌ No |
| **Full-text search** | ✅ Advanced search with tags | ⚠️ Basic (with plugins) |
| **Auto-categorization** | ✅ Automatic tagging & classification | ❌ No |
| **PDF support** | ✅ Native | ❌ N/A |
| **File storage** | Documents only | All file types |
| **Video storage** | ❌ Not suitable | ✅ Good |
| **Backup storage** | ❌ Not suitable | ✅ Good |
| **Collaboration** | ❌ Single-purpose | ✅ Full collaboration suite |

**Recommendation:** Use Paperless-NGX for receipt/document OCR and Nextcloud for general file storage (backups, videos, HR files).

## How It Works

1. User uploads a receipt image (JPG/PNG) or PDF via the OCR modal
2. The file is uploaded directly to the Paperless-NGX API, which performs OCR and returns the extracted text
3. The system parses the text to identify:
   - **Vendor name** — first non-empty line
   - **Date** — date patterns (YYYY-MM-DD, MM/DD/YYYY, etc.)
   - **Amounts** — currency patterns ($X.XX); largest is assumed total
   - **Tax** — amounts following GST/HST/TAX keywords
   - **Line items** — quantity × price patterns
4. Extracted data is presented for review before creating an expense

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| "OCR not available" | Paperless-NGX not configured | Configure Paperless-NGX in Settings > System Tools |
| Test connection returns HTTP 302 | Paperless-NGX is redirecting API requests | Ensure the URL uses the correct protocol (http/https) and includes the port (e.g. `http://paperless:8000`) |
| "Paperless-NGX OCR returned no text" | Paperless couldn't extract text | Check image quality, check Paperless-NGX logs |
| "Only JPG, PNG, and PDF files can be scanned" | Unsupported file format | Convert the file to JPG or PNG first |
| Poor extraction accuracy | Low image resolution or blurry text | Use higher resolution (300+ DPI), ensure text is legible |
