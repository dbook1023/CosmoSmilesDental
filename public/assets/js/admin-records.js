// Standardized Admin Clock
function updateAdminClock() {
    const now = new Date();
    const dateOptions = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
    const timeOptions = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };

    const dateEl = document.getElementById('admin-date');
    const timeEl = document.getElementById('admin-time');

    if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
    if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
}

// State management
let currentPatientId = null;
let currentPatientData = null;
let currentRecords = [];
let currentFilter = 'all';
let showArchived = false;
let services = [];

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    updateAdminClock();
    setInterval(updateAdminClock, 1000);

    setupSidebar();
    if (typeof initOdontogram === 'function') {
        initOdontogram();
    }
    loadServices();
});

function setupSidebar() {
    // Mobile sidebar toggle
    const hamburger = document.querySelector('.hamburger');
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.overlay');
    const mainContent = document.querySelector('.admin-main');

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
                sidebar?.classList.remove('active');
                overlay?.classList.remove('active');
            }
        });
    });
}

// DOM Elements
const searchSection = document.getElementById('search-section');
const recordsContainer = document.getElementById('records-container');
const searchBtn = document.getElementById('search-patient-btn');
const patientIdInput = document.getElementById('patient-id-input');
const patientName = document.getElementById('patient-name');
const patientIdDisplay = document.getElementById('patient-id-display');
const patientProfileImg = document.getElementById('patient-profile-img');
const patientInitials = document.getElementById('patient-initials');
const totalRecords = document.getElementById('total-records');
const lastUpdated = document.getElementById('last-updated');
const recordsList = document.getElementById('records-list');
const emptyState = document.getElementById('empty-state');
const backToSearchBtn = document.getElementById('back-to-search-btn');
const createRecordBtn = document.getElementById('create-record-btn');
const successMessage = document.getElementById('success-message');
const successMessageText = document.getElementById('success-message-text');
const errorMessage = document.getElementById('error-message');
const errorMessageText = document.getElementById('error-message-text');
const emptyCreateBtn = document.getElementById('empty-create-btn');
const showArchivedBtn = document.getElementById('show-archived-btn');

// Modal Elements
const createRecordModal = document.getElementById('create-record-modal');
const closeCreateModal = document.getElementById('close-create-modal');
const cancelCreateRecord = document.getElementById('cancel-create-record');
const saveRecordBtn = document.getElementById('save-record-btn');
const createRecordForm = document.getElementById('create-record-form');

const viewRecordModal = document.getElementById('view-record-modal');
const closeViewModal = document.getElementById('close-view-modal');
const closeViewRecord = document.getElementById('close-view-record');
const editRecordBtn = document.getElementById('edit-record-btn');
const archiveRecordModalBtn = document.getElementById('archive-record-btn');
const downloadRecordBtn = document.getElementById('download-record-btn');
const viewRecordContent = document.getElementById('record-details-content');

const medicalHistoryModal = document.getElementById('medical-history-modal');
const medicalHistoryContent = document.getElementById('medical-history-content');
const viewMedicalHistoryBtn = document.getElementById('view-medical-history-btn');
const closeMedicalHistoryModal = document.getElementById('close-medical-history-modal');
const closeMedicalHistoryBtn = document.getElementById('close-medical-history-btn');

const archiveRecordModal = document.getElementById('archive-record-modal');
const closeArchiveModal = document.getElementById('close-archive-modal');
const cancelArchive = document.getElementById('cancel-archive');
const confirmArchive = document.getElementById('confirm-archive');

// Form Inputs
const recordDateInput = document.getElementById('record-date');
const recordTimeInput = document.getElementById('record-time');
const procedureInput = document.getElementById('record-procedure');
const durationInput = document.getElementById('record-duration');

// File Upload Elements
const modalUploadArea = document.getElementById('modal-upload-area');
const modalFileInput = document.getElementById('modal-file-input');
const modalFilePreview = document.getElementById('modal-file-preview');
const modalPreviewGrid = document.getElementById('modal-preview-grid');

// Security utilities
function getCsrfToken() {
    // Get CSRF token from form input
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    return csrfInput ? csrfInput.value : '';
}

function sanitizeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize notification system
function initNotificationSystem() {
    if (!document.getElementById('notification-container')) {
        const notificationContainer = document.createElement('div');
        notificationContainer.className = 'notification-container';
        notificationContainer.id = 'notification-container';
        document.body.appendChild(notificationContainer);
    }
}

