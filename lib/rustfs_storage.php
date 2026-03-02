<?php
/**
 * RustFS S3-Compatible Storage Library
 * Provides functions for uploading, downloading, and managing files in RustFS
 * using the S3-compatible REST API with AWS Signature V4 authentication.
 */

require_once __DIR__ . '/../db_config.php';

/**
 * Get RustFS settings from database
 *
 * @param PDO $pdo Database connection
 * @return array RustFS settings
 */
function getRustFSSettings($pdo) {
    $keys = [
        'rustfs_endpoint',
        'rustfs_public_endpoint',
        'rustfs_access_key',
        'rustfs_secret_key',
        'rustfs_bucket',
        'rustfs_region',
        'rustfs_use_ssl',
        'rustfs_path_style',
    ];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Decrypt secret key if encrypted
    if (!empty($settings['rustfs_secret_key']) && function_exists('decryptPassword')) {
        $decrypted = decryptPassword($settings['rustfs_secret_key']);
        if (!empty($decrypted)) {
            $settings['rustfs_secret_key'] = $decrypted;
        }
    }

    return $settings;
}

/**
 * Check if RustFS is configured
 *
 * @param array $settings RustFS settings
 * @return bool
 */
function isRustFSConfigured($settings) {
    return !empty($settings['rustfs_endpoint'])
        && !empty($settings['rustfs_access_key'])
        && !empty($settings['rustfs_secret_key'])
        && !empty($settings['rustfs_bucket']);
}

/**
 * Build the base URL for the RustFS bucket.
 *
 * @param array $settings RustFS settings
 * @return string Base URL (e.g., https://rustfs.example.com/bucket)
 */
function getRustFSBaseUrl($settings) {
    $endpoint = rtrim($settings['rustfs_endpoint'], '/');
    $bucket = $settings['rustfs_bucket'];
    $use_ssl = ($settings['rustfs_use_ssl'] ?? '1') === '1';
    $path_style = ($settings['rustfs_path_style'] ?? '1') === '1';

    // Ensure endpoint has a scheme
    if (strpos($endpoint, 'http://') !== 0 && strpos($endpoint, 'https://') !== 0) {
        $endpoint = ($use_ssl ? 'https://' : 'http://') . $endpoint;
    }

    if ($path_style) {
        return $endpoint . '/' . $bucket;
    }
    // Virtual-hosted style: insert bucket before the host
    $parsed = parse_url($endpoint);
    $scheme = $parsed['scheme'] ?? ($use_ssl ? 'https' : 'http');
    $host = $parsed['host'] ?? $endpoint;
    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
    return $scheme . '://' . $bucket . '.' . $host . $port;
}

/**
 * Generate the public URL for an object stored in RustFS.
 *
 * @param array  $settings   RustFS settings
 * @param string $object_key S3 object key (e.g., 'Images/profiles/avatar_1.jpg')
 * @return string Public URL
 */
function getRustFSPublicUrl($settings, $object_key) {
    $base = getRustFSBaseUrl($settings);
    return $base . '/' . ltrim($object_key, '/');
}

/**
 * Sign a request using AWS Signature Version 4
 *
 * @param string $method      HTTP method (GET, PUT, DELETE, HEAD)
 * @param string $url         Full request URL
 * @param array  $headers     Request headers (key => value)
 * @param string $payload     Request body (or empty string)
 * @param string $access_key  AWS/S3 access key
 * @param string $secret_key  AWS/S3 secret key
 * @param string $region      AWS region (default: us-east-1)
 * @param string $service     Service name (default: s3)
 * @return array Signed headers to include in the request
 */
