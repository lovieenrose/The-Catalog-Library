<?php
/**
 * Database Session Handler for Serverless & Distributed Environments
 * The Cat-alog Library
 */

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $table = 'sessions';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (string)$row['data'];
            }
        } catch (Exception $e) {
            error_log("Database session read failure: " . $e->getMessage());
        }
        return "";
    }

    public function write(string $id, string $data): bool {
        try {
            $access = time();
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->table} (id, access, data) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE access = VALUES(access), data = VALUES(data)
            ");
            return $stmt->execute([$id, $access, $data]);
        } catch (Exception $e) {
            error_log("Database session write failure: " . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Database session destroy failure: " . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false {
        try {
            $old = time() - $max_lifetime;
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE access < ?");
            $stmt->execute([$old]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Database session gc failure: " . $e->getMessage());
            return false;
        }
    }
}

function register_database_session_handler(): bool {
    global $conn, $db_connected, $allow_db_failure;
    if (!isset($conn)) {
        require_once __DIR__ . '/db.php';
    }
    if (isset($conn) && $conn instanceof PDO) {
        $handler = new DatabaseSessionHandler($conn);
        return session_set_save_handler($handler, true);
    }
    return false;
}

