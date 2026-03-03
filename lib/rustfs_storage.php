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
 * Delete all objects under a prefix in RustFS S3.
 *
 * Lists all objects matching the prefix and deletes them individually.
 * Used to clean up HLS segments and playlists when a video is deleted.
 *
 * @param array  $settings  RustFS settings
 * @param string $prefix    S3 key prefix (e.g. 'Images/videos/athlete/file/hls')
 * @return array ['success'=>bool, 'deleted'=>int, 'message'=>string|null]
 */
function deleteRustFSPrefix($settings, $prefix) {
    if (!isRustFSConfigured($settings)) {
        return ['success' => false, 'deleted' => 0, 'failed' => [], 'message' => 'RustFS is not configured'];
    }

    if (empty($prefix)) {
        return ['success' => false, 'deleted' => 0, 'failed' => [], 'message' => 'Prefix is required'];
    }

    try {
        $listing = listRustFSObjects($settings, $prefix);
        if (!$listing['success']) {
            return ['success' => false, 'deleted' => 0, 'failed' => [], 'message' => 'Failed to list objects: ' . ($listing['message'] ?? '')];
        }

        $deleted = 0;
        $failed = [];
        foreach ($listing['objects'] as $obj) {
            if (deleteFromRustFS($settings, $obj['key'])) {
                $deleted++;
            } else {
                $failed[] = $obj['key'];
                error_log("RustFS prefix delete: failed to delete object " . $obj['key']);
            }
        }

        $message = null;
        if (!empty($failed)) {
            $message = count($failed) . ' object(s) failed to delete';
        }

        return ['success' => true, 'deleted' => $deleted, 'failed' => $failed, 'message' => $message];
    } catch (Exception $e) {
        error_log("RustFS prefix delete error for prefix=$prefix: " . $e->getMessage());
        return ['success' => false, 'deleted' => 0, 'failed' => [], 'message' => $e->getMessage()];
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
            '<AllowedMethod>DELETE</AllowedMethod>' .
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
 * Generate a presigned PUT URL for direct browser-to-RustFS uploads.
 *
 * Delegates to the local PHP presigned URL generator. The companion app
 * is NOT involved in the upload flow — it is only used after the upload
 * completes (e.g. for HLS transcoding).
 *
 * @param PDO         $pdo          Database connection (unused, kept for backward compatibility)
 * @param array       $settings     RustFS settings
 * @param string      $object_key   S3 object key
 * @param string      $content_type MIME type
 * @param int         $expires      URL validity in seconds
 * @param string|null $public_endpoint Optional browser-facing base URL
 * @return array ['success'=>bool, 'url'=>string|null, 'object_key'=>string, 'message'=>string|null]
 */
function generatePresignedUploadUrlViaSdk($pdo, $settings, $object_key, $content_type = 'application/octet-stream', $expires = 3600, $public_endpoint = null) {
    // Presigned URLs are generated locally in PHP — the companion is not involved
    // in the upload flow. Uploads go directly from the browser to RustFS.
    // The companion is only used after upload completes (e.g. HLS transcoding).
    error_log("Presign: generating via local PHP for key=$object_key public_endpoint=" . ($public_endpoint ?? '(none)'));
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

        error_log("RustFS stream upload: starting single PUT for key=$object_key size=$content_length url=$url");

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
        $curl_errno = curl_errno($ch);
        $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        if (!empty($curl_error)) {
            error_log("RustFS stream upload: cURL error [$curl_errno] $curl_error for key=$object_key effective_url=$effective_url total_time={$total_time}s");
            throw new Exception("cURL error [$curl_errno]: $curl_error");
        }

        if ($http_code !== 200 && $http_code !== 201 && $http_code !== 204) {
            error_log("RustFS stream upload: HTTP $http_code for key=$object_key effective_url=$effective_url response=" . substr($response, 0, 500));
            throw new Exception("RustFS stream upload failed. HTTP $http_code. Response: " . substr($response, 0, 500));
        }

        error_log("RustFS stream upload: SUCCESS key=$object_key http=$http_code total_time={$total_time}s");

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

/**
 * Upload a file to RustFS using S3 multipart upload.
 *
 * Per the RustFS SDK documentation, large files (especially video) should use
 * multipart upload (CreateMultipartUpload → UploadPart → CompleteMultipartUpload).
 * This approach is more reliable for large files because:
 * - Each part is uploaded independently and can be retried on failure
 * - S3/RustFS is optimized for receiving data in chunks
 * - Single PUT uploads can timeout or stall for multi-hundred-MB+ files
 *
 * @param array    $settings       RustFS settings
 * @param resource $input_stream   An open readable stream (e.g. fopen('php://input', 'rb'))
 * @param int      $content_length Expected total content length in bytes
 * @param string   $object_key     S3 object key
 * @param string   $content_type   MIME type
 * @param int      $part_size      Size of each part in bytes (default 16MB, minimum 5MB per S3 spec)
 * @return array ['success'=>bool, 'url'=>string|null, 'object_key'=>string, 'message'=>string|null]
 */
function multipartStreamUploadToRustFS($settings, $input_stream, $content_length, $object_key, $content_type = 'application/octet-stream', $part_size = 16777216) {
    if (!isRustFSConfigured($settings)) {
        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => 'RustFS is not configured'];
    }

    // Enforce minimum 5 MB part size per S3 spec
    if ($part_size < 5 * 1024 * 1024) {
        $part_size = 5 * 1024 * 1024;
    }

    $object_key = ltrim($object_key, '/');
    $url_base = getRustFSPublicUrl($settings, $object_key);
    $region = $settings['rustfs_region'] ?? 'us-east-1';
    $access_key = $settings['rustfs_access_key'];
    $secret_key = $settings['rustfs_secret_key'];
    $upload_id = null;

    $parsed = parse_url($url_base);
    $host = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
    $scheme = $parsed['scheme'] ?? 'https';
    $raw_path = $parsed['path'] ?? '/';
    $segments = array_filter(explode('/', $raw_path), 'strlen');
    $path = '/' . implode('/', array_map('rawurlencode', $segments));
    if ($raw_path !== '/' && substr($raw_path, -1) === '/') {
        $path .= '/';
    }

    /**
     * Helper: sign and execute an S3 API request for multipart upload operations.
     */
    $signAndExec = function($method, $query_string, $body, $extra_headers = []) use ($host, $path, $scheme, $region, $access_key, $secret_key) {
        $now = new DateTime('UTC');
        $date_stamp = $now->format('Ymd');
        $amz_date = $now->format('Ymd\THis\Z');

        $payload_hash = is_string($body) ? hash('sha256', $body) : 'UNSIGNED-PAYLOAD';

        $headers_to_sign = array_merge([
            'host' => $host,
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date' => $amz_date,
        ], $extra_headers);
        ksort($headers_to_sign);

        $canonical_headers = '';
        $signed_headers_list = [];
        foreach ($headers_to_sign as $k => $v) {
            $canonical_headers .= strtolower($k) . ':' . trim($v) . "\n";
            $signed_headers_list[] = strtolower($k);
        }
        $signed_headers = implode(';', $signed_headers_list);

        $canonical_request = implode("\n", [
            $method, $path, $query_string, $canonical_headers, $signed_headers, $payload_hash,
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

        $auth = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $access_key, $credential_scope, $signed_headers, $signature
        );

        $url = $scheme . '://' . $host . $path;
        if (!empty($query_string)) {
            $url .= '?' . $query_string;
        }

        $curl_headers = [
            'Host: ' . $host,
            'Authorization: ' . $auth,
            'x-amz-date: ' . $amz_date,
            'x-amz-content-sha256: ' . $payload_hash,
        ];
        foreach ($extra_headers as $k => $v) {
            if (!in_array(strtolower($k), ['host', 'x-amz-content-sha256', 'x-amz-date'])) {
                $curl_headers[] = $k . ': ' . $v;
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        // Include response headers so we can extract ETag
        $resp_headers = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$resp_headers) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $resp_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($header);
        });

        if (is_string($body) && strlen($body) > 0) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);

        return [
            'http_code' => $http_code,
            'body' => $response,
            'error' => $curl_error,
            'errno' => $curl_errno,
            'headers' => $resp_headers,
        ];
    };

    try {
        error_log("RustFS multipart upload: initiating for key=$object_key size=$content_length part_size=$part_size");

        // ── Step 1: CreateMultipartUpload ────────────────────────────────
        $initResult = $signAndExec('POST', 'uploads=', '', ['content-type' => $content_type]);
        if ($initResult['http_code'] !== 200) {
            throw new Exception("CreateMultipartUpload failed: HTTP {$initResult['http_code']} body=" . substr($initResult['body'], 0, 500));
        }

        // Parse UploadId from XML response
        if (preg_match('/<UploadId>([^<]+)<\/UploadId>/', $initResult['body'], $m)) {
            $upload_id = $m[1];
        } else {
            throw new Exception("CreateMultipartUpload: could not parse UploadId from response: " . substr($initResult['body'], 0, 500));
        }
        error_log("RustFS multipart upload: initiated upload_id=$upload_id for key=$object_key");

        // ── Step 2: UploadPart (stream parts from input) ─────────────────
        $parts = [];
        $part_number = 0;
        $total_uploaded = 0;

        while ($total_uploaded < $content_length) {
            $part_number++;
            $remaining = $content_length - $total_uploaded;
            $this_part_size = min($part_size, $remaining);

            // Read the part data from the input stream
            $part_data = '';
            $bytes_to_read = $this_part_size;
            while ($bytes_to_read > 0 && !feof($input_stream)) {
                $chunk = fread($input_stream, min($bytes_to_read, 1048576)); // Read in 1MB chunks
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $part_data .= $chunk;
                $bytes_to_read -= strlen($chunk);
            }

            $actual_size = strlen($part_data);
            if ($actual_size === 0) {
                break; // No more data from input stream
            }

            $qs = 'partNumber=' . $part_number . '&uploadId=' . rawurlencode($upload_id);
            $partResult = $signAndExec('PUT', $qs, $part_data, [
                'content-length' => (string)$actual_size,
            ]);

            if ($partResult['http_code'] !== 200 && $partResult['http_code'] !== 204) {
                throw new Exception("UploadPart #$part_number failed: HTTP {$partResult['http_code']} body=" . substr($partResult['body'], 0, 300));
            }

            // Extract ETag from response headers
            $etag = $partResult['headers']['etag'] ?? '';
            if (empty($etag)) {
                // Try to find ETag in body (some implementations)
                if (preg_match('/<ETag>([^<]+)<\/ETag>/', $partResult['body'], $em)) {
                    $etag = $em[1];
                }
            }
            $etag = trim($etag, '"');

            $parts[] = ['PartNumber' => $part_number, 'ETag' => $etag];
            $total_uploaded += $actual_size;

            error_log("RustFS multipart upload: part #$part_number uploaded ({$actual_size} bytes, etag=$etag) total={$total_uploaded}/{$content_length}");

            // Free memory
            unset($part_data);
        }

        if (empty($parts)) {
            throw new Exception("No parts were uploaded — input stream may be empty");
        }

        // ── Step 3: CompleteMultipartUpload ──────────────────────────────
        $complete_xml = '<CompleteMultipartUpload>';
        foreach ($parts as $p) {
            $complete_xml .= '<Part><PartNumber>' . $p['PartNumber'] . '</PartNumber><ETag>"' . htmlspecialchars($p['ETag']) . '"</ETag></Part>';
        }
        $complete_xml .= '</CompleteMultipartUpload>';

        $completeResult = $signAndExec('POST', 'uploadId=' . rawurlencode($upload_id), $complete_xml, [
            'content-type' => 'application/xml',
            'content-length' => (string)strlen($complete_xml),
        ]);

        if ($completeResult['http_code'] !== 200) {
            throw new Exception("CompleteMultipartUpload failed: HTTP {$completeResult['http_code']} body=" . substr($completeResult['body'], 0, 500));
        }

        // Verify the completion response contains a Location or ETag
        if (strpos($completeResult['body'], '<Error>') !== false) {
            throw new Exception("CompleteMultipartUpload returned error: " . substr($completeResult['body'], 0, 500));
        }

        error_log("RustFS multipart upload: COMPLETE key=$object_key parts=$part_number total_uploaded=$total_uploaded upload_id=$upload_id");

        return [
            'success' => true,
            'url' => $url_base,
            'object_key' => $object_key,
            'message' => null,
        ];
    } catch (Exception $e) {
        error_log("RustFS multipart upload error: " . $e->getMessage() . " key=$object_key upload_id=" . ($upload_id ?? 'none'));

        // Abort the multipart upload on failure to clean up partial parts
        if (!empty($upload_id)) {
            try {
                $abortResult = $signAndExec('DELETE', 'uploadId=' . rawurlencode($upload_id), '');
                error_log("RustFS multipart upload: aborted upload_id=$upload_id http={$abortResult['http_code']}");
            } catch (Exception $abortEx) {
                error_log("RustFS multipart upload: abort failed for upload_id=$upload_id: " . $abortEx->getMessage());
            }
        }

        return ['success' => false, 'url' => null, 'object_key' => $object_key, 'message' => $e->getMessage()];
    }
}