function signRustFSRequest($method, $url, $headers, $payload, $access_key, $secret_key, $region = 'us-east-1', $service = 's3') {
    $parsed = parse_url($url);
    $host = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
    $query = $parsed['query'] ?? '';

    // URI-encode each path segment per AWS Signature V4 spec.
    // Forward slashes between segments are preserved unencoded.
    $raw_path = $parsed['path'] ?? '/';
    $segments = array_filter(explode('/', $raw_path), 'strlen');
    $path = '/' . implode('/', array_map('rawurlencode', $segments));
    // Preserve trailing slash — S3 distinguishes /bucket/ from /bucket
    if ($raw_path !== '/' && substr($raw_path, -1) === '/') {
        $path .= '/';
    }

    $now = new DateTime('UTC');
    $date_stamp = $now->format('Ymd');
    $amz_date = $now->format('Ymd\THis\Z');

    // Add required headers
    $headers['host'] = $host;
    $headers['x-amz-date'] = $amz_date;
    $headers['x-amz-content-sha256'] = hash('sha256', $payload);

    // Sort headers by lowercase key
    $sorted_headers = [];
    foreach ($headers as $k => $v) {
        $sorted_headers[strtolower(trim($k))] = trim($v);
    }
    ksort($sorted_headers);

    // Create canonical headers and signed headers
    $canonical_headers = '';
    $signed_headers_list = [];
    foreach ($sorted_headers as $k => $v) {
        $canonical_headers .= $k . ':' . $v . "\n";
        $signed_headers_list[] = $k;
    }
    $signed_headers = implode(';', $signed_headers_list);

    // Parse query string parameters
    $canonical_querystring = '';
    if (!empty($query)) {
        parse_str($query, $query_params);
        ksort($query_params);
        $canonical_querystring = http_build_query($query_params, '', '&', PHP_QUERY_RFC3986);
    }

    // Canonical request
    $payload_hash = hash('sha256', $payload);
    $canonical_request = implode("\n", [
        $method,
        $path,
        $canonical_querystring,
        $canonical_headers,
        $signed_headers,
        $payload_hash,
    ]);

    // String to sign
    $credential_scope = $date_stamp . '/' . $region . '/' . $service . '/aws4_request';
    $string_to_sign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $amz_date,
        $credential_scope,
        hash('sha256', $canonical_request),
    ]);

    // Signing key
    $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret_key, true);
    $k_region = hash_hmac('sha256', $region, $k_date, true);
    $k_service = hash_hmac('sha256', $service, $k_region, true);
    $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);

    // Signature
    $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

    // Authorization header
    $auth_header = sprintf(
        'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
        $access_key,
        $credential_scope,
        $signed_headers,
        $signature
    );

    return [
        'Authorization' => $auth_header,
        'x-amz-date' => $amz_date,
        'x-amz-content-sha256' => $payload_hash,
    ];
}

/**
 * Upload a file to RustFS S3 storage from a local path.
 *
 * @param array  $settings    RustFS settings
 * @param string $local_path  Absolute path to the local file
 * @param string $object_key  S3 object key (e.g., 'Images/profiles/avatar_1.jpg')
 * @param string $content_type MIME type (auto-detected if empty)
 * @return array ['success'=>bool, 'url'=>string|null, 'object_key'=>string, 'message'=>string|null]
 */
function uploadToRustFS($settings, $local_path, $object_key, $content_type = '') {
    if (!isRustFSConfigured($settings)) {
        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => 'RustFS is not configured'];
    }

    try {
        $file_content = file_get_contents($local_path);
        if ($file_content === false) {
            throw new Exception("Failed to read file: $local_path");
        }

        if (empty($content_type)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $content_type = finfo_file($finfo, $local_path);
            finfo_close($finfo);
        }

        return uploadContentToRustFS($settings, $file_content, $object_key, $content_type);
    } catch (Exception $e) {
        error_log("RustFS upload error: " . $e->getMessage());
        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => $e->getMessage()];
    }
}

/**
 * Upload raw content (string) to RustFS S3 storage.
 *
 * @param array  $settings     RustFS settings
 * @param string $content      File content as a string
 * @param string $object_key   S3 object key
 * @param string $content_type MIME type
 * @return array ['success'=>bool, 'url'=>string|null, 'object_key'=>string, 'message'=>string|null]
 */
function uploadContentToRustFS($settings, $content, $object_key, $content_type = 'application/octet-stream') {
    if (!isRustFSConfigured($settings)) {
        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => 'RustFS is not configured'];
    }

    try {
        $object_key = ltrim($object_key, '/');
        $url = getRustFSPublicUrl($settings, $object_key);

        $region = $settings['rustfs_region'] ?? 'us-east-1';
        $access_key = $settings['rustfs_access_key'];
        $secret_key = $settings['rustfs_secret_key'];

        $headers = [
            'Content-Type' => $content_type,
            'Content-Length' => (string)strlen($content),
        ];

        $signed = signRustFSRequest('PUT', $url, $headers, $content, $access_key, $secret_key, $region);

        $curl_headers = [
            'Content-Type: ' . $content_type,
            'Content-Length: ' . strlen($content),
            'Authorization: ' . $signed['Authorization'],
            'x-amz-date: ' . $signed['x-amz-date'],
            'x-amz-content-sha256: ' . $signed['x-amz-content-sha256'],
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if (!empty($curl_error)) {
            throw new Exception("cURL error: $curl_error");
        }

        if ($http_code !== 200 && $http_code !== 201 && $http_code !== 204) {
            throw new Exception("RustFS upload failed. HTTP $http_code. Response: " . substr($response, 0, 500));
        }

        return [
            'success' => true,
            'url' => $url,
            'object_key' => $object_key,
            'message' => null,
        ];
    } catch (Exception $e) {
        error_log("RustFS upload error: " . $e->getMessage());
        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => $e->getMessage()];
    }
}

