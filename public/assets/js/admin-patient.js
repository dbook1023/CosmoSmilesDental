// admin-patients.js

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
    setupEventListeners();
    showSystemMessages();
    setupFormValidation();
    setupAgeIndicators();
});

// Global variables
let currentViewPatientId = null;

// Initialize page elements
function initializePage() {
    // Standardized Admin Clock
    updateAdminClock();
    setInterval(updateAdminClock, 1000);
}

function updateAdminClock() {
    const now = new Date();
    const dateOptions = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
    const timeOptions = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
    
    const dateEl = document.getElementById('admin-date');
    const timeEl = document.getElementById('admin-time');
    
    if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
    if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
}

// Setup all event listeners
function setupEventListeners() {
    setupSidebarToggle();
    setupModalFunctionality();
    setupFormHandlers();
    setupFilterForm();
    setupExportFunction();
}

// Mobile sidebar toggle
function setupSidebarToggle() {
    const hamburger = document.querySelector('.hamburger');
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.overlay');
    const sidebarLinks = document.querySelectorAll('.sidebar-item');

    if (hamburger) {
        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Close sidebar when clicking on a link (for mobile)
    sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        });
    });

    // Responsive sidebar behavior
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    });
}

// Modal functionality
function setupModalFunctionality() {
    // Add patient modal
    const addPatientBtn = document.getElementById('add-patient-btn');
    const addPatientBtnTable = document.getElementById('add-patient-btn-table');
    const addPatientModal = document.getElementById('add-patient-modal');
    const viewPatientModal = document.getElementById('view-patient-modal');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    const modals = document.querySelectorAll('.modal');

    // Add patient modal triggers
    if (addPatientBtn) {
        addPatientBtn.addEventListener('click', () => {
            showModal(addPatientModal);
        });
    }

    if (addPatientBtnTable) {
        addPatientBtnTable.addEventListener('click', () => {
            showModal(addPatientModal);
        });
    }

    // Close modals
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            hideModal(modal);
        });
    });

    // Cancel buttons
    document.getElementById('cancel-add-patient')?.addEventListener('click', () => {
        hideModal(addPatientModal);
        document.getElementById('add-patient-form')?.reset();
        document.getElementById('parentalConsentContainer').style.display = 'none';
    });

    document.getElementById('close-view-patient')?.addEventListener('click', () => {
        hideModal(viewPatientModal);
    });

    // Close modals on overlay click
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal(this);
            }
        });
    });

    // Birthdate change handler for parental consent
    const birthdateInput = document.getElementById('birthdate');
    const parentalConsentContainer = document.getElementById('parentalConsentContainer');
    
    if (birthdateInput && parentalConsentContainer) {
        birthdateInput.addEventListener('change', function() {
            updateParentalConsentVisibility(this.value, parentalConsentContainer, 'parental_consent');
        });
    }
}

