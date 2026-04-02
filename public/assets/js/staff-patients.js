// Set current date
const currentDate = new Date();
const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
document.getElementById('current-date').textContent = currentDate.toLocaleDateString('en-PH', options);

// Mobile sidebar toggle
const hamburger = document.querySelector('.hamburger');
const sidebar = document.querySelector('.admin-sidebar');
const overlay = document.querySelector('.overlay');

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
const sidebarLinks = document.querySelectorAll('.sidebar-item');
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

// Modal functionality
const addPatientBtn = document.getElementById('add-patient-btn');
const addPatientModal = document.getElementById('add-patient-modal');
const closeModalBtn = document.querySelector('.close-modal');
const cancelAddPatientBtn = document.getElementById('cancel-add-patient');

// View Patient Modal elements
const viewPatientModal = document.getElementById('viewPatientModal');

// Open Add Patient modal
if (addPatientBtn) {
    addPatientBtn.addEventListener('click', () => {
        if (addPatientModal) {
            // Reset form
            document.getElementById('add-patient-form').reset();
            addPatientModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    });
}

// Close modals function
function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.classList.remove('active');
    });
    document.body.style.overflow = 'auto';
}

// Close modal buttons
if (closeModalBtn) {
    closeModalBtn.addEventListener('click', closeAllModals);
}

if (cancelAddPatientBtn) {
    cancelAddPatientBtn.addEventListener('click', closeAllModals);
}

// Close modals when clicking outside
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        closeAllModals();
    }
});

// Form validation for Add Patient
const addPatientForm = document.getElementById('add-patient-form');
if (addPatientForm) {
    addPatientForm.addEventListener('submit', function(e) {
        const firstName = document.getElementById('first-name')?.value.trim();
        const lastName = document.getElementById('last-name')?.value.trim();
        const email = document.getElementById('email')?.value.trim();
        const phone = document.getElementById('phone')?.value.trim();
        const birthdate = document.getElementById('birthdate')?.value;
        
        let isValid = true;
        
        // Reset previous error states
        clearFormErrors();
        
        // Validate required fields
        if (!firstName) {
            markError('first-name', 'First name is required');
            isValid = false;
        }
        
        if (!lastName) {
            markError('last-name', 'Last name is required');
            isValid = false;
        }
        
        if (!email) {
            markError('email', 'Email is required');
            isValid = false;
        } else if (!isValidEmail(email)) {
            markError('email', 'Please enter a valid email address');
            isValid = false;
        }
        
        if (!phone) {
            markError('phone', 'Phone number is required');
            isValid = false;
        } else if (!isValidPhilippinePhone(phone)) {
            markError('phone', 'Please enter a valid Philippine phone number (11 digits starting with 09)');
            isValid = false;
        }
        
        if (!birthdate) {
            markError('birthdate', 'Date of birth is required');
            isValid = false;
        } else if (new Date(birthdate) > new Date()) {
            markError('birthdate', 'Date of birth cannot be in the future');
            isValid = false;
        }
        
        // Check parental consent for minors
        if (birthdate) {
            const birthdateObj = new Date(birthdate);
            const today = new Date();
            let age = today.getFullYear() - birthdateObj.getFullYear();
            const monthDiff = today.getMonth() - birthdateObj.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdateObj.getDate())) {
                age--;
            }
            
            if (age < 18) {
                const parentalConsent = document.getElementById('parental_consent');
                if (!parentalConsent?.checked) {
                    markError('parental_consent', 'Parental consent is required for minors');
                    isValid = false;
                }
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            showSystemMessage('Please fill in all required fields correctly.', 'error');
        }
    });
}

// Auto-format phone number (11 digits, starting with 09)
const phoneInput = document.getElementById('phone');
if (phoneInput) {
    phoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        
        // Limit to 11 digits
        if (value.length > 11) {
            value = value.substring(0, 11);
        }
        
        // Ensure it starts with 09
        if (value.length > 0 && !value.startsWith('09')) {
            value = '09' + value.substring(2);
        }
        
        e.target.value = value;
        
        // Validate as user types
        if (value.length === 11 && value.startsWith('09')) {
            e.target.classList.remove('error');
            clearError('phone');
        }
    });
    
    phoneInput.addEventListener('blur', function(e) {
        const value = e.target.value.replace(/\D/g, '');
        if (value.length > 0 && value.length !== 11) {
            markError('phone', 'Phone number must be 11 digits');
        } else if (value.length === 11 && !value.startsWith('09')) {
            markError('phone', 'Phone number must start with 09');
        }
    });
}

