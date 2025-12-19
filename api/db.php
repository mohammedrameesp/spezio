<?php
/**
 * Spezio Apartments Booking System
 * Database Connection Class
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Get PDO database connection
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Execute a query with parameters
 */
function dbQuery($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch single row
 */
function dbFetchOne($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Fetch all rows
 */
function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insert and return last insert ID
 */
function dbInsert($table, $data) {
    $db = getDB();
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));

    $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    $stmt = $db->prepare($sql);
    $stmt->execute($data);

    return $db->lastInsertId();
}

/**
 * Update rows
 */
function dbUpdate($table, $data, $where, $whereParams = []) {
    $db = getDB();
    $set = [];
    foreach (array_keys($data) as $column) {
        $set[] = "{$column} = :{$column}";
    }
    $setStr = implode(', ', $set);

    // Convert positional parameters (?) to named parameters to avoid mixing styles
    $namedWhereParams = [];
    $paramIndex = 0;
    $where = preg_replace_callback('/\?/', function($match) use (&$paramIndex, $whereParams, &$namedWhereParams) {
        $paramName = "_where_{$paramIndex}";
        if (isset($whereParams[$paramIndex])) {
            $namedWhereParams[$paramName] = $whereParams[$paramIndex];
        }
        $paramIndex++;
        return ":{$paramName}";
    }, $where);

    $sql = "UPDATE {$table} SET {$setStr} WHERE {$where}";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($data, $namedWhereParams));

    return $stmt->rowCount();
}

/**
 * Delete rows
 */
function dbDelete($table, $where, $params = []) {
    $sql = "DELETE FROM {$table} WHERE {$where}";
    $stmt = dbQuery($sql, $params);
    return $stmt->rowCount();
}

/**
 * Check if record exists
 */
function dbExists($table, $where, $params = []) {
    $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
    $result = dbFetchOne($sql, $params);
    return $result !== false;
}

/**
 * Count rows
 */
function dbCount($table, $where = '1=1', $params = []) {
    $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
    $result = dbFetchOne($sql, $params);
    return (int) $result['count'];
}

/**
 * Begin transaction
 */
function dbBeginTransaction() {
    return getDB()->beginTransaction();
}

/**
 * Commit transaction
 */
function dbCommit() {
    return getDB()->commit();
}

/**
 * Rollback transaction
 */
function dbRollback() {
    return getDB()->rollBack();
}
