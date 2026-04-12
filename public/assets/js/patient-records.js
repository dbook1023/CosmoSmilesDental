// Enhanced Navigation Functionality
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.querySelector('.hamburger');
    const mobileMenu = document.querySelector('.mobile-menu');
    const overlay = document.querySelector('.overlay');
    const userMenu = document.querySelector('.user-menu');
    const userBtn = document.querySelector('.user-btn');
    
    // Mobile Navigation Toggle
    if (hamburger) {
        hamburger.addEventListener('click', () => {
            const isActive = mobileMenu.classList.contains('active');
            
            mobileMenu.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = isActive ? '' : 'hidden';
            
            // Change hamburger icon
            hamburger.innerHTML = isActive ? 
                '<i class="fas fa-bars"></i>' : 
                '<i class="fas fa-times"></i>';
        });
    }
    
    // Close mobile menu when clicking on overlay
    if (overlay) {
        overlay.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            if (hamburger) hamburger.innerHTML = '<i class="fas fa-bars"></i>';
        });
    }
    
    // Enhanced User Menu for Desktop
    if (userMenu && userBtn) {
        let menuTimeout;
        
        userBtn.addEventListener('mouseenter', () => {
            clearTimeout(menuTimeout);
            userMenu.classList.add('active');
        });
        
        userMenu.addEventListener('mouseleave', () => {
            menuTimeout = setTimeout(() => {
                userMenu.classList.remove('active');
            }, 300);
        });
        
        userMenu.addEventListener('mouseenter', () => {
            clearTimeout(menuTimeout);
        });
    }
    
    // Close menu when clicking on links (for single page applications)
    const mobileLinks = document.querySelectorAll('.mobile-links a, .mobile-btn');
    mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            if (hamburger) hamburger.innerHTML = '<i class="fas fa-bars"></i>';
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            if (hamburger) hamburger.innerHTML = '<i class="fas fa-bars"></i>';
        }
    });

    // Add scroll effect to navbar
    window.addEventListener('scroll', () => {
        const header = document.querySelector('header');
        if (window.scrollY > 100) {
            header.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
        } else {
            header.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
        }
    });

    // Patient Records Functionality
    const recordsContainer = document.getElementById('recordsContainer');
    const paginationContainer = document.getElementById('pagination');
    const recordFilter = document.getElementById('recordFilter');
    const exportAllBtn = document.getElementById('exportAllBtn');
    const recordModal = document.getElementById('recordModal');
    const closeModal = document.querySelector('.close-modal');
    const modalBody = document.getElementById('modalBody');
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
    
    const ajaxForm = document.getElementById('ajaxForm');
    const actionInput = document.getElementById('actionInput');
    const recordIdInput = document.getElementById('recordIdInput');

    let currentPage = 1;
    const recordsPerPage = 10;
    
    // Format date
    function formatDate(dateString) {
        if (!dateString) return '';
        try {
            // Fix iOS/Safari formatting issues by converting "YYYY-MM-DD HH:MM:SS" to "YYYY-MM-DDTHH:MM:SS"
            const safeDateString = dateString.replace(' ', 'T');
            const date = new Date(safeDateString);
            if (isNaN(date.getTime())) return dateString; // Fallback
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } catch (e) {
            return dateString;
        }
    }
    
    // Format time
    function formatTime(timeString) {
        const [hours, minutes] = timeString.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const formattedHour = hour % 12 || 12;
        return `${formattedHour}:${minutes} ${ampm}`;
    }

    // Initialize records display with pagination
    function displayRecords() {
        const allRecords = document.querySelectorAll('.record-card');
        
        // Filter records based on selection
        let filteredRecords = Array.from(allRecords);
        if (recordFilter && recordFilter.value !== 'all') {
            filteredRecords = filteredRecords.filter(record => 
                record.getAttribute('data-record-type') === recordFilter.value
            );
        }
        
        const totalRecords = filteredRecords.length;
        const totalPages = Math.ceil(totalRecords / recordsPerPage);
        
        // Hide all records first
        allRecords.forEach(record => {
            record.style.display = 'none';
        });
        
        // Show records for current page
        const startIndex = (currentPage - 1) * recordsPerPage;
        const endIndex = startIndex + recordsPerPage;
        const recordsToShow = filteredRecords.slice(startIndex, endIndex);
        
        recordsToShow.forEach(record => {
            record.style.display = 'block';
        });
        
        // Update pagination
        updatePagination(totalPages, totalRecords);
    }

    // Update pagination controls
    function updatePagination(totalPages, totalRecords) {
        if (!paginationContainer) return;
        
        paginationContainer.innerHTML = '';

        if (totalPages <= 1) return;

        // Previous button
        const prevButton = document.createElement('button');
        prevButton.className = `pagination-btn ${currentPage === 1 ? 'disabled' : ''}`;
        prevButton.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevButton.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                displayRecords();
            }
        });
        paginationContainer.appendChild(prevButton);

        // Page numbers
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            const pageButton = document.createElement('button');
            pageButton.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
            pageButton.textContent = i;
            pageButton.addEventListener('click', () => {
                currentPage = i;
                displayRecords();
            });
            paginationContainer.appendChild(pageButton);
        }

        // Next button
        const nextButton = document.createElement('button');
        nextButton.className = `pagination-btn ${currentPage === totalPages ? 'disabled' : ''}`;
        nextButton.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextButton.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                displayRecords();
            }
        });
        paginationContainer.appendChild(nextButton);

        // Page info
        const pageInfo = document.createElement('span');
        pageInfo.className = 'pagination-info';
        pageInfo.textContent = `Page ${currentPage} of ${totalPages} (${totalRecords} records)`;
        paginationContainer.appendChild(pageInfo);
    }

    // Filter records
    if (recordFilter) {
        recordFilter.addEventListener('change', function() {
            currentPage = 1;
            displayRecords();
        });
    }

    // Attach event listeners to view and download buttons
    function attachEventListeners() {
        const viewButtons = document.querySelectorAll('.view-btn');
        const downloadButtons = document.querySelectorAll('.download-btn');

        // View record details
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const recordId = this.getAttribute('data-record-id');
                const recordTitle = this.getAttribute('data-record-title');
                const recordDate = this.getAttribute('data-record-date');
                const recordTime = this.getAttribute('data-record-time');
                const dentist = this.getAttribute('data-dentist');
                const duration = this.getAttribute('data-duration');
                const procedure = this.getAttribute('data-procedure');
                const description = this.getAttribute('data-description');
                const notes = this.getAttribute('data-notes');
                const followup = this.getAttribute('data-followup');
                const toothNumbers = this.getAttribute('data-tooth-numbers');
                const surfaces = this.getAttribute('data-surfaces');
                const recordType = this.getAttribute('data-record-type');
                const findings = this.getAttribute('data-findings');
                const files = this.getAttribute('data-files');
                
                // Use AJAX to get detailed record info
                sendAjaxRequest('get_record_details', { record_id: recordId })
                    .then(data => {
                        if (data.success) {
                            displayRecordModal(data.record);
                        } else {
                            showNotification('Error loading record details: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Fallback to inline data if AJAX fails
                        displayRecordModalInline({
                            title: recordTitle,
                            date: recordDate,
                            time: recordTime,
                            dentist: dentist,
                            duration: duration,
                            procedure: procedure,
                            description: description,
                            notes: notes,
                            followup: followup,
                            toothNumbers: toothNumbers,
                            surfaces: surfaces,
                            type: recordType,
                            findings: findings,
                            files: files
                        });
                    });
            });
        });

        // Download individual record
        downloadButtons.forEach(button => {
            button.addEventListener('click', function() {
                const recordId = this.getAttribute('data-record-id');
                // Use record_id from the data attribute (which is the alphanumeric ID)
                // However, the button's data-record-id is the numeric 'id' in the database
                // Let's check the button in PHP
                window.location.href = `download-record.php?id=${recordId}`;
            });
        });
    }

    // Export all records
    if (exportAllBtn) {
        exportAllBtn.addEventListener('click', function() {
            window.location.href = 'export-all-records.php';
        });
    }

    // Close modal
    closeModal.addEventListener('click', function() {
        recordModal.classList.remove('active');
        document.body.style.overflow = '';
    });

    // Close modal when clicking outside
    recordModal.addEventListener('click', function(e) {
        if (e.target === recordModal) {
            recordModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // Display record modal with AJAX data
    function displayRecordModal(record) {
        let modalContent = `
            <div class="modal-section">
                <h4>Treatment Information</h4>
                <p><strong>Procedure:</strong> ${record.record_title}</p>
                <p><strong>Record Type:</strong> ${record.record_type.charAt(0).toUpperCase() + record.record_type.slice(1)}</p>
                <p><strong>Date:</strong> ${record.formatted_date} at ${record.formatted_time}</p>
                <p><strong>Dentist:</strong> ${record.dentist}</p>
                <p><strong>Duration:</strong> ${record.duration || 'Not specified'}</p>
            </div>
            
            <div class="modal-section">
                <h4>Description</h4>
                <p>${record.description || 'No description provided'}</p>
            </div>
            
            ${record.findings ? `
                <div class="modal-section" style="background: #f0f8ff; border-left: 4px solid #007bff; padding: 15px; border-radius: 4px;">
                    <h4 style="color: #004085;"><i class="fas fa-search-plus"></i> Clinical Findings</h4>
                    <p>${record.findings}</p>
                </div>
            ` : ''}`;
        
        if (record.procedure && record.procedure.trim() !== '') {
            modalContent += `
                <div class="modal-section">
                    <h4>Procedure Details</h4>
                    <p>${record.procedure}</p>
                </div>`;
        }
        
        if (record.tooth_numbers && record.tooth_numbers.trim() !== '') {
            modalContent += `
                <div class="modal-section">
                    <h4>Tooth Numbers</h4>
                    <p>${record.tooth_numbers}</p>
                </div>`;
        }
        
        if (record.surfaces && record.surfaces.trim() !== '') {
            modalContent += `
                <div class="modal-section">
                    <h4>Surfaces</h4>
                    <p>${record.surfaces}</p>
                </div>`;
        }
        
        if (record.notes && record.notes.trim() !== '') {
            modalContent += `
                <div class="modal-section">
                    <h4>Clinical Notes</h4>
                    <p>${record.notes}</p>
                </div>`;
        }
        
        if (record.followup_instructions && record.followup_instructions.trim() !== '') {
            modalContent += `
                <div class="modal-section">
                    <h4>Follow-up Instructions</h4>
                    <p>${record.followup_instructions}</p>
                </div>`;
        }
        
        modalContent += `
            <div class="modal-section">
                <h4>Additional Information</h4>
                <p>This record contains the complete treatment details as documented by your dental care provider. For any questions about this procedure, please contact our office.</p>
            </div>`;
        
        modalBody.innerHTML = modalContent;
        recordModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Display record modal with inline data (fallback)
    function displayRecordModalInline(record) {
        let modalContent = `
            <div class="modal-section">
                <h4>Treatment Information</h4>
                <p><strong>Procedure:</strong> ${record.title}</p>
                <p><strong>Record Type:</strong> ${record.type.charAt(0).toUpperCase() + record.type.slice(1)}</p>
                <p><strong>Date:</strong> ${formatDate(record.date)} at ${formatTime(record.time)}</p>
                <p><strong>Dentist:</strong> ${record.dentist}</p>
                <p><strong>Duration:</strong> ${record.duration || 'Not specified'}</p>
            </div>
            
            <div class="modal-section">
                <h4>Description</h4>
                <p>${record.description || 'No description provided'}</p>
            </div>
            
            ${record.findings ? `
                <div class="modal-section" style="background: #f0f8ff; border-left: 4px solid #007bff; padding: 15px; border-radius: 4px;">
                    <h4 style="color: #004085;"><i class="fas fa-search-plus"></i> Clinical Findings</h4>
                    <p>${record.findings}</p>
                </div>
            ` : ''}`;

        if (record.files && record.files !== '[]') {
            try {
                const files = JSON.parse(record.files);
                if (files.length > 0) {
                    modalContent += `
                        <div class="modal-section">
                            <h4><i class="fas fa-paperclip"></i> Attached Documents</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                                ${files.map(file => `
                                    <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px; border: 1px solid #dee2e6;">
                                        <i class="fas fa-file-pdf" style="color: #dc3545; font-size: 1.2rem;"></i>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-size: 0.85rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${file}</div>
                                        </div>
                                        <a href="../uploads/patient_records/${file}" target="_blank" style="color: #00a65a;"><i class="fas fa-download"></i></a>
                                    </div>
                                `).join('')}
                            </div>
                        </div>`;
                }
            } catch (e) { console.error('Error parsing files:', e); }
        }
        
        if (record.procedure && record.procedure.trim() !== '') {
            modalContent += `
                <div class="modal-section">
                    <h4>Procedure Details</h4>
                    <p>${record.procedure}</p>
                </div>`;
        }
        
        if (record.toothNumbers && record.toothNumbers.trim() !== '') {
            modalContent += `
                <div class="modal-section">
                    <h4>Tooth Numbers</h4>
                    <p>${record.toothNumbers}</p>
                </div>`;
        }
        
        if (record.surfaces && record.surfaces.trim() !== '') {
            modalContent += `
                <div class="modal-section">
                    <h4>Surfaces</h4>
                    <p>${record.surfaces}</p>
                </div>`;
        }
        
        if (record.notes && record.notes.trim() !== '') {
            modalContent += `
                <div class="modal-section">
                    <h4>Clinical Notes</h4>
                    <p>${record.notes}</p>
                </div>`;
        }
        
        if (record.followup && record.followup.trim() !== '') {
            modalContent += `
                <div class="modal-section">
                    <h4>Follow-up Instructions</h4>
                    <p>${record.followup}</p>
                </div>`;
        }
        
        modalContent += `
            <div class="modal-section">
                <h4>Additional Information</h4>
                <p>This record contains the complete treatment details as documented by your dental care provider. For any questions about this procedure, please contact our office.</p>
            </div>`;
        
        modalBody.innerHTML = modalContent;
        recordModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Send AJAX request
    function sendAjaxRequest(action, extraData = {}) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('csrf_token', csrfToken);
            
            // Add extra data
            Object.keys(extraData).forEach(key => {
                formData.append(key, extraData[key]);
            });
            
            fetch('../controllers/ClientRecordsController.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => resolve(data))
            .catch(error => reject(error));
        });
    }

    // Trigger file download (simulated)
    function triggerFileDownload(filename, data) {
        // In a real application, you would:
        // 1. Generate a PDF/CSV file
        // 2. Create a download link
        // 3. Trigger the download
        
        console.log('Download triggered:', filename, data);
        showNotification('Download started. Check your downloads folder.', 'success');
    }

    // Notification function
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button class="notification-close">&times;</button>
        `;
        
        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
            color: ${type === 'success' ? '#155724' : '#721c24'};
            padding: 12px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Close button
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.style.cssText = `
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        closeBtn.addEventListener('click', () => {
            notification.remove();
        });
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // Add CSS for animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .record-type-badge {
            background: var(--accent);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .records-count {
            color: var(--dark);
            font-size: 0.95rem;
            margin-top: 5px;
            opacity: 0.8;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    `;
    document.head.appendChild(style);

    // Initialize the page
    const recordCards = document.querySelectorAll('.record-card');
    if (recordCards.length > 0 || document.querySelector('.no-records')) {
        attachEventListeners();
        displayRecords();
    }

    // --- Custom Modal Helper ---
    function showCustomConfirm(title, message, onConfirm) {
        const modalId = 'customConfirmModal_' + Date.now();
        const html = `
            <div class="modal custom-modal-overlay" id="${modalId}" style="display: flex; position: fixed; z-index: 10005; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
                <div class="modal-content" style="background-color: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); width: 100%; max-width: 400px; padding: 0; animation: slideIn 0.3s ease;">
                    <div style="padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 1.25rem; color: #333;">${title}</h3>
                        <button class="close-custom-modal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #999;">&times;</button>
                    </div>
                    <div style="padding: 20px; color: #555; font-size: 1rem; line-height: 1.5;">
                        ${message.replace(/\n/g, '<br>')}
                    </div>
                    <div style="padding: 15px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; background: #f9f9f9; border-radius: 0 0 8px 8px;">
                        <button class="cancel-custom-modal btn secondary" style="padding: 8px 16px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; color: #333;">Cancel</button>
                        <button class="confirm-custom-modal btn primary" style="padding: 8px 16px; border: none; background: #0d6efd; color: #fff; border-radius: 4px; cursor: pointer;">Confirm</button>
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
            close();
            onConfirm();
        };
    }

    // --- Medical Exam Logic ---
    const medicalExamModal = document.getElementById('medicalExamModal');
    const medicalExamForm = document.getElementById('medicalExamForm');
    const openExamBtn = document.getElementById('openExamBtn');
    const viewExamBtn = document.getElementById('viewExamBtn');
    const editExamBtn = document.getElementById('editExamBtn');
    const requestEditBtn = document.getElementById('requestEditBtn');
    
    // Helper: disable/enable all form inputs
    function setFormReadOnly(readonly) {
        if (!medicalExamForm) return;
        const inputs = medicalExamForm.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            if (input.type === 'hidden') return;
            input.disabled = readonly;
        });
        const submitBtn = medicalExamForm.querySelector('.submit-exam-btn');
        if (submitBtn) {
            submitBtn.style.display = readonly ? 'none' : '';
        }
        const footer = medicalExamForm.querySelector('.submit-exam-footer');
        if (footer) {
            footer.style.display = readonly ? 'none' : '';
        }
    }

    // Open exam for first-time completion (pending status)
    if (openExamBtn) {
        openExamBtn.addEventListener('click', () => {
            setFormReadOnly(false);
            medicalExamModal.style.display = 'flex';
        });
    }

    // View completed exam (read-only)
    if (viewExamBtn) {
        viewExamBtn.addEventListener('click', () => {
            setFormReadOnly(true);
            medicalExamModal.style.display = 'flex';
        });
    }

    // Edit exam (granted permission)
    if (editExamBtn) {
        editExamBtn.addEventListener('click', () => {
            setFormReadOnly(false);
            medicalExamModal.style.display = 'flex';
        });
    }

    // Request edit permission
    if (requestEditBtn) {
        requestEditBtn.addEventListener('click', () => {
            showCustomConfirm(
                'Request Update Permission',
                'Request permission from your dentist to update your medical history?\n\nYou will be notified once the request is reviewed.',
                () => {
                    requestEditBtn.disabled = true;
                    requestEditBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

                    const formData = new FormData();
                    formData.append('action', 'request_edit');

                    fetch('submit-medical-history.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            requestEditBtn.innerHTML = '<i class="fas fa-clock"></i> Update Request Pending';
                            requestEditBtn.style.opacity = '0.6';
                            requestEditBtn.style.cursor = 'not-allowed';
                        } else {
                            showNotification(data.message, 'error');
                            requestEditBtn.disabled = false;
                            requestEditBtn.innerHTML = '<i class="fas fa-pen-to-square"></i> Request Update';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('An unexpected error occurred.', 'error');
                        requestEditBtn.disabled = false;
                        requestEditBtn.innerHTML = '<i class="fas fa-pen-to-square"></i> Request Update';
                    });
                }
            );
        });
    }

    // --- Refined Medical Exam Toggle Logic ---
    const setupMedicalToggle = (radioName, detailsId) => {
        const radios = document.getElementsByName(radioName);
        const detailsContainer = document.getElementById(detailsId);
        const textarea = detailsContainer?.querySelector('textarea');

        if (radios.length > 0 && detailsContainer) {
            radios.forEach(radio => {
                radio.addEventListener('change', (e) => {
                    const isYes = e.target.value === '1';
                    if (isYes) {
                        detailsContainer.classList.add('show');
                        if (textarea) textarea.setAttribute('required', 'required');
                    } else {
                        detailsContainer.classList.remove('show');
                        if (textarea) textarea.removeAttribute('required');
                    }
                });
            });
        }
    };

    setupMedicalToggle('heart_disease', 'details-heart_disease');
    setupMedicalToggle('has_allergies', 'details-has_allergies');
    setupMedicalToggle('taking_meds', 'details-taking_meds');

    // Close modal
    const closeExamBtn = medicalExamModal?.querySelector('.close-modal');
    if (closeExamBtn) {
        closeExamBtn.addEventListener('click', () => {
            medicalExamModal.style.display = 'none';
        });
    }

    // Close modal when clicking outside
    if (medicalExamModal) {
        medicalExamModal.addEventListener('click', (e) => {
            if (e.target === medicalExamModal) {
                // Only allow closing if not pending
                const closeBtn = medicalExamModal.querySelector('.close-modal');
                if (closeBtn) {
                    medicalExamModal.style.display = 'none';
                }
            }
        });
    }

    // Submit Medical Exam (both new and update)
    if (medicalExamForm) {
        medicalExamForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitExamBtn');
            if (!submitBtn) return;
            
            const originalBtnText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            const formData = new FormData(this);
            
            fetch('submit-medical-history.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Medical history saved successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
            })
            .catch(error => {
                console.error('Error submitting exam:', error);
                showNotification('An unexpected error occurred.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            });
        });
    }
});