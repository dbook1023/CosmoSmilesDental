// Global variables
let currentStep = 1;
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();
let selectedDate = null;
let selectedTime = null;
let selectedServicePrice = 0;
let monthlyAvailability = {};
let isReschedule = false;
let rescheduleAppointmentId = '';
let rescheduleDate = '';
let rescheduleTime = '';

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - initializing scripts');
    
    // Check if this is a reschedule
    if (typeof window.isReschedule !== 'undefined') {
        isReschedule = window.isReschedule;
    }
    
    if (typeof window.rescheduleAppointmentId !== 'undefined') {
        rescheduleAppointmentId = window.rescheduleAppointmentId;
    }
    
    if (typeof window.rescheduleDate !== 'undefined') {
        rescheduleDate = window.rescheduleDate;
    }
    
    if (typeof window.rescheduleTime !== 'undefined') {
        rescheduleTime = window.rescheduleTime;
    }
    
    console.log('Reschedule data:', {
        isReschedule: isReschedule,
        rescheduleAppointmentId: rescheduleAppointmentId,
        rescheduleDate: rescheduleDate,
        rescheduleTime: rescheduleTime
    });
    
    initializeNavigation();
    initializeForm();
});

// Navigation functionality
function initializeNavigation() {
    const hamburger = document.querySelector('.hamburger');
    const mobileMenu = document.querySelector('.mobile-menu');
    const overlay = document.querySelector('.overlay');
    
    console.log('Navigation elements:', { hamburger, mobileMenu, overlay });

    // Mobile Navigation Toggle
    if (hamburger) {
        hamburger.addEventListener('click', function() {
            console.log('Hamburger clicked');
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
        overlay.addEventListener('click', function() {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            if (hamburger) hamburger.innerHTML = '<i class="fas fa-bars"></i>';
        });
    }
    
    // Close menu when clicking on links
    const mobileLinks = document.querySelectorAll('.mobile-links a, .mobile-btn');
    mobileLinks.forEach(link => {
        link.addEventListener('click', function() {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            if (hamburger) hamburger.innerHTML = '<i class="fas fa-bars"></i>';
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            if (hamburger) hamburger.innerHTML = '<i class="fas fa-bars"></i>';
        }
    });

    // Add scroll effect to navbar
    window.addEventListener('scroll', function() {
        const header = document.querySelector('header');
        if (header) {
            if (window.scrollY > 100) {
                header.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
            } else {
                header.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
            }
        }
    });
}

// Form initialization
function initializeForm() {
    console.log('Initializing form...');
    console.log('Is reschedule:', isReschedule);
    console.log('Reschedule date:', rescheduleDate);
    console.log('Reschedule time:', rescheduleTime);
    
    updateStepIndicators();
    initCalendar();
    setupEventListeners();
    setupRealTimeValidation();
    
    // If reschedule, set the price from the selected service
    if (isReschedule) {
        const serviceSelect = document.getElementById('appointment-service');
        if (serviceSelect && serviceSelect.value) {
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            const price = selectedOption.getAttribute('data-price') || selectedOption.getAttribute('data-selected-price');
            selectedServicePrice = parseFloat(price) || 0;
            
            // Update price display
            const priceDisplay = document.getElementById('service-price-display');
            if (priceDisplay) {
                priceDisplay.textContent = `Price: ₱${selectedServicePrice.toFixed(2)}`;
            }
        }
        
        // Pre-select date/time if available
        if (rescheduleDate && rescheduleTime) {
            console.log('Pre-filling reschedule date/time:', rescheduleDate, rescheduleTime);
            
            // Set current month/year to match the appointment date
            const appointmentDate = new Date(rescheduleDate + 'T00:00:00');
            currentMonth = appointmentDate.getMonth();
            currentYear = appointmentDate.getFullYear();
            
            // Set selected date
            selectedDate = appointmentDate;
            
            // Set selected time
            selectedTime = rescheduleTime;
            
            // Update hidden fields
            document.getElementById('selected-date').value = rescheduleDate;
            document.getElementById('selected-time').value = rescheduleTime;
            
            console.log('Pre-filled data:', {
                selectedDate: selectedDate,
                selectedTime: selectedTime,
                hiddenDate: document.getElementById('selected-date').value,
                hiddenTime: document.getElementById('selected-time').value
            });
        }
    }
}

// Real-time input validation
function setupRealTimeValidation() {
    const firstNameInput = document.getElementById('patient-first-name');
    const lastNameInput = document.getElementById('patient-last-name');
    const phoneInput = document.getElementById('patient-phone');
    
    // Only add validation if not reschedule (fields are readonly during reschedule)
    if (!isReschedule) {
        if (firstNameInput) {
            firstNameInput.addEventListener('input', function(e) {
                // Remove special characters and numbers, allow only letters and spaces
                this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
            });
        }
        
        if (lastNameInput) {
            lastNameInput.addEventListener('input', function(e) {
                // Remove special characters and numbers, allow only letters and spaces
                this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
            });
        }
        
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                // Allow only numbers
                let value = this.value.replace(/\D/g, '');
                
                // Ensure it starts with 0 and limit to 11 digits
                if (value.length > 0 && value[0] !== '0') {
                    value = '0' + value;
                }
                
                // Limit to 11 digits
                if (value.length > 11) {
                    value = value.substring(0, 11);
                }
                
                this.value = value;
            });
        }
    }
}

