<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host     = env('DB_HOST', 'localhost');
        $this->db_name  = env('DB_NAME', 'cosmo_smiles_dental');
        $this->username = env('DB_USERNAME', 'root');
        $this->password = env('DB_PASSWORD', '');
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // Prevent 500 error by showing a clean diagnostic message instead of crashing
            if (ini_get('display_errors')) {
                die("<div style='padding: 20px; background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; font-family: sans-serif; border-radius: 8px;'>
                    <h3 style='margin-top: 0;'>Database Connection Failed</h3>
                    <p><strong>Error:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>
                    <p>Check your <code>.env</code> file credentials and ensure the database user has proper permissions.</p>
                </div>");
            }
            error_log("Database Connection Error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