// Show notification
function showNotification(type, title, message, duration = 5000) {
    initNotificationSystem();
    const container = document.getElementById('notification-container');
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;

    const icon = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    }[type];

    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas fa-${icon}"></i>
        </div>
        <div class="notification-content">
            <div class="notification-title">${sanitizeHtml(title)}</div>
            <div class="notification-message">${sanitizeHtml(message)}</div>
        </div>
        <button class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    `;

    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => {
        notification.style.animation = 'slideInRight 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) reverse';
        setTimeout(() => notification.remove(), 400);
    });

    container.appendChild(notification);

    if (duration > 0) {
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.animation = 'slideInRight 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) reverse';
                setTimeout(() => notification.remove(), 400);
            }
        }, duration);
    }

    return notification;
}

// Patient search functionality
searchBtn.addEventListener('click', () => {
    const patientId = patientIdInput.value.trim().toUpperCase();

    // Validate patient ID format
    if (!patientId) {
        showNotification('error', 'Search Error', 'Please enter a Patient ID');
        return;
    }

    // Basic validation for patient ID
    if (!/^[A-Z0-9]+$/.test(patientId)) {
        showNotification('error', 'Invalid Format', 'Patient ID can only contain letters and numbers');
        return;
    }

    if (patientId.length > 20) {
        showNotification('error', 'Invalid Format', 'Patient ID is too long');
        return;
    }

    searchPatient(patientId);
});

// Also allow Enter key to search
patientIdInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        searchBtn.click();
    }
});

// Back to search functionality
if (backToSearchBtn) backToSearchBtn.addEventListener('click', () => {
    recordsContainer.style.display = 'none';
    searchSection.style.display = 'block';
    patientIdInput.value = '';
    patientIdInput.focus();
    resetFilterButtons();
    showArchived = false;
    if (showArchivedBtn) {
        showArchivedBtn.innerHTML = '<i class="fas fa-archive"></i> Archived';
        showArchivedBtn.classList.remove('btn-primary');
    }
});

// Search patient in database - UPDATED WITH SIMPLE FETCH
function searchPatient(patientId) {
    showLoading(true);

    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('action', 'search_patient');
    formData.append('patient_id', patientId);

    fetch('admin-records.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            showLoading(false);
            console.log('--- SEARCH DEBUG ---');
            console.log('Search response data:', data);
            
            if (data.success && data.patient) {
                searchSection.style.display = 'none';
                recordsContainer.style.display = 'block';
                currentPatientId = patientId;
                currentPatientData = data.patient;
                patientName.textContent = data.patient.full_name;
                patientIdDisplay.textContent = `Patient ID: ${patientId}`;

                const initials = getInitials(data.patient.full_name);
                const imgEl = document.getElementById('patient-profile-img');
                const initialsEl = document.getElementById('patient-initials');

                if (data.patient.profile_image) {
                    patientProfileImg.src = `../../${data.patient.profile_image}`;
                    patientProfileImg.style.display = 'block';
                    patientInitials.style.display = 'none';

                    patientProfileImg.onerror = function () {
                        this.style.display = 'none';
                        patientInitials.textContent = initials;
                        patientInitials.style.display = 'block';
                    };
                } else {
                    patientProfileImg.style.display = 'none';
                    patientInitials.textContent = initials;
                    patientInitials.style.display = 'block';
                }

                totalRecords.textContent = data.patient.total_records || '0';

                // Handle Medical History Button and Edit Requests
                const editRequestContainer = document.getElementById('medical-edit-request-container');
                if (editRequestContainer) {
                    editRequestContainer.style.display = 'none';
                    editRequestContainer.innerHTML = '';
                }

                console.log('Checking medical history for button visibility:', data.patient.medical_history);
                viewMedicalHistoryBtn.style.setProperty('display', 'none', 'important');

                if (data.patient.medical_history && Object.keys(data.patient.medical_history).length > 0) {
                    console.log('Medical history found, showing button');
                    
                    // Show the button
                    viewMedicalHistoryBtn.style.setProperty('display', 'flex', 'important');
                    viewMedicalHistoryBtn.style.setProperty('color', '#ffffff', 'important');
                    
                    // Remove any existing event listeners by replacing the button
                    const newBtn = viewMedicalHistoryBtn.cloneNode(true);
                    viewMedicalHistoryBtn.parentNode.replaceChild(newBtn, viewMedicalHistoryBtn);
                    
                    // Get the new button reference
                    const updatedBtn = document.getElementById('view-medical-history-btn');
                    
                    // Add click event
                    updatedBtn.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Opening medical history for:', data.patient.full_name);
                        if (data.patient.medical_history) {
                            displayMedicalHistory(data.patient.medical_history);
                        } else {
                            showNotification('error', 'Error', 'No medical history data available');
                        }
                    };

                    // Check for pending edit requests
                    if (data.patient.pending_edit_request) {
                        if (editRequestContainer) {
                            editRequestContainer.style.setProperty('display', 'flex', 'important');
                            editRequestContainer.innerHTML = `
                                <span class="badge" style="background-color: #ffc107; color: #000; padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; margin-left: 10px;">
                                    <i class="fas fa-exclamation-circle"></i> Update Requested
                                </span>
                                <button class="btn btn-success btn-sm approve-med-edit-btn" data-request-id="${data.patient.pending_edit_request.id}" style="padding: 2px 8px; font-size: 0.75rem; border-radius: 12px;">
                                    Approve
                                </button>
                                <button class="btn btn-danger btn-sm deny-med-edit-btn" data-request-id="${data.patient.pending_edit_request.id}" style="padding: 2px 8px; font-size: 0.75rem; border-radius: 12px;">
                                    Deny
                                </button>
                            `;

                            const approveBtn = editRequestContainer.querySelector('.approve-med-edit-btn');
                            const denyBtn = editRequestContainer.querySelector('.deny-med-edit-btn');

                            if (approveBtn) {
                                approveBtn.addEventListener('click', (e) => {
                                    e.stopPropagation();
                                    handleMedicalEditRequest(data.patient.pending_edit_request.id, 'approve');
                                });
                            }

                            if (denyBtn) {
                                denyBtn.addEventListener('click', (e) => {
                                    e.stopPropagation();
                                    handleMedicalEditRequest(data.patient.pending_edit_request.id, 'deny');
                                });
                            }
                        }
                    } else if (data.patient.medical_history_edit_allowed == 1) {
                        if (editRequestContainer) {
                            editRequestContainer.style.display = 'flex';
                            editRequestContainer.innerHTML = `
                                <span class="badge" style="background-color: #28a745; color: #fff; padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; margin-left: 10px;">
                                    <i class="fas fa-check-circle"></i> Edit Access Granted
                                </span>
                            `;
                        }
                    }
                } else {
                    console.log('No medical history found for this patient.');
                    viewMedicalHistoryBtn.style.setProperty('display', 'none', 'important');
                }

                if (data.patient.last_updated) {
                    const lastUpdate = new Date(data.patient.last_updated);
                    const now = new Date();
                    const diffDays = Math.floor((now - lastUpdate) / (1000 * 60 * 60 * 24));
                    if (diffDays === 0) {
                        lastUpdated.textContent = 'Today';
                    } else if (diffDays === 1) {
                        lastUpdated.textContent = 'Yesterday';
                    } else if (diffDays < 7) {
                        lastUpdated.textContent = `${diffDays} days ago`;
                    } else {
                        lastUpdated.textContent = formatDate(data.patient.last_updated);
                    }
                } else {
                    lastUpdated.textContent = '-';
                }

                hideMessages();
                showNotification('success', 'Patient Found', `Successfully retrieved records for ${data.patient.full_name}`);
                loadPatientRecords();
            } else {
                showNotification('error', 'Patient Not Found', data.message || 'Please check the ID and try again.');
            }
        })
        .catch(error => {
            showLoading(false);
            showNotification('error', 'Network Error', 'Please check your connection and try again.');
            console.error('Error:', error);
        });
}

// Custom Admin Modal Helper
function showAdminActionModal(title, message, isPrompt, onConfirm) {
    const modalId = 'adminActionModal_' + Date.now();
    const inputHtml = isPrompt ? `<div style="margin-top: 15px;"><input type="text" id="${modalId}_input" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Optional reason..."></div>` : '';

    const html = `
        <div class="modal custom-modal-overlay" id="${modalId}" style="display: flex; position: fixed; z-index: 10005; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
            <div class="modal-content" style="background-color: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); width: 100%; max-width: 400px; padding: 0; animation: slideIn 0.3s ease;">
                <div style="padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 1.25rem; color: #333;">${title}</h3>
                    <button class="close-custom-modal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #999;">&times;</button>
                </div>
                <div style="padding: 20px; color: #555; font-size: 1rem; line-height: 1.5;">
                    ${message.replace(/\n/g, '<br>')}
                    ${inputHtml}
                </div>
                <div style="padding: 15px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; background: #f9f9f9; border-radius: 0 0 8px 8px;">
                    <button class="cancel-custom-modal btn btn-secondary" style="padding: 6px 12px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; color: #333;">Cancel</button>
                    <button class="confirm-custom-modal btn btn-primary" style="padding: 6px 12px; border: none; background: #007bff; color: #fff; border-radius: 4px; cursor: pointer;">Confirm</button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', html);
    const modal = document.getElementById(modalId);

    const close = () => {
        modal.remove();
    };

    modal.querySelector('.close-custom-modal').onclick = close;
    modal.querySelector('.cancel-custom-modal').onclick = close;
    modal.querySelector('.confirm-custom-modal').onclick = () => {
        let val = '';
        if (isPrompt) {
            val = document.getElementById(`${modalId}_input`).value;
        }
        close();
        onConfirm(val);
    };
}

// Handle Medical Edit Request
function handleMedicalEditRequest(requestId, action) {
    if (!requestId) return;

    if (action === 'deny') {
        showAdminActionModal(
            'Deny Update Request',
            'Please provide a reason for denying the request (optional):',
            true,
            (notes) => {
                processMedicalEditRequest(requestId, action, notes);
            }
        );
    } else {
        showAdminActionModal(
            'Approve Update Request',
            'Are you sure you want to approve this edit request?\n\nThe patient will be able to update their medical history one time.',
            false,
            () => {
                processMedicalEditRequest(requestId, action, '');
            }
        );
    }
}

function processMedicalEditRequest(requestId, action, notes = '') {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('action', action === 'approve' ? 'approve_edit_request' : 'deny_edit_request');
    formData.append('request_id', requestId);
    if (action === 'deny') {
        formData.append('notes', notes);
    }

    fetch('admin-records.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showNotification('success', 'Success', data.message || `Medical update request ${action}d.`);
                searchPatient(currentPatientId);
            } else {
                showNotification('error', 'Error', data.message || `Failed to ${action} request.`);
            }
        })
        .catch(error => {
            showNotification('error', 'Network Error', 'Please check your connection and try again.');
            console.error('Error:', error);
        });
}

// Get initials from name
function getInitials(name) {
    if (!name) return '??';
    return name.split(' ').map(word => word[0]).join('').toUpperCase().substring(0, 2);
}

// Load patient records - UPDATED WITH SIMPLE FETCH
function loadPatientRecords() {
    if (!currentPatientId) return;

    showLoading(true);

    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('action', 'get_records');
    formData.append('client_id', currentPatientId);
    formData.append('filter', currentFilter);
    formData.append('include_archived', showArchived);

    fetch('admin-records.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            showLoading(false);

            if (data.success) {
                currentRecords = data.records || [];
                displayRecords(currentRecords);
                const activeRecords = currentRecords.filter(record => record.is_archived == 0);
                totalRecords.textContent = activeRecords.length;
            } else {
                showNotification('error', 'Load Error', data.message || 'Failed to load records.');
                currentRecords = [];
                displayRecords([]);
            }
        })
        .catch(error => {
            showLoading(false);
            showNotification('error', 'Network Error', 'Please try again.');
            console.error('Error:', error);
        });
}

// Display records in the list
function displayRecords(records) {
    recordsList.innerHTML = '';

    if (!records || records.length === 0) {
        emptyState.style.display = 'block';
        recordsList.style.display = 'none';
        return;
    }

    emptyState.style.display = 'none';
    recordsList.style.display = 'flex';

    records.sort((a, b) => {
        const dateA = new Date(a.record_date + ' ' + a.record_time);
        const dateB = new Date(b.record_date + ' ' + b.record_time);
        return dateB - dateA;
    });

    records.forEach((record, index) => {
        const recordItem = createRecordListItem(record, index);
        recordsList.appendChild(recordItem);
    });
}

// Create record list item
function createRecordListItem(record, index) {
    const item = document.createElement('div');
    item.className = 'record-item';

    if (record.is_archived == 1) {
        item.classList.add('archived');
    }

    item.setAttribute('data-record-id', record.record_id);

    const typeClass = record.record_type;
    const typeLabel = getTypeLabel(record.record_type);
    const formattedDate = formatDate(record.record_date);
    const formattedTime = formatTime(record.record_time);

    item.innerHTML = `
        <div class="record-type-indicator ${typeClass}"></div>
        <div class="record-main-info">
            <div class="record-header-line">
                <span class="record-type-badge ${typeClass}">${sanitizeHtml(typeLabel)}</span>
                ${record.is_archived == 1 ? '<span class="record-type-badge" style="background: #6c757d; color: white;">Archived</span>' : ''}
                <span class="record-date"><i class="far fa-calendar"></i> ${sanitizeHtml(formattedDate)}</span>
                <span class="record-date"><i class="fas fa-hashtag"></i> ${sanitizeHtml(record.record_id)}</span>
            </div>
            <div class="record-title">${sanitizeHtml(record.record_title)}</div>
            <div class="record-details-line">
                <div class="record-detail-item">
                    <i class="fas fa-user-md"></i>
                    <span>${sanitizeHtml(record.dentist)}</span>
                </div>
                <div class="record-detail-item">
                    <i class="fas fa-clock"></i>
                    <span>${sanitizeHtml(record.duration || 'Not specified')}</span>
                </div>
                <div class="record-detail-item">
                    <i class="fas fa-stethoscope"></i>
                    <span>${sanitizeHtml((record.procedure || '').substring(0, 40))}${(record.procedure || '').length > 40 ? '...' : ''}</span>
                </div>
            </div>
            <div class="record-description">
                ${sanitizeHtml((record.description || '').substring(0, 150))}${(record.description || '').length > 150 ? '...' : ''}
            </div>
        </div>
        <div class="record-actions">
            <button class="record-action-btn view-btn" title="View Details">
                <i class="fas fa-eye"></i>
            </button>
            ${record.is_archived == 1 ? '' : `
                <button class="record-action-btn edit-btn" title="Edit Record">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="record-action-btn archive-btn" title="Archive Record">
                    <i class="fas fa-archive"></i>
                </button>
            `}
            ${record.is_archived == 1 ? `
                <button class="record-action-btn restore-btn" title="Restore Record">
                    <i class="fas fa-undo"></i>
                </button>
            ` : ''}
        </div>
    `;

    const viewBtn = item.querySelector('.view-btn');
    const editBtn = item.querySelector('.edit-btn');
    const archiveBtn = item.querySelector('.archive-btn');
    const restoreBtn = item.querySelector('.restore-btn');

    viewBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        viewRecordDetails(record);
    });

    if (editBtn) {
        editBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            editRecord(record);
        });
    }

    if (archiveBtn) {
        archiveBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            showArchiveModal(record.record_id);
        });
    }

    if (restoreBtn) {
        restoreBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            restoreRecord(record.record_id);
        });
    }

    item.addEventListener('click', (e) => {
        if (!e.target.closest('.record-action-btn')) {
            viewRecordDetails(record);
        }
    });

    return item;
}

// Get type label
function getTypeLabel(type) {
    const labels = {
        'treatment': 'Treatment',
        'consultation': 'Consultation',
        'xray': 'X-Ray',
        'prescription': 'Prescription',
        'followup': 'Follow-up',
        'emergency': 'Emergency'
    };
    return labels[type] || type;
}

// Format date
function formatDate(dateString) {
    if (!dateString) return 'Unknown date';
    try {
        const safeDateString = dateString.replace(' ', 'T');
        const date = new Date(safeDateString);
        if (isNaN(date.getTime())) return dateString;
        const options = { month: 'long', day: 'numeric', year: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    } catch (e) {
        return dateString;
    }
}

// Format time
function formatTime(timeString) {
    if (!timeString) return 'Unknown time';
    try {
        const [hours, minutes] = timeString.split(':');
        const hour = parseInt(hours);
        if (isNaN(hour)) return timeString;
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    } catch (e) {
        return timeString;
    }
}

// Filter buttons
document.querySelectorAll('[data-filter]').forEach(button => {
    button.addEventListener('click', (e) => {
        const filter = e.target.getAttribute('data-filter');
        currentFilter = filter;
        resetFilterButtons();
        e.target.classList.remove('btn');
        e.target.classList.add('btn-primary');
        loadPatientRecords();
    });
});

// Reset filter buttons
function resetFilterButtons() {
    document.querySelectorAll('[data-filter]').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn');
    });
    document.getElementById('filter-all-btn').classList.remove('btn');
    document.getElementById('filter-all-btn').classList.add('btn-primary');
}

// Toggle archived records view
if (showArchivedBtn) showArchivedBtn.addEventListener('click', () => {
    showArchived = !showArchived;
    showArchivedBtn.innerHTML = showArchived ?
        '<i class="fas fa-eye-slash"></i> Hide Archived' :
        '<i class="fas fa-archive"></i> Show Archived';
    showArchivedBtn.classList.toggle('btn-primary', showArchived);
    loadPatientRecords();
});

// View Record Details Modal functionality
let currentViewRecord = null;

function viewRecordDetails(record) {
    currentViewRecord = record;
    const formattedDate = formatDate(record.record_date);
    const formattedDateTime = `${formattedDate} at ${formatTime(record.record_time)}`;

    let files = [];
    try {
        files = JSON.parse(record.files || '[]');
    } catch (e) {
        files = [];
    }

    let toothNumbers = [];
    try {
        toothNumbers = JSON.parse(record.tooth_numbers || '[]');
    } catch (e) {
        toothNumbers = [];
    }

    let surfaces = [];
    try {
        surfaces = JSON.parse(record.surfaces || '[]');
    } catch (e) {
        surfaces = [];
    }

    const surfaceLabels = {
        'mesial': 'Mesial (M)',
        'distal': 'Distal (D)',
        'occlusal': 'Occlusal (O)',
        'buccal': 'Buccal (B)',
        'lingual': 'Lingual (L)',
        'palatal': 'Palatal (P)'
    };

    const detailsHTML = `
        <div class="medical-record-container">
            <div class="medical-record-header">
                <h4><i class="fas fa-file-medical"></i> Medical Record Details</h4>
                <p>Complete treatment information and documentation</p>
                <div class="record-meta-grid">
                    <div class="record-meta-item">
                        <div class="record-meta-label"><i class="fas fa-hashtag"></i> Record ID</div>
                        <div class="record-meta-value">${sanitizeHtml(record.record_id)}</div>
                    </div>
                    <div class="record-meta-item">
                        <div class="record-meta-label"><i class="fas fa-tag"></i> Record Type</div>
                        <div class="record-meta-value">${sanitizeHtml(getTypeLabel(record.record_type))}
                            <span class="record-type-badge ${record.record_type}" style="margin-left: 8px; font-size: 0.8rem;">
                                ${sanitizeHtml(record.record_type.toUpperCase())}
                            </span>
                        </div>
                    </div>
                    <div class="record-meta-item">
                        <div class="record-meta-label"><i class="fas fa-calendar-alt"></i> Date & Time</div>
                        <div class="record-meta-value">${sanitizeHtml(formattedDateTime)}</div>
                    </div>
                    <div class="record-meta-item">
                        <div class="record-meta-label"><i class="fas fa-user-md"></i> Dentist</div>
                        <div class="record-meta-value">${sanitizeHtml(record.dentist)}</div>
                    </div>
                    <div class="record-meta-item">
                        <div class="record-meta-label"><i class="fas fa-clock"></i> Duration</div>
                        <div class="record-meta-value">${sanitizeHtml(record.duration || 'Not specified')}</div>
                    </div>
                    <div class="record-meta-item">
                        <div class="record-meta-label"><i class="fas fa-user"></i> Created By</div>
                        <div class="record-meta-value">${sanitizeHtml(record.created_by)}</div>
                    </div>
                    <div class="record-meta-item">
                        <div class="record-meta-label"><i class="fas fa-calendar-plus"></i> Created On</div>
                        <div class="record-meta-value">${formatDate(record.created_at)}</div>
                    </div>
                    ${record.is_archived == 1 ? `
                        <div class="record-meta-item">
                            <div class="record-meta-label"><i class="fas fa-archive"></i> Archive Status</div>
                            <div class="record-meta-value" style="color: #6c757d;">
                                Archived by ${record.archived_by ? sanitizeHtml(record.archived_by) : 'unknown'} on ${record.archived_at ? formatDate(record.archived_at) : 'unknown date'}
                            </div>
                        </div>
                        <div class="record-meta-item">
                            <div class="record-meta-label"><i class="fas fa-comment-alt"></i> Archive Reason</div>
                            <div class="record-meta-value">${sanitizeHtml(record.archive_reason || 'Not specified')}</div>
                        </div>
                    ` : ''}
                </div>
            </div>
            <div class="record-section">
                <div class="record-section-title"><i class="fas fa-clipboard-check"></i> Record Title</div>
                <div class="record-section-content">
                    <h3 style="color: #03074f; margin: 0; font-family: 'Forum', serif;">${sanitizeHtml(record.record_title)}</h3>
                </div>
            </div>
            <div class="record-section">
                <div class="record-section-title"><i class="fas fa-procedures"></i> Procedure Details</div>
                <div class="record-section-content">
                    <p style="font-size: 1.1rem; color: #0d5bb9; font-weight: 600; margin-bottom: 15px;">
                        <i class="fas fa-stethoscope"></i> ${sanitizeHtml(record.procedure || 'No procedure details')}
                    </p>
                    <div style="background: #e6f0ff; padding: 20px; border-radius: 8px; border-left: 4px solid #00a65a; margin-bottom: 15px;">
                        <strong style="color: #03074f; display: block; margin-bottom: 10px;">Treatment Description:</strong>
                        ${sanitizeHtml(record.description || 'No description available').replace(/\n/g, '<br>')}
                    </div>
                    ${record.findings ? `
                        <div style="background: #f0f8ff; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff;">
                            <strong style="color: #004085; display: block; margin-bottom: 10px;">Clinical Findings:</strong>
                            ${sanitizeHtml(record.findings).replace(/\n/g, '<br>')}
                        </div>
                    ` : ''}
                </div>
            </div>
            ${toothNumbers.length > 0 || surfaces.length > 0 ? `
                <div class="record-section">
                    <div class="record-section-title"><i class="fas fa-tooth"></i> Odontogram Details</div>
                    <div class="record-section-content">
                        <div class="odontogram-display-container">
                            ${toothNumbers.length > 0 ? `
                                <div class="odontogram-section">
                                    <h5 style="margin-top: 0; color: #2c3e50;">
                                        <i class="fas fa-teeth"></i> Affected Teeth
                                    </h5>
                                    <div class="tooth-numbers-display">
                                        ${toothNumbers.map(tooth => `<span class="tooth-number-badge">Tooth #${tooth}</span>`).join('')}
                                    </div>
                                    <div style="margin-top: 10px; font-size: 0.9rem; color: #6c757d;">
                                        <i class="fas fa-info-circle"></i> Universal numbering system (Teeth 1-32)
                                    </div>
                                </div>
                            ` : ''}
                            ${surfaces.length > 0 ? `
                                <div class="odontogram-section" ${toothNumbers.length > 0 ? 'style="margin-top: 20px;"' : ''}>
                                    <h5 style="margin-top: 0; color: #2c3e50;">
                                        <i class="fas fa-draw-polygon"></i> Tooth Surfaces
                                    </h5>
                                    <div class="surfaces-display">
                                        ${surfaces.map(surface => `
                                            <span class="surface-badge ${surface}">
                                                <i class="fas fa-${getSurfaceIcon(surface)}"></i>
                                                ${surfaceLabels[surface] || surface}
                                            </span>
                                        `).join('')}
                                    </div>
                                    <div style="margin-top: 10px; font-size: 0.9rem; color: #6c757d;">
                                        <i class="fas fa-info-circle"></i> Surface abbreviations in parentheses
                                    </div>
                                </div>
                            ` : ''}
                            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #0d5bb9;">
                                <h6 style="margin-top: 0; color: #2c3e50; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-lightbulb"></i> Odontogram Legend
                                </h6>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 10px;">
                                    <div style="font-size: 0.85rem; padding: 8px; background: white; border-radius: 4px;">
                                        <strong style="color: #00a65a;">M:</strong> Mesial (front)
                                    </div>
                                    <div style="font-size: 0.85rem; padding: 8px; background: white; border-radius: 4px;">
                                        <strong style="color: #f39c12;">D:</strong> Distal (back)
                                    </div>
                                    <div style="font-size: 0.85rem; padding: 8px; background: white; border-radius: 4px;">
                                        <strong style="color: #dd4b39;">O:</strong> Occlusal (biting)
                                    </div>
                                    <div style="font-size: 0.85rem; padding: 8px; background: white; border-radius: 4px;">
                                        <strong style="color: #9b59b6;">B:</strong> Buccal (cheek)
                                    </div>
                                    <div style="font-size: 0.85rem; padding: 8px; background: white; border-radius: 4px;">
                                        <strong style="color: #3498db;">L:</strong> Lingual (tongue)
                                    </div>
                                    <div style="font-size: 0.85rem; padding: 8px; background: white; border-radius: 4px;">
                                        <strong style="color: #1abc9c;">P:</strong> Palatal (roof)
                                    </div>
                                </div>
                                ${toothNumbers.length > 0 ? `
                                    <div style="margin-top: 15px; padding: 10px; background: #e6f0ff; border-radius: 4px;">
                                        <strong style="color: #0d5bb9; display: block; margin-bottom: 5px;">Tooth Numbers Reference:</strong>
                                        <div style="font-size: 0.8rem; color: #2c3e50;">
                                            <div>1-16: Upper right to upper left</div>
                                            <div>17-32: Lower left to lower right</div>
                                            <div style="margin-top: 5px; font-style: italic;">Example: Tooth #14 = Upper left first molar</div>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            ` : ''}
            ${record.notes ? `
                <div class="record-section">
                    <div class="record-section-title"><i class="fas fa-sticky-note"></i> Clinical Notes</div>
                    <div class="record-section-content">
                        ${sanitizeHtml(record.notes).replace(/\n/g, '<br>')}
                    </div>
                </div>
            ` : ''}
            ${record.followup_instructions ? `
                <div class="record-section">
                    <div class="record-section-title"><i class="fas fa-calendar-check"></i> Follow-up Instructions</div>
                    <div class="record-section-content">
                        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; border: 1px solid #ffeaa7;">
                            <strong style="color: #856404; display: block; margin-bottom: 10px;">Patient Instructions:</strong>
                            ${sanitizeHtml(record.followup_instructions).replace(/\n/g, '<br>')}
                        </div>
                    </div>
                </div>
            ` : ''}
            ${files.length > 0 ? `
                <div class="record-section">
                    <div class="record-section-title"><i class="fas fa-paperclip"></i> Attached Files</div>
                    <div class="record-section-content">
                        <p style="color: #2c3e50; margin-bottom: 20px;">The following documents are attached to this record:</p>
                        <div class="record-files-grid">
                            ${files.map(file => {
        const ext = file.split('.').pop().toLowerCase();
        let icon = 'document';
        let type = 'Document';
        if (ext === 'pdf') {
            icon = 'pdf';
            type = 'PDF';
        } else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
            icon = 'image';
            type = 'Image';
        }
        return `
                                    <div class="record-file-item" onclick="downloadFile('${sanitizeHtml(file)}')">
                                        <div class="file-icon ${icon}">
                                            <i class="fas fa-file-${icon === 'pdf' ? 'pdf' : icon === 'image' ? 'image' : 'alt'}"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">${sanitizeHtml(file)}</div>
                                            <div class="file-size">${type} File</div>
                                        </div>
                                        <div style="color: #0d5bb9; font-size: 1.2rem;">
                                            <i class="fas fa-external-link-alt"></i>
                                        </div>
                                    </div>
                                `;
    }).join('')}
                        </div>
                    </div>
                </div>
            ` : ''}
            <div class="medical-disclaimer">
                <h5><i class="fas fa-shield-alt"></i> Medical Information Disclaimer</h5>
                <p>This record contains confidential medical information. Unauthorized access, use, or disclosure is prohibited. For questions about your treatment, please contact our dental office directly.</p>
            </div>
        </div>
    `;

    viewRecordContent.innerHTML = detailsHTML;
    archiveRecordModalBtn.innerHTML = record.is_archived == 1 ?
        '<i class="fas fa-undo"></i> Restore Record' :
        '<i class="fas fa-archive"></i> Archive Record';
    editRecordBtn.style.display = record.is_archived == 1 ? 'none' : 'inline-flex';
    viewRecordModal.classList.add('active');
}

// Get surface icon
function getSurfaceIcon(surface) {
    const icons = {
        'mesial': 'arrow-right',
        'distal': 'arrow-left',
        'occlusal': 'square',
        'buccal': 'smile',
        'lingual': 'language',
        'palatal': 'archway'
    };
    return icons[surface] || 'dot-circle';
}

// Close view modal
if (closeViewModal) closeViewModal.addEventListener('click', () => {
    viewRecordModal.classList.remove('active');
    currentViewRecord = null;
});

if (closeViewRecord) closeViewRecord.addEventListener('click', () => {
    viewRecordModal.classList.remove('active');
    currentViewRecord = null;
});

// Archive/Restore button in the view modal
if (archiveRecordModalBtn) archiveRecordModalBtn.addEventListener('click', () => {
    if (!currentViewRecord) return;

    if (currentViewRecord.is_archived == 1) {
        restoreRecord(currentViewRecord.record_id);
    } else {
        showArchiveModal(currentViewRecord.record_id);
    }
});

// Show archive modal
function showArchiveModal(recordId) {
    const archiveModal = document.getElementById('archive-record-modal');
    const confirmArchiveBtn = document.getElementById('confirm-archive');
    const cancelArchiveBtn = document.getElementById('cancel-archive');
    const closeArchiveModal = document.getElementById('close-archive-modal');

    document.getElementById('archive-record-form').reset();

    confirmArchiveBtn.onclick = () => {
        const reason = document.getElementById('archive-reason').value;
        const notes = document.getElementById('archive-notes').value;

        if (!reason) {
            showNotification('warning', 'Validation Error', 'Please select an archive reason');
            return;
        }

        archiveRecord(recordId, reason, notes);
        archiveModal.classList.remove('active');
        viewRecordModal.classList.remove('active');
    };

    const cancelHandler = () => {
        archiveModal.classList.remove('active');
        document.getElementById('archive-record-form').reset();
    };

    cancelArchiveBtn.onclick = cancelHandler;
    closeArchiveModal.onclick = cancelHandler;
    archiveModal.classList.add('active');
}

// Archive record - UPDATED WITH SIMPLE FETCH
function archiveRecord(recordId, reason = '', notes = '') {
    if (!reason) {
        const confirmNotification = showNotification('info', 'Confirm Archive', 'Are you sure you want to archive this record? Click to confirm.', 0);
        confirmNotification.style.cursor = 'pointer';
        confirmNotification.querySelector('.notification-close').remove();
        confirmNotification.onclick = () => {
            confirmNotification.remove();
            showNotification('info', 'Archive Confirmed', 'Please select an archive reason in the form.', 3000);
            showArchiveModal(recordId);
        };
        return;
    }

    showLoading(true);

    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('action', 'archive_record');
    formData.append('record_id', recordId);
    formData.append('reason', reason);
    formData.append('notes', notes);

    fetch('admin-records.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            showLoading(false);

            if (data.success) {
                showNotification('success', 'Record Archived', 'Record has been successfully archived.');
                loadPatientRecords();
                if (viewRecordModal.classList.contains('active')) {
                    viewRecordModal.classList.remove('active');
                }
            } else {
                showNotification('error', 'Archive Failed', data.message || 'Failed to archive record.');
            }
        })
        .catch(error => {
            showLoading(false);
            showNotification('error', 'Network Error', 'Please try again.');
            console.error('Error:', error);
        });
}

// Restore record
function restoreRecord(recordId) {
    const confirmNotification = showNotification('info', 'Confirm Restore', 'Are you sure you want to restore this record? Click to confirm.', 0);
    confirmNotification.style.cursor = 'pointer';
    confirmNotification.querySelector('.notification-close').remove();
    confirmNotification.onclick = () => {
        confirmNotification.remove();
        performRestoreRecord(recordId);
    };
}

// Restore record - UPDATED WITH SIMPLE FETCH
function performRestoreRecord(recordId) {
    showLoading(true);

    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('action', 'restore_record');
    formData.append('record_id', recordId);

    fetch('admin-records.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            showLoading(false);

            if (data.success) {
                showNotification('success', 'Record Restored', 'Record has been successfully restored.');
                loadPatientRecords();
                if (viewRecordModal.classList.contains('active')) {
                    viewRecordModal.classList.remove('active');
                }
            } else {
                showNotification('error', 'Restore Failed', data.message || 'Failed to restore record.');
            }
        })
        .catch(error => {
            showLoading(false);
            showNotification('error', 'Network Error', 'Please try again.');
            console.error('Error:', error);
        });
}

// Create Record Modal functionality

// Set default date to today and time to current hour
const now = new Date();
recordDateInput.value = now.toISOString().split('T')[0];
recordTimeInput.value = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;

// Open create record modal
createRecordBtn.addEventListener('click', () => {
    createRecordModal.classList.add('active');

    const aptContainer = document.getElementById('appointment-selection-container');
    const aptSelect = document.getElementById('completed-appointment-select');
    if (aptContainer && aptSelect) {
        aptSelect.innerHTML = '<option value="">Create a New Record</option>';
        document.getElementById('record-appointment-id').value = '';

        if (currentPatientData && currentPatientData.completed_appointments && currentPatientData.completed_appointments.length > 0) {
            currentPatientData.completed_appointments.forEach(apt => {
                const opt = document.createElement('option');
                opt.value = apt.id;
                opt.textContent = `${formatDate(apt.appointment_date)} at ${formatTime(apt.appointment_time)} - ${apt.service_name || 'Procedure'}`;
                opt.setAttribute('data-date', apt.appointment_date);
                opt.setAttribute('data-time', apt.appointment_time);
                opt.setAttribute('data-duration', apt.duration_minutes || '');
                opt.setAttribute('data-service', apt.service_name || '');
                aptSelect.appendChild(opt);
            });
            aptContainer.style.display = 'block';
        } else {
            aptContainer.style.display = 'none';
        }
    }

    if (currentPatientId) {
        document.getElementById('record-patient-id').value = currentPatientId;
        document.getElementById('record-patient-id').readOnly = true;

        if (currentPatientData && currentPatientData.medical_history) {
            updateMedicalAlerts(currentPatientData.medical_history);
        } else {
            updateMedicalAlerts(null);
        }
    } else {
        document.getElementById('record-patient-id').readOnly = false;
        updateMedicalAlerts(null);
    }
});

const aptSelect = document.getElementById('completed-appointment-select');
if (aptSelect) {
    aptSelect.addEventListener('change', function() {
        const selectedId = this.value;
        if (!selectedId) {
            const now = new Date();
            document.getElementById('record-date').value = now.toISOString().split('T')[0];
            document.getElementById('record-time').value = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
            document.getElementById('record-duration').value = '';
            document.getElementById('record-procedure').value = '';
            document.getElementById('record-appointment-id').value = '';
            return;
        }

        const option = this.options[this.selectedIndex];
        const date = option.getAttribute('data-date');
        const time = option.getAttribute('data-time');
        const duration = option.getAttribute('data-duration');
        const service = option.getAttribute('data-service');

        document.getElementById('record-date').value = date || '';
        document.getElementById('record-time').value = time || '';
        document.getElementById('record-duration').value = duration ? `${duration} minutes` : '';
        document.getElementById('record-procedure').value = service || '';
        document.getElementById('record-appointment-id').value = selectedId;

        showNotification('info', 'Form Auto-filled', 'Record details have been populated from the selected appointment.');
    });
}

emptyCreateBtn.addEventListener('click', () => {
    createRecordModal.classList.add('active');
    if (currentPatientId) {
        document.getElementById('record-patient-id').value = currentPatientId;
        document.getElementById('record-patient-id').readOnly = true;
    } else {
        document.getElementById('record-patient-id').readOnly = false;
    }
});

closeCreateModal.addEventListener('click', () => {
    createRecordModal.classList.remove('active');
    resetCreateRecordForm();
});

cancelCreateRecord.addEventListener('click', () => {
    createRecordModal.classList.remove('active');
    resetCreateRecordForm();
});

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    modalUploadArea.addEventListener(eventName, preventDefaultsModal, false);
});

function preventDefaultsModal(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    modalUploadArea.addEventListener(eventName, highlightModal, false);
});

['dragleave', 'drop'].forEach(eventName => {
    modalUploadArea.addEventListener(eventName, unhighlightModal, false);
});

function highlightModal() {
    modalUploadArea.classList.add('active');
}

function unhighlightModal() {
    modalUploadArea.classList.remove('active');
}

modalUploadArea.addEventListener('drop', handleModalDrop, false);

function handleModalDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    handleModalFiles(files);
}

modalFileInput.addEventListener('change', (e) => {
    handleModalFiles(e.target.files);
});

let modalUploadedFiles = [];

function handleModalFiles(files) {
    for (let i = 0; i < files.length; i++) {
        const file = files[i];

        if (file.size > 10 * 1024 * 1024) {
            showNotification('error', 'File Too Large', `File "${file.name}" exceeds 10MB size limit.`);
            continue;
        }

        const validTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/jpg',
            'image/png'
        ];

        if (!validTypes.includes(file.type)) {
            showNotification('error', 'Invalid File Type', `File "${file.name}" is not a supported format.`);
            continue;
        }

        modalUploadedFiles.push(file);
    }

    updateModalFilePreview();
}

function updateModalFilePreview() {
    modalPreviewGrid.innerHTML = '';

    if (modalUploadedFiles.length > 0) {
        modalFilePreview.classList.add('active');

        modalUploadedFiles.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'modal-preview-item';

            let iconClass = 'document';
            let icon = 'fa-file-alt';

            if (file.type === 'application/pdf') {
                iconClass = 'pdf';
                icon = 'fa-file-pdf';
            } else if (file.type.includes('image/')) {
                iconClass = 'image';
                icon = 'fa-file-image';
            }

            fileItem.innerHTML = `
                <div class="modal-preview-icon ${iconClass}">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="modal-preview-info">
                    <div class="modal-preview-name">${sanitizeHtml(file.name)}</div>
                    <div class="modal-preview-size">${formatFileSize(file.size)}</div>
                </div>
                <button type="button" class="modal-preview-remove" data-index="${index}">
                    <i class="fas fa-times"></i>
                </button>
            `;

            modalPreviewGrid.appendChild(fileItem);
        });
    } else {
        modalFilePreview.classList.remove('active');
    }
}

modalPreviewGrid.addEventListener('click', (e) => {
    if (e.target.closest('.modal-preview-remove')) {
        const button = e.target.closest('.modal-preview-remove');
        const index = parseInt(button.getAttribute('data-index'));
        modalUploadedFiles.splice(index, 1);
        updateModalFilePreview();
    }
});

procedureInput.addEventListener('change', function () {
    const procedureName = this.value.trim();
    if (procedureName) {
        fetchDurationForProcedure(procedureName, 'record-duration');
    } else {
        durationInput.value = '';
    }
});

procedureInput.addEventListener('input', function () {
    const procedureName = this.value.trim();
    if (procedureName) {
        fetchDurationForProcedure(procedureName, 'record-duration');
    } else {
        durationInput.value = '';
    }
});

function fetchDurationForProcedure(procedureName, durationElementId) {
    if (!procedureName.trim()) {
        document.getElementById(durationElementId).value = '';
        return;
    }

    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('action', 'get_duration');
    formData.append('procedure', procedureName);

    fetch('admin-records.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.duration) {
                document.getElementById(durationElementId).value = data.duration;
            } else {
                document.getElementById(durationElementId).value = '';
            }
        })
        .catch(error => {
            console.error('Error fetching duration:', error);
            document.getElementById(durationElementId).value = '';
        });
}

saveRecordBtn.addEventListener('click', () => {
    const patientId = document.getElementById('record-patient-id').value.trim().toUpperCase();
    const recordType = document.getElementById('record-type').value;
    const recordTitle = document.getElementById('record-title').value.trim();
    const recordDate = document.getElementById('record-date').value;
    const recordTime = document.getElementById('record-time').value;
    const recordDentist = document.getElementById('record-dentist').value;
    const recordDuration = document.getElementById('record-duration').value;
    const recordProcedure = document.getElementById('record-procedure').value.trim();
    const recordDescription = document.getElementById('record-description').value.trim();
    const recordFindings = document.getElementById('record-findings').value.trim();
    const recordNotes = document.getElementById('record-notes').value.trim();
    const recordFollowup = document.getElementById('record-followup').value.trim();

    const toothNumbers = window.getSelectedToothNumbers ? window.getSelectedToothNumbers() : [];
    const surfaces = window.getSelectedSurfaces ? window.getSelectedSurfaces() : [];

    const requiredFields = [
        { field: patientId, name: 'Patient ID', element: 'record-patient-id' },
        { field: recordType, name: 'record type', element: 'record-type' },
        { field: recordTitle, name: 'record title', element: 'record-title' },
        { field: recordDate, name: 'date', element: 'record-date' },
        { field: recordTime, name: 'time', element: 'record-time' },
        { field: recordProcedure, name: 'procedure', element: 'record-procedure' },
        { field: recordDescription, name: 'description', element: 'record-description' }
    ];

    for (const field of requiredFields) {
        if (!field.field) {
            showNotification('warning', 'Validation Error', `Please enter ${field.name}`);
            document.getElementById(field.element).focus();
            return;
        }
    }

    if (!/^[A-Z0-9]+$/.test(patientId)) {
        showNotification('error', 'Invalid Format', 'Patient ID can only contain letters and numbers');
        document.getElementById('record-patient-id').focus();
        return;
    }

    const recordData = {
        client_id: patientId,
        record_type: recordType,
        record_title: recordTitle.substring(0, 200),
        record_date: recordDate,
        record_time: recordTime,
        dentist: recordDentist,
        duration: recordDuration || '',
        procedure: recordProcedure.substring(0, 100),
        description: recordDescription.substring(0, 2000),
        findings: recordFindings.substring(0, 2000),
        notes: recordNotes.substring(0, 1000) || '',
        followup: recordFollowup.substring(0, 1000) || '',
        appointment_id: document.getElementById('record-appointment-id').value || null,
        files: modalUploadedFiles.map(file => file.name),
        tooth_numbers: toothNumbers,
        surfaces: surfaces
    };

    console.log('Record data being sent:', recordData);

    showLoading(true);

    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('action', 'create_record');
    formData.append('record_data', JSON.stringify(recordData));

    modalUploadedFiles.forEach((file, index) => {
        formData.append('files[]', file);
    });

    fetch('admin-records.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            showLoading(false);

            if (data.success) {
                showNotification('success', 'Record Created', `Record ${data.record_id} created successfully!`);
                createRecordModal.classList.remove('active');
                resetCreateRecordForm();

                if (currentPatientId === patientId) {
                    loadPatientRecords();
                }
            } else {
                showNotification('error', 'Create Failed', data.message || 'Failed to create record.');
            }
        })
        .catch(error => {
            showLoading(false);
            showNotification('error', 'Network Error', 'Please try again.');
            console.error('Error:', error);
        });
});

function showSuccessMessage(message) {
    successMessageText.textContent = message;
    successMessage.classList.add('active');
    errorMessage.classList.remove('active');
    setTimeout(() => {
        successMessage.classList.remove('active');
    }, 5000);
}

function showErrorMessage(message) {
    errorMessageText.textContent = message;
    errorMessage.classList.add('active');
    successMessage.classList.remove('active');
    setTimeout(() => {
        errorMessage.classList.remove('active');
    }, 5000);
}

function hideMessages() {
    successMessage.classList.remove('active');
    errorMessage.classList.remove('active');
}

function resetCreateRecordForm() {
    createRecordForm.reset();
    modalUploadedFiles = [];
    updateModalFilePreview();

    const now = new Date();
    recordDateInput.value = now.toISOString().split('T')[0];
    recordTimeInput.value = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;

    if (currentPatientId) {
        document.getElementById('record-patient-id').value = currentPatientId;
        document.getElementById('record-patient-id').readOnly = true;
    } else {
        document.getElementById('record-patient-id').value = '';
        document.getElementById('record-patient-id').readOnly = false;
    }

    procedureInput.value = '';
    durationInput.value = '';

    if (typeof resetOdontogramSelections === 'function') {
        resetOdontogramSelections();
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

if (downloadRecordBtn) {
    downloadRecordBtn.addEventListener('click', () => {
        if (!currentViewRecord) return;
        const formData = new FormData();
        formData.append('csrf_token', getCsrfToken());
        formData.append('action', 'download_record');
        formData.append('record_id', currentViewRecord.record_id);

        showNotification('info', 'Preparing Download', 'Generating PDF for record ' + currentViewRecord.record_id + '...');

        fetch('admin-records.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Server error');
            return response.blob();
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `record-${currentViewRecord.record_id}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        })
        .catch(() => {
            showNotification('error', 'Download Failed', 'Could not generate PDF. Please try again.');
        });
    });
}