// Update step indicators
function updateStepIndicators() {
    console.log('Updating step indicators to step:', currentStep);
    
    // Reset all steps
    document.querySelectorAll('.step').forEach(step => {
        step.classList.remove('active', 'completed');
    });

    // Set active step
    const currentStepElement = document.getElementById(`step-${currentStep}`);
    if (currentStepElement) {
        currentStepElement.classList.add('active');
    }

    // Set completed steps
    for (let i = 1; i < currentStep; i++) {
        const stepElement = document.getElementById(`step-${i}`);
        if (stepElement) {
            stepElement.classList.add('completed');
        }
    }

    // Show active form step
    document.querySelectorAll('.form-step').forEach(step => {
        step.classList.remove('active');
    });
    
    const activeFormStep = document.getElementById(`form-step-${currentStep}`);
    if (activeFormStep) {
        activeFormStep.classList.add('active');
    }
}

// Setup event listeners
function setupEventListeners() {
    console.log('Setting up event listeners...');

    // Next step buttons
    const nextButtons = document.querySelectorAll('.next-step');
    console.log('Next buttons found:', nextButtons.length);
    
    nextButtons.forEach(button => {
        button.addEventListener('click', function() {
            const nextStep = parseInt(this.getAttribute('data-next'));
            console.log('Next step clicked, moving to step:', nextStep);
            
            if (validateStep(currentStep)) {
                currentStep = nextStep;
                updateStepIndicators();
                if (currentStep === 3) {
                    updateSummary();
                }
            }
        });
    });

    // Previous step buttons
    const prevButtons = document.querySelectorAll('.prev-step');
    console.log('Previous buttons found:', prevButtons.length);
    
    prevButtons.forEach(button => {
        button.addEventListener('click', function() {
            const prevStep = parseInt(this.getAttribute('data-prev'));
            console.log('Previous step clicked, moving to step:', prevStep);
            
            currentStep = prevStep;
            updateStepIndicators();
        });
    });

    // Form submission
    const form = document.getElementById('appointment-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            console.log('Form submission clicked');
            e.preventDefault(); // Prevent default submission
            
            if (validateStep(3)) {
                // Update hidden fields with selected date/time
                if (selectedDate && selectedTime) {
                    const dateString = formatDateForAPI(selectedDate);
                    document.getElementById('selected-date').value = dateString;
                    document.getElementById('selected-time').value = selectedTime;
                }
                
                // Log all form data before submission
                const formData = new FormData(form);
                console.log('Form data being submitted:');
                for (let [key, value] of formData.entries()) {
                    console.log(`${key}: ${value}`);
                }
                
                // Show loading state
                const confirmBtn = document.getElementById('confirm-appointment');
                if (confirmBtn) {
                    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (isReschedule ? 'Rescheduling...' : 'Booking...');
                    confirmBtn.disabled = true;
                }
                
                // Submit the form
                console.log('Submitting form...');
                this.submit();
            }
        });
    }

    // Calendar navigation
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    
    console.log('Calendar buttons:', { prevMonthBtn, nextMonthBtn });
    
    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar();
        });
    }

    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar();
        });
    }

    // Service selection change handling (using delegation)
    const servicesContainer = document.getElementById('services-container');
    if (servicesContainer) {
        servicesContainer.addEventListener('change', function(e) {
            if (e.target.classList.contains('service-selection')) {
                calculateTotalPrice();
                
                // Also update the summary if we're on step 3
                if (currentStep === 3) {
                    updateSummary();
                }
            }
        });

        // Handle service row removal
        servicesContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-service-btn')) {
                const row = e.target.closest('.service-row');
                if (document.querySelectorAll('.service-row').length > 1) {
                    row.remove();
                    calculateTotalPrice();
                    if (currentStep === 3) {
                        updateSummary();
                    }
                } else {
                    showNotification('At least one service must be selected.', 'warning');
                }
            }
        });
    }

    // Add service button
    const addServiceBtn = document.getElementById('add-service-btn');
    if (addServiceBtn) {
        addServiceBtn.addEventListener('click', function() {
            const container = document.getElementById('services-container');
            const rows = container.querySelectorAll('.service-row');
            
            // Limit number of services if needed (e.g., max 5)
            if (rows.length >= 5) {
                showNotification('Maximum of 5 services per appointment.', 'warning');
                return;
            }
            
            const firstRow = rows[0];
            const newRow = firstRow.cloneNode(true);
            
            // Reset the select in the new row
            const select = newRow.querySelector('select');
            select.value = '';
            select.removeAttribute('id'); // Remove ID to avoid duplicates
            
            // Add remove button if it doesn't exist
            if (!newRow.querySelector('.remove-service-btn')) {
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-service-btn';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.title = 'Remove Service';
                newRow.appendChild(removeBtn);
            }
            
            container.appendChild(newRow);
        });
    }

    // Dentist selection change (only if not reschedule)
    const dentistSelect = document.getElementById('appointment-dentist');
    if (dentistSelect && !dentistSelect.disabled) {
        dentistSelect.addEventListener('change', function() {
            // Reload calendar with new dentist availability
            loadMonthlyAvailability().then(() => {
                renderCalendar();
                if (selectedDate) {
                    updateTimeSlots();
                }
            });
        });
    }
}

