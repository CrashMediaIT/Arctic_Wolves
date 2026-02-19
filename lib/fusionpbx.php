<?php
/**
 * FusionPBX API Client
 * 
 * Provides functions for connecting to FusionPBX to auto-provision
 * extensions, users, and dial plans during employee onboarding.
 * 
 * FusionPBX is an open-source multi-tenant PBX built on FreeSWITCH.
 * API Documentation: https://docs.fusionpbx.com/en/latest/api/api.html
 * 
 * Features:
 * - Extension creation
 * - User account creation  
 * - Inbound dial plan creation (DID routing)
 * - Outbound dial plan creation
 * - Connection testing
 */

/**
 * Get FusionPBX settings from database
 * 
 * @param PDO $pdo Database connection
 * @return array Settings key-value pairs
 */
function getFusionPBXSettings($pdo) {
    $keys = [
        'fusionpbx_enabled',
        'fusionpbx_url',
        'fusionpbx_api_key',
        'fusionpbx_domain_uuid',
        'fusionpbx_domain',
        'fusionpbx_default_context',
        'fusionpbx_area_code',
        'fusionpbx_default_password_length'
    ];
    
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Defaults
    if (!isset($settings['fusionpbx_default_context'])) {
        $settings['fusionpbx_default_context'] = 'default';
    }
    if (!isset($settings['fusionpbx_default_password_length'])) {
        $settings['fusionpbx_default_password_length'] = '16';
    }
    
    return $settings;
}

/**
 * Check if FusionPBX integration is enabled and configured
 * 
 * @param array $settings FusionPBX settings
 * @return bool True if enabled and configured
 */
function isFusionPBXConfigured($settings) {
    return !empty($settings['fusionpbx_enabled']) 
        && $settings['fusionpbx_enabled'] === '1'
        && !empty($settings['fusionpbx_url']) 
        && !empty($settings['fusionpbx_api_key'])
        && !empty($settings['fusionpbx_domain_uuid']);
}

/**
 * Make an API request to FusionPBX
 * 
 * @param array $settings FusionPBX settings
 * @param string $endpoint API endpoint path
 * @param string $method HTTP method
 * @param array|null $data Request body data
 * @return array Response with success status
 */
function fusionpbxApiRequest($settings, $endpoint, $method = 'GET', $data = null) {
    if (empty($settings['fusionpbx_url'])) {
        return ['success' => false, 'message' => 'FusionPBX URL is not configured'];
    }
    
    if (empty($settings['fusionpbx_api_key'])) {
        return ['success' => false, 'message' => 'FusionPBX API key is not configured'];
    }
    
    $url = rtrim($settings['fusionpbx_url'], '/') . '/api' . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . $settings['fusionpbx_api_key']
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'message' => 'Connection error: ' . $curlError];
    }
    
    $decoded = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'data' => $decoded,
            'http_code' => $httpCode
        ];
    }
    
    return [
        'success' => false,
        'message' => $decoded['message'] ?? "HTTP $httpCode error",
        'data' => $decoded,
        'http_code' => $httpCode
    ];
}

/**
 * Test connection to FusionPBX server
 * 
 * @param array $settings FusionPBX settings
 * @return array Result with success status
 */
