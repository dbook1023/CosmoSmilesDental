// public/assets/staff/js/staff-appointments.js

// Global variables
let currentAppointmentId = null;
let currentAppointmentDbId = null;

// DOM Elements
const viewModal = document.getElementById('viewAppointmentModal');
const editModal = document.getElementById('editAppointmentModal');
const createModal = document.getElementById('createAppointmentModal');

// Enhanced fetch with timeout
const fetchWithTimeout = (url, options = {}, timeout = 10000) => {
    return Promise.race([
        fetch(url, options),
        new Promise((_, reject) => 
            setTimeout(() => reject(new Error('Request timeout')), timeout)
        )
    ]);
};

// Mobile sidebar functionality
function initializeMobileSidebar() {
    const hamburger = document.querySelector('.hamburger');
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.overlay');

    if (hamburger && sidebar && overlay) {
        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });

        const sidebarLinks = document.querySelectorAll('.sidebar-item');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }
            });
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        });
    }
}

// Enhanced System Messages functionality
function showSystemMessage(message, type = 'success') {
    const messagesContainer = document.getElementById('systemMessages');
    if (!messagesContainer) return;

    const messageId = 'msg-' + Date.now();
    
    const messageDiv = document.createElement('div');
    messageDiv.id = messageId;
    messageDiv.className = type === 'success' ? 'system-message-success' : 'system-message-error';
    messageDiv.innerHTML = `
        <div class="message-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="close-message" onclick="closeMessage('${messageId}')">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    messagesContainer.appendChild(messageDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        closeMessage(messageId);
    }, 5000);
}

function closeMessage(messageId) {
    const message = document.getElementById(messageId);
    if (message) {
        message.style.opacity = '0';
        message.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (message.parentNode) {
                message.parentNode.removeChild(message);
            }
        }, 300);
    }
}

// Function to show error for past appointments
function showPastAppointmentError(action = 'edit') {
    let actionText = '';
    let message = '';
    
    switch(action) {
        case 'edit':
            actionText = 'edit';
            message = 'Cannot edit past appointments. Past appointments cannot be modified.';
            break;
        case 'confirm':
            actionText = 'confirm';
            message = 'Cannot confirm past appointments. The appointment date has already passed.';
            break;
        case 'cancel':
            actionText = 'cancel';
            message = 'Cannot cancel past appointments. The appointment date has already passed.';
            break;
        default:
            actionText = 'modify';
            message = 'Cannot modify past appointments. The appointment date has already passed.';
    }
    
    showSystemMessage(message, 'error');
}

// Search functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchBtn');
    
    if (searchInput && searchBtn) {
        searchBtn.addEventListener('click', performSearch);
        
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }
}

function performSearch() {
    const searchTerm = document.getElementById('searchInput').value.trim();
    
    if (!searchTerm) {
        window.location.href = window.location.pathname;
        return;
    }
    
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('search', searchTerm);
    urlParams.delete('page');
    
    window.location.href = `${window.location.pathname}?${urlParams.toString()}`;
}

// Create Appointment Modal Functions
function openCreateAppointmentModal() {
    document.getElementById('createAppointmentForm').reset();
    
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('create_appointment_date').value = today;
    document.getElementById('create_appointment_date').min = today;
    document.getElementById('create_duration_minutes').value = '60';
    
    const timeSelect = document.getElementById('create_appointment_time');
    timeSelect.innerHTML = '<option value="">Select Date and Dentist First</option>';
    timeSelect.disabled = true;
    
    document.getElementById('patient_details').style.display = 'none';
    document.getElementById('patient_error').style.display = 'none';
    document.getElementById('createSubmitBtn').disabled = true;
    
    initializeCreateServiceHandler();
    
    if (createModal) {
        createModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeCreateAppointmentModal() {
    if (createModal) {
        createModal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function initializeCreateServiceHandler() {
    const createServiceSelect = document.getElementById('create_service_id');
    const durationInput = document.getElementById('create_duration_minutes');
    
    if (createServiceSelect && durationInput) {
        createServiceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const duration = selectedOption.getAttribute('data-duration');
                durationInput.value = duration || '60';
            } else {
                durationInput.value = '60';
            }
        });
    }
}

// Confirmation Dialog
function showConfirmation(action, appointmentDbId, patientName) {
    let message, confirmButtonText;
    
    switch(action) {
        case 'confirm':
            message = `Confirm appointment for <strong>${patientName}</strong>?`;
            confirmButtonText = 'Confirm';
            break;
        case 'cancel':
            message = `Cancel appointment for <strong>${patientName}</strong>?`;
            confirmButtonText = 'Cancel';
            break;
    }
    
    const dialog = document.createElement('div');
    dialog.className = 'confirmation-dialog';
    dialog.innerHTML = `
        <div class="confirmation-content">
            <div class="confirmation-icon">?</div>
            <div class="confirmation-message">${message}</div>
            <div class="confirmation-buttons">
                <button class="confirmation-btn confirmation-btn-ok" onclick="handleConfirmation('${action}', ${appointmentDbId})">
                    ${confirmButtonText}
                </button>
                <button class="confirmation-btn confirmation-btn-cancel" onclick="closeConfirmation()">
                    Keep
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(dialog);
}

function handleConfirmation(action, appointmentDbId) {
    closeConfirmation();
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    form.style.display = 'none';
    
    const appointmentIdInput = document.createElement('input');
    appointmentIdInput.type = 'hidden';
    appointmentIdInput.name = 'appointment_id';
    appointmentIdInput.value = appointmentDbId;
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = window.phpData?.csrfToken || '';
    
    switch(action) {
        case 'confirm':
            actionInput.value = 'confirm_appointment';
            break;
        case 'cancel':
            actionInput.value = 'cancel_appointment';
            break;
    }
    
    form.appendChild(appointmentIdInput);
    form.appendChild(actionInput);
    if (window.phpData?.csrfToken) {
        form.appendChild(csrfInput);
    }
    document.body.appendChild(form);
    form.submit();
}

function closeConfirmation() {
    const dialog = document.querySelector('.confirmation-dialog');
    if (dialog) {
        dialog.remove();
    }
}

// Check if appointment date/time is in the past
function isAppointmentInPast(appointmentDate, appointmentTime) {
    const now = new Date();
    const appointmentDateTime = new Date(appointmentDate + 'T' + appointmentTime);
    return appointmentDateTime < now;
}

