<?php
/**
 * Field-Level Encryption Library
 * Provides AES-256-CBC encryption for sensitive user data (PII) at rest.
 * 
 * Encrypts: names, emails, phone numbers, addresses, birthdates
 * Uses OpenSSL with a per-installation key stored in the environment file.
 */

class FieldEncryption {

    private static $cipher = 'aes-256-cbc';

    /**
     * Get the encryption key from the environment.
     * Falls back gracefully if not configured (returns null).
     * @return string|null Raw binary key or null if not configured
     */
    private static function getKey() {
        $hex = $_ENV['ENCRYPTION_KEY'] ?? '';
        if (empty($hex)) {
            return null;
        }
        return hex2bin($hex);
    }

    /**
     * Encrypt a plaintext value.
     * Returns base64-encoded ciphertext with IV prepended.
     * Returns the original value unchanged if encryption is not configured.
     *
     * @param string|null $value Plaintext to encrypt
     * @return string|null Encrypted string or original value
     */
    public static function encrypt($value) {
        if ($value === null || $value === '') {
            return $value;
        }
        $key = self::getKey();
        if ($key === null) {
            return $value;
        }
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher));
        $encrypted = openssl_encrypt($value, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            error_log('FieldEncryption::encrypt failed: ' . openssl_error_string());
            return $value;
        }
        // Prepend IV so we can decrypt later; base64-encode the whole thing
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a previously encrypted value.
     * Returns the original plaintext.
     * Returns the value unchanged if it does not appear to be encrypted
     * or if encryption is not configured.
     *
     * @param string|null $value Encrypted string
     * @return string|null Decrypted plaintext or original value
     */
    public static function decrypt($value) {
        if ($value === null || $value === '') {
            return $value;
        }
        $key = self::getKey();
        if ($key === null) {
            return $value;
        }
        $data = base64_decode($value, true);
        if ($data === false) {
            // Not base64 → likely plain text (not yet encrypted)
            return $value;
        }
        $ivLen = openssl_cipher_iv_length(self::$cipher);
        if (strlen($data) <= $ivLen) {
            // Too short to contain IV + ciphertext → return as-is
            return $value;
        }
        $iv = substr($data, 0, $ivLen);
        $ciphertext = substr($data, $ivLen);
        $decrypted = openssl_decrypt($ciphertext, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            // Decryption failed – could be plain text that happened to be valid base64
            return $value;
        }
        return $decrypted;
    }

    /**
     * Generate a new random 256-bit hex-encoded key.
     * Use this during setup to create the ENCRYPTION_KEY value.
     *
     * @return string 64-character hex string
     */
    public static function generateKey() {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }

    /**
     * Encrypt an array of fields in-place.
     * Only encrypts fields that are present and non-empty.
     *
     * @param array $data Associative array of field => value
     * @param array $fields List of field names to encrypt
     * @return array The array with specified fields encrypted
     */
    public static function encryptFields(array $data, array $fields) {
        foreach ($fields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $data[$field] = self::encrypt($data[$field]);
            }
        }
        return $data;
    }

    /**
     * Decrypt an array of fields in-place.
     *
     * @param array $data Associative array of field => value
     * @param array $fields List of field names to decrypt
     * @return array The array with specified fields decrypted
     */
    public static function decryptFields(array $data, array $fields) {
        foreach ($fields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $data[$field] = self::decrypt($data[$field]);
            }
        }
        return $data;
    }

    /**
     * Decrypt multiple rows (e.g. from a database result set).
     *
     * @param array $rows Array of associative arrays
     * @param array $fields List of field names to decrypt
     * @return array Rows with specified fields decrypted
     */
    public static function decryptRows(array $rows, array $fields) {
        foreach ($rows as &$row) {
            $row = self::decryptFields($row, $fields);
        }
        unset($row);
        return $rows;
    }

    /**
     * Check whether encryption is configured and functional.
     *
     * @return bool True if a valid key is available
     */
    public static function isConfigured() {
        return self::getKey() !== null;
    }

    /**
     * Standard PII fields found in the users table.
     * Note: email is listed for reference but should NOT be encrypted
     * in practice because it is used in WHERE clauses and UNIQUE constraints.
     */
    const USER_PII_FIELDS = [
        'first_name', 'last_name', 'phone', 'birth_date', 'date_of_birth'
    ];

    /**
     * Standard PII fields found in employee/HR records.
     */
    const EMPLOYEE_PII_FIELDS = [
        'first_name', 'last_name', 'email', 'phone', 'date_of_birth',
        'street_address', 'city', 'emergency_contact_name', 'emergency_contact_phone'
    ];

    /**
     * PII fields in customer/order records.
     */
    const CUSTOMER_PII_FIELDS = [
        'customer_email', 'customer_first_name', 'customer_last_name', 'customer_phone',
        'billing_address_line1', 'billing_address_line2', 'billing_city',
        'shipping_address_line1', 'shipping_address_line2', 'shipping_city'
    ];
}
?>