// Show modal with animation
function showModal(modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Hide modal with animation
function hideModal(modal) {
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Show system messages
function showSystemMessages() {
    const messagesContainer = document.getElementById('systemMessages');
    
    // Check if there are messages in the data attributes
    const successMessage = document.body.getAttribute('data-success-message');
    const errorMessage = document.body.getAttribute('data-error-message');
    
    if (successMessage) {
        showMessage(successMessage, 'success');
        document.body.removeAttribute('data-success-message');
    }
    
    if (errorMessage) {
        showMessage(errorMessage, 'error');
        document.body.removeAttribute('data-error-message');
    }
}

// Show message alert
function showMessage(message, type) {
    const messagesContainer = document.getElementById('systemMessages');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    alertDiv.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
    
    messagesContainer.appendChild(alertDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        alertDiv.style.transform = 'translateX(100%)';
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}

// View patient details
async function viewPatient(patientId) {
    try {
        currentViewPatientId = patientId;
        
        // Show loading state
        const viewModalBody = document.getElementById('view-patient-body');
        viewModalBody.innerHTML = `
            <div class="modal-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading patient details...</p>
            </div>
        `;
        
        showModal(document.getElementById('view-patient-modal'));
        
        const response = await fetch(`?action=get_patient_details&id=${patientId}`);
        const data = await response.json();
        
        console.log("Patient data received:", data); // Debug log
        
        if (data.success) {
            displayPatientDetails(data.patient);
        } else {
            viewModalBody.innerHTML = `
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    ${data.message || 'Error loading patient details'}
                </div>
            `;
        }
    } catch (error) {
        console.error('Error viewing patient:', error);
        document.getElementById('view-patient-body').innerHTML = `
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                Error loading patient details
            </div>
        `;
    }
}

// Display patient details in modal - WITH FULL ADDRESS AND PROFILE IMAGE
function displayPatientDetails(patient) {
    console.log("Displaying patient:", patient); // Debug log
    console.log("Address fields:", {
        address_line1: patient.address_line1,
        address_line2: patient.address_line2,
        city: patient.city,
        state: patient.state,
        postal_code: patient.postal_code,
        country: patient.country,
        full_address: patient.full_address
    });
    
    // Calculate age
    const birthdate = new Date(patient.birthdate);
    const today = new Date();
    let age = today.getFullYear() - birthdate.getFullYear();
    const monthDiff = today.getMonth() - birthdate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
        age--;
    }
    
    // Format dates safely for older browsers/Safari
    const safeFormat = (dateStr) => dateStr ? dateStr.replace(' ', 'T') : null;
    const createdDate = new Date(safeFormat(patient.created_at));
    const updatedDate = new Date(safeFormat(patient.updated_at));
    const lastVisit = patient.last_visit ? new Date(safeFormat(patient.last_visit)).toLocaleDateString() : 'Never';
    
    // Determine status (based on appointments in last 90 days) - using is_active from the controller
    const isActive = patient.is_active === true || patient.is_active === 1;
    const status = isActive ? 'Active' : 'Inactive';
    const statusClass = isActive ? 'status-active' : 'status-inactive';
    
    // Get patient initial for avatar fallback
    const patientInitial = patient.first_name ? patient.first_name.charAt(0).toUpperCase() : '?';
    
    // Check if profile image exists
    let profileImageHtml = '';
    if (patient.profile_image) {
        // Clean up the profile image path
        let imagePath = patient.profile_image;
        // Remove any leading slashes or duplicate paths
        if (imagePath.startsWith('/')) {
            imagePath = imagePath.substring(1);
        }
        // Construct the full URL
        const fullImageUrl = (window.URL_ROOT || '/Cosmo_Smiles_Dental_Clinic/') + imagePath;
        console.log("Profile image URL:", fullImageUrl); // Debug log
        profileImageHtml = `<img src="${fullImageUrl}" alt="${patient.first_name} ${patient.last_name}" onerror="this.onerror=null; this.parentNode.innerHTML='<div class=\\'patient-avatar-large\\'>${patientInitial}</div>';">`;
    } else {
        profileImageHtml = `<div class="patient-avatar-large">${patientInitial}</div>`;
    }
    
    // Build address parts for display
    const addressParts = [];
    if (patient.address_line1) addressParts.push(patient.address_line1);
    if (patient.address_line2) addressParts.push(patient.address_line2);
    
    const cityState = [];
    if (patient.city) cityState.push(patient.city);
    if (patient.state) cityState.push(patient.state);
    if (patient.postal_code) cityState.push(patient.postal_code);
    
    if (cityState.length > 0) addressParts.push(cityState.join(', '));
    if (patient.country) addressParts.push(patient.country);
    
    const fullAddress = addressParts.length > 0 ? addressParts.join(', ') : 'No address provided';
    
    // Build address display with line breaks for better readability
    const addressDisplay = fullAddress.split(', ').join('<br>');
    
    const content = `
        <div class="patient-details-view">
            <div class="patient-header">
                ${profileImageHtml}
                <div class="patient-title">
                    <h4>${patient.first_name || ''} ${patient.last_name || ''}</h4>
                    <p>Patient ID: ${patient.client_id || 'N/A'}</p>
                </div>
            </div>
            
            <div class="details-grid">
                <div class="detail-item">
                    <label>Age</label>
                    <span>${age} years</span>
                </div>
                <div class="detail-item">
                    <label>Gender</label>
                    <span>${patient.gender ? patient.gender.charAt(0).toUpperCase() + patient.gender.slice(1) : 'Not specified'}</span>
                </div>
                <div class="detail-item">
                    <label>Date of Birth</label>
                    <span>${patient.birthdate ? new Date(safeFormat(patient.birthdate)).toLocaleDateString() : 'Not specified'}</span>
                </div>
                <div class="detail-item">
                    <label>Email</label>
                    <span>${patient.email || 'Not provided'}</span>
                </div>
                <div class="detail-item">
                    <label>Phone</label>
                    <span>${patient.phone || 'Not provided'}</span>
                </div>
                <div class="detail-item" style="grid-column: span 2;">
                    <label>Street Address</label>
                    <span>${patient.address_line1 || 'Not provided'} ${patient.address_line2 ? '<br>' + patient.address_line2 : ''}</span>
                </div>
                <div class="detail-item">
                    <label>City</label>
                    <span>${patient.city || 'Not provided'}</span>
                </div>
                <div class="detail-item">
                    <label>State/Province</label>
                    <span>${patient.state || 'Not provided'}</span>
                </div>
                <div class="detail-item">
                    <label>Postal Code</label>
                    <span>${patient.postal_code || 'Not provided'}</span>
                </div>
                <div class="detail-item">
                    <label>Country</label>
                    <span>${patient.country || 'Philippines'}</span>
                </div>
                <div class="detail-item" style="grid-column: span 2;">
                    <label>Full Address</label>
                    <span style="line-height: 1.6; word-wrap: break-word; white-space: pre-line; background-color: #f8fafc; padding: 12px 15px; border-radius: 8px; border-left: 3px solid var(--accent);">${addressDisplay}</span>
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <span class="patient-status ${statusClass}">${status}</span>
                </div>
                <div class="detail-item">
                    <label>Last Visit</label>
                    <span>${lastVisit}</span>
                </div>
                <div class="detail-item">
                    <label>Account Created</label>
                    <span>${createdDate.toLocaleDateString()}</span>
                </div>
                <div class="detail-item">
                    <label>Last Updated</label>
                    <span>${updatedDate.toLocaleDateString()}</span>
                </div>
                ${patient.is_minor ? `
                <div class="detail-item">
                    <label>Minor Status</label>
                    <span>Under 18 years old</span>
                </div>
                ${patient.parental_consent ? `
                <div class="detail-item">
                    <label>Parental Consent</label>
                    <span class="text-success"><i class="fas fa-check-circle"></i> Given</span>
                </div>
                ` : `
                <div class="detail-item">
                    <label>Parental Consent</label>
                    <span class="text-warning"><i class="fas fa-exclamation-circle"></i> Not Given</span>
                </div>
                `}
                ` : ''}
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: var(--light-accent); border-radius: 8px; border-left: 4px solid var(--accent);">
                <p style="margin: 0; color: var(--primary); font-weight: 600; font-size: 0.95rem;">
                    <i class="fas fa-info-circle"></i> Patient ID: <strong>${patient.client_id || 'N/A'}</strong> | Created: ${createdDate.toLocaleDateString()}
                </p>
            </div>

            <!-- Medical History Section -->
            <div class="medical-history-section">
                <h4><i class="fas fa-notes-medical"></i> Medical History</h4>
                ${patient.medical_history ? `
                    <div class="medical-grid">
                        <div class="medical-card ${patient.medical_history.heart_disease == 1 ? 'critical' : ''}">
                            <h5>
                                <i class="fas fa-heartbeat"></i> Heart Disease
                                <span class="medical-badge ${patient.medical_history.heart_disease == 1 ? 'badge-yes' : 'badge-no'}">
                                    ${patient.medical_history.heart_disease == 1 ? 'Yes' : 'No'}
                                </span>
                            </h5>
                            ${patient.medical_history.heart_disease_details ? `
                                <div class="exam-details-text">${patient.medical_history.heart_disease_details}</div>
                            ` : ''}
                        </div>

                        <div class="medical-card ${patient.medical_history.high_blood_pressure == 1 ? 'critical' : ''}">
                            <h5>
                                <i class="fas fa-tint"></i> Blood Pressure
                                <span class="medical-badge ${patient.medical_history.high_blood_pressure == 1 ? 'badge-yes' : 'badge-no'}">
                                    ${patient.medical_history.high_blood_pressure == 1 ? 'High' : 'Normal'}
                                </span>
                            </h5>
                        </div>

                        <div class="medical-card ${patient.medical_history.diabetes == 1 ? 'critical' : ''}">
                            <h5>
                                <i class="fas fa-disease"></i> Diabetes
                                <span class="medical-badge ${patient.medical_history.diabetes == 1 ? 'badge-yes' : 'badge-no'}">
                                    ${patient.medical_history.diabetes == 1 ? 'Yes' : 'No'}
                                </span>
                            </h5>
                        </div>

                        <div class="medical-card ${patient.medical_history.allergies ? 'critical' : ''}">
                            <h5><i class="fas fa-allergies"></i> Allergies</h5>
                            <p>${patient.medical_history.allergies || 'None reported'}</p>
                        </div>

                        <div class="medical-card">
                            <h5><i class="fas fa-pills"></i> Medications</h5>
                            <p>${patient.medical_history.current_medications || 'None'}</p>
                        </div>

                        <div class="medical-card">
                            <h5><i class="fas fa-history"></i> Past Surgeries</h5>
                            <p>${patient.medical_history.past_surgeries || 'None'}</p>
                        </div>

                        <div class="medical-card">
                            <h5><i class="fas fa-file-medical-alt"></i> Other Conditions</h5>
                            <p>${patient.medical_history.other_conditions || 'None'}</p>
                        </div>
                        
                        ${patient.gender === 'female' ? `
                        <div class="medical-card">
                            <h5>
                                <i class="fas fa-baby"></i> Pregnant
                                <span class="medical-badge ${patient.medical_history.is_pregnant == 1 ? 'badge-yes' : 'badge-no'}">
                                    ${patient.medical_history.is_pregnant == 1 ? 'Yes' : 'No'}
                                </span>
                            </h5>
                        </div>
                        ` : ''}
                    </div>
                    <p style="margin-top: 15px; font-size: 0.8rem; color: #666; text-align: right;">
                        Last Updated: ${(() => {
                            if (!patient.medical_history.updated_at) return 'Unknown date';
                            const safeDateString = patient.medical_history.updated_at.replace(' ', 'T');
                            const d = new Date(safeDateString);
                            if (isNaN(d.getTime())) return patient.medical_history.updated_at;
                            return d.toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
                        })()}
                    </p>
                ` : `
                    <div class="no-history-msg">
                        <i class="fas fa-exclamation-triangle"></i> Patient has not completed the medical history exam yet.
                    </div>
                `}
            </div>
        </div>
    `;
    
    document.getElementById('view-patient-body').innerHTML = content;
}

// Update parental consent visibility
function updateParentalConsentVisibility(birthdateString, containerElement, checkboxId) {
    if (!birthdateString) {
        containerElement.style.display = 'none';
        return;
    }
    
    const birthdate = new Date(birthdateString);
    const today = new Date();
    let age = today.getFullYear() - birthdate.getFullYear();
    const monthDiff = today.getMonth() - birthdate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
        age--;
    }
    
    if (age < 18) {
        containerElement.style.display = 'block';
    } else {
        containerElement.style.display = 'none';
        document.getElementById(checkboxId).checked = false;
    }
}

// Setup form handlers
function setupFormHandlers() {
    const forms = document.querySelectorAll('form[id$="-patient-form"]');
    
    forms.forEach(form => {
        // Add loading state to submit buttons
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.dataset.originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
            }
        });
    });
}

