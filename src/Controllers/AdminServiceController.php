<?php
require_once __DIR__ . '/../../config/database.php';

class AdminServiceController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAllServices($filters = []) {
        try {
            $query = "SELECT * FROM services WHERE 1=1";
            $params = [];

            if (!empty($filters['search'])) {
                $query .= " AND name LIKE :search";
                $params[':search'] = '%' . $filters['search'] . '%';
            }

            if (isset($filters['status']) && $filters['status'] !== 'all') {
                $query .= " AND is_active = :status";
                $params[':status'] = (int)$filters['status'];
            }

            $query .= " ORDER BY name ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAllServices: " . $e->getMessage());
            return [];
        }
    }

    public function addService($data) {
        try {
            $query = "INSERT INTO services (name, description, duration_minutes, price, is_active) 
                      VALUES (:name, :description, :duration, :price, :is_active)";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                ':name' => $data['name'],
                ':description' => $data['description'] ?? null,
                ':duration' => $data['duration'] ?? 30,
                ':price' => $data['price'] ?? 0.00,
                ':is_active' => $data['is_active'] ?? 1
            ]);
        } catch (PDOException $e) {
            error_log("Error adding service: " . $e->getMessage());
            return false;
        }
    }

    public function updateService($id, $data) {
        try {
            $query = "UPDATE services SET 
                      name = :name, 
                      description = :description, 
                      duration_minutes = :duration, 
                      price = :price, 
                      is_active = :is_active 
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                ':id' => $id,
                ':name' => $data['name'],
                ':description' => $data['description'] ?? null,
                ':duration' => $data['duration'] ?? 30,
                ':price' => $data['price'] ?? 0.00,
                ':is_active' => $data['is_active'] ?? 1
            ]);
        } catch (PDOException $e) {
            error_log("Error updating service: " . $e->getMessage());
            return false;
        }
    }

    public function deleteService($id) {
        try {
            // Check if service is in use by appointments (optional, but good for safety)
            $checkQuery = "SELECT COUNT(*) FROM appointments WHERE service_id = :id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([':id' => $id]);
            if ($checkStmt->fetchColumn() > 0) {
                // Instead of deleting, we might want to just deactivate it if in use
                return $this->toggleServiceStatus($id, 0);
            }

            $query = "DELETE FROM services WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("Error deleting service: " . $e->getMessage());
            return false;
        }
    }

    public function toggleServiceStatus($id, $status) {
        try {
            $query = "UPDATE services SET is_active = :status WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                ':id' => $id,
                ':status' => $status
            ]);
        } catch (PDOException $e) {
            error_log("Error toggling service status: " . $e->getMessage());
            return false;
        }
    }

    public function getServiceStats() {
        try {
            $stats = [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'avg_price' => 0
            ];
            
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
                        AVG(price) as avg_price
                      FROM services";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $stats = [
                    'total' => (int)$result['total'],
                    'active' => (int)$result['active'],
                    'inactive' => (int)$result['inactive'],
                    'avg_price' => (float)$result['avg_price']
                ];
            }
            return $stats;
        } catch (PDOException $e) {
            error_log("Error getting service stats: " . $e->getMessage());
            return ['total' => 0, 'active' => 0, 'inactive' => 0, 'avg_price' => 0];
        }
    }
}
?>
