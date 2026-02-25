<?php
/**
 * Garage S3-Compatible Storage Client
 * 
 * Provides S3-compatible API operations for Garage (https://garagehq.deuxfleurs.fr/)
 * Uses AWS Signature Version 4 for authentication.
 * Supports: upload, download, delete, list, head, pre-signed URLs.
 */

class GarageS3 {

    private $endpoint;
    private $accessKey;
    private $secretKey;
    private $region;
    private $bucket;

    /**
     * @param string $endpoint  Garage S3 API endpoint (e.g., https://s3.garage.example.com)
     * @param string $accessKey Garage API access key ID
     * @param string $secretKey Garage API secret access key
     * @param string $region    S3 region (default: garage for Garage instances)
     * @param string $bucket    Default bucket name
     */
    public function __construct($endpoint, $accessKey, $secretKey, $region = 'garage', $bucket = '') {
        $this->endpoint  = rtrim($endpoint, '/');
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region    = $region ?: 'garage';
        $this->bucket    = $bucket;
    }

    /**
     * Upload a file to Garage S3.
     *
     * @param string      $objectKey   S3 object key (path in bucket)
     * @param string      $filePath    Local file path to upload
     * @param string|null $contentType MIME type (auto-detected if null)
     * @param string|null $bucket      Override default bucket
     * @return array ['success'=>bool, 'message'=>string, 'url'=>string]
     */
    public function putObject($objectKey, $filePath, $contentType = null, $bucket = null) {
        $bucket = $bucket ?: $this->bucket;
        if (empty($bucket)) {
            return ['success' => false, 'message' => 'No bucket specified'];
        }

        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => "File not found: $filePath"];
        }

        if ($contentType === null) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $contentType = finfo_file($finfo, $filePath) ?: 'application/octet-stream';
            finfo_close($finfo);
        }

        $body = file_get_contents($filePath);
        $objectKey = ltrim($objectKey, '/');

        $url = $this->buildUrl($bucket, $objectKey);
        $headers = $this->signRequest('PUT', $bucket, $objectKey, $headers_extra = [
            'Content-Type' => $contentType,
        ], $body);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if (!empty($error)) {
            return ['success' => false, 'message' => "cURL error: $error"];
        }

        if ($httpCode === 200 || $httpCode === 201) {
            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'url'     => $url,
                'key'     => $objectKey,
            ];
        }

        return ['success' => false, 'message' => "Upload failed. HTTP $httpCode: $response"];
    }

    /**
     * Upload a large file using streaming (memory-efficient).
     *
     * @param string      $objectKey   S3 object key
     * @param string      $filePath    Local file path
     * @param string|null $contentType MIME type
     * @param string|null $bucket      Override bucket
     * @return array
     */
    public function putObjectStreaming($objectKey, $filePath, $contentType = null, $bucket = null) {
        $bucket = $bucket ?: $this->bucket;
        if (empty($bucket)) {
            return ['success' => false, 'message' => 'No bucket specified'];
        }

        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => "File not found: $filePath"];
        }

        $fileSize = filesize($filePath);
        if ($contentType === null) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $contentType = finfo_file($finfo, $filePath) ?: 'application/octet-stream';
            finfo_close($finfo);
        }

        $objectKey = ltrim($objectKey, '/');

        // For streaming, we need the content hash to be UNSIGNED-PAYLOAD
        $url = $this->buildUrl($bucket, $objectKey);
        $headers = $this->signRequest('PUT', $bucket, $objectKey, [
            'Content-Type'   => $contentType,
            'Content-Length' => (string) $fileSize,
        ], null, true); // streaming = true

        $fh = fopen($filePath, 'rb');
        if ($fh === false) {
            return ['success' => false, 'message' => "Cannot open file: $filePath"];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 7200);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if (!empty($error)) {
            return ['success' => false, 'message' => "cURL error: $error"];
        }

        if ($httpCode === 200 || $httpCode === 201) {
            return [
                'success'   => true,
                'message'   => 'File uploaded successfully (streaming)',
                'url'       => $url,
                'key'       => $objectKey,
                'file_size' => $fileSize,
            ];
        }

        return ['success' => false, 'message' => "Streaming upload failed. HTTP $httpCode: $response"];
    }

    /**
     * Download an object from S3 and save to local file.
     *
     * @param string      $objectKey S3 object key
     * @param string      $savePath  Local path to save the file
     * @param string|null $bucket    Override bucket
     * @return array
     */
    public function getObject($objectKey, $savePath, $bucket = null) {
        $bucket = $bucket ?: $this->bucket;
        if (empty($bucket)) {
            return ['success' => false, 'message' => 'No bucket specified'];
        }

        $objectKey = ltrim($objectKey, '/');
        $url = $this->buildUrl($bucket, $objectKey);
        $headers = $this->signRequest('GET', $bucket, $objectKey);

        $dir = dirname($savePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fh = fopen($savePath, 'wb');
        if ($fh === false) {
            return ['success' => false, 'message' => "Cannot open file for writing: $savePath"];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if (!empty($error)) {
            @unlink($savePath);
            return ['success' => false, 'message' => "cURL error: $error"];
        }

        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'File downloaded', 'path' => $savePath];
        }

        @unlink($savePath);
        return ['success' => false, 'message' => "Download failed. HTTP $httpCode"];
    }

    /**
     * Get object content as string.
     *
     * @param string      $objectKey S3 object key
     * @param string|null $bucket    Override bucket
     * @return array ['success'=>bool, 'content'=>string]
     */
    public function getObjectContent($objectKey, $bucket = null) {
        $bucket = $bucket ?: $this->bucket;
        if (empty($bucket)) {
            return ['success' => false, 'message' => 'No bucket specified'];
        }

        $objectKey = ltrim($objectKey, '/');
        $url = $this->buildUrl($bucket, $objectKey);
        $headers = $this->signRequest('GET', $bucket, $objectKey);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if (!empty($error)) {
            return ['success' => false, 'message' => "cURL error: $error"];
        }

        if ($httpCode === 200) {
            return ['success' => true, 'content' => $response];
        }

        return ['success' => false, 'message' => "GET failed. HTTP $httpCode"];
    }

    /**
     * Delete an object from S3.
     *
     * @param string      $objectKey S3 object key
     * @param string|null $bucket    Override bucket
     * @return array
     */
    public function deleteObject($objectKey, $bucket = null) {
        $bucket = $bucket ?: $this->bucket;
        if (empty($bucket)) {
            return ['success' => false, 'message' => 'No bucket specified'];
        }

        $objectKey = ltrim($objectKey, '/');
        $url = $this->buildUrl($bucket, $objectKey);
        $headers = $this->signRequest('DELETE', $bucket, $objectKey);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if (!empty($error)) {
            return ['success' => false, 'message' => "cURL error: $error"];
        }

        if ($httpCode === 204 || $httpCode === 200) {
            return ['success' => true, 'message' => 'Object deleted'];
        }

        return ['success' => false, 'message' => "Delete failed. HTTP $httpCode: $response"];
    }

    /**
     * Check if an object exists (HEAD request).
     *
     * @param string      $objectKey S3 object key
     * @param string|null $bucket    Override bucket
     * @return array ['exists'=>bool, 'content_type'=>string, 'content_length'=>int]
     */
    public function headObject($objectKey, $bucket = null) {
        $bucket = $bucket ?: $this->bucket;
        if (empty($bucket)) {
            return ['exists' => false, 'message' => 'No bucket specified'];
        }

        $objectKey = ltrim($objectKey, '/');
        $url = $this->buildUrl($bucket, $objectKey);
        $headers = $this->signRequest('HEAD', $bucket, $objectKey);

        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$responseHeaders) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($header);
        });
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return [
                'exists'         => true,
                'content_type'   => $responseHeaders['content-type'] ?? '',
                'content_length' => (int) ($responseHeaders['content-length'] ?? 0),
            ];
        }

        return ['exists' => false];
    }

    /**
     * Generate a pre-signed URL for temporary public access.
     *
     * @param string      $objectKey S3 object key
     * @param int         $expires   Seconds until expiry (default: 3600)
     * @param string      $method    HTTP method (GET or PUT)
     * @param string|null $bucket    Override bucket
     * @return string Pre-signed URL
     */
    public function getPresignedUrl($objectKey, $expires = 3600, $method = 'GET', $bucket = null) {
        $bucket    = $bucket ?: $this->bucket;
        $objectKey = ltrim($objectKey, '/');
        $now       = new \DateTime('UTC');
        $datestamp = $now->format('Ymd');
        $amzDate   = $now->format('Ymd\THis\Z');

        $scope       = "$datestamp/{$this->region}/s3/aws4_request";
        $canonicalUri = '/' . $objectKey;

        $queryParams = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $this->accessKey . '/' . $scope,
            'X-Amz-Date'          => $amzDate,
            'X-Amz-Expires'       => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
        ];

        ksort($queryParams);
        $canonicalQueryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $host = $this->getHost($bucket);
        $canonicalHeaders = "host:$host\n";

        $canonicalRequest = implode("\n", [
            $method,
            $this->uriEncodePath($canonicalUri),
            $canonicalQueryString,
            $canonicalHeaders,
            'host',
            'UNSIGNED-PAYLOAD',
        ]);

        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->getSigningKey($datestamp);
        $signature  = hash_hmac('sha256', $stringToSign, $signingKey);

        $baseUrl = $this->endpoint . '/' . $bucket . $canonicalUri;
        return $baseUrl . '?' . $canonicalQueryString . '&X-Amz-Signature=' . $signature;
    }

    /**
     * Test the connection to Garage S3 by listing bucket contents.
     *
     * @param string|null $bucket Override bucket
     * @return array ['success'=>bool, 'message'=>string]
     */
    public function testConnection($bucket = null) {
        $bucket = $bucket ?: $this->bucket;
        if (empty($bucket)) {
            return ['success' => false, 'message' => 'No bucket specified'];
        }

        $objectKey = '';
        $url = $this->endpoint . '/' . $bucket . '/?list-type=2&max-keys=1';
        $headers = $this->signRequest('GET', $bucket, '', [
            'list-type' => '2',
            'max-keys'  => '1',
        ], '', false, true);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if (!empty($error)) {
            return ['success' => false, 'message' => "Connection failed: $error"];
        }

        if ($httpCode === 200) {
            return ['success' => true, 'message' => "Connected to Garage S3. Bucket '$bucket' is accessible."];
        }

        if ($httpCode === 404) {
            return ['success' => false, 'message' => "Bucket '$bucket' not found. Create it in the Garage admin panel first."];
        }

        if ($httpCode === 403) {
            return ['success' => false, 'message' => 'Access denied. Check your access key and secret key.'];
        }

        return ['success' => false, 'message' => "Unexpected response. HTTP $httpCode: " . substr($response, 0, 200)];
    }

    // ─── AWS Signature V4 ─────────────────────────────────────────────

    /**
     * Sign an S3 request with AWS Signature Version 4.
     *
     * @param string $method       HTTP method
     * @param string $bucket       Bucket name
     * @param string $objectKey    Object key
     * @param array  $extraHeaders Additional headers
     * @param mixed  $body         Request body (string or null)
     * @param bool   $streaming    Use UNSIGNED-PAYLOAD for streaming
     * @param bool   $isList       Whether this is a list request (query params in URL)
     * @return array Signed headers
     */
    private function signRequest($method, $bucket, $objectKey, $extraHeaders = [], $body = null, $streaming = false, $isList = false) {
        $now       = new \DateTime('UTC');
        $datestamp = $now->format('Ymd');
        $amzDate   = $now->format('Ymd\THis\Z');
        $host      = $this->getHost($bucket);

        // Content hash
        if ($streaming) {
            $payloadHash = 'UNSIGNED-PAYLOAD';
        } else {
            $payloadHash = hash('sha256', $body ?? '');
        }

        // Build headers to sign
        $headers = array_merge([
            'Host'                 => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date'          => $amzDate,
        ], $extraHeaders);

        // Canonical URI
        $canonicalUri = '/' . $objectKey;

        // Canonical query string
        $canonicalQueryString = '';
        if ($isList) {
            $qp = [];
            if (isset($extraHeaders['list-type'])) {
                $qp['list-type'] = $extraHeaders['list-type'];
                unset($headers['list-type']);
            }
            if (isset($extraHeaders['max-keys'])) {
                $qp['max-keys'] = $extraHeaders['max-keys'];
                unset($headers['max-keys']);
            }
            ksort($qp);
            $canonicalQueryString = http_build_query($qp, '', '&', PHP_QUERY_RFC3986);
        }

        // Build canonical headers and signed headers list
        $signedHeaders = [];
        $canonicalHeaderStr = '';
        $lowerHeaders = [];
        foreach ($headers as $k => $v) {
            $lowerHeaders[strtolower($k)] = trim($v);
        }
        ksort($lowerHeaders);
        foreach ($lowerHeaders as $k => $v) {
            $canonicalHeaderStr .= "$k:$v\n";
            $signedHeaders[] = $k;
        }
        $signedHeadersStr = implode(';', $signedHeaders);

        // Canonical request
        $canonicalRequest = implode("\n", [
            $method,
            $this->uriEncodePath($canonicalUri),
            $canonicalQueryString,
            $canonicalHeaderStr,
            $signedHeadersStr,
            $payloadHash,
        ]);

        // String to sign
        $scope = "$datestamp/{$this->region}/s3/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        // Signing key
        $signingKey = $this->getSigningKey($datestamp);
        $signature  = hash_hmac('sha256', $stringToSign, $signingKey);

        // Authorization header
        $headers['Authorization'] = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->accessKey,
            $scope,
            $signedHeadersStr,
            $signature
        );

        return $headers;
    }

    /**
     * Derive the signing key for AWS Signature V4.
     */
    private function getSigningKey($datestamp) {
        $kDate    = hash_hmac('sha256', $datestamp, 'AWS4' . $this->secretKey, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    /**
     * Get host header value (path-style: endpoint host).
     */
    private function getHost($bucket) {
        $parsed = parse_url($this->endpoint);
        $host = $parsed['host'] ?? '';
        if (!empty($parsed['port']) && $parsed['port'] != 443 && $parsed['port'] != 80) {
            $host .= ':' . $parsed['port'];
        }
        return $host;
    }

    /**
     * Build full URL for an object (path-style).
     */
    private function buildUrl($bucket, $objectKey) {
        $path = '/' . $bucket;
        if (!empty($objectKey)) {
            $path .= '/' . $objectKey;
        }
        return $this->endpoint . $path;
    }

    /**
     * URI-encode a path per S3 spec (encode each segment individually).
     */
    private function uriEncodePath($path) {
        $segments = explode('/', $path);
        $encoded = array_map(function($s) {
            return rawurlencode($s);
        }, $segments);
        return implode('/', $encoded);
    }

    /**
     * Format associative headers array into cURL header array.
     */
    private function formatHeaders($headers) {
        $formatted = [];
        foreach ($headers as $k => $v) {
            $formatted[] = "$k: $v";
        }
        return $formatted;
    }
}
?>