/**
 * Upload a large file to RustFS using streaming (for videos and large files).
 * Uses CURLOPT_INFILE to avoid loading the full file into memory.
 *
 * @param array  $settings    RustFS settings
 * @param string $local_path  Absolute path to the local file
 * @param string $object_key  S3 object key
 * @param string $content_type MIME type (auto-detected if empty)
 * @return array ['success'=>bool, 'url'=>string|null, 'object_key'=>string, 'message'=>string|null]
 */
function uploadLargeFileToRustFS($settings, $local_path, $object_key, $content_type = '') {
    if (!isRustFSConfigured($settings)) {
        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => 'RustFS is not configured'];
    }

    try {
        $object_key = ltrim($object_key, '/');
        $file_size = filesize($local_path);

        if (empty($content_type)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $content_type = finfo_file($finfo, $local_path);
            finfo_close($finfo);
        }

        $url = getRustFSPublicUrl($settings, $object_key);
        $region = $settings['rustfs_region'] ?? 'us-east-1';
        $access_key = $settings['rustfs_access_key'];
        $secret_key = $settings['rustfs_secret_key'];

        // For streaming uploads, use UNSIGNED-PAYLOAD as content hash
        $payload_hash = 'UNSIGNED-PAYLOAD';
        $parsed = parse_url($url);
        $host = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');

        // URI-encode each path segment per AWS Signature V4 spec
        $raw_path = $parsed['path'] ?? '/';
        $segments = array_filter(explode('/', $raw_path), 'strlen');
        $path = '/' . implode('/', array_map('rawurlencode', $segments));
        if ($raw_path !== '/' && substr($raw_path, -1) === '/') {
            $path .= '/';
        }

        $now = new DateTime('UTC');
        $date_stamp = $now->format('Ymd');
        $amz_date = $now->format('Ymd\THis\Z');

        $headers_to_sign = [
            'content-length' => (string)$file_size,
            'content-type' => $content_type,
            'host' => $host,
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date' => $amz_date,
        ];
        ksort($headers_to_sign);

        $canonical_headers = '';
        $signed_headers_list = [];
        foreach ($headers_to_sign as $k => $v) {
            $canonical_headers .= $k . ':' . $v . "\n";
            $signed_headers_list[] = $k;
        }
        $signed_headers = implode(';', $signed_headers_list);

        $canonical_request = implode("\n", [
            'PUT', $path, '', $canonical_headers, $signed_headers, $payload_hash,
        ]);

        $credential_scope = $date_stamp . '/' . $region . '/s3/aws4_request';
        $string_to_sign = implode("\n", [
            'AWS4-HMAC-SHA256', $amz_date, $credential_scope, hash('sha256', $canonical_request),
        ]);

        $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret_key, true);
        $k_region = hash_hmac('sha256', $region, $k_date, true);
        $k_service = hash_hmac('sha256', 's3', $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

        $auth_header = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $access_key, $credential_scope, $signed_headers, $signature
        );

        $fh = fopen($local_path, 'rb');
        if ($fh === false) {
            throw new Exception("Failed to open file for streaming: $local_path");
        }

        $curl_headers = [
            'Content-Type: ' . $content_type,
            'Content-Length: ' . $file_size,
            'Host: ' . $host,
            'Authorization: ' . $auth_header,
            'x-amz-date: ' . $amz_date,
            'x-amz-content-sha256: ' . $payload_hash,
            'Expect: ',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, $file_size);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if (!empty($curl_error)) {
            throw new Exception("cURL error: $curl_error");
        }

        if ($http_code !== 200 && $http_code !== 201 && $http_code !== 204) {
            throw new Exception("RustFS streaming upload failed. HTTP $http_code. Response: " . substr($response, 0, 500));
        }

        return [
            'success' => true,
            'url' => $url,
            'object_key' => $object_key,
            'file_size' => $file_size,
            'message' => null,
        ];
    } catch (Exception $e) {
        error_log("RustFS large file upload error: " . $e->getMessage());
        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => $e->getMessage()];
    }
}

/**
 * Download a file from RustFS S3 storage.
 *
 * @param array  $settings   RustFS settings
 * @param string $object_key S3 object key
 * @return string|false File content or false on failure
 */