// Setup filter form
function setupFilterForm() {
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        // Add real-time search if needed
        const searchInput = document.getElementById('search-filter');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 3 || this.value.length === 0) {
                        filterForm.submit();
                    }
                }, 500);
            });
        }
    }
}

// Setup export function
function setupExportFunction() {
    const exportBtn = document.querySelector('button[onclick="exportPatients()"]');
    if (exportBtn) {
        exportBtn.addEventListener('click', exportPatients);
    }
}

// Export patients
function exportPatients() {
    const filters = {
        status: document.getElementById('status-filter')?.value || 'all',
        gender: document.getElementById('gender-filter')?.value || 'all',
        is_minor: document.getElementById('minor-filter')?.value || 'all',
        search: document.getElementById('search-filter')?.value || ''
    };
    
    const queryString = new URLSearchParams(filters).toString();
    window.location.href = `?action=export_patients&${queryString}`;
}

// Form validation with real-time feedback
function setupFormValidation() {
    const forms = document.querySelectorAll('form[id$="-patient-form"]');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('.form-control[required]');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid', 'is-valid');
                const feedback = this.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.remove();
                }
            });
        });
    });
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    
    field.classList.remove('is-invalid', 'is-valid');
    
    let isValid = true;
    let errorMessage = '';
    
    // Check required
    if (field.required && !value) {
        isValid = false;
        errorMessage = 'This field is required';
    }
    
    // Check email
    if (type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address';
        }
    }
    
    // Check phone
    if (field.name === 'phone' && value) {
        const phoneRegex = /^09[0-9]{9}$/;
        if (!phoneRegex.test(value)) {
            isValid = false;
            errorMessage = 'Must be 11 digits starting with 09';
        }
    }
    
    // Check date
    if (type === 'date' && value) {
        const selectedDate = new Date(value);
        const today = new Date();
        if (selectedDate > today) {
            isValid = false;
            errorMessage = 'Date cannot be in the future';
        }
    }
    
    if (!isValid) {
        field.classList.add('is-invalid');
        showFieldError(field, errorMessage);
    } else if (value) {
        field.classList.add('is-valid');
    }
}

