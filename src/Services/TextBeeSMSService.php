<?php
// src/Services/TextBeeSMSService.php

require_once __DIR__ . '/../../config/env.php';

class TextBeeSMSService {
    private $baseUrl;
    private $apiKey;
    private $deviceId;
    
    public function __construct() {
        $this->baseUrl  = env('SMS_BASE_URL', 'https://api.textbee.dev/api/v1');
        $this->apiKey   = env('SMS_API_KEY', '');
        $this->deviceId = env('SMS_DEVICE_ID', '');
    }
    
    /**
     * Send SMS via TextBee API
     */
    public function sendSMS($recipient, $message) {
        try {
            $url = $this->baseUrl . '/gateway/devices/' . $this->deviceId . '/send-sms';
            
            // Format phone number properly
            $formattedPhone = $this->formatPhone($recipient);
            
            if (!$formattedPhone) {
                error_log("Invalid phone number format: " . $recipient);
                return false;
            }
            
            $data = [
                'recipients' => [$formattedPhone],
                'message' => $message
            ];
            
            $headers = [
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                error_log("CURL Error sending SMS: " . curl_error($ch));
                curl_close($ch);
                return false;
            }
            
            curl_close($ch);
            
            // Check for success (201 Created is success for TextBee)
            if ($httpCode == 200 || $httpCode == 201) {
                error_log("SMS sent successfully to: " . $formattedPhone);
                return true;
            } else {
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['message'] ?? $response;
                error_log("Failed to send SMS to $formattedPhone. HTTP Code: $httpCode, Error: $errorMsg");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Exception sending SMS: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Format Philippine phone number for API
     */
    private function formatPhone($phone) {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if it's a valid Philippine mobile number
        if (strlen($cleaned) === 11 && substr($cleaned, 0, 2) === '09') {
            return '+63' . substr($cleaned, 1);
        }
        
        if (strlen($cleaned) === 12 && substr($cleaned, 0, 2) === '63') {
            return '+' . $cleaned;
        }
        
        if (strlen($phone) === 13 && substr($phone, 0, 3) === '+63') {
            return $phone;
        }
        
        return false; // Invalid format
    }
    
    /**
     * Send appointment confirmation SMS
     */
    public function sendAppointmentConfirmation($appointmentData) {
        $patientName = $appointmentData['patient_full_name'];
        $appointmentDate = $appointmentData['appointment_date'];
        $appointmentTime = $appointmentData['appointment_time'];
        $patientPhone = $appointmentData['patient_phone'];
        $appointmentId = $appointmentData['appointment_id'];
        $dentistName = $appointmentData['dentist_name'] ?? 'dentist';
        
        $dateFormatted = date('F j, Y', strtotime($appointmentDate));
        $timeFormatted = date('g:i A', strtotime($appointmentTime));
        
        $message = "Hi $patientName! Your dental appointment (ID: $appointmentId) at Cosmo Smiles Dental with $dentistName has been confirmed for $dateFormatted at $timeFormatted. Please arrive 10 minutes early. Reply STOP to unsubscribe.";
        
        return $this->sendSMS($patientPhone, $message);
    }
    
    /**
     * Send appointment cancellation SMS
     */
    public function sendAppointmentCancellation($appointmentData, $reason = '') {
        $patientName = $appointmentData['patient_full_name'];
        $appointmentDate = $appointmentData['appointment_date'];
        $appointmentTime = $appointmentData['appointment_time'];
        $patientPhone = $appointmentData['patient_phone'];
        $appointmentId = $appointmentData['appointment_id'];
        
        $dateFormatted = date('F j, Y', strtotime($appointmentDate));
        $timeFormatted = date('g:i A', strtotime($appointmentTime));
        
        $message = "Hi $patientName! Your dental appointment (ID: $appointmentId) at Cosmo Smiles Dental on $dateFormatted at $timeFormatted has been cancelled.";
        
        if (!empty($reason)) {
            $message .= " Reason: $reason";
        }
        
        $message .= " Please contact us at (02) 123-4567 to reschedule. Reply STOP to unsubscribe.";
        
        return $this->sendSMS($patientPhone, $message);
    }
}
?>