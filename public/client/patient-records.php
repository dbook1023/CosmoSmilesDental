<?php
// Start session
session_start();

// Check if user is logged in as CLIENT (not staff/admin)
if (!isset($_SESSION['client_id']) || !isset($_SESSION['client_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Include the controller
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../src/Controllers/ClientRecordsController.php';

// Initialize controller
$controller = new ClientRecordsController();
$data = $controller->getPatientRecords();

// If there's an error, show it
if (!$data['success']) {
    echo "<div style='color: red; padding: 20px; text-align: center;'>Error: " . htmlspecialchars($data['error']) . "</div>";
    exit();
}

// Get user profile image if logged in
$profileImage = null;
$client_id = $_SESSION['client_id'] ?? null;
if ($client_id) {
    $sql = "SELECT profile_image FROM clients WHERE id = :id LIMIT 1";
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $client_id);
        $stmt->execute();
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userData && !empty($userData['profile_image'])) {
            $profileImage = $userData['profile_image'];
        }
    } catch (Exception $e) {
        error_log("Error fetching profile image: " . $e->getMessage());
    }
}

// Map user name for header
$userName = $data['user_name'] ?? 'My Account';
$isLoggedIn = true; // They are logged in if they reached here
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/x-icon" href="<?php echo clean_url('public/assets/images/logo1-white.png'); ?>">
  <title>Patient Records - Cosmo Smiles Dental</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?php echo clean_url('public/assets/css/patient-records.css'); ?>">
  <?php include 'includes/client-header-css.php'; ?>
</head>

