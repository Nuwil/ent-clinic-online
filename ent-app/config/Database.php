<?php
/**
 * Database Connection using PDO
 */

require_once __DIR__ . '/config.php';

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $config = DB_CONFIG;
            $dsn = "mysql:host={$config['host']}:{$config['port']};dbname={$config['name']};charset={$config['charset']}";

            $this->connection = new PDO(
                $dsn,
                $config['user'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            if (ENV === 'development') {
                die('Database Connection Failed: ' . $e->getMessage());
            } else {
                die('Database Connection Failed. Please try again later.');
            }
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('Query failed: ' . $e->getMessage());
        }
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetch($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function insert($table, $data)
    {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(array_values($data));
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('Insert failed: ' . $e->getMessage());
        }
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $set = implode(',', array_map(fn($k) => "$k=?", array_keys($data)));
        $sql = "UPDATE $table SET $set WHERE $where";

        try {
            $stmt = $this->connection->prepare($sql);
            $params = array_merge(array_values($data), $whereParams);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception('Update failed: ' . $e->getMessage());
        }
    }

    public function delete($table, $where, $whereParams = [])
    {
        $sql = "DELETE FROM $table WHERE $where";

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($whereParams);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception('Delete failed: ' . $e->getMessage());
        }
    }

    public function beginTransaction()
    {
        $this->connection->beginTransaction();
    }

    public function commit()
    {
        $this->connection->commit();
    }

    public function rollback()
    {
        $this->connection->rollBack();
    }

    private function __clone() {}
    public function __wakeup() {}
}