const refreshBtn = document.getElementById('refresh-btn');
if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
        loadPatientRecords();
        showNotification('info', 'Refreshed', 'Records list has been refreshed.');
    });
}

function downloadFile(filename) {
    showNotification('info', 'Download Started', `Downloading: ${filename}`);
}

function showLoading(show) {
    const loader = document.getElementById('loading-overlay') || createLoader();
    loader.style.display = show ? 'flex' : 'none';
}

function createLoader() {
    const loader = document.createElement('div');
    loader.id = 'loading-overlay';
    loader.style.cssText = `
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(255, 255, 255, 0.9); 
        display: none; 
        justify-content: center; 
        align-items: center; 
        z-index: 9999; 
        flex-direction: column; 
        backdrop-filter: blur(3px);
    `;

    loader.innerHTML = `
        <div style="text-align: center;">
            <div class="spinner" style="width: 50px; height: 50px; border: 4px solid #e6f0ff; border-top: 4px solid #00a65a; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
            <p style="color: #03074f; font-size: 1rem; font-weight: 600;">Processing your request...</p>
            <p style="color: #2c3e50; opacity: 0.7; font-size: 0.9rem;">Please wait a moment</p>
        </div>
        <style>
            @keyframes spin { 
                0% { transform: rotate(0deg); } 
                100% { transform: rotate(360deg); } 
            }
        </style>
    `;

    document.body.appendChild(loader);
    return loader;
}

