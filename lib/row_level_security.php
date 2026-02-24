<?php
/**
 * Row Level Security (RLS) Library
 * Enforces data access based on user role and ownership.
 * All queries that return user-specific data should be filtered
 * through this class to ensure users can only access their own data.
 */

class RowLevelSecurity {

    private $pdo;
    private $user_id;
    private $user_role;

    /**
     * Role hierarchy for permission checks.
     * Higher roles inherit access to lower-scoped data.
     */
    const ROLE_HIERARCHY = [
        'admin'    => 100,
        'coach'    => 80,
        'staff'    => 60,
        'parent'   => 40,
        'athlete'  => 20,
    ];

    /**
     * Tables and their ownership column mapping.
     * Defines which column in each table identifies the "owner" of a row.
     */
    const TABLE_OWNER_MAP = [
        'users'                     => 'id',
        'athlete_evaluations'       => 'athlete_id',
        'evaluation_scores'         => null, // Accessed via evaluation join
        'goals'                     => 'user_id',
        'goal_evaluations'          => null, // Accessed via goal join
        'bookings'                  => 'user_id',
        'transactions'              => 'user_id',
        'user_packages'             => 'user_id',
        'login_history'             => 'user_id',
        'messages'                  => null, // sender_id or receiver_id
        'parent_athlete_relationships' => 'parent_id',
        'managed_athletes'          => 'parent_id',
        'user_agreements'           => 'user_id',
    ];

    public function __construct($pdo, $user_id = null, $user_role = null) {
        $this->pdo = $pdo;
        $this->user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        $this->user_role = $user_role ?? ($_SESSION['user_role'] ?? null);
    }

    /**
     * Validate that a table name is in the allowed whitelist.
     * Prevents SQL injection via table/column name interpolation.
     *
     * @param string $table Table name to validate
     * @return bool True if the table is in the whitelist
     */
    private function isAllowedTable($table) {
        return array_key_exists($table, self::TABLE_OWNER_MAP)
            || in_array($table, ['messages', 'evaluation_scores'], true);
    }

