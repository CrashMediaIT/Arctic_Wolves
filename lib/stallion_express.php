<?php
/**
 * Stallion Express API Client
 * 
 * Provides functions for connecting to the Stallion Express API
 * for automated shipping label generation and tracking.
 * 
 * Stallion Express is a Canadian shipping fulfillment service that
 * aggregates rates from multiple carriers (Canada Post, UPS, FedEx, 
 * DHL, etc.) to provide the best shipping rates for e-commerce businesses.
 * API Documentation: https://stallionexpress.redocly.app/stallionexpress-v4
 * 
 * Features:
 * - Shipment creation with label generation via best-rate carrier selection
 * - Rate shopping across multiple carriers
 * - Tracking status retrieval
 * - Label PDF download
 * - Connection testing
 */

/**
 * Get Stallion Express settings from database
 * 
 * @param PDO $pdo Database connection
 * @return array Stallion Express settings
 */
function getStallionSettings($pdo) {
    $keys = [
        'stallion_enabled',
        'stallion_api_key',
        'stallion_api_secret',
        'stallion_api_url',
        'stallion_sender_name',
        'stallion_sender_company',
        'stallion_sender_address',
        'stallion_sender_city',
        'stallion_sender_province',
        'stallion_sender_postal_code',
        'stallion_sender_phone',
        'stallion_default_weight',
        'stallion_default_length',
        'stallion_default_width',
        'stallion_default_height'
    ];
    
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    return $settings;
}

/**
 * Check if Stallion Express is enabled and configured
 * 
 * @param array $settings Stallion settings
 * @return bool True if ready to use
 */
function isStallionConfigured($settings) {
    return !empty($settings['stallion_enabled']) 
        && !empty($settings['stallion_api_key'])
        && !empty($settings['stallion_api_url']);
}

/**
 * Make an API request to Stallion Express
 * 
 * @param array $settings Stallion Express settings
 * @param string $endpoint API endpoint path
 * @param string $method HTTP method (GET, POST, PUT, DELETE)
 * @param array|null $data Request body data
 * @return array Response with success status
 */
function stallionApiRequest($settings, $endpoint, $method = 'GET', $data = null) {
    if (empty($settings['stallion_api_url'])) {
        return ['success' => false, 'message' => 'Stallion Express API URL is not configured'];
    }
    
    if (empty($settings['stallion_api_key'])) {
        return ['success' => false, 'message' => 'Stallion Express API key is not configured'];
    }
    
    $url = rtrim($settings['stallion_api_url'], '/') . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $headers = [
        'Authorization: Bearer ' . $settings['stallion_api_key'],
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    // Add API secret header if available
    if (!empty($settings['stallion_api_secret'])) {
        $headers[] = 'X-Api-Secret: ' . $settings['stallion_api_secret'];
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'API request failed: ' . $error];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'data' => $responseData,
            'status_code' => $httpCode
        ];
    }
    
    $errorMessage = isset($responseData['message']) 
        ? $responseData['message'] 
        : (isset($responseData['error']) ? $responseData['error'] : 'API returned status code: ' . $httpCode);
    return ['success' => false, 'message' => $errorMessage, 'status_code' => $httpCode];
}

/**
 * Test connection to Stallion Express API
 * 
 * @param array $settings API settings
 * @return array Result with success status and message
 */
function testStallionConnection($settings) {
    if (empty($settings['stallion_api_url'])) {
        return ['success' => false, 'message' => 'Stallion Express API URL is not configured'];
    }
    
    if (empty($settings['stallion_api_key'])) {
        return ['success' => false, 'message' => 'Stallion Express API key is not configured'];
    }
    
    // Attempt to get account info or list shipments to verify credentials
    $result = stallionApiRequest($settings, '/account', 'GET');
    
    if ($result['success']) {
        return [
            'success' => true,
            'message' => 'Connection successful! Stallion Express API is reachable.'
        ];
    }
    
    return $result;
}

/**
 * Create a shipment through Stallion Express fulfillment service
 * 
 * Stallion Express will automatically select the best carrier and rate
 * from their network of carriers (Canada Post, UPS, FedEx, DHL, etc.)
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Stallion settings
 * @param array $order Order data from shop_orders
 * @param array $items Order items
 * @param array $overrides Optional overrides for package dimensions/weight
 * @return array Result with shipment data and label URL
 */