function loadServices() {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('action', 'get_services');

    fetch('admin-records.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.services) {
                services = data.services.map(service => service.name);

                const procedureOptions = document.getElementById('procedure-options');
                if (procedureOptions) {
                    procedureOptions.innerHTML = services.map(service =>
                        `<option value="${sanitizeHtml(service)}">${sanitizeHtml(service)}</option>`
                    ).join('');
                }
            }
        })
        .catch(error => {
            console.error('Error loading services:', error);
        });
}

// Function to display Medical History - WITH IMPROVED LAYOUT
function displayMedicalHistory(history) {
    if (!history) {
        console.error('No medical history data provided');
        showNotification('error', 'Error', 'No medical history data available');
        return;
    }

    console.log('Creating medical history modal');

    // Check if there's already a floating modal and remove it
    const existingFloatingModal = document.getElementById('floating-medical-modal');
    if (existingFloatingModal) {
        existingFloatingModal.remove();
    }

    // Create new modal element
    const floatingModal = document.createElement('div');
    floatingModal.id = 'floating-medical-modal';
    floatingModal.style.cssText = `
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        background-color: rgba(0, 0, 0, 0.7) !important;
        z-index: 10006 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        backdrop-filter: blur(5px) !important;
    `;

    // Create modal content
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white !important;
        border-radius: 12px !important;
        width: 90% !important;
        max-width: 900px !important;
        max-height: 90vh !important;
        overflow: hidden !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
        animation: modalSlideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
    `;

    // Create header
    const modalHeader = document.createElement('div');
    modalHeader.style.cssText = `
        padding: 25px 30px !important;
        border-bottom: 1px solid #e1e5e9 !important;
        background: linear-gradient(135deg, #03074f, #0d5bb9) !important;
        color: white !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        border-radius: 12px 12px 0 0 !important;
    `;
    modalHeader.innerHTML = `
        <h3 style="margin: 0; font-size: 1.5rem; font-family: 'Inter', sans-serif; font-weight: 700; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-notes-medical" style="color: #00a65a;"></i> Patient Medical History
        </h3>
        <button id="close-floating-modal" style="background: rgba(255,255,255,0.1); border: none; font-size: 1.8rem; color: white; cursor: pointer; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s ease;">&times;</button>
    `;

    // Create body
    const modalBody = document.createElement('div');
    modalBody.style.cssText = `
        padding: 30px !important;
        max-height: 60vh !important;
        overflow-y: auto !important;
    `;

    modalBody.style.cssText += `
        scrollbar-width: thin !important;
        scrollbar-color: #0d5bb9 #f1f1f1 !important;
    `;

    // Build HTML content with improved layout
    modalBody.innerHTML = `
        <style>
            #floating-medical-modal .medical-grid {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            #floating-medical-modal .medical-card {
                background: #f8fafc;
                border-radius: 10px;
                padding: 0;
                border-left: 4px solid #0d5bb9;
                transition: all 0.3s ease;
                overflow: hidden;
            }
            #floating-medical-modal .medical-card.critical {
                border-left-color: #dc3545;
                background: #fff5f5;
            }
            #floating-medical-modal .card-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 15px 20px;
                background: rgba(0,0,0,0.02);
                border-bottom: 1px solid #e2e8f0;
            }
            #floating-medical-modal .card-title {
                display: flex;
                align-items: center;
                gap: 12px;
                font-weight: 700;
                font-size: 1rem;
                color: #03074f;
            }
            #floating-medical-modal .card-title i {
                font-size: 1.2rem;
                width: 24px;
            }
            #floating-medical-modal .medical-card.critical .card-title {
                color: #991b1b;
            }
            #floating-medical-modal .medical-card.critical .card-title i {
                color: #dc3545;
            }
            #floating-medical-modal .medical-badge {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
            }
            #floating-medical-modal .badge-yes {
                background: #dc3545;
                color: white;
            }
            #floating-medical-modal .badge-no {
                background: #28a745;
                color: white;
            }
            #floating-medical-modal .card-content {
                padding: 15px 20px;
            }
            #floating-medical-modal .exam-details-text {
                background: rgba(255,255,255,0.8);
                border-radius: 6px;
                font-size: 0.9rem;
                color: #555;
                line-height: 1.6;
            }
            #floating-medical-modal .exam-date {
                margin-top: 20px;
                font-size: 0.85rem;
                color: #64748b;
                text-align: right;
                border-top: 1px solid #e2e8f0;
                padding-top: 15px;
            }
            #floating-medical-modal .modal-footer-custom {
                padding: 20px 30px;
                border-top: 1px solid #e1e5e9;
                display: flex;
                justify-content: flex-end;
                background: #f8fafc;
                border-radius: 0 0 12px 12px;
            }
            #floating-medical-modal .btn-close {
                background: linear-gradient(135deg, #03074f, #0d5bb9);
                color: white;
                border: none;
                padding: 10px 24px;
                border-radius: 6px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                font-family: 'Open Sans', sans-serif;
                font-size: 0.9rem;
            }
            #floating-medical-modal .btn-close:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(13, 91, 185, 0.3);
            }
            #floating-medical-modal .condition-text {
                margin-top: 8px;
                font-size: 0.9rem;
                color: #555;
                line-height: 1.6;
            }
        </style>
        <div class="medical-grid">
            <!-- Heart Disease -->
            <div class="medical-card ${history.heart_disease == 1 ? 'critical' : ''}">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-heartbeat"></i>
                        <span>Heart Disease</span>
                    </div>
                    <span class="medical-badge ${history.heart_disease == 1 ? 'badge-yes' : 'badge-no'}">
                        ${history.heart_disease == 1 ? 'Yes' : 'No'}
                    </span>
                </div>
                ${history.heart_disease_details ? `
                    <div class="card-content">
                        <div class="exam-details-text">${sanitizeHtml(history.heart_disease_details)}</div>
                    </div>
                ` : ''}
            </div>

            <!-- Blood Pressure -->
            <div class="medical-card ${history.high_blood_pressure == 1 ? 'critical' : ''}">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-tint"></i>
                        <span>Blood Pressure</span>
                    </div>
                    <span class="medical-badge ${history.high_blood_pressure == 1 ? 'badge-yes' : 'badge-no'}">
                        ${history.high_blood_pressure == 1 ? 'High' : 'Normal'}
                    </span>
                </div>
            </div>

            <!-- Diabetes -->
            <div class="medical-card ${history.diabetes == 1 ? 'critical' : ''}">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-disease"></i>
                        <span>Diabetes</span>
                    </div>
                    <span class="medical-badge ${history.diabetes == 1 ? 'badge-yes' : 'badge-no'}">
                        ${history.diabetes == 1 ? 'Yes' : 'No'}
                    </span>
                </div>
            </div>

            ${history.is_pregnant == 1 ? `
            <div class="medical-card critical">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-baby"></i>
                        <span>Pregnancy Status</span>
                    </div>
                    <span class="medical-badge badge-yes">Pregnant</span>
                </div>
            </div>
            ` : ''}

            <!-- Allergies -->
            <div class="medical-card ${history.allergies ? 'critical' : ''}">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-allergies"></i>
                        <span>Allergies</span>
                    </div>
                    <span class="medical-badge ${history.allergies ? 'badge-yes' : 'badge-no'}">
                        ${history.allergies ? 'Yes' : 'No'}
                    </span>
                </div>
                ${history.allergies ? `
                    <div class="card-content">
                        <div class="condition-text">${sanitizeHtml(history.allergies)}</div>
                    </div>
                ` : ''}
            </div>

            <!-- Current Medications -->
            <div class="medical-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-pills"></i>
                        <span>Current Medications</span>
                    </div>
                </div>
                <div class="card-content">
                    <div class="condition-text">${sanitizeHtml(history.current_medications || 'None reported')}</div>
                </div>
            </div>

            <!-- Past Surgeries -->
            <div class="medical-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-history"></i>
                        <span>Past Surgeries</span>
                    </div>
                </div>
                <div class="card-content">
                    <div class="condition-text">${sanitizeHtml(history.past_surgeries || 'None reported')}</div>
                </div>
            </div>

            <!-- Other Conditions -->
            <div class="medical-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-file-medical-alt"></i>
                        <span>Other Medical Conditions</span>
                    </div>
                </div>
                <div class="card-content">
                    <div class="condition-text">${sanitizeHtml(history.other_conditions || 'None reported')}</div>
                </div>
            </div>
        </div>
        <div class="exam-date">
            <i class="fas fa-clock"></i> Last Exam Date: ${(() => {
                try {
                    if (!history.updated_at) return 'Unknown date';
                    const safeDateString = history.updated_at.replace(' ', 'T');
                    const d = new Date(safeDateString);
                    if (isNaN(d.getTime())) return history.updated_at;
                    return d.toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
                } catch (e) {
                    return history.updated_at || 'Unknown date';
                }
            })()}
        </div>
    `;

    // Create footer
    const modalFooter = document.createElement('div');
    modalFooter.className = 'modal-footer-custom';
    modalFooter.innerHTML = `<button class="btn-close" id="close-floating-modal-btn"><i class="fas fa-times"></i> Close</button>`;

    // Assemble modal
    modalContent.appendChild(modalHeader);
    modalContent.appendChild(modalBody);
    modalContent.appendChild(modalFooter);
    floatingModal.appendChild(modalContent);
    document.body.appendChild(floatingModal);

    // Add close functionality
    const closeBtn1 = document.getElementById('close-floating-modal');
    const closeBtn2 = document.getElementById('close-floating-modal-btn');
    
    const closeModal = () => {
        floatingModal.style.animation = 'modalFadeOut 0.3s ease';
        setTimeout(() => floatingModal.remove(), 300);
    };
    
    if (closeBtn1) closeBtn1.onclick = closeModal;
    if (closeBtn2) closeBtn2.onclick = closeModal;
    
    // Close on background click
    floatingModal.onclick = (e) => {
        if (e.target === floatingModal) {
            closeModal();
        }
    };

    // Add hover effect for close button
    if (closeBtn1) {
        closeBtn1.onmouseover = () => {
            closeBtn1.style.background = 'rgba(255,255,255,0.2)';
            closeBtn1.style.transform = 'rotate(90deg)';
        };
        closeBtn1.onmouseout = () => {
            closeBtn1.style.background = 'rgba(255,255,255,0.1)';
            closeBtn1.style.transform = 'rotate(0deg)';
        };
    }
    
    console.log('Medical history modal created successfully');
}

// Medical History Modal Events
if (closeMedicalHistoryModal) {
    closeMedicalHistoryModal.addEventListener('click', () => {
        medicalHistoryModal.classList.remove('active');
    });
}
if (closeMedicalHistoryBtn) {
    closeMedicalHistoryBtn.addEventListener('click', () => {
        medicalHistoryModal.classList.remove('active');
    });
}

window.addEventListener('click', (e) => {
    if (e.target === medicalHistoryModal) {
        medicalHistoryModal.classList.remove('active');
    }
});

function updateMedicalAlerts(history) {
    const container = document.getElementById('create-record-medical-alerts');
    if (!container) return;

    if (!history) {
        container.innerHTML = '';
        container.classList.remove('active');
        return;
    }

    const alerts = [];
    if (history.heart_disease == 1) alerts.push('Heart Disease');
    if (history.high_blood_pressure == 1) alerts.push('Hypertension');
    if (history.diabetes == 1) alerts.push('Diabetes');
    if (history.has_allergies == 1 || (history.allergies && history.allergies.trim() !== '')) alerts.push('Allergies');

    if (alerts.length > 0) {
        container.innerHTML = `
            <div class="medical-alert-banner">
                <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="alert-content">
                    <div class="alert-title">Medical Alert: Critical Conditions Detected</div>
                    <div class="alert-list">${alerts.map(alert => `<span class="alert-badge">${alert}</span>`).join('')}</div>
                    <div class="view-history-link-container"><a href="javascript:void(0)" class="view-history-link" id="view-history-from-alert"><i class="fas fa-file-medical"></i> View Full Medical History</a></div>
                </div>
            </div>
        `;
        container.classList.add('active');
        document.getElementById('view-history-from-alert').onclick = () => { displayMedicalHistory(history); };
    } else {
        container.innerHTML = `
            <div class="medical-alert-banner" style="border-left-color: var(--success); background: #f0fff4; border-color: #c6f6d5;">
                <div class="alert-icon" style="color: var(--success);"><i class="fas fa-check-circle"></i></div>
                <div class="alert-content">
                    <div class="alert-title" style="color: #22543d;">No Critical Medical Conditions Reported</div>
                    <div class="view-history-link-container" style="border-top-color: #c6f6d5;"><a href="javascript:void(0)" class="view-history-link" id="view-history-from-alert"><i class="fas fa-file-medical"></i> Review Health Assessment</a></div>
                </div>
            </div>
        `;
        container.classList.add('active');
        document.getElementById('view-history-from-alert').onclick = () => { displayMedicalHistory(history); };
    }
}

// EDIT RECORD FUNCTIONALITY
const editRecordModal = document.getElementById('edit-record-modal');
const closeEditModalBtn = document.getElementById('close-edit-modal');
const cancelEditRecordBtn = document.getElementById('cancel-edit-record');
const editRecordForm = document.getElementById('edit-record-form');
const updateRecordBtn = document.getElementById('update-record-btn');

const viewEditRecordBtn = document.getElementById('edit-record-btn');
if (viewEditRecordBtn) {
    viewEditRecordBtn.addEventListener('click', () => {
        if (currentViewRecord) {
            viewRecordModal.classList.remove('active');
            editRecord(currentViewRecord);
        }
    });
}

if (closeEditModalBtn) closeEditModalBtn.addEventListener('click', () => editRecordModal.classList.remove('active'));
if (cancelEditRecordBtn) cancelEditRecordBtn.addEventListener('click', (e) => { e.preventDefault(); editRecordModal.classList.remove('active'); });

function editRecord(record) {
    if (record.is_archived == 1) {
        showNotification('warning', 'Cannot Edit', 'Archived records cannot be edited. Please restore the record first.');
        return;
    }

    document.getElementById('edit-record-id').value = record.record_id;
    document.getElementById('edit-record-patient-id').value = record.client_id;
    document.getElementById('edit-record-type').value = record.record_type;
    document.getElementById('edit-record-title').value = record.record_title;
    document.getElementById('edit-record-date').value = record.record_date;
    document.getElementById('edit-record-time').value = record.record_time;
    document.getElementById('edit-record-duration').value = record.duration || '';
    document.getElementById('edit-record-procedure').value = record.procedure || '';
    document.getElementById('edit-record-description').value = record.description || '';
    document.getElementById('edit-record-findings').value = record.findings || '';
    document.getElementById('edit-record-notes').value = record.notes || '';
    document.getElementById('edit-record-followup').value = record.followup_instructions || '';

    let toothNumbers = [];
    try { toothNumbers = JSON.parse(record.tooth_numbers || '[]'); } catch (e) { }

    const editToothGrid = document.getElementById('edit-tooth-grid');
    if (editToothGrid) {
        editToothGrid.innerHTML = '';
        for (let i = 1; i <= 32; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'tooth-btn';
            if (toothNumbers.includes(i.toString()) || toothNumbers.includes(i)) {
                btn.classList.add('selected');
            }
            btn.dataset.tooth = i;
            btn.textContent = i;
            btn.addEventListener('click', function () {
                this.classList.toggle('selected');
                const selected = Array.from(editToothGrid.querySelectorAll('.tooth-btn.selected')).map(b => b.dataset.tooth);
                updateEditSelectedTeethDisplay(selected);
            });
            editToothGrid.appendChild(btn);
        }
        updateEditSelectedTeethDisplay(toothNumbers);
    }

    let surfaces = [];
    try { surfaces = JSON.parse(record.surfaces || '[]'); } catch (e) { }

    const surfaceCheckboxes = document.querySelectorAll('input[name="edit_surfaces[]"]');
    surfaceCheckboxes.forEach(cb => {
        cb.checked = surfaces.includes(cb.value);
    });
    document.getElementById('edit-record-surfaces').value = JSON.stringify(surfaces);

    if (editToothGrid) {
        editToothGrid.addEventListener('click', (e) => {
            const btn = e.target.closest('.tooth-btn');
            if (!btn) return;
            setTimeout(() => {
                const selectedList = Array.from(editToothGrid.querySelectorAll('.tooth-btn.selected')).map(b => b.dataset.tooth);
                updateEditSelectedTeethDisplay(selectedList);
            }, 50);
        });
    }

    const checkboxesContainer = document.getElementById('edit-surface-checkboxes');
    if (checkboxesContainer) {
        checkboxesContainer.addEventListener('change', () => {
            const selectedSurfaces = Array.from(document.querySelectorAll('input[name="edit_surfaces[]"]:checked')).map(cb => cb.value);
            document.getElementById('edit-record-surfaces').value = JSON.stringify(selectedSurfaces);
        });
    }

    editRecordModal.classList.add('active');
}

function updateEditSelectedTeethDisplay(teeth) {
    const list = document.getElementById('edit-selected-teeth-list');
    const input = document.getElementById('edit-record-tooth-numbers');
    if (list) list.textContent = teeth.length > 0 ? teeth.join(', ') : 'None';
    if (input) input.value = JSON.stringify(teeth);
}

if (editRecordForm) {
    editRecordForm.addEventListener('submit', function (e) {
        e.preventDefault();
        showLoading(true);
        const originalText = updateRecordBtn.innerHTML;
        updateRecordBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        updateRecordBtn.disabled = true;

        const recordData = {
            record_id: document.getElementById('edit-record-id').value,
            client_id: document.getElementById('edit-record-patient-id').value,
            record_type: document.getElementById('edit-record-type').value,
            record_title: document.getElementById('edit-record-title').value,
            record_date: document.getElementById('edit-record-date').value,
            record_time: document.getElementById('edit-record-time').value,
            duration: document.getElementById('edit-record-duration').value,
            procedure: document.getElementById('edit-record-procedure').value,
            description: document.getElementById('edit-record-description').value,
            findings: document.getElementById('edit-record-findings').value,
            notes: document.getElementById('edit-record-notes').value,
            followup: document.getElementById('edit-record-followup').value,
        };

        try { 
            const toothInput = document.getElementById('edit-record-tooth-numbers');
            recordData.tooth_numbers = toothInput ? JSON.parse(toothInput.value || '[]') : []; 
        } catch (e) { recordData.tooth_numbers = []; }
        
        try { 
            const surfaceInput = document.getElementById('edit-record-surfaces');
            recordData.surfaces = surfaceInput ? JSON.parse(surfaceInput.value || '[]') : []; 
        } catch (e) { recordData.surfaces = []; }

        const submitData = new FormData();
        submitData.append('csrf_token', getCsrfToken());
        submitData.append('action', 'update_record');
        submitData.append('record_id', recordData.record_id);
        submitData.append('record_data', JSON.stringify(recordData));

        fetch('admin-records.php', {
            method: 'POST',
            body: submitData
        })
            .then(response => {
                if (!response.ok) throw new Error('Server returned ' + response.status);
                return response.json();
            })
            .then(data => {
                showLoading(false);
                updateRecordBtn.innerHTML = originalText;
                updateRecordBtn.disabled = false;
                if (data.success) {
                    editRecordModal.classList.remove('active');
                    showNotification('success', 'Success', 'Patient record updated successfully');
                    loadPatientRecords();
                } else {
                    showNotification('error', 'Update Failed', data.message || 'An error occurred during update');
                }
            })
            .catch(error => {
                console.error('Error updating record:', error);
                showLoading(false);
                updateRecordBtn.innerHTML = originalText;
                updateRecordBtn.disabled = false;
                showNotification('error', 'System Error', 'Network timeout or server unavailable. Please check the logs.');
            });
    });
}