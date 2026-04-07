<?php
// src/Controllers/TestimonialController.php

require_once __DIR__ . '/../../config/database.php';

class TestimonialController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Get featured testimonials for the public site
     */
    public function getFeaturedTestimonials() {
        $query = "
            SELECT f.id, f.rating, f.feedback, f.created_at, 
                   CONCAT(c.first_name, ' ', c.last_name) as client_name, c.profile_image
            FROM appointment_feedbacks f
            JOIN appointments a ON f.appointment_id = a.appointment_id
            JOIN clients c ON a.client_id = c.client_id
            WHERE f.is_featured = 1
            ORDER BY f.created_at DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all feedbacks grouped by client for the admin interface
     */
    public function getAllFeedbacksForAdmin($minRating = 0) {
        $whereClause = "";
        if ($minRating > 0) {
            $whereClause = "WHERE f.rating >= " . intval($minRating);
        }

        $query = "
            SELECT f.id, f.appointment_id, f.rating, f.feedback, f.created_at, f.is_featured,
                   c.client_id, CONCAT(c.first_name, ' ', c.last_name) as client_name, c.profile_image
            FROM appointment_feedbacks f
            JOIN appointments a ON f.appointment_id = a.appointment_id
            JOIN clients c ON a.client_id = c.client_id
            $whereClause
            ORDER BY f.created_at DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $grouped = [];
        foreach ($results as $row) {
            $clientId = $row['client_id'];
            if (!isset($grouped[$clientId])) {
                $grouped[$clientId] = [
                    'client_id' => $clientId,
                    'client_name' => $row['client_name'],
                    'feedbacks' => []
                ];
            }
            $grouped[$clientId]['feedbacks'][] = $row;
        }
        return array_values($grouped);
    }

    /**
     * Set a feedback as featured for a specific client
     * This enforces the rule: only one featured feedback per client
     */
    public function setFeaturedTestimonial($feedbackId, $clientId) {
        // Ensure session is started and admin is authorized
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['admin_id'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        try {
            $this->conn->beginTransaction();

            // Un-feature all feedbacks for this client
            // Using a more standard SQL syntax for compatibility
            $unfeatureQuery = "
                UPDATE appointment_feedbacks 
                SET is_featured = 0 
                WHERE appointment_id IN (
                    SELECT appointment_id FROM appointments WHERE client_id = ?
                )
            ";
            $unfeatureStmt = $this->conn->prepare($unfeatureQuery);
            $unfeatureStmt->execute([$clientId]);

            // If feedbackId > 0, set exactly that one as featured
            if ($feedbackId > 0) {
                // Verify the feedback belongs to the client and set it
                $featureQuery = "
                    UPDATE appointment_feedbacks f
                    JOIN appointments a ON f.appointment_id = a.appointment_id
                    SET f.is_featured = 1 
                    WHERE f.id = ? AND a.client_id = ?
                ";
                $featureStmt = $this->conn->prepare($featureQuery);
                $featureStmt->execute([$feedbackId, $clientId]);
                
                if ($featureStmt->rowCount() === 0) {
                    $this->conn->rollBack();
                    return ['success' => false, 'message' => 'Feedback ID invalid or does not belong to specified client'];
                }
            }

            $this->conn->commit();
            $msg = ($feedbackId > 0) ? 'Testimonial marked as featured' : 'Testimonial removed from featured';
            return ['success' => true, 'message' => $msg];
            
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