    /**
     * Validate that a column name matches expected format (alphanumeric + underscores only).
     *
     * @param string $column Column name to validate
     * @return bool True if valid
     */
    private function isValidColumnName($column) {
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $column);
    }

    /**
     * Check if the current user has access to a specific row in a table.
     *
     * @param string $table  Table name
     * @param int    $row_id Row primary key
     * @return bool True if user has access
     */
    public function canAccessRow($table, $row_id) {
        if ($this->user_id === null) {
            return false;
        }

        // Validate table name against whitelist
        if (!$this->isAllowedTable($table)) {
            error_log("RowLevelSecurity: Rejected access to unknown table: " . $table);
            return false;
        }

        // Admins and coaches have broad access
        if ($this->isPrivilegedRole()) {
            return true;
        }

        $owner_col = self::TABLE_OWNER_MAP[$table] ?? null;
        if ($owner_col === null) {
            // Table not in owner map — defer to specific logic
            return $this->checkSpecialTableAccess($table, $row_id);
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM `$table` WHERE id = ? AND `$owner_col` = ?"
            );
            $stmt->execute([$row_id, $this->user_id]);
            if ($stmt->fetchColumn() > 0) {
                return true;
            }

            // Parents can access their athletes' data
            if ($this->user_role === 'parent') {
                return $this->canAccessViaParentRelationship($table, $row_id, $owner_col);
            }

            return false;
        } catch (PDOException $e) {
            error_log("RowLevelSecurity::canAccessRow Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add WHERE clause filters to enforce RLS on a query.
     * Returns an array with 'where' clause string and 'params' array.
     *
     * @param string $table      Table name or alias
     * @param string $owner_col  Column that identifies the owner (default: auto-detect)
     * @return array ['where' => string, 'params' => array]
     */
    public function getAccessFilter($table, $owner_col = null) {
        if ($this->user_id === null) {
            return ['where' => '1 = 0', 'params' => []];
        }

        // Privileged roles see all rows
        if ($this->isPrivilegedRole()) {
            return ['where' => '1 = 1', 'params' => []];
        }

        // Auto-detect owner column
        if ($owner_col === null) {
            $base_table = preg_replace('/\s+.*/', '', $table); // Strip alias
            $owner_col = self::TABLE_OWNER_MAP[$base_table] ?? 'user_id';
        }

        // Validate column name to prevent SQL injection
        if (!$this->isValidColumnName($owner_col)) {
            error_log("RowLevelSecurity: Invalid column name rejected: " . $owner_col);
            return ['where' => '1 = 0', 'params' => []];
        }

        // Athletes see only their own rows
        if ($this->user_role === 'athlete') {
            return [
                'where' => "`$owner_col` = ?",
                'params' => [$this->user_id]
            ];
        }

        // Parents see their own + their athletes' rows
        if ($this->user_role === 'parent') {
            $athlete_ids = $this->getManagedAthleteIds();
            $all_ids = array_merge([$this->user_id], $athlete_ids);
            $placeholders = implode(',', array_fill(0, count($all_ids), '?'));
            return [
                'where' => "`$owner_col` IN ($placeholders)",
                'params' => $all_ids
            ];
        }

        // Default: own rows only
        return [
            'where' => "`$owner_col` = ?",
            'params' => [$this->user_id]
        ];
    }

    /**
     * Enforce that the current user owns or can access a record.
     * Throws an exception or returns false if access is denied.
     *
     * @param string $table  Table name
     * @param int    $row_id Row primary key
     * @param bool   $throw  If true, send 403 response and exit on denial
     * @return bool
     */
    public function enforceAccess($table, $row_id, $throw = true) {
        if (!$this->canAccessRow($table, $row_id)) {
            if ($throw) {
                http_response_code(403);
                error_log("RLS Access Denied: user={$this->user_id} table={$table} row={$row_id}");
                die(json_encode([
                    'success' => false,
                    'error' => 'Access denied. You do not have permission to view this record.'
                ]));
            }
            return false;
        }
        return true;
    }

    /**
     * Check if the current user has a privileged role (admin/coach).
     */
    public function isPrivilegedRole() {
        $role_level = self::ROLE_HIERARCHY[$this->user_role] ?? 0;
        return $role_level >= self::ROLE_HIERARCHY['coach'];
    }

    /**
     * Get all athlete IDs managed by the current parent user.
     *
     * @return array List of athlete user IDs
     */
    public function getManagedAthleteIds() {
        if ($this->user_role !== 'parent') {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT athlete_id FROM parent_athlete_relationships WHERE parent_id = ?
                 UNION
                 SELECT athlete_id FROM managed_athletes WHERE parent_id = ? AND status = 'active'"
            );
            $stmt->execute([$this->user_id, $this->user_id]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("RowLevelSecurity::getManagedAthleteIds Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check parent relationship for athlete-owned tables.
     */
    private function canAccessViaParentRelationship($table, $row_id, $owner_col) {
        // Validate table and column names against whitelist
        if (!$this->isAllowedTable($table) || !$this->isValidColumnName($owner_col)) {
            return false;
        }

        try {
            $athlete_ids = $this->getManagedAthleteIds();
            if (empty($athlete_ids)) {
                return false;
            }

            $placeholders = implode(',', array_fill(0, count($athlete_ids), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM `$table` WHERE id = ? AND `$owner_col` IN ($placeholders)"
            );
            $stmt->execute(array_merge([$row_id], $athlete_ids));
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("RowLevelSecurity::canAccessViaParentRelationship Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle access checks for tables with non-standard ownership patterns.
     */
    private function checkSpecialTableAccess($table, $row_id) {
        try {
            switch ($table) {
                case 'messages':
                    $stmt = $this->pdo->prepare(
                        "SELECT COUNT(*) FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)"
                    );
                    $stmt->execute([$row_id, $this->user_id, $this->user_id]);
                    return $stmt->fetchColumn() > 0;

                case 'evaluation_scores':
                    $stmt = $this->pdo->prepare(
                        "SELECT COUNT(*) FROM evaluation_scores es
                         JOIN athlete_evaluations ae ON es.evaluation_id = ae.id
                         WHERE es.id = ? AND ae.athlete_id = ?"
                    );
                    $stmt->execute([$row_id, $this->user_id]);
                    if ($stmt->fetchColumn() > 0) {
                        return true;
                    }
                    // Check parent access
                    if ($this->user_role === 'parent') {
                        $athlete_ids = $this->getManagedAthleteIds();
                        if (!empty($athlete_ids)) {
                            $placeholders = implode(',', array_fill(0, count($athlete_ids), '?'));
                            $stmt = $this->pdo->prepare(
                                "SELECT COUNT(*) FROM evaluation_scores es
                                 JOIN athlete_evaluations ae ON es.evaluation_id = ae.id
                                 WHERE es.id = ? AND ae.athlete_id IN ($placeholders)"
                            );
                            $stmt->execute(array_merge([$row_id], $athlete_ids));
                            return $stmt->fetchColumn() > 0;
                        }
                    }
                    return false;

                default:
                    return false;
            }
        } catch (PDOException $e) {
            error_log("RowLevelSecurity::checkSpecialTableAccess Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