// READ - View Appointment Details
function viewAppointment(appointmentDbId) {
    console.log('viewAppointment called with ID:', appointmentDbId);
    currentAppointmentDbId = appointmentDbId;
    
    document.getElementById('viewModalBody').innerHTML = '<div class="loading-container"><i class="fas fa-spinner fa-spin"></i><p>Loading appointment details...</p></div>';
    
    const url = `staff-appointments.php?action=get_appointment_details&id=${appointmentDbId}`;
    
    fetchWithTimeout(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.appointment) {
                const appointment = data.appointment;
                
                const time = new Date('1970-01-01T' + appointment.appointment_time);
                const formattedTime = time.toLocaleString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit', 
                    hour12: true 
                });
                
                const modalBody = document.getElementById('viewModalBody');
                modalBody.innerHTML = `
                    <div class="appointment-details">
                        <div class="detail-section">
                            <h4><i class="fas fa-id-card"></i> Appointment Information</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Appointment ID:</label>
                                    <span>${appointment.appointment_id}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Patient ID:</label>
                                    <span>${appointment.patient_client_id || 'N/A'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Status:</label>
                                    <span class="appointment-status status-${appointment.status}">${(appointment.status || 'pending').charAt(0).toUpperCase() + (appointment.status || 'pending').slice(1)}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-calendar-alt"></i> Appointment Schedule</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Date:</label>
                                    <span>${new Date(appointment.appointment_date).toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Time:</label>
                                    <span>${formattedTime}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Duration:</label>
                                    <span>${appointment.duration_minutes || 60} minutes</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-user"></i> Patient Information</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Patient Name:</label>
                                    <span>${appointment.patient_full_name}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Phone:</label>
                                    <span>${appointment.patient_phone}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Email:</label>
                                    <span>${appointment.patient_email}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-stethoscope"></i> Service Details</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Dentist:</label>
                                    <span>${appointment.dentist_name || 'Not Assigned'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Service:</label>
                                    <span>
                                        ${(appointment.service || appointment.service_name).split(', ').length > 1 
                                            ? `<ul class="modal-service-list">${(appointment.service || appointment.service_name).split(', ').map(s => `<li><i class="fas fa-check-circle"></i> ${s}</li>`).join('')}</ul>`
                                            : (appointment.service || appointment.service_name)
                                        }
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <label>Price:</label>
                                    <span>₱${parseFloat(appointment.service_price || 0).toFixed(2)}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-file-invoice-dollar"></i> Payment Information</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Payment Type:</label>
                                    <span class="payment-type payment-${appointment.payment_type}">${(appointment.payment_type || 'cash').charAt(0).toUpperCase() + (appointment.payment_type || 'cash').slice(1)}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Created:</label>
                                    <span>${new Date(appointment.created_at).toLocaleString('en-PH')}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Last Updated:</label>
                                    <span>${new Date(appointment.updated_at).toLocaleString('en-PH')}</span>
                                </div>
                            </div>
                        </div>
                        
                        ${appointment.notes ? `
                        <div class="detail-section">
                            <h4><i class="fas fa-sticky-note"></i> Notes</h4>
                            <div class="notes-content">${appointment.notes}</div>
                        </div>
                        ` : ''}

                        ${appointment.feedback ? `
                        <!-- ORGANIZED FEEDBACK VIEW -->
                        <div class="detail-section feedback-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-top: 25px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h4 style="margin: 0; color: var(--primary); font-weight: 700; font-size: 1rem;"><i class="fas fa-comment-dots" style="margin-right: 8px; color: var(--secondary);"></i> Client Experience</h4>
                                <div class="feedback-rating" style="color: #f59e0b; font-size: 0.9rem;">
                                    ${Array.from({length: 5}, (_, i) => `<i class="${i < appointment.feedback.rating ? 'fas' : 'far'} fa-star"></i>`).join('')}
                                </div>
                            </div>
                            <div class="feedback-quote" style="position: relative; padding: 15px 20px; background: white; border-radius: 8px; border-left: 4px solid var(--secondary); margin-bottom: 10px;">
                                <div style="font-style: italic; color: #475569; line-height: 1.6; font-size: 0.95rem;">"${appointment.feedback.comment}"</div>
                            </div>
                            <div class="feedback-meta" style="font-size: 0.8rem; color: #94a3b8; display: flex; align-items: center; gap: 5px;">
                                <i class="far fa-clock"></i> Submitted on ${appointment.feedback.date}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                `;
            } else {
                document.getElementById('viewModalBody').innerHTML = '<div class="error-container"><i class="fas fa-exclamation-circle"></i><p>Error loading appointment details: ' + (data.message || 'Unknown error') + '</p></div>';
            }
        })
        .catch(error => {
            document.getElementById('viewModalBody').innerHTML = '<div class="error-container"><i class="fas fa-exclamation-circle"></i><p>Error loading appointment details. Please try again.</p></div>';
        });
    
    if (viewModal) {
        viewModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

// USER-FRIENDLY Edit Appointment - Shows available times immediately
async function editAppointment(appointmentDbId) {
    console.log('editAppointment called with ID:', appointmentDbId);
    currentAppointmentDbId = appointmentDbId;
    
    document.getElementById('editModalBody').innerHTML = '<div class="loading-container"><i class="fas fa-spinner fa-spin"></i><p>Loading appointment details...</p></div>';
    
    try {
        // First, try to load the appointment details
        const appointmentResponse = await fetchWithTimeout(
            `staff-appointments.php?action=get_appointment_details&id=${appointmentDbId}`,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        
        if (!appointmentResponse.ok) {
            throw new Error(`HTTP error! status: ${appointmentResponse.status}`);
        }
        
        const appointmentData = await appointmentResponse.json();
        
        if (!appointmentData.success || !appointmentData.appointment) {
            throw new Error(appointmentData.message || 'Failed to load appointment');
        }
        
        const appointment = appointmentData.appointment;
        console.log('Appointment data loaded:', appointment);
        
        // Check if appointment is in the past
        if (isAppointmentInPast(appointment.appointment_date, appointment.appointment_time)) {
            document.getElementById('editModalBody').innerHTML = `
                <div class="past-appointment-warning">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4>Cannot Edit Past Appointment</h4>
                    <div class="past-appointment-details">
                        <p><strong>Appointment ID:</strong> ${appointment.appointment_id}</p>
                        <p><strong>Date:</strong> ${new Date(appointment.appointment_date).toLocaleDateString()}</p>
                        <p><strong>Time:</strong> ${new Date('1970-01-01T' + appointment.appointment_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                        <p><strong>Patient:</strong> ${appointment.patient_full_name}</p>
                        <p><strong>Status:</strong> ${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}</p>
                    </div>
                    <p>Past appointments cannot be modified. Please use the view option to see details.</p>
                </div>
            `;
            return;
        }
        
        // Get time from appointment data
        const appointmentTime = appointment.appointment_time;
        
        // Parse the time string
        const timeParts = appointmentTime.split(':');
        const hour = parseInt(timeParts[0]);
        const minute = timeParts[1] || '00';
        const second = timeParts[2] || '00';
        
        // Format for display (12-hour format)
        const ampm = hour >= 12 ? 'PM' : 'AM';
        let displayHour = hour > 12 ? hour - 12 : hour;
        if (displayHour === 0) displayHour = 12;
        const formattedTime = minute === '00' ? `${displayHour}:00 ${ampm}` : `${displayHour}:${minute} ${ampm}`;
        
        // Format for value (HH:MM:SS)
        const timeValue = `${hour.toString().padStart(2, '0')}:${minute}:${second}`;
        
        // Get dentists data
        let dentists = window.phpData?.dentists || [];
        if (dentists.length === 0) {
            try {
                const dentistsResponse = await fetchWithTimeout(
                    'staff-appointments.php?action=get_dentists',
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );
                if (dentistsResponse.ok) {
                    const dentistsData = await dentistsResponse.json();
                    if (dentistsData.success && dentistsData.dentists) {
                        dentists = dentistsData.dentists;
                    }
                }
            } catch (e) {
                console.warn('Could not fetch dentists:', e);
            }
        }
        
        // Get services data
        let services = window.phpData?.services || [];
        if (services.length === 0) {
            try {
                const servicesResponse = await fetchWithTimeout(
                    'staff-appointments.php?action=get_services',
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );
                if (servicesResponse.ok) {
                    const servicesData = await servicesResponse.json();
                    if (servicesData.success && servicesData.services) {
                        services = servicesData.services;
                    }
                }
            } catch (e) {
                console.warn('Could not fetch services:', e);
            }
        }
        
        // Build dentist options with selected value
        let dentistOptions = '<option value="">Select Dentist</option>';
        let checkedInCount = 0;
        dentists.forEach(d => {
            if (d.is_checked_in == 1) checkedInCount++;
        });
        if (checkedInCount === 1 || appointment.dentist_id === 'any') {
            const isAnySelected = appointment.dentist_id === 'any';
            dentistOptions += `<option value="any" ${isAnySelected ? 'selected' : ''}>Any Available Dentist</option>`;
        }
        
        dentists.forEach(dentist => {
            const isSelected = parseInt(appointment.dentist_id) === parseInt(dentist.id);
            dentistOptions += `<option value="${dentist.id}" ${isSelected ? 'selected' : ''}>Dr. ${dentist.name}</option>`;
        });
        
        // Build service options with selected value
        let serviceOptions = '<option value="">Select Service</option>';
        services.forEach(service => {
            const isSelected = parseInt(appointment.service_id) === parseInt(service.id);
            serviceOptions += `<option value="${service.id}" data-price="${service.price}" data-duration="${service.duration_minutes}" ${isSelected ? 'selected' : ''}>${service.name} (₱${service.price})</option>`;
        });
        
        // First, show the form with current appointment time
        const modalBody = document.getElementById('editModalBody');
        
        modalBody.innerHTML = `
            <div class="form-section">
                <h4><i class="fas fa-user"></i> Patient Information</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_client_id">Patient ID *</label>
                        <input type="text" id="edit_client_id" name="client_id" class="form-control" 
                               value="${appointment.patient_client_id || ''}" required readonly>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status *</label>
                        <select id="edit_status" name="status" class="form-control" required>
                            <option value="pending" ${appointment.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="confirmed" ${appointment.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                            <option value="completed" ${appointment.status === 'completed' ? 'selected' : ''}>Completed</option>
                            <option value="cancelled" ${appointment.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                            <option value="no_show" ${appointment.status === 'no_show' ? 'selected' : ''}>No Show</option>
                        </select>
                    </div>
                </div>
                
                <!-- Hidden patient details -->
                <input type="hidden" name="patient_first_name" value="${appointment.patient_first_name || ''}">
                <input type="hidden" name="patient_last_name" value="${appointment.patient_last_name || ''}">
                <input type="hidden" name="patient_phone" value="${appointment.patient_phone || ''}">
                <input type="hidden" name="patient_email" value="${appointment.patient_email || ''}">
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-stethoscope"></i> Service Details</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_dentist_id">Dentist *</label>
                        <select id="edit_dentist_id" name="dentist_id" class="form-control" required>
                            ${dentistOptions}
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_service_id">Service *</label>
                        <select id="edit_service_id" name="service_id" class="form-control" required>
                            ${serviceOptions}
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-calendar-alt"></i> Schedule</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_appointment_date">Date *</label>
                        <input type="date" id="edit_appointment_date" name="appointment_date" class="form-control" 
                               value="${appointment.appointment_date || ''}" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_appointment_time">Time *</label>
                        <div class="time-selection-container">
                            <select id="edit_appointment_time" name="appointment_time" class="form-control" required disabled>
                                <option value="${timeValue}" selected>${formattedTime} (Loading available times...)</option>
                            </select>
                            <div id="edit_time_slots_loading" class="loading-message" style="display: block;">
                                <i class="fas fa-spinner fa-spin"></i> Loading available time slots...
                            </div>
                            <div id="edit_time_slots_message" class="message-info" style="display: none;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-file-invoice-dollar"></i> Payment & Additional Information</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_payment_type">Payment Type</label>
                        <select id="edit_payment_type" name="payment_type" class="form-control">
                            <option value="cash" ${appointment.payment_type === 'cash' ? 'selected' : ''}>Cash</option>
                            <option value="gcash" ${appointment.payment_type === 'gcash' ? 'selected' : ''}>GCash</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_duration_minutes">Duration (minutes)</label>
                        <input type="number" id="edit_duration_minutes" name="duration_minutes" 
                               class="form-control" value="${appointment.duration_minutes || 60}" 
                               min="60" step="60" readonly>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_notes">Notes</label>
                    <textarea id="edit_notes" name="notes" class="form-control" rows="3" 
                              placeholder="Additional notes...">${appointment.notes || ''}</textarea>
                </div>
            </div>
            
            <div class="sms-notice-edit">
                <i class="fas fa-info-circle"></i>
                <span>SMS will be sent if status changes to "Confirmed" or "Cancelled".</span>
            </div>
        `;
        
        // Set appointment ID in the hidden field
        document.getElementById('editAppointmentId').value = appointmentDbId;
        
        // Initialize service handler
        initializeEditServiceHandler();
        
        // Show the modal immediately
        if (editModal) {
            editModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Now load available time slots IMMEDIATELY
        loadAvailableTimeSlotsForEdit(
            appointment.appointment_date,
            appointment.dentist_id,
            appointmentDbId,
            timeValue
        );
        
        // Set up event listeners for when date or dentist changes
        const dateInput = document.getElementById('edit_appointment_date');
        const dentistSelect = document.getElementById('edit_dentist_id');
        const timeSelect = document.getElementById('edit_appointment_time');
        
        if (dateInput && dentistSelect && timeSelect) {
            // Set min date to today for edit
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
            
            // When date or dentist changes, reload time slots
            const reloadTimeSlots = () => {
                const date = dateInput.value;
                const dentistId = dentistSelect.value;
                
                if (date && dentistId) {
                    loadAvailableTimeSlotsForEdit(date, dentistId, appointmentDbId, timeValue);
                }
            };
            
            dateInput.addEventListener('change', reloadTimeSlots);
            dentistSelect.addEventListener('change', reloadTimeSlots);
        }
        
    } catch (error) {
        console.error('Error loading edit modal:', error);
        document.getElementById('editModalBody').innerHTML = `
            <div class="error-container">
                <i class="fas fa-exclamation-circle"></i>
                <h4>Error Loading Appointment</h4>
                <p>Could not load appointment details. Please try again.</p>
                <p><small>Error: ${error.message}</small></p>
                <button class="btn btn-primary" onclick="editAppointment(${appointmentDbId})">Retry</button>
            </div>
        `;
        
        if (editModal) {
            editModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }
}

// Load available time slots AUTOMATICALLY when edit modal opens
function loadAvailableTimeSlotsForEdit(date, dentistId, excludeId = null, currentTime = null) {
    console.log(`loadAvailableTimeSlotsForEdit called: date=${date}, dentistId=${dentistId}, excludeId=${excludeId}, currentTime=${currentTime}`);
    
    const timeSelect = document.getElementById('edit_appointment_time');
    const loadingDiv = document.getElementById('edit_time_slots_loading');
    const messageDiv = document.getElementById('edit_time_slots_message');
    
    if (!timeSelect) return;
    
    // Show loading
    timeSelect.disabled = true;
    if (loadingDiv) loadingDiv.style.display = 'block';
    if (messageDiv) messageDiv.style.display = 'none';
    
    // Validate date first
    const validationResult = validateDateForAppointment(date);
    if (!validationResult.valid) {
        timeSelect.innerHTML = `<option value="">${validationResult.message}</option>`;
        if (loadingDiv) loadingDiv.style.display = 'none';
        if (messageDiv) {
            messageDiv.textContent = validationResult.message;
            messageDiv.className = 'message-error';
            messageDiv.style.display = 'block';
        }
        return;
    }
    
    // Get booked slots
    let url = `staff-appointments.php?action=get_booked_slots&date=${date}&dentist_id=${dentistId}`;
    if (excludeId) {
        url += `&exclude_id=${excludeId}`;
    }
    
    fetchWithTimeout(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            console.log('Booked slots response:', data);
            if (loadingDiv) loadingDiv.style.display = 'none';
            
            if (data.success) {
                const bookedSlots = data.bookedSlots.map(slot => slot.appointment_time);
                
                // Generate all possible time slots
                const allTimeSlots = [];
                const availableSlots = [];
                const unavailableSlots = [];
                
                // Check if it's Saturday
                const dateObj = new Date(date);
                const dayOfWeek = dateObj.getDay();
                const isSaturday = (dayOfWeek === 6);
                const saturdayStartHour = 9;
                const saturdayEndHour = 15;
                const weekdayStartHour = 8;
                const weekdayEndHour = 18;
                
                const actualStartHour = isSaturday ? saturdayStartHour : weekdayStartHour;
                const actualEndHour = isSaturday ? saturdayEndHour : weekdayEndHour;
                
                // Get current time
                const now = new Date();
                const today = now.toISOString().split('T')[0];
                const currentHour = now.getHours();
                const currentMinute = now.getMinutes();
                
                // Generate all time slots
                for (let hour = actualStartHour; hour <= actualEndHour; hour++) {
                    const timeString = `${hour.toString().padStart(2, '0')}:00:00`;
                    
                    // Check if this time slot is in the past for today
                    let isPastTime = false;
                    if (date === today) {
                        if (hour < currentHour || (hour === currentHour && currentMinute > 0)) {
                            isPastTime = true;
                        }
                    }
                    
                    // Check if slot is booked
                    const isBooked = bookedSlots.includes(timeString);
                    
                    const ampm = hour >= 12 ? 'PM' : 'AM';
                    const displayHour = hour > 12 ? hour - 12 : hour;
                    const formattedHour = displayHour === 0 ? 12 : displayHour;
                    const formattedTime = `${formattedHour}:00 ${ampm}`;
                    
                    const timeSlot = {
                        value: timeString,
                        display: formattedTime,
                        status: !isPastTime && !isBooked ? 'available' : (isPastTime ? 'past' : 'booked'),
                        reason: isPastTime ? 'Past time' : (isBooked ? 'Already booked' : 'Available')
                    };
                    
                    allTimeSlots.push(timeSlot);
                    
                    if (timeSlot.status === 'available') {
                        availableSlots.push(timeSlot);
                    } else {
                        unavailableSlots.push(timeSlot);
                    }
                }
                
                // Clear and rebuild the time dropdown
                timeSelect.innerHTML = '';
                
                // Add current appointment time FIRST (always available to select)
                if (currentTime) {
                    const currentHour = parseInt(currentTime.split(':')[0]);
                    const currentTimeFormatted = `${currentHour.toString().padStart(2, '0')}:00:00`;
                    
                    // Find the current time slot
                    const currentTimeSlot = allTimeSlots.find(slot => slot.value === currentTimeFormatted);
                    
                    const ampm = currentHour >= 12 ? 'PM' : 'AM';
                    let displayHour = currentHour > 12 ? currentHour - 12 : currentHour;
                    if (displayHour === 0) displayHour = 12;
                    const formattedCurrentTime = `${displayHour}:00 ${ampm}`;
                    
                    // Always allow selecting current appointment time, even if it's "booked" (it's booked by THIS appointment)
                    const currentOption = document.createElement('option');
                    currentOption.value = currentTimeFormatted;
                    currentOption.textContent = `${formattedCurrentTime} ✓ Current Appointment`;
                    currentOption.selected = true;
                    currentOption.className = 'time-slot-current';
                    timeSelect.appendChild(currentOption);
                }
                
                // Add separator for available slots
                if (availableSlots.length > 0) {
                    const availableHeader = document.createElement('option');
                    availableHeader.disabled = true;
                    availableHeader.textContent = '────────── Available Times ──────────';
                    timeSelect.appendChild(availableHeader);
                    
                    // Add available slots
                    availableSlots.forEach(slot => {
                        // Skip if this is the current time (already added)
                        if (currentTime && slot.value === `${parseInt(currentTime.split(':')[0]).toString().padStart(2, '0')}:00:00`) {
                            return;
                        }
                        
                        const option = document.createElement('option');
                        option.value = slot.value;
                        option.textContent = `${slot.display} ✓ Available`;
                        option.className = 'time-slot-available';
                        timeSelect.appendChild(option);
                    });
                }
                
                // Add separator for unavailable slots
                if (unavailableSlots.length > 0) {
                    const unavailableHeader = document.createElement('option');
                    unavailableHeader.disabled = true;
                    unavailableHeader.textContent = '────────── Unavailable Times ──────────';
                    timeSelect.appendChild(unavailableHeader);
                    
                    // Add unavailable slots (disabled)
                    unavailableSlots.forEach(slot => {
                        // Skip if this is the current time (already added and enabled)
                        if (currentTime && slot.value === `${parseInt(currentTime.split(':')[0]).toString().padStart(2, '0')}:00:00`) {
                            return;
                        }
                        
                        const option = document.createElement('option');
                        option.value = slot.value;
                        option.textContent = `${slot.display} ✗ ${slot.reason}`;
                        option.disabled = true;
                        option.className = slot.status === 'past' ? 'time-slot-past' : 'time-slot-booked';
                        timeSelect.appendChild(option);
                    });
                }
                
                // Enable the dropdown
                timeSelect.disabled = false;
                
                // Show summary message
                if (messageDiv) {
                    const totalSlots = allTimeSlots.length;
                    const availableCount = availableSlots.length + (currentTime ? 1 : 0); // +1 for current appointment
                    const unavailableCount = unavailableSlots.length - (currentTime ? 1 : 0); // -1 if current is in unavailable
                    
                    messageDiv.innerHTML = `
                        <strong>Time Slot Summary:</strong><br>
                        • ${availableCount} time slots available (including current)<br>
                        • ${unavailableCount > 0 ? `${unavailableCount} unavailable` : 'All slots available'}<br>
                        ${isSaturday ? '• Saturday hours: 9:00 AM - 3:00 PM' : '• Weekday hours: 8:00 AM - 6:00 PM'}
                    `;
                    messageDiv.className = 'message-info';
                    messageDiv.style.display = 'block';
                }
                
            } else {
                // Error loading slots, but still show current time
                timeSelect.innerHTML = `<option value="${currentTime}" selected>${formatTimeForDisplay(currentTime)} ✓ Current Appointment</option>`;
                timeSelect.disabled = false;
                
                if (messageDiv) {
                    messageDiv.textContent = 'Could not load other time slots, but current appointment time is available.';
                    messageDiv.className = 'message-warning';
                    messageDiv.style.display = 'block';
                }
            }
        })
        .catch(error => {
            console.error('Error loading time slots:', error);
            if (loadingDiv) loadingDiv.style.display = 'none';
            
            // On error, still show current time
            timeSelect.innerHTML = `<option value="${currentTime}" selected>${formatTimeForDisplay(currentTime)} ✓ Current Appointment</option>`;
            timeSelect.disabled = false;
            
            if (messageDiv) {
                messageDiv.textContent = 'Network error loading time slots, but current appointment time is available.';
                messageDiv.className = 'message-warning';
                messageDiv.style.display = 'block';
            }
        });
}

// Helper function to format time for display
function formatTimeForDisplay(timeString) {
    if (!timeString) return 'Select Time';
    
    const timeParts = timeString.split(':');
    const hour = parseInt(timeParts[0]);
    const minute = timeParts[1] || '00';
    
    const ampm = hour >= 12 ? 'PM' : 'AM';
    let displayHour = hour > 12 ? hour - 12 : hour;
    if (displayHour === 0) displayHour = 12;
    
    return minute === '00' ? `${displayHour}:00 ${ampm}` : `${displayHour}:${minute} ${ampm}`;
}

// Validate date for appointment
function validateDateForAppointment(date) {
    if (!date) {
        return { valid: false, message: 'Please select a date' };
    }
    
    const today = new Date().toISOString().split('T')[0];
    const dateObj = new Date(date);
    const dayOfWeek = dateObj.getDay();
    
    // Check if date is in the past
    if (date < today) {
        return { valid: false, message: 'Cannot book appointments in the past' };
    }
    
    // Check if it's Sunday
    if (dayOfWeek === 0) {
        return { valid: false, message: 'Sundays are unavailable' };
    }
    
    // Check if it's Saturday
    if (dayOfWeek === 6) {
        return { valid: true, message: 'Saturday appointments: 9:00 AM to 3:00 PM' };
    }
    
    return { valid: true, message: '' };
}

// Close modals
function closeViewModal() {
    if (viewModal) {
        viewModal.style.display = 'none';
        document.body.style.overflow = 'auto';
        currentAppointmentDbId = null;
    }
}

function closeEditModal() {
    if (editModal) {
        editModal.style.display = 'none';
        document.body.style.overflow = 'auto';
        currentAppointmentDbId = null;
    }
}

// Service selection handlers
function initializeEditServiceHandler() {
    const editServiceSelect = document.getElementById('edit_service_id');
    const durationInput = document.getElementById('edit_duration_minutes');
    
    if (editServiceSelect && durationInput) {
        editServiceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const duration = selectedOption.getAttribute('data-duration');
                durationInput.value = duration || '60';
            }
        });
    }
}

// Close modals when clicking outside
document.addEventListener('click', (event) => {
    if (event.target === viewModal) closeViewModal();
    if (event.target === editModal) closeEditModal();
    if (event.target === createModal) closeCreateAppointmentModal();
});

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Basic UI Initializations
    if (typeof initializeMobileSidebar === 'function') initializeMobileSidebar();
    if (typeof initializeSearch === 'function') initializeSearch();
    if (typeof initializeAjaxFilters === 'function') initializeAjaxFilters();

    // Make functions globally available
    window.showPastAppointmentError = showPastAppointmentError;
    window.showSystemMessage = showSystemMessage;
    window.closeMessage = closeMessage;
    window.showConfirmation = showConfirmation;
    window.handleConfirmation = handleConfirmation;
    window.closeConfirmation = closeConfirmation;
    window.viewAppointment = viewAppointment;
    window.editAppointment = editAppointment;
    window.closeViewModal = closeViewModal;
    window.closeEditModal = closeEditModal;
    window.openCreateAppointmentModal = openCreateAppointmentModal;
    window.closeCreateAppointmentModal = closeCreateAppointmentModal;
    window.performSearch = performSearch;
    window.validateDateForAppointment = validateDateForAppointment;
    window.isAppointmentInPast = isAppointmentInPast;
    window.fetchWithTimeout = fetchWithTimeout;
    window.loadAppointments = loadAppointments;
    window.renderAppointmentsTable = renderAppointmentsTable;
    window.renderPagination = renderPagination;
    window.initializeAjaxFilters = initializeAjaxFilters;

    // Set current date if element exists
    const currentDateEl = document.getElementById('current-date');
    if (currentDateEl && window.phpData?.currentDate) {
        currentDateEl.textContent = window.phpData.currentDate;
    }

    // Initialize patient check functionality for create modal
    const checkPatientBtn = document.getElementById('checkPatientBtn');
    const createClientIdInput = document.getElementById('create_client_id');
    const patientDetails = document.getElementById('patient_details');
    const patientError = document.getElementById('patient_error');
    const createSubmitBtn = document.getElementById('createSubmitBtn');

    if (checkPatientBtn && createClientIdInput) {
        checkPatientBtn.addEventListener('click', function() {
            const clientId = createClientIdInput.value.trim();
            if (!clientId) {
                if (patientError) {
                    patientError.textContent = 'Please enter a Patient ID first';
                    patientError.style.display = 'block';
                }
                if (patientDetails) patientDetails.style.display = 'none';
                if (createSubmitBtn) createSubmitBtn.disabled = true;
                return;
            }

            // Show loading
            if (patientError) patientError.style.display = 'none';
            if (patientDetails) patientDetails.style.display = 'none';
            checkPatientBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            checkPatientBtn.disabled = true;

            // Fetch patient details
            fetch(`staff-appointments.php?action=get_client_details&client_id=${clientId}`)
                .then(response => response.json())
                .then(data => {
                    checkPatientBtn.innerHTML = '<i class="fas fa-search"></i> Check';
                    checkPatientBtn.disabled = false;

                    if (data.success && data.client) {
                        const client = data.client;
                        
                        // Update display
                        if (document.getElementById('patient_name'))
                            document.getElementById('patient_name').textContent = client.first_name + ' ' + client.last_name;
                        if (document.getElementById('patient_phone'))
                            document.getElementById('patient_phone').textContent = client.phone;
                        if (document.getElementById('patient_email'))
                            document.getElementById('patient_email').textContent = client.email;
                        
                        if (patientDetails) patientDetails.style.display = 'block';
                        if (patientError) patientError.style.display = 'none';
                        
                        // Update hidden fields
                        if (document.getElementById('create_patient_first_name'))
                            document.getElementById('create_patient_first_name').value = client.first_name;
                        if (document.getElementById('create_patient_last_name'))
                            document.getElementById('create_patient_last_name').value = client.last_name;
                        if (document.getElementById('create_patient_phone'))
                            document.getElementById('create_patient_phone').value = client.phone;
                        if (document.getElementById('create_patient_email'))
                            document.getElementById('create_patient_email').value = client.email;
                        
                        if (createSubmitBtn) createSubmitBtn.disabled = false;
                    } else {
                        if (patientError) {
                            patientError.textContent = 'Patient not found. Please check the Patient ID.';
                            patientError.style.display = 'block';
                        }
                        if (patientDetails) patientDetails.style.display = 'none';
                        
                        // Clear hidden fields
                        ['first_name', 'last_name', 'phone', 'email'].forEach(f => {
                            const el = document.getElementById('create_patient_' + f);
                            if (el) el.value = '';
                        });
                        
                        if (createSubmitBtn) createSubmitBtn.disabled = true;
                    }
                })
                .catch(error => {
                    checkPatientBtn.innerHTML = '<i class="fas fa-search"></i> Check';
                    checkPatientBtn.disabled = false;
                    if (patientError) {
                        patientError.textContent = 'Error checking patient. Please try again.';
                        patientError.style.display = 'block';
                    }
                    if (patientDetails) patientDetails.style.display = 'none';
                    if (createSubmitBtn) createSubmitBtn.disabled = true;
                });
        });

        // Also check when Enter key is pressed
        createClientIdInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                checkPatientBtn.click();
            }
        });
    }

    // Add event listeners for create modal date/time
    const createDentistSelect = document.getElementById('create_dentist_id');
    const createDateInput = document.getElementById('create_appointment_date');
    const createDateValidation = document.getElementById('date_validation');
    
    if (createDateInput && createDateValidation) {
        createDateInput.addEventListener('change', function() {
            const date = this.value;
            const validation = validateDateForAppointment(date);
            
            if (validation.valid) {
                createDateValidation.textContent = validation.message || '';
                createDateValidation.className = 'message-info';
                createDateValidation.style.display = validation.message ? 'block' : 'none';
                
                // Load time slots if dentist is selected
                const dentistId = createDentistSelect ? createDentistSelect.value : null;
                if (dentistId) {
                    loadAvailableTimeSlotsForCreate(date, dentistId);
                }
            } else {
                createDateValidation.textContent = validation.message;
                createDateValidation.className = 'message-error';
                createDateValidation.style.display = 'block';
                
                // Clear time select
                const timeSelect = document.getElementById('create_appointment_time');
                if (timeSelect) {
                    timeSelect.innerHTML = `<option value="">${validation.message}</option>`;
                    timeSelect.disabled = true;
                }
            }
        });
    }
    
    if (createDentistSelect) {
        createDentistSelect.addEventListener('change', function() {
            const dentistId = this.value;
            const date = createDateInput ? createDateInput.value : null;
            
            if (date && dentistId) {
                loadAvailableTimeSlotsForCreate(date, dentistId);
            } else {
                const timeSelect = document.getElementById('create_appointment_time');
                if (timeSelect) {
                    timeSelect.innerHTML = '<option value="">Select Date and Dentist First</option>';
                    timeSelect.disabled = true;
                }
            }
        });
    }

    // Show any PHP messages
    setTimeout(() => {
        if (window.phpData?.successMessage) {
            showSystemMessage(window.phpData.successMessage, 'success');
        }
        if (window.phpData?.errorMessage) {
            showSystemMessage(window.phpData.errorMessage, 'error');
        }
    }, 500);
});

// Load available time slots for create modal
function loadAvailableTimeSlotsForCreate(date, dentistId) {
    const timeSelect = document.getElementById('create_appointment_time');
    const loadingDiv = document.getElementById('create_time_slots_loading');
    const messageDiv = document.getElementById('create_time_slots_message');
    
    if (!timeSelect) return;
    
    // Show loading
    timeSelect.disabled = true;
    timeSelect.innerHTML = '<option value="">Loading available slots...</option>';
    if (loadingDiv) loadingDiv.style.display = 'block';
    if (messageDiv) messageDiv.style.display = 'none';
    
    // Validate date first
    const validationResult = validateDateForAppointment(date);
    if (!validationResult.valid) {
        timeSelect.innerHTML = `<option value="">${validationResult.message}</option>`;
        if (loadingDiv) loadingDiv.style.display = 'none';
        if (messageDiv) {
            messageDiv.textContent = validationResult.message;
            messageDiv.className = 'message-error';
            messageDiv.style.display = 'block';
        }
        return;
    }
    
    // Get booked slots
    const url = `staff-appointments.php?action=get_booked_slots&date=${date}&dentist_id=${dentistId}`;
    
    fetchWithTimeout(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (loadingDiv) loadingDiv.style.display = 'none';
            
            if (data.success) {
                const bookedSlots = data.bookedSlots.map(slot => slot.appointment_time);
                
                // Generate all possible time slots
                const allTimeSlots = [];
                const availableSlots = [];
                const unavailableSlots = [];
                
                // Check if it's Saturday
                const dateObj = new Date(date);
                const dayOfWeek = dateObj.getDay();
                const isSaturday = (dayOfWeek === 6);
                const saturdayStartHour = 9;
                const saturdayEndHour = 15;
                const weekdayStartHour = 8;
                const weekdayEndHour = 18;
                
                const actualStartHour = isSaturday ? saturdayStartHour : weekdayStartHour;
                const actualEndHour = isSaturday ? saturdayEndHour : weekdayEndHour;
                
                // Get current time
                const now = new Date();
                const today = now.toISOString().split('T')[0];
                const currentHour = now.getHours();
                const currentMinute = now.getMinutes();
                
                // Generate all time slots
                for (let hour = actualStartHour; hour <= actualEndHour; hour++) {
                    const timeString = `${hour.toString().padStart(2, '0')}:00:00`;
                    
                    // Check if this time slot is in the past for today
                    let isPastTime = false;
                    if (date === today) {
                        if (hour < currentHour || (hour === currentHour && currentMinute > 0)) {
                            isPastTime = true;
                        }
                    }
                    
                    // Check if slot is booked
                    const isBooked = bookedSlots.includes(timeString);
                    
                    const ampm = hour >= 12 ? 'PM' : 'AM';
                    const displayHour = hour > 12 ? hour - 12 : hour;
                    const formattedHour = displayHour === 0 ? 12 : displayHour;
                    const formattedTime = `${formattedHour}:00 ${ampm}`;
                    
                    const timeSlot = {
                        value: timeString,
                        display: formattedTime,
                        status: !isPastTime && !isBooked ? 'available' : (isPastTime ? 'past' : 'booked'),
                        reason: isPastTime ? 'Past time' : (isBooked ? 'Already booked' : 'Available')
                    };
                    
                    allTimeSlots.push(timeSlot);
                    
                    if (timeSlot.status === 'available') {
                        availableSlots.push(timeSlot);
                    } else {
                        unavailableSlots.push(timeSlot);
                    }
                }
                
                // Clear and rebuild the time dropdown
                timeSelect.innerHTML = '';
                
                // Add default option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Select a time';
                timeSelect.appendChild(defaultOption);
                
                // Add available slots
                if (availableSlots.length > 0) {
                    availableSlots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot.value;
                        option.textContent = `${slot.display} ✓ Available`;
                        option.className = 'time-slot-available';
                        timeSelect.appendChild(option);
                    });
                }
                
                // Add separator for unavailable slots
                if (unavailableSlots.length > 0) {
                    const separator = document.createElement('option');
                    separator.disabled = true;
                    separator.textContent = '────────── Unavailable Times ──────────';
                    timeSelect.appendChild(separator);
                    
                    // Add unavailable slots (disabled)
                    unavailableSlots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot.value;
                        option.textContent = `${slot.display} ✗ ${slot.reason}`;
                        option.disabled = true;
                        option.className = slot.status === 'past' ? 'time-slot-past' : 'time-slot-booked';
                        timeSelect.appendChild(option);
                    });
                }
                
                // Enable the dropdown
                timeSelect.disabled = false;
                
                // Show summary message
                if (messageDiv) {
                    const totalSlots = allTimeSlots.length;
                    const availableCount = availableSlots.length;
                    
                    messageDiv.innerHTML = `
                        <strong>Time Slot Summary:</strong><br>
                        • ${availableCount} of ${totalSlots} time slots available<br>
                        ${isSaturday ? '• Saturday hours: 9:00 AM - 3:00 PM' : '• Weekday hours: 8:00 AM - 6:00 PM'}
                    `;
                    messageDiv.className = 'message-info';
                    messageDiv.style.display = 'block';
                }
                
            } else {
                timeSelect.innerHTML = '<option value="">Error loading time slots</option>';
                
                if (messageDiv) {
                    messageDiv.textContent = data.message || 'Error loading time slots';
                    messageDiv.className = 'message-error';
                    messageDiv.style.display = 'block';
                }
            }
        });
}

// AJAX - Load Appointments without refresh
function loadAppointments(page = 1) {
    const filterForm = document.getElementById('appointments-filter-form');
    const tableBody = document.getElementById('appointments-table-body');
    const paginationContainer = document.getElementById('pagination-container');
    
    if (!tableBody) return;
    
    // Show loading state
    tableBody.innerHTML = `
        <tr>
            <td colspan="10" class="no-appointments">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading appointments...</p>
            </td>
        </tr>
    `;
    
    // Gather filter data
    const formData = new FormData(filterForm);
    const params = new URLSearchParams();
    
    for (const [key, value] of formData.entries()) {
        params.append(key, value);
    }
    
    // Explicitly set hide_no_show because FormData can omit unchecked checkboxes
    const hideNoShowCheckbox = document.getElementById('staff-hide-noshow');
    if (hideNoShowCheckbox) {
        params.set('hide_no_show', hideNoShowCheckbox.checked ? 'true' : 'false');
    }
    
    // Ensure search is included if present
    const searchInput = document.getElementById('searchInput');
    if (searchInput && searchInput.value.trim()) {
        params.set('search', searchInput.value.trim());
    }
    
    params.set('action', 'fetch_appointments');
    params.set('page', page);
    
    // Perform AJAX fetch
    fetch(`staff-appointments.php?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderAppointmentsTable(data.appointments);
            renderPagination(data.total, data.page, data.limit, params);
            
            // Update URL without refreshing (optional but good for UX/back button)
            const newUrl = `${window.location.pathname}?${params.toString().replace('&action=fetch_appointments', '')}`;
            window.history.pushState({ path: newUrl }, '', newUrl);
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="no-appointments">
                        <i class="fas fa-exclamation-circle" style="color: #e74c3c;"></i>
                        <p>${data.message || 'Error loading appointments'}</p>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('AJAX Error:', error);
        tableBody.innerHTML = `
            <tr>
                <td colspan="10" class="no-appointments">
                    <i class="fas fa-exclamation-circle" style="color: #e74c3c;"></i>
                    <p>Network error. Please check your connection.</p>
                </td>
            </tr>
        `;
    });
}

function renderAppointmentsTable(appointments) {
    const tableBody = document.getElementById('appointments-table-body');
    if (!tableBody) return;
    
    if (!appointments || appointments.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="10" class="no-appointments">
                    <i class="fas fa-calendar-times"></i>
                    <p>No appointments found.</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const now = new Date();
    
    const rows = appointments.map(app => {
        const appDate = new Date(`${app.appointment_date}T${app.appointment_time}`);
        const isPast = appDate < now;
        
        // Format time for display
        const time = new Date('1970-01-01T' + app.appointment_time);
        const formattedTime = time.toLocaleString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit', 
            hour12: true 
        });
        
        // Format date for display
        const formattedDate = new Date(app.appointment_date).toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
        
        const statusClass = `status-${app.status}`;
        const statusLabel = app.status.charAt(0).toUpperCase() + app.status.slice(1);
        const paymentClass = `payment-${app.payment_type}`;
        const paymentLabel = app.payment_type.charAt(0).toUpperCase() + app.payment_type.slice(1);
        
        return `
            <tr class="${isPast ? 'past-appointment-indicator' : ''}">
                <td>${app.appointment_id}</td>
                <td>${app.patient_client_id || 'N/A'}</td>
                <td>
                    <div class="patient-info">
                        <div class="patient-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="patient-details">
                            <h4>${app.patient_full_name} ${isPast ? '<span class="past-tag">(Past)</span>' : ''}</h4>
                            <p>${app.patient_phone}</p>
                        </div>
                    </div>
                </td>
                <td>${formattedDate}</td>
                <td>${formattedTime}</td>
                <td>${app.dentist_name || 'Not Assigned'}</td>
                <td>${app.service || app.service_name}</td>
                <td><span class="payment-type ${paymentClass}">${paymentLabel}</span></td>
                <td><span class="appointment-status ${statusClass}">${statusLabel}</span></td>
                <td>
                    <div class="appointment-actions">
                        <button class="action-btn view" onclick="viewAppointment(${app.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="action-btn edit" onclick="${isPast ? 'showPastAppointmentError()' : `editAppointment(${app.id})`}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        ${app.status === 'pending' ? `
                            <button class="action-btn confirm" onclick="${isPast ? "showPastAppointmentError('confirm')" : `showConfirmation('confirm', ${app.id}, '${app.patient_full_name}')`}">
                                <i class="fas fa-check"></i> Confirm
                            </button>
                        ` : ''}
                        ${app.status !== 'cancelled' && app.status !== 'completed' && app.status !== 'no_show' ? `
                            <button class="action-btn cancel" onclick="${isPast ? "showPastAppointmentError('cancel')" : `showConfirmation('cancel', ${app.id}, '${app.patient_full_name}')`}">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    tableBody.innerHTML = rows;
}

function renderPagination(total, page, limit, params) {
    const container = document.getElementById('pagination-container');
    if (!container) return;
    
    const totalPages = Math.ceil(total / limit);
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = `
        <div class="pagination">
            <div class="pagination-info">
                Showing ${((page - 1) * limit) + 1} to ${Math.min(page * limit, total)} of ${total} appointments
            </div>
            <div class="pagination-controls">
    `;
    
    if (page > 1) {
        html += `
            <a href="javascript:void(0)" onclick="loadAppointments(${page - 1})" class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
        `;
    }
    
    for (let i = 1; i <= totalPages; i++) {
        if (i == page) {
            html += `<span class="pagination-btn active">${i}</span>`;
        } else {
            html += `<a href="javascript:void(0)" onclick="loadAppointments(${i})" class="pagination-btn">${i}</a>`;
        }
    }
    
    if (page < totalPages) {
        html += `
            <a href="javascript:void(0)" onclick="loadAppointments(${page + 1})" class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
        `;
    }
    
    html += `
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

// Initialize AJAX Filters
function initializeAjaxFilters() {
    const filterForm = document.getElementById('appointments-filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            loadAppointments(1);
        });
        
        // Handle dropdown changes immediately
        const filters = filterForm.querySelectorAll('select.filter-control');
        filters.forEach(filter => {
            filter.addEventListener('change', () => loadAppointments(1));
        });
        
        // Handle toggle change immediately
        const noShowToggle = document.getElementById('staff-hide-noshow');
        if (noShowToggle) {
            noShowToggle.addEventListener('change', () => loadAppointments(1));
        }
        
        // Reset button
        const resetBtn = filterForm.querySelector('a.btn[href="staff-appointments.php"]');
        if (resetBtn) {
            resetBtn.addEventListener('click', function(e) {
                e.preventDefault();
                filterForm.reset();
                const searchInput = document.getElementById('searchInput');
                if (searchInput) searchInput.value = '';
                loadAppointments(1);
            });
        }
    }
    
    // Search button
    const searchBtn = document.getElementById('searchBtn');
    if (searchBtn) {
        // Remove existing listener if possible or just prevent default behavior
        searchBtn.onclick = function(e) {
            e.preventDefault();
            loadAppointments(1);
        };
    }
    
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.onkeypress = function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadAppointments(1);
            }
        };
    }
}

// AJAX - Load Appointments without refresh