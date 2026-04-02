document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in
    const appointmentsContent = document.querySelector('.appointments-content');
    if (!appointmentsContent) {
        return; // User not logged in, stop execution
    }

    // Initialize mobile menu
    initMobileMenu();

    // Helper to truncate text and add "See More" link
    function truncateText(text, maxLength = 100) {
        if (!text || text === 'No additional notes' || text.length <= maxLength) {
            return `<span>${text || 'No additional notes'}</span>`;
        }

        const truncated = text.substring(0, maxLength);
        const full = text;

        return `
            <span class="note-content">
                <span class="note-truncated">${truncated}...</span>
                <span class="note-full">${full}</span>
                <button class="note-toggle-btn">See more</button>
            </span>
        `;
    }

    // Centralized helper to setup note toggle event listeners
    function setupNoteToggles(container = document) {
        container.querySelectorAll('.note-toggle-btn').forEach(btn => {
            // Remove existing listener to avoid duplicates if called multiple times on same container
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
            
            newBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const noteContent = this.closest('.note-content');
                const truncated = noteContent.querySelector('.note-truncated');
                const full = noteContent.querySelector('.note-full');
                const isExpanded = full.classList.contains('show');

                if (isExpanded) {
                    full.classList.remove('show');
                    truncated.style.display = 'inline';
                    this.textContent = 'See more';
                } else {
                    full.classList.add('show');
                    truncated.style.display = 'none';
                    this.textContent = 'See less';
                }
            });
        });
    }

    // Calendar functionality
    let currentDate = new Date();
    const calendarDays = document.getElementById('calendar-days');
    const currentMonthElement = document.getElementById('current-month');
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    
    // Appointment display elements
    const selectedDateInfo = document.getElementById('selected-date-info');
    const appointmentDetailsList = document.getElementById('appointment-details-list');
    const appointmentHistoryContainer = document.getElementById('appointment-history');
    const paginationContainer = document.getElementById('pagination-container');
    
    // Store current month appointments
    let currentMonthAppointments = {};
    
    // Store current appointment ID and status for modals
    let currentAppointmentId = null;
    let currentAppointmentStatus = null;
    let currentAppointmentDateTime = null;
    let currentAppointmentDetails = null;
    
    // Pagination variables
    let currentHistoryPage = 1;
    const historyPerPage = 5;
    let totalHistoryPages = 1;
    
    // Initialize calendar
    function initCalendar() {
        console.log("Initializing calendar...");
        renderCalendar(currentDate);
        setupEventListeners();
        loadAppointmentHistory(currentHistoryPage);
        initializeModals();
        initializeFeedbackModal();
    }
    
    // Initialize mobile menu functionality
    function initMobileMenu() {
        const hamburger = document.querySelector('.hamburger');
        const mobileMenu = document.querySelector('.mobile-menu');
        const overlay = document.querySelector('.overlay');
        
        if (hamburger && mobileMenu && overlay) {
            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                mobileMenu.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
            });
            
            overlay.addEventListener('click', function() {
                mobileMenu.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
            
            // Close menu when clicking on links
            const mobileLinks = document.querySelectorAll('.mobile-links a, .mobile-btn');
            mobileLinks.forEach(link => {
                link.addEventListener('click', function() {
                    mobileMenu.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
            });
            
            // Close menu when pressing ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && mobileMenu.classList.contains('active')) {
                    mobileMenu.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        }
    }
    
    // Render calendar for given month
    function renderCalendar(date) {
        console.log("Rendering calendar for:", date);
        const year = date.getFullYear();
        const month = date.getMonth();
        
        // Update current month display
        currentMonthElement.textContent = date.toLocaleDateString('en-US', {
            month: 'long',
            year: 'numeric'
        });
        
        // Get first day of month and number of days
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDay = firstDay.getDay(); // 0 = Sunday
        
        // Clear previous calendar
        calendarDays.innerHTML = '';
        
        // Add empty cells for days before first day of month
        for (let i = 0; i < startingDay; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day empty';
            calendarDays.appendChild(emptyDay);
        }
        
        // Load appointments for the current month
        loadAppointmentsForMonth(year, month + 1).then(appointments => {
            console.log("Appointments loaded:", appointments);
            currentMonthAppointments = appointments;
            
            // Add days of the month
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                dayElement.dataset.date = dateString;
                
                const currentDay = new Date(year, month, day);
                if (currentDay.getTime() === today.getTime()) {
                    dayElement.classList.add('today');
                }
                
                // Add day number
                const dayNumber = document.createElement('div');
                dayNumber.className = 'day-number';
                dayNumber.textContent = day;
                dayElement.appendChild(dayNumber);
                
                // Add appointments for this date if they exist
                if (appointments[dateString] && appointments[dateString].length > 0) {
                    const dayAppointments = appointments[dateString];
                    
                    // Show the first appointment time with status-based background
                    const firstAppointment = dayAppointments[0];
                    
                    // Create time indicator with status-based background
                    const timeIndicator = document.createElement('div');
                    timeIndicator.className = `appointment-time-indicator ${firstAppointment.status}`;
                    
                    // Format time safely - FIXED VERSION
                    const timeString = firstAppointment.time;
                    let formattedTime = timeString;
                    
                    try {
                        // Try to parse and format the time
                        if (timeString && timeString.includes(':')) {
                            const timeParts = timeString.split(':');
                            if (timeParts.length >= 2) {
                                const hours = parseInt(timeParts[0]);
                                const minutes = timeParts[1].padStart(2, '0');
                                // Get just hours and minutes (ignore seconds if present)
                                formattedTime = `${hours}:${minutes}`;
                            }
                        }
                    } catch (error) {
                        console.error('Error formatting time:', error, timeString);
                        // Fallback to original time string
                        formattedTime = timeString.split(':')[0] + ':' + (timeString.split(':')[1] || '00');
                    }
                    
                    timeIndicator.textContent = formattedTime;
                    timeIndicator.title = `${firstAppointment.service} - ${firstAppointment.status}`;
                    
                    dayElement.appendChild(timeIndicator);
                    
                    // If there are multiple appointments, show count
                    if (dayAppointments.length > 1) {
                        const countIndicator = document.createElement('div');
                        countIndicator.className = 'appointment-count';
                        countIndicator.textContent = `+${dayAppointments.length - 1}`;
                        countIndicator.title = `${dayAppointments.length} appointments`;
                        dayElement.appendChild(countIndicator);
                    }
                }
                
                // Add click event
                dayElement.addEventListener('click', () => {
                    selectDate(dayElement, dateString);
                });
                
                calendarDays.appendChild(dayElement);
            }
        }).catch(error => {
            console.error('Error rendering calendar:', error);
            renderCalendarDaysWithoutAppointments(year, month, daysInMonth, startingDay);
        });
    }
    
    // Fallback function to render calendar without appointments if API fails
    function renderCalendarDaysWithoutAppointments(year, month, daysInMonth, startingDay) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        for (let day = 1; day <= daysInMonth; day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            dayElement.dataset.date = dateString;
            
            const currentDay = new Date(year, month, day);
            if (currentDay.getTime() === today.getTime()) {
                dayElement.classList.add('today');
            }
            
            const dayNumber = document.createElement('div');
            dayNumber.className = 'day-number';
            dayNumber.textContent = day;
            dayElement.appendChild(dayNumber);
            
            dayElement.addEventListener('click', () => {
                selectDate(dayElement, dateString);
            });
            
            calendarDays.appendChild(dayElement);
        }
    }
    
    // Load appointments for entire month from server
    async function loadAppointmentsForMonth(year, month) {
        try {
            console.log(`Loading appointments for ${year}-${month}`);
            const response = await fetch(`?action=get_appointments&month=${month}&year=${year}&t=${new Date().getTime()}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log("Appointments data received:", data);
            return data;
        } catch (error) {
            console.error('Error loading appointments:', error);
            return {};
        }
    }
    
    // Select date in calendar
    function selectDate(dayElement, dateString) {
        // Remove selected class from all days
        document.querySelectorAll('.calendar-day').forEach(day => {
            day.classList.remove('selected');
        });
        
        // Add selected class to clicked day
        dayElement.classList.add('selected');
        
        // Display appointments for selected date
        displayAppointmentsForDate(dateString);
    }
    
    // Display appointments for selected date
    function displayAppointmentsForDate(dateString) {
        const appointments = currentMonthAppointments[dateString] || [];
        const dateObj = new Date(dateString);
        const formattedDate = dateObj.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        console.log(`Displaying appointments for ${dateString}:`, appointments);
        
        // Update selected date info
        selectedDateInfo.innerHTML = `
            <h4>${formattedDate}</h4>
            <p>${appointments.length} appointment${appointments.length !== 1 ? 's' : ''} scheduled</p>
        `;
        
        // Clear previous appointment details
        appointmentDetailsList.innerHTML = '';
        
        if (appointments.length === 0) {
            appointmentDetailsList.innerHTML = `
                <div class="no-appointments">
                    <div class="no-appointments-content">
                        <i class="fas fa-calendar-plus"></i>
                        <h4>No Appointments</h4>
                        <p>No appointments scheduled for this date</p>
                    </div>
                </div>
            `;
        } else {
            // Add appointment details
            appointments.forEach(appointment => {
                const appointmentElement = document.createElement('div');
                appointmentElement.className = 'appointment-card';
                
                // Check if actions should be shown based on 48-hour rule
                const showActions = canShowActions(appointment.status, appointment.appointment_date, appointment.appointment_time);
                
                // Format time for display
                const timeString = appointment.time;
                let displayTime = timeString;
                
                try {
                    if (timeString && timeString.includes(':')) {
                        const timeParts = timeString.split(':');
                        if (timeParts.length >= 2) {
                            const hours = parseInt(timeParts[0]);
                            const minutes = timeParts[1].padStart(2, '0');
                            displayTime = `${hours}:${minutes}`;
                        }
                    }
                } catch (error) {
                    console.error('Error formatting display time:', error);
                    displayTime = timeString;
                }
                
                appointmentElement.innerHTML = `
                    <div class="appointment-header">
                        <div class="appointment-date">
                            <span class="date">${displayTime}</span>
                            <span class="time">${appointment.duration || '30 mins'}</span>
                        </div>
                        <div class="appointment-status status-${appointment.status}">
                            <i class="fas fa-${getStatusIcon(appointment.status)}"></i>
                            ${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}
                        </div>
                    </div>
                    <div class="appointment-content">
                        <h4 class="appointment-title">${appointment.service}</h4>
                        <div class="appointment-details">
                            <div class="detail-item">
                                <i class="fas fa-id-badge"></i>
                                <span>Appointment ID: ${appointment.appointment_id}</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-user-md"></i>
                                <span>${appointment.dentist || 'Any Available Dentist'}</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-user"></i>
                                <span>${appointment.patient_first_name} ${appointment.patient_last_name}</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-phone"></i>
                                <span>${appointment.patient_phone}</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-sticky-note"></i>
                                <span><strong>Notes:</strong> ${truncateText(appointment.notes)}</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-money-bill"></i>
                                <span>₱${appointment.service_price || '0'} - ${appointment.payment_type || 'Not specified'}</span>
                            </div>
                        </div>
                    </div>
                    ${showActions.canShow ? `
                    <div class="appointment-actions">
                        <button class="action-btn reschedule-btn" 
                                data-id="${appointment.appointment_id}" 
                                data-status="${appointment.status}"
                                data-date="${appointment.appointment_date}"
                                data-time="${appointment.appointment_time}">
                            <i class="fas fa-calendar-alt"></i> Reschedule
                        </button>
                        <button class="action-btn cancel-btn" 
                                data-id="${appointment.appointment_id}" 
                                data-status="${appointment.status}"
                                data-date="${appointment.appointment_date}"
                                data-time="${appointment.appointment_time}">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                    <div class="appointment-messages">
                        <div class="message-item arrival-message">
                            <i class="fas fa-clock"></i>
                            <span>Please arrive an hour before the appointment.</span>
                        </div>
                        <div class="message-item cancellation-message">
                            <i class="fas fa-info-circle"></i>
                            <div class="message-content">
                                <span class="message-preview">Appointments cannot be cancelled or rescheduled within 48 hours of the appointment time...</span>
                                <span class="message-full">Appointments cannot be cancelled or rescheduled within 48 hours of the appointment time. If you want to cancel or reschedule, please contact our receptionist at least 48 hours before your scheduled appointment.</span>
                                <button class="view-more-btn">View more...</button>
                            </div>
                        </div>
                    </div>
                    ` : showActions.message ? `
                    <div class="appointment-note">
                        <i class="fas fa-info-circle"></i>
                        <div class="receptionist-contact-content">
                            <span class="contact-preview">${showActions.message}</span>
                            <div class="contact-details">
                                <div class="contact-item">
                                    <i class="fas fa-phone"></i>
                                    <span>Phone: 09266492903</span>
                                </div>
                                <div class="contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <span>Email: reception@cosmosmilesdental.com</span>
                                </div>
                                <div class="contact-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Hours: Mon-Sat, 8:00 AM - 6:00 PM</span>
                                </div>
                            </div>
                            <button class="contact-toggle-btn">Click more...</button>
                        </div>
                    </div>
                    ` : ''}
                `;
                
                // Add feedback button icon for completed appointments in calendar details
                if (appointment.status === 'completed') {
                    const feedbackSection = document.createElement('div');
                    feedbackSection.className = 'appointment-feedback-action';
                    feedbackSection.style.padding = '15px';
                    feedbackSection.style.borderTop = '1px solid #eee';
                    
                    if (!appointment.has_feedback) {
                        feedbackSection.innerHTML = `
                            <button class="action-btn feedback-btn" data-id="${appointment.appointment_id}" style="width: 100%;">
                                <i class="fas fa-star"></i> Give Feedback
                            </button>
                        `;
                    } else {
                        feedbackSection.innerHTML = `
                            <div class="feedback-submitted-indicator" style="color: var(--success); text-align: center;">
                                <i class="fas fa-check-circle"></i> Feedback Submitted
                            </div>
                        `;
                    }
                    appointmentElement.appendChild(feedbackSection);
                }

                appointmentDetailsList.appendChild(appointmentElement);
            });
            
            // Note: Event delegation for feedback buttons is handled in setupEventListeners
            
            // Add event listeners to action buttons
            document.querySelectorAll('.reschedule-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const appointmentId = e.currentTarget.dataset.id;
                    const status = e.currentTarget.dataset.status;
                    const appointmentDate = e.currentTarget.dataset.date;
                    const appointmentTime = e.currentTarget.dataset.time;
                    handleAppointmentAction(appointmentId, 'reschedule', status, appointmentDate, appointmentTime);
                });
            });
            
            document.querySelectorAll('.cancel-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const appointmentId = e.currentTarget.dataset.id;
                    const status = e.currentTarget.dataset.status;
                    const appointmentDate = e.currentTarget.dataset.date;
                    const appointmentTime = e.currentTarget.dataset.time;
                    handleAppointmentAction(appointmentId, 'cancel', status, appointmentDate, appointmentTime);
                });
            });
            
            // Add event listeners to view more buttons for cancellation messages
            document.querySelectorAll('.view-more-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const messageContent = this.parentElement;
                    const isExpanded = messageContent.classList.contains('expanded');
                    
                    if (isExpanded) {
                        // Collapse
                        messageContent.classList.remove('expanded');
                        this.textContent = 'View more...';
                        this.classList.remove('expanded');
                    } else {
                        // Expand
                        messageContent.classList.add('expanded');
                        this.textContent = 'View less';
                        this.classList.add('expanded');
                    }
                });
            });
            
            // Add event listeners to contact toggle buttons
            document.querySelectorAll('.contact-toggle-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const contactContent = this.parentElement;
                    const contactDetails = contactContent.querySelector('.contact-details');
                    const isExpanded = contactDetails.classList.contains('show');
                    
                    if (isExpanded) {
                        // Collapse
                        contactDetails.classList.remove('show');
                        this.textContent = 'Click more...';
                        this.classList.remove('expanded');
                    } else {
                        // Expand
                        contactDetails.classList.add('show');
                        this.textContent = 'Click less';
                        this.classList.add('expanded');
                    }
                });
            });


            // Setup note toggles for the newly rendered appointments
            setupNoteToggles(appointmentDetailsList);
        }
    }
    
    // Check if actions should be shown for an appointment
    function canShowActions(status, appointmentDate, appointmentTime) {
        // Only pending and confirmed appointments can have actions
        if (status !== 'pending' && status !== 'confirmed') {
            return { canShow: false, message: '' };
        }
        
        // Check if it's a past appointment
        const appointmentDateTime = new Date(appointmentDate + ' ' + appointmentTime);
        const now = new Date();
        if (appointmentDateTime < now) {
            return { canShow: false, message: 'This appointment has already passed' };
        }
        
        // For confirmed appointments, check 48-hour rule
        if (status === 'confirmed') {
            const canModify = canModifyAppointment(appointmentDate, appointmentTime);
            if (!canModify.canModify) {
                return { 
                    canShow: false, 
                    message: 'Contact receptionist for changes within 48 hours of appointment' 
                };
            }
        }
        
        return { canShow: true, message: '' };
    }
    
    // Check if appointment can be modified (48-hour rule)
    function canModifyAppointment(appointmentDate, appointmentTime) {
        try {
            // Create appointment datetime
            const appointmentDateTime = new Date(appointmentDate + ' ' + appointmentTime);
            const now = new Date();
            
            // Calculate the difference in hours
            const diffMs = appointmentDateTime.getTime() - now.getTime();
            const diffHours = diffMs / (1000 * 60 * 60);
            
            // If appointment is within 48 hours, cannot modify
            if (diffHours < 48) {
                return {
                    canModify: false,
                    message: 'Appointments cannot be cancelled or rescheduled within 48 hours of the appointment time. Please contact the clinic directly.'
                };
            }
            
            return {
                canModify: true,
                message: 'Appointment can be modified.'
            };
            
        } catch (error) {
            console.error('Error checking appointment modification:', error);
            return {
                canModify: false,
                message: 'Error checking appointment modification eligibility.'
            };
        }
    }
    
    // Handle appointment actions
    function handleAppointmentAction(appointmentId, action, status, appointmentDate, appointmentTime) {
        currentAppointmentId = appointmentId;
        currentAppointmentStatus = status;
        currentAppointmentDateTime = { date: appointmentDate, time: appointmentTime };
        
        const cancelModal = document.getElementById('cancelModal');
        const rescheduleModal = document.getElementById('rescheduleModal');
        const verificationModal = document.getElementById('confirmedAppointmentModal');
        
        // Check if it's a confirmed appointment and within 48 hours
        if (status === 'confirmed') {
            const canModify = canModifyAppointment(appointmentDate, appointmentTime);
            
            if (!canModify.canModify) {
                // Show verification modal for appointments within 48 hours
                showModal(verificationModal);
                return;
            }
        }
        
        // For pending appointments or confirmed appointments outside 48 hours
        if (action === 'cancel') {
            showModal(cancelModal);
        } else if (action === 'reschedule') {
            // First load the appointment details for rescheduling
            loadRescheduleAppointmentDetails(appointmentId).then(result => {
                if (result.success) {
                    currentAppointmentDetails = result.appointment;
                    // Now check if it can be rescheduled
                    checkRescheduleEligibility(appointmentId).then(rescheduleResult => {
                        console.log("Reschedule check result:", rescheduleResult);
                        if (rescheduleResult.success && rescheduleResult.can_reschedule) {
                            // Show the reschedule modal with appointment details
                            showRescheduleModalWithDetails(currentAppointmentDetails);
                        } else if (!rescheduleResult.success) {
                            showNotification(rescheduleResult.message || 'Unable to reschedule this appointment. Please contact reception.', 'error');
                        } else {
                            showNotification('This appointment cannot be rescheduled. ' + (rescheduleResult.message || 'Please contact reception.'), 'error');
                        }
                    }).catch(error => {
                        console.error('Error checking reschedule eligibility:', error);
                        showNotification('Error checking reschedule eligibility. Please contact reception.', 'error');
                    });
                } else {
                    showNotification(result.message || 'Error loading appointment details.', 'error');
                }
            }).catch(error => {
                console.error('Error loading appointment details:', error);
                showNotification('Error loading appointment details. Please try again.', 'error');
            });
        }
    }
    
    // Load appointment details for rescheduling
    async function loadRescheduleAppointmentDetails(appointmentId) {
        try {
            console.log("Loading reschedule appointment details for:", appointmentId);
            const response = await fetch(`?action=get_reschedule_details&id=${appointmentId}&t=${new Date().getTime()}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            console.log("Reschedule details result:", result);
            return result;
        } catch (error) {
            console.error('Error loading reschedule appointment details:', error);
            return {
                success: false,
                message: 'Error loading appointment details.'
            };
        }
    }
    
    // Show reschedule modal with appointment details
    function showRescheduleModalWithDetails(appointmentDetails) {
        const rescheduleModal = document.getElementById('rescheduleModal');
        const currentAppointmentDetailsElement = document.getElementById('current-appointment-details');
        
        // Format the original appointment date and time
        const originalDate = new Date(appointmentDetails.original_appointment_date);
        const formattedDate = originalDate.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // Display current appointment details in the modal
        currentAppointmentDetailsElement.innerHTML = `
            <div class="current-appointment-info">
                <h4>Current Appointment Details:</h4>
                <div class="appointment-summary">
                    <div class="summary-item">
                        <i class="fas fa-calendar-day"></i>
                        <span><strong>Date:</strong> ${formattedDate}</span>
                    </div>
                    <div class="summary-item">
                        <i class="fas fa-clock"></i>
                        <span><strong>Time:</strong> ${appointmentDetails.original_appointment_time_display}</span>
                    </div>
                    <div class="summary-item">
                        <i class="fas fa-user-md"></i>
                        <span><strong>Service:</strong> ${appointmentDetails.service_name}</span>
                    </div>
                    <div class="summary-item">
                        <i class="fas fa-user"></i>
                        <span><strong>Patient:</strong> ${appointmentDetails.patient_first_name} ${appointmentDetails.patient_last_name}</span>
                    </div>
                    <div class="summary-item">
                        <i class="fas fa-sticky-note"></i>
                        <span><strong>Notes:</strong> ${appointmentDetails.notes || 'None'}</span>
                    </div>
                </div>
                <div class="reschedule-note">
                    <i class="fas fa-info-circle"></i>
                    <p><strong>Note:</strong> When you proceed to reschedule, all your current appointment details will be pre-filled. You will only need to select a new date and time.</p>
                </div>
            </div>
        `;
        
        // Show the modal
        showModal(rescheduleModal);
    }
    
    // Check if appointment can be rescheduled
    async function checkRescheduleEligibility(appointmentId) {
        try {
            console.log("Checking reschedule eligibility for appointment:", appointmentId);
            const response = await fetch(`?action=check_reschedule&id=${appointmentId}&t=${new Date().getTime()}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            console.log("Reschedule eligibility result:", result);
            return result;
        } catch (error) {
            console.error('Error checking reschedule eligibility:', error);
            return {
                success: false,
                message: 'Error checking reschedule eligibility.'
            };
        }
    }
    
    // Initialize modal functionality
    function initializeModals() {
        // Cancel modal
        const cancelModal = document.getElementById('cancelModal');
        const cancelYesBtn = document.getElementById('cancel-yes');
        const cancelNoBtn = document.getElementById('cancel-no');
        const cancelReasonSelect = document.getElementById('cancel-reason');
        const otherReasonContainer = document.getElementById('other-reason-container');
        
        // Reschedule modal
        const rescheduleModal = document.getElementById('rescheduleModal');
        const rescheduleYesBtn = document.getElementById('reschedule-yes');
        const rescheduleNoBtn = document.getElementById('reschedule-no');
        const rescheduleReasonSelect = document.getElementById('reschedule-reason');
        const rescheduleOtherContainer = document.getElementById('reschedule-other-container');
        
        // Verification modal
        const verificationModal = document.getElementById('confirmedAppointmentModal');
        const closeVerificationBtn = document.getElementById('close-verification');
        
        // Close buttons
        const closeButtons = document.querySelectorAll('.close-modal');
        
        // Cancel reason select change
        if (cancelReasonSelect) {
            cancelReasonSelect.addEventListener('change', function() {
                otherReasonContainer.style.display = this.value === 'other' ? 'block' : 'none';
            });
        }
        
        // Reschedule reason select change
        if (rescheduleReasonSelect) {
            rescheduleReasonSelect.addEventListener('change', function() {
                rescheduleOtherContainer.style.display = this.value === 'other' ? 'block' : 'none';
            });
        }
        
        // Cancel appointment confirmation
        if (cancelYesBtn) {
            cancelYesBtn.addEventListener('click', function() {
                const reason = cancelReasonSelect.value;
                const otherReason = document.getElementById('other-reason').value;
                
                if (!reason) {
                    showNotification('Please select a reason for cancellation', 'error');
                    return;
                }
                
                if (reason === 'other' && !otherReason.trim()) {
                    showNotification('Please specify your reason for cancellation', 'error');
                    return;
                }
                
                const finalReason = reason === 'other' ? otherReason : cancelReasonSelect.options[cancelReasonSelect.selectedIndex].text;
                cancelAppointment(currentAppointmentId, finalReason);
                closeModal(cancelModal);
            });
        }
        
        if (cancelNoBtn) {
            cancelNoBtn.addEventListener('click', function() {
                closeModal(cancelModal);
            });
        }
        
        // Reschedule appointment confirmation
        if (rescheduleYesBtn) {
            rescheduleYesBtn.addEventListener('click', function() {
                const reason = rescheduleReasonSelect.value;
                const otherReason = document.getElementById('reschedule-other-reason').value;
                
                if (!reason) {
                    showNotification('Please select a reason for rescheduling', 'error');
                    return;
                }
                
                if (reason === 'other' && !otherReason.trim()) {
                    showNotification('Please specify your reason for rescheduling', 'error');
                    return;
                }
                
                // Store reschedule details in sessionStorage to pass to the booking page
                if (currentAppointmentDetails) {
                    const rescheduleData = {
                        appointmentId: currentAppointmentId,
                        appointmentDetails: currentAppointmentDetails,
                        reason: reason === 'other' ? otherReason : rescheduleReasonSelect.options[rescheduleReasonSelect.selectedIndex].text
                    };
                    
                    // Store in sessionStorage for the booking page to access
                    sessionStorage.setItem('rescheduleData', JSON.stringify(rescheduleData));
                    
                    // Redirect to new appointment page with reschedule parameter
                    window.location.href = 'new-appointments.php?reschedule=' + encodeURIComponent(currentAppointmentId);
                } else {
                    showNotification('Error: Appointment details not loaded. Please try again.', 'error');
                }
            });
        }
        
        if (rescheduleNoBtn) {
            rescheduleNoBtn.addEventListener('click', function() {
                closeModal(rescheduleModal);
            });
        }
        
        // Verification modal
        if (closeVerificationBtn) {
            closeVerificationBtn.addEventListener('click', function() {
                closeModal(verificationModal);
            });
        }
        
        // Close modal buttons
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                closeModal(modal);
            });
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target);
            }
        });
    }
    
    // Show modal function
    function showModal(modal) {
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }
    
    // Close modal function
    function closeModal(modal) {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            
            // Reset form fields
            const reasonSelects = modal.querySelectorAll('select');
            const textareas = modal.querySelectorAll('textarea');
            
            reasonSelects.forEach(select => {
                select.value = '';
                const otherContainer = modal.querySelector('.other-reason');
                if (otherContainer) otherContainer.style.display = 'none';
            });
            
            textareas.forEach(textarea => {
                textarea.value = '';
            });
            
            // Clear current appointment details
            const currentAppointmentDetailsElement = document.getElementById('current-appointment-details');
            if (currentAppointmentDetailsElement) {
                currentAppointmentDetailsElement.innerHTML = '';
            }
        }
    }
    
    // Load appointment history from server
    async function loadAppointmentHistory(page = 1) {
        try {
            console.log(`Loading appointment history page ${page}`);
            showHistoryLoading();
            
            const response = await fetch(`?action=get_appointment_history&page=${page}&per_page=${historyPerPage}&t=${new Date().getTime()}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            console.log("Appointment history received:", result);
            displayAppointmentHistory(result.history || []);
            createPagination(result.pagination || {});
            currentHistoryPage = page;
            totalHistoryPages = result.pagination?.total_pages || 1;
        } catch (error) {
            console.error('Error loading appointment history:', error);
            displayAppointmentHistory([]);
            createPagination({
                current_page: 1,
                total_pages: 1,
                has_previous: false,
                has_next: false,
                total_records: 0
            });
        }
    }
    
    // Show loading state for history
    function showHistoryLoading() {
        appointmentHistoryContainer.innerHTML = `
            <div class="no-appointments">
                <div class="no-appointments-content">
                    <i class="fas fa-spinner fa-spin"></i>
                    <h4>Loading History...</h4>
                    <p>Your appointment history is being loaded</p>
                </div>
            </div>
        `;
    }
    
    // Display appointment history with full details
    function displayAppointmentHistory(history) {
        console.log("Displaying history:", history);
        appointmentHistoryContainer.innerHTML = '';
        
        if (!history || history.length === 0) {
            appointmentHistoryContainer.innerHTML = `
                <div class="no-appointments">
                    <div class="no-appointments-content">
                        <i class="fas fa-history"></i>
                        <h4>No Past Appointments</h4>
                        <p>Your appointment history will appear here</p>
                    </div>
            </div>
            `;
            return;
        }
        
        history.forEach(appointment => {
            const historyCard = document.createElement('div');
            historyCard.className = 'history-card';
            
            const dateObj = new Date(appointment.date);
            const formattedDate = dateObj.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            // Format service price
            const servicePrice = appointment.service_price ? `₱${parseFloat(appointment.service_price).toFixed(2)}` : '₱0.00';
            
            // Format time for display
            const timeString = appointment.time;
            let displayTime = timeString;
            
            try {
                if (timeString && timeString.includes(':')) {
                    const timeParts = timeString.split(':');
                    if (timeParts.length >= 2) {
                        const hours = parseInt(timeParts[0]);
                        const minutes = timeParts[1].padStart(2, '0');
                        displayTime = `${hours}:${minutes}`;
                    }
                }
            } catch (error) {
                console.error('Error formatting history time:', error);
                displayTime = timeString;
            }
            
            // Create history card HTML with full details
            historyCard.innerHTML = `
                <div class="history-header">
                    <div class="history-date">
                        <span class="date">${formattedDate}</span>
                        <span class="time">${displayTime} • ${appointment.duration_minutes || 30} minutes</span>
                    </div>
                    <div class="history-status status-${appointment.status}">
                        <i class="fas fa-${getStatusIcon(appointment.status)}"></i>
                        ${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1).replace('_', ' ')}
                    </div>
                </div>
                <div class="history-content">
                    <h4 class="history-title">${appointment.service}</h4>
                    <div class="history-details">
                        <div class="history-detail-item">
                            <i class="fas fa-id-badge"></i>
                            <span><strong>Appointment ID:</strong> ${appointment.appointment_id}</span>
                        </div>
                        <div class="history-detail-item">
                            <i class="fas fa-user-md"></i>
                            <span><strong>Dentist:</strong> ${appointment.dentist || 'Any Available Dentist'}</span>
                        </div>
                        ${appointment.specialization ? `
                        <div class="history-detail-item">
                            <i class="fas fa-graduation-cap"></i>
                            <span><strong>Specialization:</strong> ${appointment.specialization}</span>
                        </div>
                        ` : ''}
                        <div class="history-detail-item">
                            <i class="fas fa-user"></i>
                            <span><strong>Patient:</strong> ${appointment.patient_first_name} ${appointment.patient_last_name}</span>
                        </div>
                        <div class="history-detail-item">
                            <i class="fas fa-phone"></i>
                            <span><strong>Phone:</strong> ${appointment.patient_phone}</span>
                        </div>
                        <div class="history-detail-item">
                            <i class="fas fa-envelope"></i>
                            <span><strong>Email:</strong> ${appointment.patient_email}</span>
                        </div>
                        <div class="history-detail-item">
                            <i class="fas fa-money-bill"></i>
                            <span><strong>Price:</strong> ${servicePrice}</span>
                        </div>
                        <div class="history-detail-item">
                            <i class="fas fa-credit-card"></i>
                            <span><strong>Payment:</strong> ${appointment.payment_type || 'Not specified'}</span>
                        </div>
                        ${appointment.notes && appointment.notes !== 'No additional notes' ? `
                        <div class="history-detail-item">
                            <i class="fas fa-sticky-note"></i>
                            <span><strong>Notes:</strong> ${truncateText(appointment.notes)}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                </div>
            `;
            
            // Add feedback button for completed appointments without feedback
            if (appointment.status === 'completed' && !appointment.has_feedback) {
                const feedbackAction = document.createElement('div');
                feedbackAction.className = 'history-actions';
                feedbackAction.style.marginTop = '15px';
                feedbackAction.innerHTML = `
                    <button class="action-btn feedback-btn" data-id="${appointment.appointment_id}">
                        <i class="fas fa-star"></i> Give Feedback
                    </button>
                `;
                historyCard.querySelector('.history-content').appendChild(feedbackAction);
            } else if (appointment.status === 'completed' && appointment.has_feedback) {
                const feedbackIndicator = document.createElement('div');
                feedbackIndicator.className = 'feedback-submitted-indicator';
                feedbackIndicator.style.marginTop = '10px';
                feedbackIndicator.style.color = 'var(--success)';
                feedbackIndicator.style.fontSize = '0.9rem';
                feedbackIndicator.innerHTML = '<i class="fas fa-check-circle"></i> Feedback Submitted';
                historyCard.querySelector('.history-content').appendChild(feedbackIndicator);
            }
            
            appointmentHistoryContainer.appendChild(historyCard);
        });

        // Note: Event delegation for feedback buttons is handled in setupEventListeners
        
        // Setup note toggles for the newly rendered history cards
        setupNoteToggles(appointmentHistoryContainer);
    }
    
    // Create pagination controls - UPDATED for new layout
    function createPagination(pagination) {
        paginationContainer.innerHTML = '';
        
        const { current_page = 1, total_pages = 1, has_previous = false, has_next = false, total_records = 0 } = pagination;
        
        if (total_pages <= 1 && total_records === 0) {
            return;
        }
        
        const paginationElement = document.createElement('div');
        paginationElement.className = 'pagination';
        
        // Previous button
        const prevButton = document.createElement('button');
        prevButton.className = 'pagination-btn';
        prevButton.innerHTML = '<i class="fas fa-chevron-left"></i> Previous';
        prevButton.disabled = !has_previous;
        prevButton.addEventListener('click', () => {
            if (has_previous) {
                loadAppointmentHistory(Number(current_page) - 1);
            }
        });
        
        // Next button
        const nextButton = document.createElement('button');
        nextButton.className = 'pagination-btn';
        nextButton.innerHTML = 'Next <i class="fas fa-chevron-right"></i>';
        nextButton.disabled = !has_next;
        nextButton.addEventListener('click', () => {
            if (has_next) {
                loadAppointmentHistory(Number(current_page) + 1);
            }
        });
        
        // Page numbers (show limited range)
        if (total_pages > 1) {
            const pageNumbers = document.createElement('div');
            pageNumbers.className = 'page-numbers';
            
            // Calculate page range to show (max 5 pages)
            let startPage = Math.max(1, current_page - 2);
            let endPage = Math.min(total_pages, startPage + 4);
            
            // Adjust start if we're near the end
            if (endPage - startPage < 4 && startPage > 1) {
                startPage = Math.max(1, endPage - 4);
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const pageNumber = document.createElement('div');
                pageNumber.className = `page-number ${i === current_page ? 'active' : ''}`;
                pageNumber.textContent = i;
                pageNumber.addEventListener('click', () => {
                    if (i !== current_page) {
                        loadAppointmentHistory(i);
                    }
                });
                pageNumbers.appendChild(pageNumber);
            }
            
            paginationElement.appendChild(prevButton);
            paginationElement.appendChild(pageNumbers);
            paginationElement.appendChild(nextButton);
        } else {
            // Only show Previous and Next buttons if multiple pages
            if (has_previous || has_next) {
                paginationElement.appendChild(prevButton);
                paginationElement.appendChild(nextButton);
            }
        }
        
        // Page info - now below the pagination buttons
        const pageInfo = document.createElement('div');
        pageInfo.className = 'pagination-info';
        pageInfo.textContent = `Page ${current_page} of ${total_pages} (${total_records} records)`;
        paginationElement.appendChild(pageInfo);
        
        paginationContainer.appendChild(paginationElement);
    }
    
    // Cancel appointment
    async function cancelAppointment(appointmentId, reason) {
        try {
            showNotification('Cancelling appointment...', 'info');
            
            const response = await fetch(`?action=cancel_appointment&id=${appointmentId}&reason=${encodeURIComponent(reason)}&t=${new Date().getTime()}`);
            const result = await response.json();
            
            if (result.success) {
                showNotification(result.message, 'success');
                // Refresh calendar and history
                renderCalendar(currentDate);
                loadAppointmentHistory(currentHistoryPage);
                // Clear selected date info
                selectedDateInfo.innerHTML = `
                    <h4>No Date Selected</h4>
                    <p>Click on a date in the calendar to view appointments</p>
                `;
                appointmentDetailsList.innerHTML = '';
            } else {
                showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Error cancelling appointment:', error);
            showNotification('Error cancelling appointment', 'error');
        }
    }
    
    // Get status icon
    function getStatusIcon(status) {
        switch(status) {
            case 'confirmed': return 'check-circle';
            case 'pending': return 'clock';
            case 'completed': return 'calendar-check';
            case 'cancelled': return 'times-circle';
            case 'no_show': return 'user-slash';
            case 'rescheduled': return 'exchange-alt';
            default: return 'circle';
        }
    }
    
    // Setup event listeners
    function setupEventListeners() {
        // New Appointment button click - Prerequisite check
        document.querySelectorAll('.new-appointment-btn').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                const targetUrl = this.getAttribute('href');
                
                try {
                    const response = await fetch(`?action=check_medical_history&t=${new Date().getTime()}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        if (result.is_completed) {
                            window.location.href = targetUrl;
                        } else {
                            showNotification('Please complete your medical history before booking an appointment.', 'error');
                            // Redirect to medical records after a short delay
                            setTimeout(() => {
                                window.location.href = 'patient-records.php';
                            }, 2000);
                        }
                    } else {
                        showNotification(result.message || 'Error checking medical history status.', 'error');
                    }
                } catch (error) {
                    console.error('Error checking medical history:', error);
                    showNotification('Error checking medical history status. Please try again.', 'error');
                }
            });
        });

        prevMonthBtn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar(currentDate);
            // Clear selected date when changing months
            selectedDateInfo.innerHTML = `
                <h4>No Date Selected</h4>
                <p>Click on a date in the calendar to view appointments</p>
            `;
            appointmentDetailsList.innerHTML = '';
        });
        
        nextMonthBtn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar(currentDate);
            // Clear selected date when changing months
            selectedDateInfo.innerHTML = `
                <h4>No Date Selected</h4>
                <p>Click on a date in the calendar to view appointments</p>
            `;
            appointmentDetailsList.innerHTML = '';
        });

        // Event delegation for feedback buttons in appointment history and calendar details
        if (appointmentHistoryContainer) {
            appointmentHistoryContainer.addEventListener('click', function(e) {
                const feedbackBtn = e.target.closest('.feedback-btn');
                if (feedbackBtn) {
                    e.stopPropagation();
                    openFeedbackModal(feedbackBtn.dataset.id);
                }
            });
        }

        if (appointmentDetailsList) {
            appointmentDetailsList.addEventListener('click', function(e) {
                const feedbackBtn = e.target.closest('.feedback-btn');
                if (feedbackBtn) {
                    e.stopPropagation();
                    openFeedbackModal(feedbackBtn.dataset.id);
                }
            });
        }
    }
    
    // Show notification
    function showNotification(message, type) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.custom-notification');
        existingNotifications.forEach(notification => notification.remove());
        
        const notification = document.createElement('div');
        notification.className = `custom-notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="notification-icon ${getNotificationIcon(type)}"></i>
                <span class="notification-message">${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    
    function getNotificationIcon(type) {
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    // Initialize Feedback Modal
    function initializeFeedbackModal() {
        const feedbackModal = document.getElementById('feedbackModal');
        const feedbackSubmitBtn = document.getElementById('feedback-submit');
        const feedbackCancelBtn = document.getElementById('feedback-cancel');
        const closeFeedbackBtn = document.getElementById('close-feedback-modal');

        if (feedbackSubmitBtn) {
            feedbackSubmitBtn.addEventListener('click', submitFeedback);
        }

        if (feedbackCancelBtn) {
            feedbackCancelBtn.addEventListener('click', () => closeModal(feedbackModal));
        }

        if (closeFeedbackBtn) {
            closeFeedbackBtn.addEventListener('click', () => closeModal(feedbackModal));
        }
    }

    function openFeedbackModal(appointmentId) {
        const feedbackModal = document.getElementById('feedbackModal');
        const appointmentIdInput = document.getElementById('feedback-appointment-id');
        const feedbackForm = document.getElementById('feedbackForm');
        
        if (appointmentIdInput) {
            appointmentIdInput.value = appointmentId;
        }
        
        // Reset form
        if (feedbackForm) {
            feedbackForm.reset();
        }
        
        showModal(feedbackModal);
    }

    async function submitFeedback() {
        const appointmentIdInput = document.getElementById('feedback-appointment-id');
        const appointmentId = appointmentIdInput ? appointmentIdInput.value : null;
        const ratingInput = document.querySelector('input[name="rating"]:checked');
        const rating = ratingInput ? ratingInput.value : null;
        const feedbackCommentText = document.getElementById('feedback-comment');
        const feedbackText = feedbackCommentText ? feedbackCommentText.value : '';

        if (!rating) {
            showNotification('Please select a rating', 'error');
            return;
        }

        try {
            showNotification('Submitting feedback...', 'info');
            
            const response = await fetch('?action=submit_feedback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    appointment_id: appointmentId,
                    rating: rating,
                    feedback: feedbackText
                })
            });

            const result = await response.json();

            if (result.success) {
                showNotification('Thank you for your feedback!', 'success');
                closeModal(document.getElementById('feedbackModal'));
                // Refresh history to show "Feedback Submitted"
                loadAppointmentHistory(currentHistoryPage);
            } else {
                showNotification(result.message || 'Error submitting feedback', 'error');
            }
        } catch (error) {
            console.error('Error submitting feedback:', error);
            showNotification('Error submitting feedback', 'error');
        }
    }
    
    // Initialize the appointments page
    initCalendar();
});