// Validate current step
function validateStep(step) {
    console.log('Validating step:', step);
    console.log('Is reschedule during validation:', isReschedule);
    let isValid = true;

    if (step === 1) {
        const firstName = document.getElementById('patient-first-name');
        const lastName = document.getElementById('patient-last-name');
        const phone = document.getElementById('patient-phone');
        const email = document.getElementById('patient-email');

        console.log('Step 1 elements:', { 
            firstName: firstName?.value,
            lastName: lastName?.value,
            phone: phone?.value,
            email: email?.value
        });

        // Reset validation messages
        document.querySelectorAll('.validation-message').forEach(msg => {
            msg.textContent = '';
        });
        document.querySelectorAll('.form-control').forEach(input => {
            input.classList.remove('invalid');
        });

        // Validate first name
        if (!firstName || !firstName.value.trim()) {
            showFieldError(firstName, 'Please enter your first name');
            isValid = false;
        } else if (!/^[a-zA-Z\s]{2,}$/.test(firstName.value.trim())) {
            showFieldError(firstName, 'First name should only contain letters and spaces (minimum 2 characters)');
            isValid = false;
        }

        // Validate last name
        if (!lastName || !lastName.value.trim()) {
            showFieldError(lastName, 'Please enter your last name');
            isValid = false;
        } else if (!/^[a-zA-Z\s]{2,}$/.test(lastName.value.trim())) {
            showFieldError(lastName, 'Last name should only contain letters and spaces (minimum 2 characters)');
            isValid = false;
        }

        // Validate phone - Philippine format: 11 digits starting with 0
        if (!phone || !phone.value.trim()) {
            showFieldError(phone, 'Please enter your phone number');
            isValid = false;
        } else {
            const phoneRegex = /^0[0-9]{10}$/;
            if (!phoneRegex.test(phone.value.trim())) {
                showFieldError(phone, 'Please enter a valid 11-digit Philippine phone number starting with 0 (e.g., 09171234567)');
                isValid = false;
            }
        }

        // Validate email
        if (!email || !email.value.trim()) {
            showFieldError(email, 'Please enter your email address');
            isValid = false;
        } else {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email.value)) {
                showFieldError(email, 'Please enter a valid email address');
                isValid = false;
            }
        }

    } else if (step === 2) {
        // For reschedule, we need to check hidden fields
        if (isReschedule) {
            console.log('Validating reschedule step 2');
            
            // Check hidden fields for reschedule values
            const hiddenService = document.querySelector('input[name="service_id"]');
            const hiddenDentist = document.querySelector('input[name="dentist_id"]');
            const hiddenPayment = document.querySelector('input[name="payment_type"]');
            
            console.log('Reschedule hidden fields:', {
                service: hiddenService?.value,
                dentist: hiddenDentist?.value,
                payment: hiddenPayment?.value,
                selectedDate: selectedDate,
                selectedTime: selectedTime
            });
            
            // Reset validation messages
            document.querySelectorAll('.validation-message').forEach(msg => {
                msg.textContent = '';
            });
            document.querySelectorAll('.form-control').forEach(input => {
                input.classList.remove('invalid');
            });
            
            // Check if we have all required values from hidden fields
            if (!hiddenService || !hiddenService.value) {
                showNotification('Service type is required', 'error');
                isValid = false;
            }
            
            if (!hiddenDentist || !hiddenDentist.value) {
                showNotification('Dentist selection is required', 'error');
                isValid = false;
            }
            
            if (!hiddenPayment || !hiddenPayment.value) {
                showNotification('Payment type is required', 'error');
                isValid = false;
            }
        } else {
            // Regular validation for new appointments
            const service = document.getElementById('appointment-service');
            const dentist = document.getElementById('appointment-dentist');
            const paymentType = document.getElementById('payment-type');
            
            console.log('Step 2 elements:', { 
                service: service?.value,
                dentist: dentist?.value,
                paymentType: paymentType?.value
            });

            // Reset validation messages
            document.querySelectorAll('.validation-message').forEach(msg => {
                msg.textContent = '';
            });
            document.querySelectorAll('.form-control').forEach(input => {
                input.classList.remove('invalid');
            });

            // Validate services (at least one must be selected)
            const serviceSelects = document.querySelectorAll('.service-selection');
            let atLeastOneService = false;
            
            serviceSelects.forEach(select => {
                if (select.value) {
                    atLeastOneService = true;
                }
            });

            if (!atLeastOneService) {
                // Show error on the first select
                if (serviceSelects.length > 0) {
                    showFieldError(serviceSelects[0], 'Please select at least one service');
                }
                isValid = false;
            }

            // Validate dentist
            if (!dentist || !dentist.value) {
                showFieldError(dentist, 'Please select a dentist');
                isValid = false;
            }

            // Validate payment type
            if (!paymentType || !paymentType.value) {
                showFieldError(paymentType, 'Please select a payment type');
                isValid = false;
            }
        }

        // Validate date and time selection (for both reschedule and new appointments)
        if (!selectedDate || !selectedTime) {
            showNotification('Please select both a date and time for your appointment', 'error');
            isValid = false;
        } else {
            // Additional validation: Check if selected date/time is in the past
            const now = new Date();
            const selectedDateTime = getDateTimeFromSelection(selectedDate, selectedTime);
            
            if (selectedDateTime < now) {
                showNotification('Cannot select past date and time. Please choose a future appointment.', 'error');
                isValid = false;
            }
        }
    }

    console.log('Validation result:', isValid);
    return isValid;
}