function showFieldError(field, message) {
    // Remove existing error message
    const existingError = field.nextElementSibling;
    if (existingError && existingError.classList.contains('invalid-feedback')) {
        existingError.remove();
    }
    
    // Add new error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = message;
    field.parentNode.insertBefore(errorDiv, field.nextSibling);
}

// Age indicator update for birthdate fields
function setupAgeIndicators() {
    const birthdateInputs = document.querySelectorAll('input[type="date"]');
    
    birthdateInputs.forEach(input => {
        input.addEventListener('change', function() {
            updateAgeIndicator(this);
        });
        
        // Initial check
        if (input.value) {
            updateAgeIndicator(input);
        }
    });
}

function updateAgeIndicator(input) {
    // Remove existing indicator
    const existingIndicator = input.parentNode.querySelector('.age-indicator');
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    if (!input.value) return;
    
    const birthdate = new Date(input.value);
    const today = new Date();
    let age = today.getFullYear() - birthdate.getFullYear();
    const monthDiff = today.getMonth() - birthdate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
        age--;
    }
    
    const indicator = document.createElement('span');
    indicator.className = `age-indicator ${age < 18 ? 'age-minor' : 'age-adult'}`;
    indicator.textContent = `${age} years old`;
    indicator.title = age < 18 ? 'Minor (Under 18)' : 'Adult (18+)';
    
    // Find the label to insert after
    const label = input.parentNode.querySelector('label');
    if (label) {
        label.appendChild(indicator);
    }
}

// Make functions available globally
window.viewPatient = viewPatient;
window.exportPatients = exportPatients;