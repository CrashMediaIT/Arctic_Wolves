<?php
/**
 * Registration Blocklist Library
 * Manages named registration restrictions, each containing multiple trigger entries
 * (blocked emails, names, and IP addresses) for registration prevention
 */

class Blocklist {

    /**
     * Ensure the registration_restrictions and registration_blocklist tables exist
     * @param PDO $pdo Database connection
     */
    public static function ensureTable($pdo) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `registration_restrictions` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `title` VARCHAR(255) NOT NULL,
                    `created_by` INT DEFAULT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `registration_blocklist` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `restriction_id` INT NOT NULL,
                    `block_type` ENUM('email', 'name', 'ip') NOT NULL,
                    `block_value` VARCHAR(255) NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `unique_block` (`block_type`, `block_value`),
                    INDEX `idx_type` (`block_type`),
                    INDEX `idx_value` (`block_value`),
                    INDEX `idx_restriction` (`restriction_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            error_log("Blocklist::ensureTable error: " . $e->getMessage());
        }
    }

    // ---- RESTRICTION (parent) METHODS ----

    /**
     * Create a new named restriction
     * @param PDO $pdo Database connection
     * @param string $title The restriction title/name
     * @param int|null $createdBy Admin user ID who created it
     * @return int|false The new restriction ID, or false on failure
     */
    public static function createRestriction($pdo, $title, $createdBy = null) {
        try {
            self::ensureTable($pdo);
            $title = trim($title);
            if (empty($title)) {
                return false;
            }
            $stmt = $pdo->prepare("INSERT INTO registration_restrictions (title, created_by) VALUES (?, ?)");
            $stmt->execute([$title, $createdBy]);
            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Blocklist::createRestriction error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove a restriction and all its entries (cascade)
     * @param PDO $pdo Database connection
     * @param int $restrictionId Restriction ID
     * @return bool Success
     */
    public static function removeRestriction($pdo, $restrictionId) {
        try {
            // Delete child entries first (in case FK cascade is not available)
            $stmt = $pdo->prepare("DELETE FROM registration_blocklist WHERE restriction_id = ?");
            $stmt->execute([(int)$restrictionId]);
            $stmt = $pdo->prepare("DELETE FROM registration_restrictions WHERE id = ?");
            $stmt->execute([(int)$restrictionId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Blocklist::removeRestriction error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all restrictions with their entries
     * @param PDO $pdo Database connection
     * @return array Restrictions with nested entries
     */
    public static function getRestrictions($pdo) {
        try {
            self::ensureTable($pdo);
            $stmt = $pdo->query("
                SELECT r.*, u.first_name as creator_first_name, u.last_name as creator_last_name
                FROM registration_restrictions r
                LEFT JOIN users u ON r.created_by = u.id
                ORDER BY r.created_at DESC
            ");
            $restrictions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decrypt creator names
            foreach ($restrictions as &$r) {
                if (class_exists('FieldEncryption')) {
                    $r['creator_first_name'] = FieldEncryption::decrypt($r['creator_first_name'] ?? '');
                    $r['creator_last_name'] = FieldEncryption::decrypt($r['creator_last_name'] ?? '');
                }
                $r['created_by_name'] = trim(($r['creator_first_name'] ?? '') . ' ' . ($r['creator_last_name'] ?? ''));
            }
            unset($r);

            // Fetch entries for each restriction
            $entryStmt = $pdo->prepare("SELECT * FROM registration_blocklist WHERE restriction_id = ? ORDER BY created_at DESC");
            foreach ($restrictions as &$r) {
                $entryStmt->execute([(int)$r['id']]);
                $r['entries'] = $entryStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($r);

            return $restrictions;
        } catch (PDOException $e) {
            error_log("Blocklist::getRestrictions error: " . $e->getMessage());
            return [];
        }
    }

    // ---- ENTRY (child) METHODS ----

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
     * Add an entry to a restriction
     * @param PDO $pdo Database connection
     * @param int $restrictionId The parent restriction ID
     * @param string $type 'email', 'name', or 'ip'
     * @param string $value The value to block
     * @return bool Success
     */
    public static function addEntry($pdo, $restrictionId, $type, $value) {
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
                INSERT INTO registration_blocklist (restriction_id, block_type, block_value)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([(int)$restrictionId, $type, $value]);
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
                    SELECT bl.*, r.title as restriction_title
                    FROM registration_blocklist bl
                    LEFT JOIN registration_restrictions r ON bl.restriction_id = r.id
                    WHERE bl.block_type = ?
                    ORDER BY bl.created_at DESC
                ");
                $stmt->execute([$type]);
            } else {
                $stmt = $pdo->query("
                    SELECT bl.*, r.title as restriction_title
                    FROM registration_blocklist bl
                    LEFT JOIN registration_restrictions r ON bl.restriction_id = r.id
                    ORDER BY bl.created_at DESC
                ");
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Blocklist::getEntries error: " . $e->getMessage());
            return [];
        }
    }
}
?>
