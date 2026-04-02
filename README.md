# Cosmo Smiles Dental Clinic

A modern, secure dental practice management system designed for Cosmo Smiles Dental Clinic. This application provides a comprehensive suite for patients, staff, and administrators to manage appointments, records, and clinic settings.

## 🚀 Features

- **Patient Portal**: Self-registration, profile management, and easy appointment booking.
- **Admin Dashboard**: Full control over staff, patients, appointments, and site content.
- **Staff Interface**: Specialized views for dentists and clinic staff to manage daily schedules and patient records.
- **Dual OTP Verification**: Secure account verification via both Email (PHPMailer) and SMS (TextBee).
- **Security First**: 
    - Google reCAPTCHA v3 integration on all authentication forms.
    - Progressive rate-limiting and DDoS protection.
    - Secure password hashing and session management.
- **Modern UI**: Responsive design with a premium aesthetic, using vanilla CSS and interactive JS.

## 🛠️ Technology Stack

- **Backend**: PHP (PDO for Database)
- **Frontend**: Vanilla HTML5, CSS3, JavaScript (ES6)
- **Email Service**: PHPMailer
- **SMS Service**: TextBee API
- **Security**: Google reCAPTCHA v3
- **Environment**: `.env` based configuration

## 📦 Installation & Setup

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd Cosmo_Smiles_Dental_Clinic
   ```

2. **Run the Setup Script**:
   This project includes an automated setup script to configure your environment.
   ```bash
   php setup.php
   ```
   *This will create your `.env` file and verify directory permissions.*

3. **Install Dependencies**:
   Ensure you have Composer installed, then run:
   ```bash
   composer install
   ```

4. **Configure Environment**:
   Open the `.env` file and fill in your credentials:
   - Database connection details.
   - SMTP credentials for Email.
   - TextBee API key for SMS.
   - Google reCAPTCHA v3 Site and Secret keys.

5. **Database Setup**:
   Import the project's database schema into your MySQL server.

## 🔒 Security Configuration

To enable reCAPTCHA v3:
1. Register your domain at [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin).
2. Add your **Site Key** and **Secret Key** to the `.env` file.
3. The system will automatically activate reCAPTCHA across all login and signup forms.

## 📄 License

This project is proprietary. All rights reserved.