<body>
    <?php 
    $baseDir = '../'; 
    include 'includes/client-header.php'; 
    ?>

  <!-- Patient Records Section -->
  <section class="patient-records">
    <div class="container">
      <div class="records-header">
        <div class="header-content">
          <h1 class="records-title">Patient Records</h1>
          <p class="records-subtitle">View and download your dental treatment records</p>
          <p class="records-count">You have <?php echo $data['record_count']; ?> medical record(s)</p>
        </div>
        <div class="header-actions">
          <?php if($data['medical_history_status'] === 'pending'): ?>
          <button class="action-btn warning" id="openExamBtn">
            <i class="fas fa-exclamation-triangle"></i> Complete Medical Exam
          </button>
          <?php else: ?>
          <button class="action-btn secondary" id="viewExamBtn">
            <i class="fas fa-clipboard-list"></i> View Medical History
          </button>
          <?php if($data['medical_history_edit_allowed']): ?>
          <button class="action-btn" id="editExamBtn" style="background: #28a745; color: white;">
            <i class="fas fa-edit"></i> Update Medical History
          </button>
          <?php elseif($data['pending_edit_request']): ?>
          <button class="action-btn" disabled style="opacity: 0.6; cursor: not-allowed;">
            <i class="fas fa-clock"></i> Update Request Pending
          </button>
          <?php else: ?>
          <button class="action-btn" id="requestEditBtn">
            <i class="fas fa-pen-to-square"></i> Request Update
          </button>
          <?php endif; ?>
          <?php endif; ?>
          
          <?php if($data['record_count'] > 0): ?>
          <button class="action-btn" id="exportAllBtn">
            <i class="fas fa-download"></i> Export All Records
          </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="records-content">
        <div class="records-list">
          <div class="section-header">
            <h2>Treatment History</h2>
            <div class="filter-options">
              <select class="filter-select" id="recordFilter">
                <option value="all">All Records</option>
                <option value="treatment">Treatment</option>
                <option value="consultation">Consultation</option>
                <option value="xray">X-Ray</option>
                <option value="prescription">Prescription</option>
                <option value="followup">Follow-up</option>
                <option value="emergency">Emergency</option>
              </select>
            </div>
          </div>

          <div class="records-cards" id="recordsContainer">
            <?php if(empty($data['records'])): ?>
              <div class="no-records" style="text-align: center; padding: 40px; color: var(--dark);">
                <i class="fas fa-file-medical" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i>
                <h3>No records found</h3>
                <p>You don't have any medical records yet.</p>
                <?php if($data['medical_history_status'] === 'completed'): ?>
                <a href="new-appointments.php" class="action-btn" style="margin-top: 20px; display: inline-block;">
                  <i class="fas fa-calendar-plus"></i> Book Your First Appointment
                </a>
                <?php else: ?>
                <p style="margin-top: 20px; color: var(--error); font-weight: 600;">Please complete your Medical History Exam above to enable appointment booking.</p>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <?php foreach($data['records'] as $record): ?>
                <div class="record-card" data-record-type="<?php echo htmlspecialchars($record['record_type']); ?>">
                  <div class="record-header">
                    <div class="record-date">
                      <span class="date"><?php echo $controller->formatDate($record['record_date']); ?></span>
                      <span class="time"><?php echo $controller->formatTime($record['record_time']); ?></span>
                    </div>
                    <span class="record-type-badge"><?php echo ucfirst($record['record_type']); ?></span>
                  </div>
                  <div class="record-content">
                    <h4><?php echo htmlspecialchars($record['record_title']); ?></h4>
                    <p class="record-desc"><?php echo htmlspecialchars($record['description']); ?></p>
                    
                    <?php if (!empty($record['findings'])): ?>
                    <div class="record-findings-preview">
                      <i class="fas fa-notes-medical"></i>
                      <span><?php echo htmlspecialchars(substr($record['findings'], 0, 150)) . (strlen($record['findings']) > 150 ? '...' : ''); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php 
                    $files = json_decode($record['files'], true);
                    if (!empty($files)): 
                    ?>
                    <div class="record-attachments-badge">
                      <i class="fas fa-paperclip"></i>
                      <span><?php echo count($files); ?> Attachment(s)</span>
                    </div>
                    <?php endif; ?>

                    <div class="record-details">
                      <div class="detail-item">
                        <span class="detail-label">Dentist</span>
                        <span class="detail-value"><?php echo htmlspecialchars($record['dentist']); ?></span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-label">Duration</span>
                        <span class="detail-value"><?php echo htmlspecialchars($record['duration'] ?: 'Not specified'); ?></span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-label">Record ID</span>
                        <span class="detail-value"><?php echo htmlspecialchars($record['record_id']); ?></span>
                      </div>
                    </div>
                    <div class="record-actions">
                      <button class="view-btn" 
                              data-record-id="<?php echo $record['id']; ?>"
                              data-record-title="<?php echo htmlspecialchars($record['record_title']); ?>"
                              data-record-date="<?php echo $record['record_date']; ?>"
                              data-record-time="<?php echo $record['record_time']; ?>"
                              data-dentist="<?php echo htmlspecialchars($record['dentist']); ?>"
                              data-duration="<?php echo htmlspecialchars($record['duration']); ?>"
                              data-procedure="<?php echo htmlspecialchars($record['procedure']); ?>"
                              data-description="<?php echo htmlspecialchars($record['description']); ?>"
                              data-notes="<?php echo htmlspecialchars($record['notes']); ?>"
                              data-followup="<?php echo htmlspecialchars($record['followup_instructions']); ?>"
                              data-tooth-numbers="<?php echo htmlspecialchars($record['tooth_numbers']); ?>"
                              data-surfaces="<?php echo htmlspecialchars($record['surfaces']); ?>"
                              data-record-type="<?php echo htmlspecialchars($record['record_type']); ?>" data-findings="<?php echo htmlspecialchars($record['findings']); ?>" data-files='<?php echo htmlspecialchars($record['files']); ?>'>
                        <i class="fas fa-eye"></i> View Details
                      </button>
                      <button class="download-btn" data-record-id="<?php echo $record['id']; ?>">
                        <i class="fas fa-download"></i> Download Report
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Pagination - Will be handled by JavaScript if needed -->
          <?php if($data['record_count'] > 0): ?>
          <div class="pagination" id="pagination">
            <!-- Pagination will be populated by JavaScript -->
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Record Details Modal -->
  <div class="modal" id="recordModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Record Details</h3>
        <button class="close-modal">&times;</button>
      </div>
      <div class="modal-body" id="modalBody">
        <!-- Modal content will be inserted here by JavaScript -->
      </div>
    </div>
  </div>

  <!-- Medical Exam Modal -->
  <?php
    $mh = $data['medical_history'] ?? null;
    $isCompleted = ($data['medical_history_status'] === 'completed');
    $editAllowed = !empty($data['medical_history_edit_allowed']);
    $isEditing = $isCompleted && $editAllowed;
  ?>
  <div class="modal" id="medicalExamModal" <?php echo (isset($_GET['exam']) && $_GET['exam'] === 'pending' && $data['medical_history_status'] === 'pending') ? 'style="display: flex;"' : ''; ?>>
    <div class="modal-content medical-exam-content">
      <div class="modal-header">
        <h3><?php echo $isEditing ? 'Update Medical History' : ($isCompleted ? 'Medical History' : 'Mandatory Medical History Exam'); ?></h3>
        <?php if($isCompleted): ?>
        <button class="close-modal">&times;</button>
        <?php endif; ?>
      </div>
      <div class="modal-body">
        <div class="exam-intro">
          <i class="fas fa-info-circle"></i>
          <?php if($isEditing): ?>
            You have been granted permission to update your medical history. Please review and update your information below.
          <?php elseif($isCompleted): ?>
            Your medical history assessment is complete. The information below is read-only. To make changes, request an update and wait for dentist approval.
          <?php else: ?>
            Welcome to your personal health assessment. This information ensures your dental treatments are safe and perfectly tailored to your health profile. This is a one-time process.
          <?php endif; ?>
        </div>
        
        <form id="medicalExamForm">
          <input type="hidden" name="action" value="<?php echo $isEditing ? 'update_medical_history' : 'submit_medical_history'; ?>">
          
          <div class="exam-section">
            <h4><i class="fas fa-heartbeat"></i> Cardiovascular Health</h4>
            <div class="question-grid">
              <div class="question-group">
                <span class="question-text">Do you have any heart diseases?</span>
                <div class="radio-options">
                  <label class="radio-btn-label">
                    <input type="radio" name="heart_disease" value="1" required <?php echo ($mh && $mh['heart_disease'] == 1) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> Yes
                  </label>
                  <label class="radio-btn-label">
                    <input type="radio" name="heart_disease" value="0" <?php echo (!$mh || $mh['heart_disease'] == 0) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> No
                  </label>
                </div>
                <div class="details-container<?php echo ($mh && $mh['heart_disease'] == 1) ? ' show' : ''; ?>" id="details-heart_disease">
                  <textarea name="heart_disease_details" placeholder="Please specify condition (e.g. valve issues, pacemaker)..." class="exam-details"><?php echo htmlspecialchars($mh['heart_disease_details'] ?? ''); ?></textarea>
                </div>
              </div>

              <div class="question-group">
                <span class="question-text">Do you have high blood pressure?</span>
                <div class="radio-options">
                  <label class="radio-btn-label">
                    <input type="radio" name="high_blood_pressure" value="1" required <?php echo ($mh && $mh['high_blood_pressure'] == 1) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> Yes
                  </label>
                  <label class="radio-btn-label">
                    <input type="radio" name="high_blood_pressure" value="0" <?php echo (!$mh || $mh['high_blood_pressure'] == 0) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> No
                  </label>
                </div>
              </div>

              <div class="question-group">
                <span class="question-text">Do you have diabetes?</span>
                <div class="radio-options">
                  <label class="radio-btn-label">
                    <input type="radio" name="diabetes" value="1" required <?php echo ($mh && $mh['diabetes'] == 1) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> Yes
                  </label>
                  <label class="radio-btn-label">
                    <input type="radio" name="diabetes" value="0" <?php echo (!$mh || $mh['diabetes'] == 0) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> No
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="exam-section">
            <h4><i class="fas fa-pills"></i> Allergies & Medications</h4>
            <div class="question-grid">
              <div class="question-group">
                <span class="question-text">Do you have any allergies?</span>
                <div class="radio-options">
                  <label class="radio-btn-label">
                    <input type="radio" name="has_allergies" value="1" required <?php echo ($mh && !empty($mh['allergies'])) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> Yes
                  </label>
                  <label class="radio-btn-label">
                    <input type="radio" name="has_allergies" value="0" <?php echo (!$mh || empty($mh['allergies'])) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> No
                  </label>
                </div>
                <div class="details-container<?php echo ($mh && !empty($mh['allergies'])) ? ' show' : ''; ?>" id="details-has_allergies">
                  <textarea name="allergies" placeholder="List allergies (e.g. Penicillin, Latex, Food)..." class="exam-details"><?php echo htmlspecialchars($mh['allergies'] ?? ''); ?></textarea>
                </div>
              </div>

              <div class="question-group">
                <span class="question-text">Are you taking any medications?</span>
                <div class="radio-options">
                  <label class="radio-btn-label">
                    <input type="radio" name="taking_meds" value="1" required <?php echo ($mh && !empty($mh['current_medications'])) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> Yes
                  </label>
                  <label class="radio-btn-label">
                    <input type="radio" name="taking_meds" value="0" <?php echo (!$mh || empty($mh['current_medications'])) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> No
                  </label>
                </div>
                <div class="details-container<?php echo ($mh && !empty($mh['current_medications'])) ? ' show' : ''; ?>" id="details-taking_meds">
                  <textarea name="current_medications" placeholder="List medications and dosage..." class="exam-details"><?php echo htmlspecialchars($mh['current_medications'] ?? ''); ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="exam-section">
            <h4><i class="fas fa-notes-medical"></i> Clinical History</h4>
            <div class="question-grid">
              <div class="question-group">
                <span class="question-text">Past surgeries or hospitalizations?</span>
                <textarea name="past_surgeries" placeholder="Describe past surgeries and approximate dates..." class="exam-textarea"><?php echo htmlspecialchars($mh['past_surgeries'] ?? ''); ?></textarea>
              </div>

              <div class="question-group">
                <span class="question-text">Other medical conditions?</span>
                <textarea name="other_conditions" placeholder="e.g. Asthma, Epilepsy, Kidney issues..." class="exam-textarea"><?php echo htmlspecialchars($mh['other_conditions'] ?? ''); ?></textarea>
              </div>

              <div class="question-group">
                <span class="question-text">Are you pregnant?</span>
                <div class="radio-options">
                  <label class="radio-btn-label">
                    <input type="radio" name="is_pregnant" value="1" required <?php echo ($mh && $mh['is_pregnant'] == 1) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> Yes
                  </label>
                  <label class="radio-btn-label">
                    <input type="radio" name="is_pregnant" value="0" <?php echo (!$mh || $mh['is_pregnant'] == 0) ? 'checked' : ''; ?>>
                    <span class="custom-radio"></span> No
                  </label>
                </div>
              </div>
            </div>
          </div>

          <?php if(!$isCompleted || $isEditing): ?>
          <div class="submit-exam-footer">
            <button type="submit" class="submit-exam-btn" id="submitExamBtn">
              <?php echo $isEditing ? 'Update Assessment' : 'Confirm & Complete Assessment'; ?>
            </button>
          </div>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

<script src="<?php echo clean_url('public/assets/js/patient-records.js'); ?>"></script>
</body>
</html>