function createStallionShipment($pdo, $settings, $order, $items, $overrides = []) {
    if (!isStallionConfigured($settings)) {
        return ['success' => false, 'message' => 'Stallion Express is not properly configured'];
    }
    
    // Build recipient address
    $recipientAddress1 = $order['shipping_address_line1'] ?? $order['billing_address_line1'] ?? '';
    $recipientAddress2 = $order['shipping_address_line2'] ?? $order['billing_address_line2'] ?? '';
    $recipientCity = $order['shipping_city'] ?? $order['billing_city'] ?? '';
    $recipientProvince = $order['shipping_state'] ?? $order['billing_state'] ?? '';
    $recipientPostalCode = $order['shipping_postal_code'] ?? $order['billing_postal_code'] ?? '';
    $recipientCountry = $order['shipping_country'] ?? $order['billing_country'] ?? 'CA';
    
    // Build item descriptions
    $itemDescriptions = [];
    $totalQuantity = 0;
    foreach ($items as $item) {
        $desc = $item['product_name'];
        if (!empty($item['size'])) {
            $desc .= ' (' . $item['size'] . ')';
        }
        $itemDescriptions[] = $desc;
        $totalQuantity += intval($item['quantity']);
    }
    
    // Build shipment payload
    $shipmentData = [
        'sender' => [
            'name' => $settings['stallion_sender_name'] ?? '',
            'company' => $settings['stallion_sender_company'] ?? '',
            'address1' => $settings['stallion_sender_address'] ?? '',
            'city' => $settings['stallion_sender_city'] ?? '',
            'province' => $settings['stallion_sender_province'] ?? '',
            'postal_code' => $settings['stallion_sender_postal_code'] ?? '',
            'country' => 'CA',
            'phone' => $settings['stallion_sender_phone'] ?? ''
        ],
        'recipient' => [
            'name' => implode(' ', array_filter([$order['customer_first_name'] ?? '', $order['customer_last_name'] ?? ''])),
            'address1' => $recipientAddress1,
            'address2' => $recipientAddress2,
            'city' => $recipientCity,
            'province' => $recipientProvince,
            'postal_code' => $recipientPostalCode,
            'country' => $recipientCountry,
            'phone' => $order['customer_phone'] ?? '',
            'email' => $order['customer_email'] ?? ''
        ],
        'package' => [
            'weight' => floatval($overrides['weight'] ?? $settings['stallion_default_weight'] ?? 0.5),
            'length' => floatval($overrides['length'] ?? $settings['stallion_default_length'] ?? 25),
            'width' => floatval($overrides['width'] ?? $settings['stallion_default_width'] ?? 20),
            'height' => floatval($overrides['height'] ?? $settings['stallion_default_height'] ?? 10),
            'description' => implode(', ', array_slice($itemDescriptions, 0, 3)),
            'quantity' => $totalQuantity
        ],
        'reference' => $order['order_number'] ?? ('ORD-' . $order['id']),
        'order_id' => $order['order_number'] ?? $order['id']
    ];
    
    $result = stallionApiRequest($settings, '/shipments', 'POST', $shipmentData);
    
    if ($result['success'] && !empty($result['data'])) {
        $shipment = $result['data'];
        
        // Store the shipment record in our database
        $trackingNumber = $shipment['tracking_number'] ?? $shipment['id'] ?? '';
        $labelUrl = $shipment['label_url'] ?? $shipment['label'] ?? '';
        $stallionShipmentId = $shipment['id'] ?? $shipment['shipment_id'] ?? '';
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO stallion_shipping_labels 
                (order_id, stallion_shipment_id, tracking_number, label_url, shipment_data, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'created', NOW())
            ");
            $stmt->execute([
                $order['id'],
                $stallionShipmentId,
                $trackingNumber,
                $labelUrl,
                json_encode($shipment)
            ]);
        } catch (PDOException $e) {
            error_log("Failed to save Stallion shipment record: " . $e->getMessage());
        }
        
        return [
            'success' => true,
            'message' => 'Shipment created successfully!',
            'tracking_number' => $trackingNumber,
            'label_url' => $labelUrl,
            'shipment_id' => $stallionShipmentId,
            'data' => $shipment
        ];
    }
    
    return $result;
}

/**
 * Get tracking info for a Stallion Express shipment
 * 
 * @param array $settings Stallion settings
 * @param string $trackingNumber Tracking number
 * @return array Result with tracking data
 */
function getStallionTracking($settings, $trackingNumber) {
    if (!isStallionConfigured($settings)) {
        return ['success' => false, 'message' => 'Stallion Express is not properly configured'];
    }
    
    return stallionApiRequest($settings, '/tracking/' . urlencode($trackingNumber), 'GET');
}

/**
 * Get shipping label PDF URL for a shipment
 * 
 * @param array $settings Stallion settings
 * @param string $shipmentId Stallion shipment ID
 * @return array Result with label URL
 */
function getStallionLabel($settings, $shipmentId) {
    if (!isStallionConfigured($settings)) {
        return ['success' => false, 'message' => 'Stallion Express is not properly configured'];
    }
    
    return stallionApiRequest($settings, '/shipments/' . urlencode($shipmentId) . '/label', 'GET');
}

/**
 * Get shipping rates from Stallion Express for a given package
 * 
 * Returns available carrier rates so the user can compare options.
 * Stallion Express aggregates rates from multiple carriers.
 * 
 * @param array $settings Stallion settings
 * @param array $packageData Package details (weight, dimensions, origin, destination)
 * @return array Result with available rates from multiple carriers
 */
function getStallionRates($settings, $packageData) {
    if (!isStallionConfigured($settings)) {
        return ['success' => false, 'message' => 'Stallion Express is not properly configured'];
    }
    
    return stallionApiRequest($settings, '/rates', 'POST', $packageData);
}