// Show/hide minor consent field based on birthdate
const birthdateInput = document.getElementById('birthdate');
if (birthdateInput) {
    birthdateInput.addEventListener('change', function() {
        const birthdate = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - birthdate.getFullYear();
        const monthDiff = today.getMonth() - birthdate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
            age--;
        }
        
        const parentalConsentContainer = document.getElementById('parentalConsentContainer');
        const parentalConsent = document.getElementById('parental_consent');
        
        if (parentalConsentContainer && parentalConsent) {
            if (age < 18) {
                parentalConsentContainer.style.display = 'block';
                parentalConsent.required = true;
            } else {
                parentalConsentContainer.style.display = 'none';
                parentalConsent.required = false;
                parentalConsent.checked = false;
            }
        }
    });
}

// Filter form submission
const filterForm = document.getElementById('filter-form');
if (filterForm) {
    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form values
        const search = document.getElementById('search-filter').value.trim();
        const gender = document.getElementById('gender-filter').value;
        const isMinor = document.getElementById('minor-filter').value;
        
        // Build query string
        let queryParams = [];
        
        if (search) {
            queryParams.push(`search=${encodeURIComponent(search)}`);
        }
        
        if (gender && gender !== 'all') {
            queryParams.push(`gender=${encodeURIComponent(gender)}`);
        }
        
        if (isMinor && isMinor !== 'all') {
            queryParams.push(`is_minor=${encodeURIComponent(isMinor)}`);
        }
        
        // Redirect with query parameters
        const queryString = queryParams.length > 0 ? '?' + queryParams.join('&') : '';
        window.location.href = `staff-patients.php${queryString}`;
    });
}

// Make sure modals close with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeAllModals();
    }
});

// Helper functions
function markError(elementId, message) {
    const element = document.getElementById(elementId);
    if (element) {
        element.classList.add('error');
        
        // Add error message below input
        let errorDiv = element.parentElement.querySelector('.error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.style.color = 'var(--error)';
            errorDiv.style.fontSize = '0.8rem';
            errorDiv.style.marginTop = '5px';
            element.parentElement.appendChild(errorDiv);
        }
        errorDiv.textContent = message;
    }
}

function clearError(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.classList.remove('error');
        
        const errorDiv = element.parentElement.querySelector('.error-message');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
}

function clearFormErrors() {
    document.querySelectorAll('.form-control').forEach(input => {
        input.classList.remove('error');
        
        const errorDiv = input.parentElement.querySelector('.error-message');
        if (errorDiv) {
            errorDiv.remove();
        }
    });
}

function showSystemMessage(message, type = 'info') {
    const messagesContainer = document.getElementById('systemMessages');
    if (!messagesContainer) return;

    const messageId = 'msg-' + Date.now();
    
    const messageDiv = document.createElement('div');
    messageDiv.id = messageId;
    messageDiv.className = type === 'success' ? 'message-success' : (type === 'error' ? 'message-error' : 'message-info');
    messageDiv.innerHTML = `
        <div class="message-content">${message}</div>
        <button class="message-close" onclick="closeMessage('${messageId}')">&times;</button>
    `;
    
    messagesContainer.appendChild(messageDiv);
    
    setTimeout(() => {
        closeMessage(messageId);
    }, 5000);
}

