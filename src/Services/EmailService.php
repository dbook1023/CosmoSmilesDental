<?php
// src/Services/EmailService.php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/env.php';

class EmailService {
    private $resend;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $apiKey = env('RESEND_API_KEY');
        $this->fromEmail = env('RESEND_FROM_EMAIL', 'onboarding@resend.dev');
        $this->fromName = env('SMTP_FROM_NAME', 'Cosmo Smiles Dental');
        
        // Initialize Resend Client
        $this->resend = Resend::client($apiKey);
    }

    /**
     * Send OTP verification email
     */
    public function sendOTP($recipientEmail, $otpCode, $firstName = 'User') {
        try {
            $this->resend->emails->send([
                'from' => "{$this->fromName} <{$this->fromEmail}>",
                'to' => [$recipientEmail],
                'subject' => 'Your Verification Code - Cosmo Smiles Dental',
                'html' => $this->buildOTPEmailTemplate($otpCode, $firstName),
            ]);
            
            error_log("OTP email sent successfully via Resend to: " . $recipientEmail);
            return true;
            
        } catch (\Exception $e) {
            error_log("Failed to send OTP email via Resend to $recipientEmail: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Password Reset Email
     */
    public function sendPasswordResetEmail($recipientEmail, $resetLink, $firstName = 'User') {
        try {
            $this->resend->emails->send([
                'from' => "{$this->fromName} <{$this->fromEmail}>",
                'to' => [$recipientEmail],
                'subject' => 'Password Reset Request - Cosmo Smiles Dental',
                'html' => $this->buildResetEmailTemplate($resetLink, $firstName),
            ]);
            
            error_log("Password reset email sent successfully via Resend to: " . $recipientEmail);
            return true;
            
        } catch (\Exception $e) {
            error_log("Failed to send reset email via Resend to $recipientEmail: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build branded HTML email template for password reset
     */
    private function buildResetEmailTemplate($resetLink, $firstName) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0;padding:0;background-color:#f4f7fa;font-family:Arial,Helvetica,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f7fa;padding:40px 0;">
                <tr>
                    <td align="center">
                        <table width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.08);overflow:hidden;max-width:90%;">
                            <!-- Header -->
                            <tr>
                                <td style="background:linear-gradient(135deg,#03074f,#0d5bb9);padding:30px 40px;text-align:center;">
                                    <h1 style="color:#ffffff;margin:0;font-size:22px;">Cosmo Smiles Dental</h1>
                                    <p style="color:rgba(255,255,255,0.8);margin:8px 0 0;font-size:14px;">Password Reset Request</p>
                                </td>
                            </tr>
                            <!-- Body -->
                            <tr>
                                <td style="padding: 40px;">
                                    <p style="color:#2c3e50;font-size:16px;margin:0 0 20px;">Hi <strong>' . htmlspecialchars($firstName) . '</strong>,</p>
                                    <p style="color:#2c3e50;font-size:14px;line-height:1.6;margin:0 0 30px;">We received a request to reset your password. If you didn\'t make this request, please <strong>change your password immediately</strong> at our website or <strong>contact the administrator</strong> to secure your account.</p>
                                    
                                    <!-- Reset Button -->
                                    <div style="text-align:center;margin:0 0 30px;">
                                        <a href="' . $resetLink . '" style="background-color:#0d5bb9;color:#ffffff;padding:15px 30px;text-decoration:none;border-radius:8px;font-weight:bold;display:inline-block;">Reset Password</a>
                                    </div>
                                    
                                    <p style="color:#666;font-size:13px;line-height:1.5;margin:0 0 10px;">For security reasons, this link will expire in <strong>1 hour</strong>.</p>
                                    <p style="color:#999;font-size:12px;line-height:1.5;margin:0;">If the button above doesn\'t work, copy and paste this URL into your browser:</p>
                                    <p style="color:#0d5bb9;font-size:12px;word-break:break-all;margin:5px 0 0;">' . $resetLink . '</p>
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e1e5e9;">
                                    <p style="color:#999;font-size:12px;margin:0;">&copy; ' . date('Y') . ' Cosmo Smiles Dental Clinic. All rights reserved.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
    
    /**
     * Build branded HTML email template for OTP
     */
    private function buildOTPEmailTemplate($otpCode, $firstName) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0;padding:0;background-color:#f4f7fa;font-family:Arial,Helvetica,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f7fa;padding:40px 0;">
                <tr>
                    <td align="center">
                        <table width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.08);overflow:hidden;max-width:90%;">
                            <!-- Header -->
                            <tr>
                                <td style="background:linear-gradient(135deg,#03074f,#0d5bb9);padding:30px 40px;text-align:center;">
                                    <h1 style="color:#ffffff;margin:0;font-size:22px;">Cosmo Smiles Dental</h1>
                                    <p style="color:rgba(255,255,255,0.8);margin:8px 0 0;font-size:14px;">Email Verification</p>
                                </td>
                            </tr>
                            <!-- Body -->
                            <tr>
                                <td style="padding:40px;">
                                    <p style="color:#2c3e50;font-size:16px;margin:0 0 20px;">Hi <strong>' . htmlspecialchars($firstName) . '</strong>,</p>
                                    <p style="color:#2c3e50;font-size:14px;line-height:1.6;margin:0 0 30px;">Please use the verification code below to complete your sign-up. This code is valid for <strong>5 minutes</strong>.</p>
                                    
                                    <!-- OTP Code Box -->
                                    <div style="background:#f0f4ff;border:2px dashed #0d5bb9;border-radius:10px;padding:25px;text-align:center;margin:0 0 30px;">
                                        <p style="color:#666;font-size:12px;margin:0 0 10px;text-transform:uppercase;letter-spacing:2px;">Your Verification Code</p>
                                        <p style="color:#03074f;font-size:36px;font-weight:bold;letter-spacing:8px;margin:0;">' . $otpCode . '</p>
                                    </div>
                                    
                                    <p style="color:#999;font-size:12px;line-height:1.5;margin:0;">If you did not request this code, you can safely ignore this email. Do not share this code with anyone.</p>
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e1e5e9;">
                                    <p style="color:#999;font-size:12px;margin:0;">&copy; ' . date('Y') . ' Cosmo Smiles Dental Clinic. All rights reserved.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
}
?>
