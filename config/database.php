<?php

require_once __DIR__ . '/env.php';

// Database configuration
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct()
    {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'farmscout_online';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
    }

    public function getConnection() {
        $this->conn = null;

        $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8";
        $attempts = [
            [$this->username, $this->password],
        ];

        // Dev-friendly fallback for local XAMPP when env credentials are stale/missing.
        if ($this->host === 'localhost' && !($this->username === 'root' && $this->password === '')) {
            $attempts[] = ['root', ''];
        }

        foreach ($attempts as [$user, $pass]) {
            try {
                $this->conn = new PDO($dsn, $user, $pass);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $this->conn;
            } catch(PDOException $exception) {
                error_log("Connection error ({$user}@{$this->host}): " . $exception->getMessage());
            }
        }

        return $this->conn;
    }
}
?>