function testFusionPBXConnection($settings) {
    if (empty($settings['fusionpbx_url'])) {
        return ['success' => false, 'message' => 'FusionPBX URL is not configured'];
    }
    
    if (empty($settings['fusionpbx_api_key'])) {
        return ['success' => false, 'message' => 'FusionPBX API key is not configured'];
    }
    
    // Try to list extensions to verify connectivity and authentication
    $result = fusionpbxApiRequest($settings, '/extensions', 'GET');
    
    if ($result['success']) {
        return [
            'success' => true,
            'message' => 'Successfully connected to FusionPBX',
            'server_url' => $settings['fusionpbx_url']
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Connection failed: ' . ($result['message'] ?? 'Unknown error')
    ];
}

/**
 * Generate a secure SIP password
 * 
 * @param int $length Password length
 * @return string Generated password
 */
function generateSipPassword($length = 16) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Find the next available extension number
 * 
 * @param array $settings FusionPBX settings
 * @return array Result with next extension number
 */
function findNextAvailableExtension($settings) {
    $result = fusionpbxApiRequest($settings, '/extensions', 'GET');
    
    if (!$result['success']) {
        return ['success' => false, 'message' => 'Failed to fetch extensions: ' . $result['message']];
    }
    
    // Collect all existing extension numbers
    $existingExtensions = [];
    if (!empty($result['data']) && is_array($result['data'])) {
        foreach ($result['data'] as $ext) {
            if (isset($ext['extension'])) {
                $existingExtensions[] = (int)$ext['extension'];
            }
        }
    }
    
    // Start from 1001 and find the next available
    $nextExtension = 1001;
    while (in_array($nextExtension, $existingExtensions)) {
        $nextExtension++;
    }
    
    return ['success' => true, 'extension' => (string)$nextExtension];
}

/**
 * Create an extension in FusionPBX
 * 
 * @param array $settings FusionPBX settings
 * @param string $extension Extension number
 * @param string $password SIP password
 * @param string $displayName Display name for the extension (e.g., "John Smith")
 * @param string $email User email address
 * @return array Result with success status
 */
function createFusionPBXExtension($settings, $extension, $password, $displayName, $email = '') {
    $domainUuid = $settings['fusionpbx_domain_uuid'] ?? '';
    $context = $settings['fusionpbx_default_context'] ?? 'default';
    
    $data = [
        'extension' => $extension,
        'password' => $password,
        'effective_caller_id_name' => $displayName,
        'effective_caller_id_number' => $extension,
        'outbound_caller_id_name' => $displayName,
        'outbound_caller_id_number' => $extension,
        'description' => $displayName,
        'enabled' => 'true',
        'domain_uuid' => $domainUuid,
        'user_context' => $context,
        'directory_visible' => 'true',
        'directory_exten_visible' => 'true'
    ];
    
    $result = fusionpbxApiRequest($settings, '/extensions', 'POST', $data);
    
    if ($result['success']) {
        return [
            'success' => true,
            'extension_uuid' => $result['data']['extension_uuid'] ?? null,
            'extension' => $extension,
            'message' => "Extension $extension created successfully"
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to create extension: ' . ($result['message'] ?? 'Unknown error')
    ];
}

/**
 * Create a user account in FusionPBX linked to an extension
 * 
 * @param array $settings FusionPBX settings
 * @param string $username Username (typically the email)
 * @param string $password User password
 * @param string $extensionUuid UUID of the extension to link
 * @param string $displayName Display name
 * @return array Result with success status
 */
function createFusionPBXUser($settings, $username, $password, $extensionUuid, $displayName) {
    $domainUuid = $settings['fusionpbx_domain_uuid'] ?? '';
    
    $data = [
        'username' => $username,
        'password' => $password,
        'user_enabled' => 'true',
        'domain_uuid' => $domainUuid,
        'contact_name_given' => explode(' ', $displayName)[0] ?? $displayName,
        'contact_name_family' => implode(' ', array_slice(explode(' ', $displayName), 1)) ?: '',
    ];
    
    if (!empty($extensionUuid)) {
        $data['extension_uuid'] = $extensionUuid;
    }
    
    $result = fusionpbxApiRequest($settings, '/users', 'POST', $data);
    
    if ($result['success']) {
        return [
            'success' => true,
            'user_uuid' => $result['data']['user_uuid'] ?? null,
            'message' => "FusionPBX user created successfully"
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to create FusionPBX user: ' . ($result['message'] ?? 'Unknown error')
    ];
}

/**
 * Create an inbound dial plan (routes a DID to an extension)
 * 
 * @param array $settings FusionPBX settings
 * @param string $didNumber The DID number to route
 * @param string $extension Extension to route to
 * @param string $displayName Description for the dial plan
 * @return array Result with success status
 */
function createFusionPBXInboundRoute($settings, $didNumber, $extension, $displayName) {
    $domainUuid = $settings['fusionpbx_domain_uuid'] ?? '';
    $context = $settings['fusionpbx_default_context'] ?? 'default';
    
    // Clean the DID number (allow leading + followed by digits only)
    $cleanDid = preg_replace('/[^0-9]/', '', $didNumber);
    if (strpos($didNumber, '+') === 0) {
        $cleanDid = '+' . $cleanDid;
    }
    
    $data = [
        'dialplan_name' => 'Inbound - ' . $displayName,
        'dialplan_number' => $cleanDid,
        'dialplan_context' => $context ?: 'public',
        'dialplan_destination' => 'transfer ' . $extension . ' XML ' . ($context ?: 'default'),
        'dialplan_type' => 'inbound',
        'dialplan_enabled' => 'true',
        'dialplan_description' => 'Inbound route for ' . $displayName . ' (' . $cleanDid . ' -> ext ' . $extension . ')',
        'domain_uuid' => $domainUuid
    ];
    
    $result = fusionpbxApiRequest($settings, '/dialplans', 'POST', $data);
    
    if ($result['success']) {
        return [
            'success' => true,
            'dialplan_uuid' => $result['data']['dialplan_uuid'] ?? null,
            'message' => "Inbound route created: $cleanDid -> ext $extension"
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to create inbound route: ' . ($result['message'] ?? 'Unknown error')
    ];
}

/**
 * Create an outbound dial plan for an extension
 * 
 * @param array $settings FusionPBX settings
 * @param string $extension Extension number
 * @param string $displayName Description for the dial plan
 * @return array Result with success status
 */
function createFusionPBXOutboundRoute($settings, $extension, $displayName) {
    $domainUuid = $settings['fusionpbx_domain_uuid'] ?? '';
    $context = $settings['fusionpbx_default_context'] ?? 'default';
    $areaCode = $settings['fusionpbx_area_code'] ?? '';
    
    $data = [
        'dialplan_name' => 'Outbound - ' . $displayName,
        'dialplan_context' => $context ?: 'default',
        'dialplan_type' => 'outbound',
        'dialplan_enabled' => 'true',
        'dialplan_description' => 'Outbound route for ' . $displayName . ' (ext ' . $extension . ')',
        'domain_uuid' => $domainUuid
    ];
    
    if (!empty($areaCode)) {
        $data['dialplan_number'] = $areaCode;
    }
    
    $result = fusionpbxApiRequest($settings, '/dialplans', 'POST', $data);
    
    if ($result['success']) {
        return [
            'success' => true,
            'dialplan_uuid' => $result['data']['dialplan_uuid'] ?? null,
            'message' => "Outbound route created for ext $extension"
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to create outbound route: ' . ($result['message'] ?? 'Unknown error')
    ];
}

/**
 * Provision a complete FusionPBX setup for a new employee.
 * This creates: extension, user, inbound dial plan, and outbound dial plan.
 * 
 * @param PDO $pdo Database connection
 * @param string $displayName Employee full name
 * @param string $email Employee email
 * @param string|null $didNumber Optional DID number for inbound routing
 * @return array Result with provisioning details
 */
function provisionFusionPBXExtension($pdo, $displayName, $email, $didNumber = null) {
    $settings = getFusionPBXSettings($pdo);
    
    if (!isFusionPBXConfigured($settings)) {
        return ['success' => false, 'message' => 'FusionPBX is not configured or not enabled'];
    }
    
    $results = [
        'success' => true,
        'extension' => null,
        'sip_password' => null,
        'extension_uuid' => null,
        'user_uuid' => null,
        'inbound_dialplan_uuid' => null,
        'outbound_dialplan_uuid' => null,
        'errors' => []
    ];
    
    // Step 1: Find next available extension
    $nextExt = findNextAvailableExtension($settings);
    if (!$nextExt['success']) {
        return ['success' => false, 'message' => 'Could not determine next extension: ' . $nextExt['message']];
    }
    $extension = $nextExt['extension'];
    $results['extension'] = $extension;
    
    // Step 2: Generate SIP password
    $passwordLength = intval($settings['fusionpbx_default_password_length'] ?? 16);
    $sipPassword = generateSipPassword($passwordLength);
    $results['sip_password'] = $sipPassword;
    
    // Step 3: Create extension
    $extResult = createFusionPBXExtension($settings, $extension, $sipPassword, $displayName, $email);
    if ($extResult['success']) {
        $results['extension_uuid'] = $extResult['extension_uuid'];
    } else {
        $results['errors'][] = $extResult['message'];
        $results['success'] = false;
        return $results;
    }
    
    // Step 4: Create FusionPBX user linked to extension
    $userResult = createFusionPBXUser($settings, $email, $sipPassword, $results['extension_uuid'], $displayName);
    if ($userResult['success']) {
        $results['user_uuid'] = $userResult['user_uuid'];
    } else {
        $results['errors'][] = $userResult['message'];
        // Non-fatal: extension was created, user creation failed
    }
    
    // Step 5: Create inbound dial plan (if DID provided)
    if (!empty($didNumber)) {
        $inboundResult = createFusionPBXInboundRoute($settings, $didNumber, $extension, $displayName);
        if ($inboundResult['success']) {
            $results['inbound_dialplan_uuid'] = $inboundResult['dialplan_uuid'];
        } else {
            $results['errors'][] = $inboundResult['message'];
        }
    }
    
    // Step 6: Create outbound dial plan
    $outboundResult = createFusionPBXOutboundRoute($settings, $extension, $displayName);
    if ($outboundResult['success']) {
        $results['outbound_dialplan_uuid'] = $outboundResult['dialplan_uuid'];
    } else {
        $results['errors'][] = $outboundResult['message'];
    }
    
    $results['message'] = $results['success'] 
        ? "Extension $extension provisioned successfully for $displayName"
        : "Extension provisioning completed with errors";
    
    return $results;
}
