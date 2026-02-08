<?php
/**
 * Registration Blocklist Library
 * Manages blocked emails, names, and IP addresses for registration prevention
 */

class Blocklist {

    /**
     * Ensure the registration_blocklist table exists
     * @param PDO $pdo Database connection
     */
    public static function ensureTable($pdo) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `registration_blocklist` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `block_type` ENUM('email', 'name', 'ip') NOT NULL,
                    `block_value` VARCHAR(255) NOT NULL,
                    `reason` TEXT DEFAULT NULL,
                    `created_by` INT DEFAULT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `unique_block` (`block_type`, `block_value`),
                    INDEX `idx_type` (`block_type`),
                    INDEX `idx_value` (`block_value`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            error_log("Blocklist::ensureTable error: " . $e->getMessage());
        }
    }

    /**
     * Check if an email is on the blocklist
     * @param PDO $pdo Database connection
     * @param string $email Email to check
     * @return bool True if blocked
     */
    public static function isEmailBlocked($pdo, $email) {
        try {
            self::ensureTable($pdo);
            $email = strtolower(trim($email));
            $stmt = $pdo->prepare("SELECT id FROM registration_blocklist WHERE block_type = 'email' AND LOWER(block_value) = ?");
            $stmt->execute([$email]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Blocklist::isEmailBlocked error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a full name is on the blocklist
     * @param PDO $pdo Database connection
     * @param string $firstName First name
     * @param string $lastName Last name
     * @return bool True if blocked
     */
    public static function isNameBlocked($pdo, $firstName, $lastName) {
        try {
            self::ensureTable($pdo);
            $fullName = strtolower(trim($firstName) . ' ' . trim($lastName));
            $stmt = $pdo->prepare("SELECT id FROM registration_blocklist WHERE block_type = 'name' AND LOWER(block_value) = ?");
            $stmt->execute([$fullName]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Blocklist::isNameBlocked error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if an IP address is on the blocklist
     * @param PDO $pdo Database connection
     * @param string $ip IP address to check
     * @return bool True if blocked
     */
    public static function isIpBlocked($pdo, $ip) {
        try {
            self::ensureTable($pdo);
            $ip = trim($ip);
            $stmt = $pdo->prepare("SELECT id FROM registration_blocklist WHERE block_type = 'ip' AND block_value = ?");
            $stmt->execute([$ip]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Blocklist::isIpBlocked error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check all blocklist criteria for a registration attempt
     * Only one match is needed to block registration
     * @param PDO $pdo Database connection
     * @param string $email Email address
     * @param string $firstName First name
     * @param string $lastName Last name
     * @param string|null $ip IP address (optional)
     * @return array ['blocked' => bool, 'type' => string|null]
     */
    public static function checkRegistration($pdo, $email, $firstName, $lastName, $ip = null) {
        if (self::isEmailBlocked($pdo, $email)) {
            return ['blocked' => true, 'type' => 'email'];
        }
        if (self::isNameBlocked($pdo, $firstName, $lastName)) {
            return ['blocked' => true, 'type' => 'name'];
        }
        if ($ip && self::isIpBlocked($pdo, $ip)) {
            return ['blocked' => true, 'type' => 'ip'];
        }
        return ['blocked' => false, 'type' => null];
    }

    /**
     * Add an entry to the blocklist (only one criterion needed)
     * @param PDO $pdo Database connection
     * @param string $type 'email', 'name', or 'ip'
     * @param string $value The value to block
     * @param string|null $reason Reason for blocking
     * @param int|null $createdBy Admin user ID who created the entry
     * @return bool Success
     */
    public static function addEntry($pdo, $type, $value, $reason = null, $createdBy = null) {
        try {
            self::ensureTable($pdo);
            if (!in_array($type, ['email', 'name', 'ip'])) {
                return false;
            }
            $value = trim($value);
            if ($type !== 'ip') {
                $value = strtolower($value);
            }
            if (empty($value)) {
                return false;
            }
            $stmt = $pdo->prepare("
                INSERT INTO registration_blocklist (block_type, block_value, reason, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$type, $value, $reason, $createdBy]);
            return true;
        } catch (PDOException $e) {
            error_log("Blocklist::addEntry error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove an entry from the blocklist
     * @param PDO $pdo Database connection
     * @param int $id Entry ID to remove
     * @return bool Success
     */
    public static function removeEntry($pdo, $id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM registration_blocklist WHERE id = ?");
            $stmt->execute([(int)$id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Blocklist::removeEntry error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all blocklist entries with optional type filter
     * @param PDO $pdo Database connection
     * @param string|null $type Filter by type or null for all
     * @return array Blocklist entries
     */
    public static function getEntries($pdo, $type = null) {
        try {
            self::ensureTable($pdo);
            if ($type && in_array($type, ['email', 'name', 'ip'])) {
                $stmt = $pdo->prepare("
                    SELECT bl.*, u.first_name as creator_first_name, u.last_name as creator_last_name
                    FROM registration_blocklist bl
                    LEFT JOIN users u ON bl.created_by = u.id
                    WHERE bl.block_type = ?
                    ORDER BY bl.created_at DESC
                ");
                $stmt->execute([$type]);
            } else {
                $stmt = $pdo->query("
                    SELECT bl.*, u.first_name as creator_first_name, u.last_name as creator_last_name
                    FROM registration_blocklist bl
                    LEFT JOIN users u ON bl.created_by = u.id
                    ORDER BY bl.created_at DESC
                ");
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Decrypt creator names and build display name
            if (class_exists('FieldEncryption')) {
                foreach ($rows as &$row) {
                    $row['creator_first_name'] = FieldEncryption::decrypt($row['creator_first_name'] ?? '');
                    $row['creator_last_name'] = FieldEncryption::decrypt($row['creator_last_name'] ?? '');
                    $row['created_by_name'] = trim(($row['creator_first_name'] ?? '') . ' ' . ($row['creator_last_name'] ?? ''));
                }
                unset($row);
            } else {
                foreach ($rows as &$row) {
                    $row['created_by_name'] = trim(($row['creator_first_name'] ?? '') . ' ' . ($row['creator_last_name'] ?? ''));
                }
                unset($row);
            }
            return $rows;
        } catch (PDOException $e) {
            error_log("Blocklist::getEntries error: " . $e->getMessage());
            return [];
        }
    }
}
?>
