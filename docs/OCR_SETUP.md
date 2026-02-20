# OCR Receipt Scanning Setup Guide

The OCR (Optical Character Recognition) feature allows automatic extraction of vendor, date, and amount data from receipt images and PDFs.

## Required Software

### Tesseract OCR (Required)

Tesseract is the core OCR engine used to extract text from receipt images. **OCR will not function without it.**

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

## How It Works

1. User uploads a receipt image (JPG/PNG) or PDF via the OCR modal
2. For PDFs: the first page is converted to a 300 DPI PNG image using `pdftoppm` or ImageMagick
3. Tesseract processes the image and extracts raw text
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
| "OCR not available - Tesseract not installed" | Tesseract binary not found at `/usr/bin/tesseract` | Install Tesseract OCR (see above) |
| "OCR failed - no text extracted" | Image quality too low or not a receipt | Try a clearer photo with good lighting |
| "PDF conversion failed" | Neither `pdftoppm` nor ImageMagick installed | Install poppler-utils or ImageMagick |
| "Only JPG, PNG, and PDF files can be scanned" | Unsupported file format | Convert the file to JPG or PNG first |
| Poor extraction accuracy | Low image resolution or blurry text | Use higher resolution (300+ DPI), ensure text is legible |

## Docker Setup

If running in Docker, add these to your Dockerfile:

```dockerfile
RUN apt-get update && apt-get install -y \
    tesseract-ocr \
    poppler-utils \
    && rm -rf /var/lib/apt/lists/*
```