// Helper function to create Date object from selected date and time
function getDateTimeFromSelection(date, timeString) {
    const dateStr = formatDateForAPI(date);
    const [time, period] = timeString.split(' ');
    let [hours, minutes] = time.split(':').map(Number);
    
    // Convert to 24-hour format
    if (period === 'PM' && hours < 12) hours += 12;
    if (period === 'AM' && hours === 12) hours = 0;
    
    const dateTimeStr = `${dateStr} ${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:00`;
    return new Date(dateTimeStr);
}

// Helper function to show field errors
function showFieldError(field, message) {
    if (field && field.nextElementSibling) {
        field.classList.add('invalid');
        field.nextElementSibling.textContent = message;
    }
}

// Custom notification function
function showNotification(message, type = 'info') {
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

// Calculate total price of all selected services
function calculateTotalPrice() {
    const selects = document.querySelectorAll('.service-selection');
    let total = 0;
    
    selects.forEach(select => {
        if (select.value) {
            const selectedOption = select.options[select.selectedIndex];
            const price = selectedOption.getAttribute('data-price') || selectedOption.getAttribute('data-selected-price');
            total += parseFloat(price) || 0;
        }
    });
    
    selectedServicePrice = total;
    
    const priceDisplay = document.getElementById('service-price-display');
    if (priceDisplay) {
        priceDisplay.textContent = `Total Price: ₱${selectedServicePrice.toFixed(2)}`;
    }
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

// Update summary section
function updateSummary() {
    console.log('Updating summary...');
    
    const setSummaryValue = (id, value) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value || '-';
    };

    // Personal Information - These come from the form inputs
    const firstName = document.getElementById('patient-first-name')?.value;
    const lastName = document.getElementById('patient-last-name')?.value;
    const phone = document.getElementById('patient-phone')?.value;
    const email = document.getElementById('patient-email')?.value;
    
    setSummaryValue('summary-first-name', firstName);
    setSummaryValue('summary-last-name', lastName);
    setSummaryValue('summary-phone', phone);
    setSummaryValue('summary-email', email);
    
    // Service Information
    const selects = document.querySelectorAll('.service-selection');
    const serviceNames = [];
    let totalPrice = 0;
    
    selects.forEach(select => {
        if (select.value) {
            const selectedOption = select.options[select.selectedIndex];
            const serviceText = selectedOption.text;
            const price = selectedOption.getAttribute('data-price') || selectedOption.getAttribute('data-selected-price');
            
            const serviceName = serviceText ? serviceText.split(' - ₱')[0] : '-';
            serviceNames.push(serviceName);
            totalPrice += parseFloat(price) || 0;
        }
    });

    if (serviceNames.length > 0) {
        setSummaryValue('summary-service', serviceNames.join(', '));
        selectedServicePrice = totalPrice;
        setSummaryValue('summary-service-price', `₱${selectedServicePrice.toFixed(2)}`);
    } else {
        setSummaryValue('summary-service', '-');
        setSummaryValue('summary-service-price', '-');
    }
    
    // Date & Time
    setSummaryValue('summary-datetime', selectedDate && selectedTime ? 
        `${formatDate(selectedDate.toISOString().split('T')[0])} at ${selectedTime}` : '-');
    
    // Dentist
    const dentistSelect = document.getElementById('appointment-dentist');
    const dentistText = dentistSelect?.options[dentistSelect.selectedIndex]?.text;
    // Format dentist display
    let dentistDisplay = '-';
    if (dentistText) {
        if (dentistText.includes('Any Available Dentist')) {
            dentistDisplay = 'Any Available Dentist';
        } else {
            // Format as "Dr. Name - Specialization"
            dentistDisplay = dentistText;
        }
    }
    setSummaryValue('summary-dentist', dentistDisplay);
    
    // Payment Type
    const paymentSelect = document.getElementById('payment-type');
    const paymentText = paymentSelect?.options[paymentSelect.selectedIndex]?.text;
    setSummaryValue('summary-payment-type', paymentText || '-');
    
    // Notes
    const notes = document.getElementById('appointment-notes')?.value;
    setSummaryValue('summary-notes', notes || 'None');
    
    // Update appointment ID text
    const appointmentIdElement = document.getElementById('summary-appointment-id');
    if (appointmentIdElement) {
        if (isReschedule && rescheduleAppointmentId) {
            appointmentIdElement.textContent = rescheduleAppointmentId;
            appointmentIdElement.style.color = 'var(--primary)';
            appointmentIdElement.style.fontWeight = 'bold';
        } else if (appointmentIdElement.textContent.includes('Will be auto-generated')) {
            appointmentIdElement.textContent = 'Will be auto-generated';
            appointmentIdElement.style.color = '';
            appointmentIdElement.style.fontWeight = '';
        }
    }
    
    // Update Patient ID display for guest patients
    const patientIdElement = document.getElementById('summary-patient-id');
    if (patientIdElement) {
        // Keep the existing patient ID (already set from PHP)
        // If it says "Guest Patient" and we have names, update it
        if (patientIdElement.textContent === 'Guest Patient' && firstName && lastName) {
            patientIdElement.textContent = `Guest - ${firstName} ${lastName}`;
        }
    }
}

// Format date for display
function formatDate(dateString) {
    if (!dateString) return '-';
    try {
        const date = new Date(dateString + 'T00:00:00'); // Add time to avoid timezone issues
        return date.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    } catch (e) {
        return '-';
    }
}

// Helper function to format date for API (YYYY-MM-DD)
function formatDateForAPI(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Calendar functionality
function initCalendar() {
    console.log('Initializing calendar...');
    loadMonthlyAvailability().then(() => {
        renderCalendar();
    });
}

// Load monthly availability data
async function loadMonthlyAvailability() {
    const dentistSelect = document.getElementById('appointment-dentist');
    const dentistId = dentistSelect ? dentistSelect.value : null;
    
    try {
        const response = await fetch(`new-appointments.php?action=get_monthly_availability&month=${currentMonth + 1}&year=${currentYear}&dentist=${dentistId}&t=${new Date().getTime()}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        monthlyAvailability = data.availability || {};
        console.log('Monthly availability loaded:', monthlyAvailability);
        
    } catch (error) {
        console.error('Error loading monthly availability:', error);
        monthlyAvailability = {};
    }
}

function renderCalendar() {
    const calendarContainer = document.getElementById('calendar-container');
    if (!calendarContainer) {
        console.error('Calendar container not found');
        return;
    }

    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDay = firstDay.getDay();

    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    let calendarHTML = '<div class="calendar-grid">';
    
    // Day headers
    const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    dayNames.forEach(day => {
        calendarHTML += `<div class="calendar-day-header">${day}</div>`;
    });

    // Empty cells for days before the first day of the month
    for (let i = 0; i < startingDay; i++) {
        calendarHTML += `<div class="calendar-day other-month"></div>`;
    }

    // Days of the month - create dates in local timezone
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    for (let day = 1; day <= daysInMonth; day++) {
        // Create date in local timezone to avoid timezone issues
        const date = new Date(currentYear, currentMonth, day);
        const dateString = formatDateForAPI(date);
        const isToday = date.getTime() === today.getTime();
        const isPast = date < today;
        
        // Check availability for this date
        const dateAvailability = monthlyAvailability[dateString];
        const isFullyBooked = dateAvailability ? dateAvailability.is_fully_booked : false;
        const isPastDate = dateAvailability ? dateAvailability.is_past_date : isPast;
        const isAvailable = !isPastDate && !isFullyBooked;
        
        // Check if this is the reschedule date
        const isRescheduleDate = isReschedule && rescheduleDate && dateString === rescheduleDate;
        
        // Determine if selected
        let isSelected = false;
        if (selectedDate) {
            isSelected = date.getDate() === selectedDate.getDate() && 
                        date.getMonth() === selectedDate.getMonth() && 
                        date.getFullYear() === selectedDate.getFullYear();
        } else if (isRescheduleDate) {
            isSelected = true;
        }

        let dayClass = 'calendar-day';
        if (isToday) dayClass += ' today';
        if (isSelected) dayClass += ' selected';
        if (isAvailable) dayClass += ' available';
        if (!isAvailable) dayClass += ' unavailable';
        
        // Add tooltip for past dates
        let tooltip = '';
        if (isPastDate) {
            tooltip = 'title="This date has already passed. Please select a future date."';
        } else if (isFullyBooked) {
            tooltip = 'title="This date is fully booked. Please select another date."';
        }

        calendarHTML += `<div class="${dayClass}" data-date="${dateString}" ${tooltip}>${day}`;
        
        // Add booking status indicator only for future dates
        if (!isPastDate && dateAvailability) {
            if (isFullyBooked) {
                calendarHTML += `<div class="day-status fully-booked" title="Fully Booked"><i class="fas fa-times-circle"></i></div>`;
            } else if (dateAvailability.booked_slots > 0) {
                calendarHTML += `<div class="day-status partially-booked" title="${dateAvailability.available_slots.length} slots available"><i class="fas fa-info-circle"></i></div>`;
            } else {
                calendarHTML += `<div class="day-status available" title="All slots available"><i class="fas fa-check-circle"></i></div>`;
            }
        }
        
        calendarHTML += `</div>`;
    }

    calendarHTML += '</div>';
    calendarContainer.innerHTML = calendarHTML;

    // Update calendar header with current month and year
    const monthYearElement = document.getElementById('current-month-year');
    if (monthYearElement) {
        monthYearElement.textContent = `${monthNames[currentMonth]} ${currentYear}`;
    }

    // Add event listeners to calendar days
    document.querySelectorAll('.calendar-day').forEach(day => {
        day.addEventListener('click', function() {
            const dateString = this.getAttribute('data-date');
            // Parse date consistently - create from YYYY-MM-DD string
            const dateParts = dateString.split('-');
            const date = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
            const isAvailable = this.classList.contains('available');
            const isPast = this.classList.contains('unavailable') && date < today;
            const isFullyBooked = this.classList.contains('unavailable') && !isPast;
            
            if (!isAvailable) {
                if (isPast) {
                    showNotification('This date has already passed. Please select a future date.', 'error');
                } else if (isFullyBooked) {
                    showNotification('This date is fully booked. Please select another date.', 'error');
                } else {
                    showNotification('This date is not available. Please select another date.', 'error');
                }
                return;
            }

            document.querySelectorAll('.calendar-day.selected').forEach(el => {
                el.classList.remove('selected');
            });

            this.classList.add('selected');
            selectedDate = date;
            selectedTime = null; // Reset time when date changes
            
            // Update hidden field
            document.getElementById('selected-date').value = dateString;
            
            console.log('Date selected:', selectedDate, 'Formatted:', dateString);
            updateTimeSlots();
        });
    });
    
    // If reschedule and we have a date, pre-select it
    if (isReschedule && rescheduleDate) {
        console.log('Trying to pre-select reschedule date:', rescheduleDate);
        const dateElement = document.querySelector(`.calendar-day[data-date="${rescheduleDate}"]`);
        if (dateElement) {
            console.log('Found date element for reschedule:', dateElement);
            if (dateElement.classList.contains('available')) {
                dateElement.classList.add('selected');
                
                // Parse the date
                const dateParts = rescheduleDate.split('-');
                selectedDate = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
                
                // Update hidden field
                document.getElementById('selected-date').value = rescheduleDate;
                console.log('Pre-selected date for reschedule:', selectedDate);
            }
        }
    }
}

// Update time slots based on selected date and dentist
async function updateTimeSlots() {
    const timeSlotsContainer = document.getElementById('time-slots');
    if (!timeSlotsContainer || !selectedDate) {
        console.error('Time slots container not found or no date selected');
        return;
    }

    // Show loading state
    timeSlotsContainer.innerHTML = '<div class="loading-time-slots">Loading available time slots...</div>';

    const dentistSelect = document.getElementById('appointment-dentist');
    const dentistId = dentistSelect ? dentistSelect.value : null;
    const dateString = formatDateForAPI(selectedDate);

    try {
        // Fetch real availability from server
        const response = await fetch(`new-appointments.php?action=check_availability&dentist=${dentistId}&date=${dateString}&t=${new Date().getTime()}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Availability data for', dateString, ':', data);
        
        const availableSlots = data.available_slots || [];
        const isFullyBooked = data.is_fully_booked || false;
        const isToday = data.is_today || false;
        const currentTime = data.current_time || '00:00:00';
        
        // Define all possible time slots
        const allTimeSlots = [
            '8:00 AM', '9:00 AM', '10:00 AM', '11:00 AM',
            '12:00 PM', '1:00 PM', '2:00 PM', '3:00 PM', 
            '4:00 PM', '5:00 PM', '6:00 PM'
        ];
        
        let timeSlotsHTML = '';
        
        if (isFullyBooked) {
            timeSlotsHTML = `
                <div class="fully-booked-message">
                    <i class="fas fa-calendar-times"></i>
                    <h4>Fully Booked</h4>
                    <p>All time slots for ${formatDate(dateString)} are currently booked.</p>
                    <p>Please select another date.</p>
                </div>
            `;
        } else {
            const now = new Date();
            const isTodayDate = selectedDate.getDate() === now.getDate() && 
                               selectedDate.getMonth() === now.getMonth() && 
                               selectedDate.getFullYear() === now.getFullYear();
            
            // Show all time slots, but mark unavailable ones as disabled
            allTimeSlots.forEach(time => {
                const isAvailable = availableSlots.includes(time);
                const isSelected = selectedTime === time;
                const isRescheduleTime = isReschedule && rescheduleTime && time === rescheduleTime;
                
                // Check if time is in the past (for today's date)
                let isPastTime = false;
                if (isTodayDate) {
                    const selectedDateTime = getDateTimeFromSelection(selectedDate, time);
                    isPastTime = selectedDateTime < now;
                }
                
                let timeClass = 'time-slot';
                if (!isAvailable || isPastTime) {
                    timeClass += ' unavailable-time';
                }
                if (isSelected || (isRescheduleTime && !selectedTime)) {
                    timeClass += ' selected';
                    if (isRescheduleTime && !selectedTime) {
                        selectedTime = time;
                        document.getElementById('selected-time').value = time;
                        console.log('Auto-selected reschedule time:', time);
                    }
                }

                timeSlotsHTML += `<div class="${timeClass}" data-time="${time}" data-available="${isAvailable && !isPastTime}">${time}`;
                if (!isAvailable || isPastTime) {
                    timeSlotsHTML += ` <i class="fas fa-lock unavailable-icon"></i>`;
                    if (isPastTime) {
                        timeSlotsHTML = timeSlotsHTML.replace('data-available="true"', 'data-available="false"');
                    }
                }
                timeSlotsHTML += `</div>`;
            });
            
            // Filter available slots that are not in the past
            const validAvailableSlots = availableSlots.filter(time => {
                if (!isTodayDate) return true;
                const selectedDateTime = getDateTimeFromSelection(selectedDate, time);
                return selectedDateTime > now;
            });
            
            timeSlotsHTML += `<div class="availability-info">${validAvailableSlots.length} time slots available</div>`;
        }

        timeSlotsContainer.innerHTML = timeSlotsHTML;

        // Add event listeners to time slots
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.addEventListener('click', function() {
                const isAvailable = this.getAttribute('data-available') === 'true';
                
                if (!isAvailable) {
                    const now = new Date();
                    const selectedDateTime = getDateTimeFromSelection(selectedDate, this.getAttribute('data-time'));
                    
                    if (selectedDateTime < now) {
                        showNotification('This time has already passed. Please select a future time.', 'error');
                    } else {
                        showNotification('This time slot is not available. Please select another time.', 'error');
                    }
                    return;
                }

                document.querySelectorAll('.time-slot.selected').forEach(el => {
                    el.classList.remove('selected');
                });

                this.classList.add('selected');
                selectedTime = this.getAttribute('data-time');
                
                // Update hidden field
                document.getElementById('selected-time').value = selectedTime;
                
                console.log('Time selected:', selectedTime);
                
                // Update summary immediately when time is selected
                updateSummary();
            });
        });

    } catch (error) {
        console.error('Error fetching time slots:', error);
        timeSlotsContainer.innerHTML = '<div class="error-loading-slots">Error loading time slots. Please try again.</div>';
    }
}