function closeMessage(messageId) {
    const message = document.getElementById(messageId);
    if (message) {
        message.classList.add('message-hiding');
        setTimeout(() => {
            if (message.parentNode) {
                message.parentNode.removeChild(message);
            }
        }, 500);
    }
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function isValidPhilippinePhone(phone) {
    // Remove all non-digit characters
    const cleaned = phone.replace(/\D/g, '');
    
    // Check if it's exactly 11 digits and starts with 09
    return cleaned.length === 11 && cleaned.startsWith('09');
}

// View patient details - FIXED with proper quotes
function viewPatient(patientId) {
    document.getElementById('viewModalBody').innerHTML = '<div class="loading-content"><i class="fas fa-spinner fa-spin"></i><p>Loading patient details...</p></div>';
    
    fetch(`staff-patients.php?action=get_patient_details&id=${patientId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.patient) {
                const patient = data.patient;
                
                const birthdate = new Date(patient.birthdate);
                const today = new Date();
                let age = today.getFullYear() - birthdate.getFullYear();
                const monthDiff = today.getMonth() - birthdate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                    age--;
                }
                
                const isActive = patient.is_active || false;
                
                // Format full address
                const fullAddress = patient.full_address || 'No address provided';
                
                // FIXED: Profile image with proper quotes
                const profileImage = patient.profile_image ? 
    `<img src="/Cosmo_Smiles_Dental_Clinic/${patient.profile_image}" alt="Profile" class="profile-image" onerror="this.onerror=null; this.parentNode.innerHTML='<i class=\'fas fa-user-circle\'></i>';">` : 
    '<i class="fas fa-user-circle"></i>';
                
                const modalBody = document.getElementById('viewModalBody');
                modalBody.innerHTML = `
                    <div class="patient-profile-header">
                        <div class="profile-image-container">
                            ${profileImage}
                        </div>
                        <div class="profile-name-container">
                            <h2>${patient.first_name} ${patient.last_name}</h2>
                            <p class="patient-id">ID: ${patient.client_id}</p>
                            <span class="patient-status ${isActive ? 'status-active' : 'status-inactive'}">
                                ${isActive ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                    </div>
                    
                    <div class="patient-details-grid">
                        <div class="details-section">
                            <h4><i class="fas fa-user"></i> Personal Information</h4>
                            <div class="detail-row">
                                <div class="detail-label">Age:</div>
                                <div class="detail-value">${age} years old</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Gender:</div>
                                <div class="detail-value">${patient.gender ? patient.gender.charAt(0).toUpperCase() + patient.gender.slice(1) : 'Not specified'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Date of Birth:</div>
                                <div class="detail-value">${new Date(patient.birthdate).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                            </div>
                            ${patient.is_minor ? `
                            <div class="detail-row">
                                <div class="detail-label">Minor Status:</div>
                                <div class="detail-value">${patient.parental_consent ? '✅ Parental Consent Given' : '⚠️ Parental Consent Required'}</div>
                            </div>
                            ` : ''}
                        </div>
                        
                        <div class="details-section">
                            <h4><i class="fas fa-address-card"></i> Address Information</h4>
                            <div class="detail-row">
                                <div class="detail-label">Full Address:</div>
                                <div class="detail-value address-value">${fullAddress}</div>
                            </div>
                        </div>
                        
                        <div class="details-section">
                            <h4><i class="fas fa-phone-alt"></i> Contact Information</h4>
                            <div class="detail-row">
                                <div class="detail-label">Phone:</div>
                                <div class="detail-value">${patient.phone || 'Not provided'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Email:</div>
                                <div class="detail-value">${patient.email || 'Not provided'}</div>
                            </div>
                        </div>
                        
                        <div class="details-section">
                            <h4><i class="fas fa-calendar-alt"></i> Account Information</h4>
                            <div class="detail-row">
                                <div class="detail-label">Member Since:</div>
                                <div class="detail-value">${new Date(patient.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Last Updated:</div>
                                <div class="detail-value">${new Date(patient.updated_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('viewModalBody').innerHTML = '<div class="alert alert-error">Error loading patient details.</div>';
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            document.getElementById('viewModalBody').innerHTML = '<div class="alert alert-error">Error loading patient details.</div>';
        });
    
    const modal = document.getElementById('viewPatientModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// Modal close function
function closeViewModal() {
    const modal = document.getElementById('viewPatientModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    // Show PHP messages
    if (phpData.successMessage) {
        setTimeout(() => {
            showSystemMessage(phpData.successMessage, 'success');
        }, 500);
    }

    if (phpData.errorMessage) {
        setTimeout(() => {
            showSystemMessage(phpData.errorMessage, 'error');
        }, 500);
    }

    // Update stats with real data from database
    const statCards = document.querySelectorAll('.stat-number');
    if (statCards.length >= 4) {
        // Total Patients
        statCards[0].textContent = phpData.totalPatients;
        // Active Patients
        statCards[1].textContent = phpData.activePatients;
        // New This Month
        statCards[2].textContent = phpData.newThisMonth;
        // Inactive Patients
        statCards[3].textContent = phpData.inactivePatients;
    }

    // Initialize parental consent checkbox based on birthdate
    const birthdateInput = document.getElementById('birthdate');
    const parentalConsentContainer = document.getElementById('parentalConsentContainer');
    
    if (birthdateInput && parentalConsentContainer) {
        birthdateInput.addEventListener('change', function() {
            const birthdate = new Date(this.value);
            const today = new Date();
            const age = today.getFullYear() - birthdate.getFullYear();
            
            if (age < 18) {
                parentalConsentContainer.style.display = 'block';
                document.getElementById('parental_consent').required = true;
            } else {
                parentalConsentContainer.style.display = 'none';
                document.getElementById('parental_consent').required = false;
            }
        });
    }

    // Initialize search input
    const searchInput = document.getElementById('search-filter');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('filter-form').submit();
            }
        });
    }

    // Add patient button in table
    const addPatientBtnTable = document.getElementById('add-patient-btn-table');
    if (addPatientBtnTable) {
        addPatientBtnTable.addEventListener('click', function() {
            document.getElementById('add-patient-btn').click();
        });
    }
    
    // Detect if table is scrollable and add indicator
    const tableContent = document.querySelector('.table-content');
    if (tableContent) {
        const checkScrollable = () => {
            if (tableContent.scrollWidth > tableContent.clientWidth) {
                tableContent.classList.add('scrollable');
            } else {
                tableContent.classList.remove('scrollable');
            }
        };
        
        checkScrollable();
        window.addEventListener('resize', checkScrollable);
    }
});