function downloadFromRustFS($settings, $object_key) {
    if (!isRustFSConfigured($settings)) {
        return false;
    }

    try {
        $object_key = ltrim($object_key, '/');
        $url = getRustFSPublicUrl($settings, $object_key);
        $region = $settings['rustfs_region'] ?? 'us-east-1';

        $signed = signRustFSRequest('GET', $url, [], '', $settings['rustfs_access_key'], $settings['rustfs_secret_key'], $region);

        $curl_headers = [
            'Authorization: ' . $signed['Authorization'],
            'x-amz-date: ' . $signed['x-amz-date'],
            'x-amz-content-sha256: ' . $signed['x-amz-content-sha256'],
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            error_log("RustFS download failed for $object_key. HTTP $http_code");
            return false;
        }

        return $response;
    } catch (Exception $e) {
        error_log("RustFS download error: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a file from RustFS S3 storage.
 *
 * @param array  $settings   RustFS settings
 * @param string $object_key S3 object key
 * @return bool True on success
 */
function deleteFromRustFS($settings, $object_key) {
    if (!isRustFSConfigured($settings)) {
        return false;
    }

    try {
        $object_key = ltrim($object_key, '/');
        $url = getRustFSPublicUrl($settings, $object_key);
        $region = $settings['rustfs_region'] ?? 'us-east-1';

        $signed = signRustFSRequest('DELETE', $url, [], '', $settings['rustfs_access_key'], $settings['rustfs_secret_key'], $region);

        $curl_headers = [
            'Authorization: ' . $signed['Authorization'],
            'x-amz-date: ' . $signed['x-amz-date'],
            'x-amz-content-sha256: ' . $signed['x-amz-content-sha256'],
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($http_code === 204 || $http_code === 200);
    } catch (Exception $e) {
        error_log("RustFS delete error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if an object exists in RustFS S3.
 *
 * Retries up to 3 times with a 1-second delay to handle eventual
 * consistency and transient network issues that can occur immediately
 * after an upload completes.
 *
 * @param array  $settings   RustFS settings
 * @param string $object_key S3 object key
 * @return bool
 */
function rustfsObjectExists($settings, $object_key) {
    if (!isRustFSConfigured($settings)) {
        return false;
    }

    $object_key = ltrim($object_key, '/');
    $max_retries = 3;

    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        try {
            $url = getRustFSPublicUrl($settings, $object_key);
            $region = $settings['rustfs_region'] ?? 'us-east-1';

            $signed = signRustFSRequest('HEAD', $url, [], '', $settings['rustfs_access_key'], $settings['rustfs_secret_key'], $region);

            $curl_headers = [
                'Authorization: ' . $signed['Authorization'],
                'x-amz-date: ' . $signed['x-amz-date'],
                'x-amz-content-sha256: ' . $signed['x-amz-content-sha256'],
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($http_code === 200) {
                return true;
            }

            error_log("rustfsObjectExists: attempt $attempt/$max_retries for '$object_key' returned HTTP $http_code" . (!empty($curl_error) ? " (curl: $curl_error)" : ''));
        } catch (Exception $e) {
            error_log("rustfsObjectExists: attempt $attempt/$max_retries for '$object_key' exception: " . $e->getMessage());
        }

        if ($attempt < $max_retries) {
            sleep(1);
        }
    }

    return false;
}

/**
 * Test connection to RustFS by listing the bucket (HEAD on bucket).
 *
 * @param array $settings RustFS settings
 * @return array ['success'=>bool, 'message'=>string]
 */
function testRustFSConnection($settings) {
    if (!isRustFSConfigured($settings)) {
        return ['success' => false, 'message' => 'RustFS settings are incomplete. Please provide endpoint, access key, secret key, and bucket.'];
    }

    try {
        $base_url = getRustFSBaseUrl($settings);
        $region = $settings['rustfs_region'] ?? 'us-east-1';

        // HEAD request on the bucket root
        $signed = signRustFSRequest('HEAD', $base_url . '/', [], '', $settings['rustfs_access_key'], $settings['rustfs_secret_key'], $region);

        $curl_headers = [
            'Authorization: ' . $signed['Authorization'],
            'x-amz-date: ' . $signed['x-amz-date'],
            'x-amz-content-sha256: ' . $signed['x-amz-content-sha256'],
        ];

        $ch = curl_init($base_url . '/');
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if (!empty($curl_error)) {
            return ['success' => false, 'message' => 'Connection failed: ' . $curl_error];
        }

        if ($http_code === 200 || $http_code === 301 || $http_code === 404) {
            // 200 = bucket exists, 301 = redirect (region mismatch but reachable), 404 = bucket doesn't exist
            if ($http_code === 404) {
                return ['success' => false, 'message' => 'Connected to RustFS server but bucket "' . $settings['rustfs_bucket'] . '" was not found. Please create the bucket first.'];
            }
            return ['success' => true, 'message' => 'Successfully connected to RustFS. Bucket "' . $settings['rustfs_bucket'] . '" is accessible.'];
        }

        if ($http_code === 403) {
            return ['success' => false, 'message' => 'Authentication failed. Check your access key and secret key. HTTP 403.'];
        }

        return ['success' => false, 'message' => 'Unexpected response from RustFS. HTTP ' . $http_code];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Connection error: ' . $e->getMessage()];
    }
}

/**
 * List objects in a RustFS bucket with optional prefix.
 *
 * @param array  $settings RustFS settings
 * @param string $prefix   Object key prefix to filter by
 * @param int    $max_keys Maximum number of keys to return
 * @return array ['success'=>bool, 'objects'=>array, 'message'=>string|null]
 */
function listRustFSObjects($settings, $prefix = '', $max_keys = 1000) {
    if (!isRustFSConfigured($settings)) {
        return ['success' => false, 'objects' => [], 'message' => 'RustFS is not configured'];
    }

    try {
        $base_url = getRustFSBaseUrl($settings);
        $query_params = ['list-type' => '2', 'max-keys' => $max_keys];
        if (!empty($prefix)) {
            $query_params['prefix'] = $prefix;
        }
        $url = $base_url . '/?' . http_build_query($query_params);

        $region = $settings['rustfs_region'] ?? 'us-east-1';
        $signed = signRustFSRequest('GET', $url, [], '', $settings['rustfs_access_key'], $settings['rustfs_secret_key'], $region);

        $curl_headers = [
            'Authorization: ' . $signed['Authorization'],
            'x-amz-date: ' . $signed['x-amz-date'],
            'x-amz-content-sha256: ' . $signed['x-amz-content-sha256'],
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("Failed to list objects. HTTP $http_code");
        }

        $objects = [];
        $xml = @simplexml_load_string($response);
        if ($xml !== false) {
            foreach ($xml->Contents as $item) {
                $objects[] = [
                    'key' => (string)$item->Key,
                    'size' => (int)$item->Size,
                    'last_modified' => (string)$item->LastModified,
                ];
            }
        }

        return ['success' => true, 'objects' => $objects, 'message' => null];
    } catch (Exception $e) {
        return ['success' => false, 'objects' => [], 'message' => $e->getMessage()];
    }
}

/**
 * Ensure the RustFS bucket has a CORS policy that allows direct browser uploads.
 *
 * Without CORS headers the browser blocks cross-origin PUT requests from XHR
 * (the presigned-URL flow).  This function uses the S3 PutBucketCors API which
 * all S3-compatible services (MinIO, RustFS, etc.) support.
 *
 * The policy allows PUT/POST/GET from any origin — safe because each upload
 * is authorised by its own presigned URL signature.
 *
 * The result is cached in a static flag so it runs at most once per PHP request.
 *
 * @param array $settings  RustFS settings
 * @return array ['success'=>bool, 'message'=>string|null]
 */
function ensureRustFSBucketCors($settings) {
    static $done = false;
    if ($done) return ['success' => true, 'message' => 'already applied'];

    if (!isRustFSConfigured($settings)) {
        return ['success' => false, 'message' => 'RustFS is not configured'];
    }

    try {
        $endpoint = rtrim($settings['rustfs_endpoint'], '/');
        $bucket   = $settings['rustfs_bucket'];
        $region   = $settings['rustfs_region'] ?? 'us-east-1';
        $access_key = $settings['rustfs_access_key'];
        $secret_key = $settings['rustfs_secret_key'];
        $use_ssl    = ($settings['rustfs_use_ssl'] ?? '1') === '1';
        $path_style = ($settings['rustfs_path_style'] ?? '1') === '1';

        // Ensure scheme
        if (strpos($endpoint, 'http://') !== 0 && strpos($endpoint, 'https://') !== 0) {
            $endpoint = ($use_ssl ? 'https://' : 'http://') . $endpoint;
        }

        // Build the bucket-level URL
        if ($path_style) {
            $url = $endpoint . '/' . $bucket . '/?cors';
        } else {
            $parsed = parse_url($endpoint);
            $scheme = $parsed['scheme'] ?? ($use_ssl ? 'https' : 'http');
            $host   = $parsed['host'] ?? $endpoint;
            $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $url    = $scheme . '://' . $bucket . '.' . $host . $port . '/?cors';
        }

        // S3 CORS XML payload
        $cors_xml = '<?xml version="1.0" encoding="UTF-8"?>' .
            '<CORSConfiguration>' .
            '<CORSRule>' .
            '<AllowedOrigin>*</AllowedOrigin>' .
            '<AllowedMethod>GET</AllowedMethod>' .
            '<AllowedMethod>PUT</AllowedMethod>' .
            '<AllowedMethod>POST</AllowedMethod>' .
            '<AllowedMethod>HEAD</AllowedMethod>' .
            '<AllowedHeader>*</AllowedHeader>' .
            '<ExposeHeader>ETag</ExposeHeader>' .
            '<MaxAgeSeconds>3600</MaxAgeSeconds>' .
            '</CORSRule>' .
            '</CORSConfiguration>';

        // Sign and send the PutBucketCors request
        $headers = [
            'Content-Type' => 'application/xml',
        ];
        $signed = signRustFSRequest('PUT', $url, $headers, $cors_xml,
                                     $access_key, $secret_key, $region);

        $curl_headers = [
            'Content-Type: application/xml',
            'Authorization: ' . $signed['Authorization'],
            'x-amz-date: ' . $signed['x-amz-date'],
            'x-amz-content-sha256: ' . $signed['x-amz-content-sha256'],
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cors_xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if (!empty($curl_error)) {
            error_log("RustFS CORS set error (curl): $curl_error");
            return ['success' => false, 'message' => "cURL error: $curl_error"];
        }

        if ($http_code >= 200 && $http_code < 300) {
            $done = true;
            return ['success' => true, 'message' => null];
        }

        error_log("RustFS CORS set error: HTTP $http_code — " . substr($response, 0, 500));
        return ['success' => false, 'message' => "HTTP $http_code — " . substr($response, 0, 200)];
    } catch (Exception $e) {
        error_log("RustFS CORS set exception: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Generate a presigned PUT URL for direct browser-to-RustFS uploads.
 * Uses AWS Signature V4 query-string authentication so the client can
 * PUT a file directly to S3-compatible storage without knowing the secret key.
 *
 * @param array       $settings        RustFS settings
 * @param string      $object_key      S3 object key (e.g., 'Images/videos/athlete/video_123.mp4')
 * @param string      $content_type    MIME type the client will send (e.g., 'video/mp4')
 * @param int         $expires         URL validity in seconds (default: 3600)
 * @param string|null $public_endpoint Optional browser-facing base URL (e.g., 'https://tnode1.example.com').
 *                                     When set, the presigned URL uses this host/scheme instead of the
 *                                     internal endpoint so uploads work through a reverse proxy (HAProxy).
 * @return array ['success'=>bool, 'url'=>string|null, 'object_key'=>string, 'message'=>string|null]
 */
function generatePresignedUploadUrl($settings, $object_key, $content_type = 'application/octet-stream', $expires = 3600, $public_endpoint = null) {
    if (!isRustFSConfigured($settings)) {
        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => 'RustFS is not configured'];
    }

    try {
        // Ensure CORS is configured on the bucket so the browser can PUT directly
        ensureRustFSBucketCors($settings);

        $object_key = ltrim($object_key, '/');
        $url = getRustFSPublicUrl($settings, $object_key);
        $parsed = parse_url($url);

        // URI-encode each path segment per S3 Signature V4 spec.
        // Forward slashes between segments are preserved unencoded.
        $raw_path = $parsed['path'] ?? '/';
        $segments = array_filter(explode('/', $raw_path), 'strlen');
        $path = '/' . implode('/', array_map('rawurlencode', $segments));
        if ($raw_path !== '/' && substr($raw_path, -1) === '/') {
            $path .= '/';
        }

        // When a public endpoint is provided (browser-facing URL behind HAProxy),
        // use its scheme/host/port so the presigned URL is reachable from the browser.
        if (!empty($public_endpoint)) {
            $pub = parse_url(rtrim($public_endpoint, '/'));
            $scheme = $pub['scheme'] ?? 'https';
            $host = $pub['host'] . (isset($pub['port']) ? ':' . $pub['port'] : '');
        } else {
            $host = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
            $scheme = $parsed['scheme'] ?? 'https';
        }

        $region = $settings['rustfs_region'] ?? 'us-east-1';
        $access_key = $settings['rustfs_access_key'];
        $secret_key = $settings['rustfs_secret_key'];

        $now = new DateTime('UTC');
        $date_stamp = $now->format('Ymd');
        $amz_date = $now->format('Ymd\THis\Z');

        $credential_scope = $date_stamp . '/' . $region . '/s3/aws4_request';
        $credential = $access_key . '/' . $credential_scope;

        // Canonical query string parameters (sorted alphabetically)
        // Only sign 'host' — this matches the AWS SDK behaviour for
        // presigned PUT URLs and avoids SignatureDoesNotMatch errors
        // on RustFS / MinIO when the browser's Content-Type header
        // differs even slightly from the value used at signing time
        // (see https://github.com/rustfs/rustfs/issues/700).
        $query_params = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $credential,
            'X-Amz-Date'          => $amz_date,
            'X-Amz-Expires'       => (string)$expires,
            'X-Amz-SignedHeaders'  => 'host',
        ];
        ksort($query_params);
        $canonical_querystring = http_build_query($query_params, '', '&', PHP_QUERY_RFC3986);

        // Canonical headers — only 'host' is signed for presigned URLs
        $canonical_headers = 'host:' . $host . "\n";
        $signed_headers = 'host';

        // For presigned URLs the payload hash is always UNSIGNED-PAYLOAD
        $payload_hash = 'UNSIGNED-PAYLOAD';

        $canonical_request = implode("\n", [
            'PUT',
            $path,
            $canonical_querystring,
            $canonical_headers,
            $signed_headers,
            $payload_hash,
        ]);

        $string_to_sign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amz_date,
            $credential_scope,
            hash('sha256', $canonical_request),
        ]);

        // Derive signing key
        $k_date    = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret_key, true);
        $k_region  = hash_hmac('sha256', $region, $k_date, true);
        $k_service = hash_hmac('sha256', 's3', $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);

        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

        // Build the final presigned URL
        $presigned_url = $scheme . '://' . $host . $path
            . '?' . $canonical_querystring
            . '&X-Amz-Signature=' . $signature;

        return [
            'success'    => true,
            'url'        => $presigned_url,
            'object_key' => $object_key,
            'message'    => null,
        ];
    } catch (Exception $e) {
        error_log("RustFS presigned URL error: " . $e->getMessage());
        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => $e->getMessage()];
    }
}

/**
 * Generate a presigned PUT URL via the companion server's boto3 SDK.
 *
 * The companion uses boto3.generate_presigned_url() which is the official
 * AWS-SDK approach recommended by the RustFS documentation.  This avoids
 * subtle signature issues that can occur with hand-rolled Sig V4 code.
 *
 * Falls back to the local PHP implementation when the companion is
 * unavailable or not configured.
 *
 * @param PDO         $pdo          Database connection (to read companion settings)
 * @param array       $settings     RustFS settings
 * @param string      $object_key   S3 object key
 * @param string      $content_type MIME type
 * @param int         $expires      URL validity in seconds
 * @param string|null $public_endpoint Optional browser-facing base URL
 * @return array ['success'=>bool, 'url'=>string|null, 'object_key'=>string, 'message'=>string|null]
 */
function generatePresignedUploadUrlViaSdk($pdo, $settings, $object_key, $content_type = 'application/octet-stream', $expires = 3600, $public_endpoint = null) {
    // Try the companion server's SDK-based presign endpoint first
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('gameplan_companion_url', 'gameplan_companion_api_key')");
        $stmt->execute();
        $companion = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $companion[$row['setting_key']] = $row['setting_value'] ?? '';
        }

        $companion_url = $companion['gameplan_companion_url'] ?? '';
        $companion_key = $companion['gameplan_companion_api_key'] ?? '';

        if (!empty($companion_url)) {
            $companion_url = rtrim($companion_url, '/');
            $presign_payload = [
                'object_key'   => ltrim($object_key, '/'),
                'content_type' => $content_type,
                'expires'      => $expires,
            ];
            // Pass the browser-facing public endpoint so the companion generates
            // a presigned URL reachable from the browser (not the internal address).
            if (!empty($public_endpoint)) {
                $presign_payload['public_endpoint'] = $public_endpoint;
            }
            $payload = json_encode($presign_payload);

            $ch = curl_init($companion_url . '/api/presign');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-API-Key: ' . $companion_key,
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200 && $response) {
                $data = json_decode($response, true);
                if (!empty($data['success']) && !empty($data['url'])) {
                    error_log("Presigned URL generated via companion SDK for key=$object_key");
                    return [
                        'success'    => true,
                        'url'        => $data['url'],
                        'object_key' => $data['object_key'] ?? $object_key,
                        'message'    => null,
                    ];
                }
            }
            // Companion returned an error — log it and fall through to local PHP
            error_log("Companion presign returned HTTP $http_code — falling back to local PHP presign");
        }
    } catch (Exception $e) {
        error_log("Companion presign call failed: " . $e->getMessage() . " — falling back to local PHP presign");
    }

    // Fall back to local PHP presigned URL generation
    return generatePresignedUploadUrl($settings, $object_key, $content_type, $expires, $public_endpoint);
}

/**
 * Upload a file to RustFS by streaming from a PHP input stream.
 *
 * This is used as a server-side proxy: the browser PUTs the raw file body
 * to a PHP endpoint, and this function streams it through to RustFS.
 * This avoids CORS issues and the need for the browser to reach RustFS
 * directly.
 *
 * @param array    $settings     RustFS settings
 * @param resource $input_stream An open readable stream (e.g. fopen('php://input', 'rb'))
 * @param int      $content_length  Expected content length in bytes
 * @param string   $object_key  S3 object key
 * @param string   $content_type MIME type
 * @return array ['success'=>bool, 'url'=>string|null, 'object_key'=>string, 'message'=>string|null]
 */
function streamUploadToRustFS($settings, $input_stream, $content_length, $object_key, $content_type = 'application/octet-stream') {
    if (!isRustFSConfigured($settings)) {
        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => 'RustFS is not configured'];
    }

    try {
        $object_key = ltrim($object_key, '/');
        $url = getRustFSPublicUrl($settings, $object_key);
        $region = $settings['rustfs_region'] ?? 'us-east-1';
        $access_key = $settings['rustfs_access_key'];
        $secret_key = $settings['rustfs_secret_key'];

        // For streaming uploads, use UNSIGNED-PAYLOAD as content hash
        $payload_hash = 'UNSIGNED-PAYLOAD';
        $parsed = parse_url($url);
        $host = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');

        // URI-encode each path segment per AWS Signature V4 spec
        $raw_path = $parsed['path'] ?? '/';
        $segments = array_filter(explode('/', $raw_path), 'strlen');
        $path = '/' . implode('/', array_map('rawurlencode', $segments));
        if ($raw_path !== '/' && substr($raw_path, -1) === '/') {
            $path .= '/';
        }

        $now = new DateTime('UTC');
        $date_stamp = $now->format('Ymd');
        $amz_date = $now->format('Ymd\THis\Z');

        $headers_to_sign = [
            'content-length' => (string)$content_length,
            'content-type' => $content_type,
            'host' => $host,
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date' => $amz_date,
        ];
        ksort($headers_to_sign);

        $canonical_headers = '';
        $signed_headers_list = [];
        foreach ($headers_to_sign as $k => $v) {
            $canonical_headers .= $k . ':' . $v . "\n";
            $signed_headers_list[] = $k;
        }
        $signed_headers = implode(';', $signed_headers_list);

        $canonical_request = implode("\n", [
            'PUT', $path, '', $canonical_headers, $signed_headers, $payload_hash,
        ]);

        $credential_scope = $date_stamp . '/' . $region . '/s3/aws4_request';
        $string_to_sign = implode("\n", [
            'AWS4-HMAC-SHA256', $amz_date, $credential_scope, hash('sha256', $canonical_request),
        ]);

        $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret_key, true);
        $k_region = hash_hmac('sha256', $region, $k_date, true);
        $k_service = hash_hmac('sha256', 's3', $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

        $auth_header = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $access_key, $credential_scope, $signed_headers, $signature
        );

        $curl_headers = [
            'Content-Type: ' . $content_type,
            'Content-Length: ' . $content_length,
            'Host: ' . $host,
            'Authorization: ' . $auth_header,
            'x-amz-date: ' . $amz_date,
            'x-amz-content-sha256: ' . $payload_hash,
            'Expect: ',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $input_stream);
        curl_setopt($ch, CURLOPT_INFILESIZE, $content_length);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        // No hard timeout — large files (multi-GB) can take a very long time.
        // Use low-speed limits instead to detect stalled transfers.
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);   // minimum 1 byte/sec
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 120);   // abort after 120 s of stall

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if (!empty($curl_error)) {
            throw new Exception("cURL error: $curl_error");
        }

        if ($http_code !== 200 && $http_code !== 201 && $http_code !== 204) {
            throw new Exception("RustFS stream upload failed. HTTP $http_code. Response: " . substr($response, 0, 500));
        }

        return [
            'success' => true,
            'url' => $url,
            'object_key' => $object_key,
            'message' => null,
        ];
    } catch (Exception $e) {
        error_log("RustFS stream upload error: " . $e->getMessage());
        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => $e->getMessage()];
    }
}
