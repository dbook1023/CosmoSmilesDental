const PH_TIMEZONE = 'Asia/Manila';
let currentView = 'month';
let currentPeriodStart = new Date();
currentPeriodStart.setHours(0, 0, 0, 0); // Initialize to midnight to avoid rollover issues
let confirmedAppointmentsData = [];
let completedAppointmentsData = [];
let allAppointmentsData = [];
let calendarAppointmentsData = [];
let currentAppointmentId = null;
let currentAppointmentDetails = null;
let servicesData = [];
let bookedTimeSlots = {};
let confirmedCurrentPage = 1;
let completedCurrentPage = 1;
let allCurrentPage = 1;
const itemsPerPage = 10;
let confirmationCallback = null;
let isEditing = false;
let editFormFields = {};
const WORKING_HOURS = {
    0: null,
    1: { start: 8, end: 18 },
    2: { start: 8, end: 18 },
    3: { start: 8, end: 18 },
    4: { start: 8, end: 18 },
    5: { start: 8, end: 18 },
    6: { start: 9, end: 15 }
};
async function initCalendar() {
    try {
        await loadDentists();
        await loadServices();
        await loadConfirmedAppointments();
        await loadCompletedAppointments();
        await loadAllAppointments();
        await loadCalendarAppointments();
        await loadBookedTimeSlots();
        updateCalendarView();
        updateConfirmedTable();
        updateCompletedTable();
        updateAllAppointmentsTable();
        setInterval(checkAndUpdateNoShowAppointments, 300000);
    } catch (error) {
        console.error('Initialization error:', error);
        showMessage('Error initializing calendar: ' + error.message, 'error');
    }
}
async function checkAndUpdateNoShowAppointments() {
    try {
        const response = await fetch(`${API_BASE_URL}?action=updateNoShow`);
        const data = await response.json();
        if (data.success && data.updated > 0) {
            console.log(`Updated ${data.updated} appointments to no_show`);
        }
    } catch (error) {
        console.error('Error updating no-show appointments:', error);
    }
}
async function loadDentists() {
    try {
        const response = await fetch(`${API_BASE_URL}?action=fetchDentists`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        if (data.success) {
            const dentistFilter = document.getElementById('dentist-filter');
            if (dentistFilter) {
                if (IS_SUPER_ADMIN) {
                    // Super Admin can see all dentists
                    let options = '<option value="all">All Dentists</option>';
                    data.dentists.forEach(dentist => {
                        const fullName = `Dr. ${dentist.first_name} ${dentist.last_name}`;
                        options += `<option value="${dentist.id}">${fullName}</option>`;
                    });
                    dentistFilter.innerHTML = options;
                    dentistFilter.disabled = false;
                    dentistFilter.value = CURRENT_DENTIST_ID; // Default to the logged-in dentist
                } else {
                    // Regular dentist can only see their own appointments
                    const options = `<option value="${CURRENT_DENTIST_ID}">${ADMIN_FULL_NAME}</option>`;
                    dentistFilter.innerHTML = options;
                    dentistFilter.value = CURRENT_DENTIST_ID;
                    dentistFilter.disabled = true;
                }
            }
        }
    } catch (error) {
        console.error('Error loading dentists:', error);
    }
}
async function loadServices() {
    try {
        const response = await fetch(`${API_BASE_URL}?action=fetchServices`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json();
        if (data.success) {
            servicesData = data.services || [];
            console.log('Loaded services:', servicesData.length);
            updateFollowupServiceDropdown();
        } else {
            servicesData = [];
        }
    } catch (error) {
        console.error('Error loading services:', error);
        servicesData = [];
    }
}
function updateFollowupServiceDropdown() {
    const serviceSelect = document.getElementById('followupService');
    if (!serviceSelect) {
        console.log('Followup service dropdown not found');
        return;
    }
    let html = '<option value="">Select Service</option>';
    servicesData.forEach(service => {
        html += `<option value="${service.id}">${service.name}</option>`;
    });
    serviceSelect.innerHTML = html;
    console.log('Updated followup service dropdown with', servicesData.length, 'services');
}
async function loadBookedTimeSlots() {
    try {
        const response = await fetch(`${API_BASE_URL}?action=fetchBookedSlots&dentist_id=${CURRENT_DENTIST_ID}`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json();
        if (data.success) {
            bookedTimeSlots = data.booked_slots || {};
        } else {
            bookedTimeSlots = {};
        }
    } catch (error) {
        console.error('Error loading booked time slots:', error);
        bookedTimeSlots = {};
    }
}
function updateTimeDisplay() {
    try {
        const now = new Date();
        const dateOptions = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
        const timeOptions = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
        
        const dateEl = document.getElementById('admin-date');
        const timeEl = document.getElementById('admin-time');
        
        if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
        if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
    } catch (error) {
        console.error('Error updating time:', error);
    }
}
async function loadConfirmedAppointments() {
    try {
        const patientFilter = document.getElementById('patient-filter');
        const dateFilter = document.getElementById('date-filter');
        const statusFilter = document.getElementById('status-filter');
        const dentistFilter = document.getElementById('dentist-filter');
        
        let dentistId = dentistFilter ? dentistFilter.value : CURRENT_DENTIST_ID;
        
        let params = new URLSearchParams({
            action: 'fetchConfirmed',
            dentist_id: dentistId
        });
        if (patientFilter && patientFilter.value) {
            params.append('patient', patientFilter.value);
        }
        if (dateFilter && dateFilter.value) {
            params.append('date', dateFilter.value);
        }
        if (statusFilter && statusFilter.value !== 'all') {
            params.append('status', statusFilter.value);
        }
        const apiUrl = `${API_BASE_URL}?${params}`;
        const response = await fetch(apiUrl);
        if (!response.ok) {
            const text = await response.text();
            console.error('Server response:', text.substring(0, 200));
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json();
        if (data.success) {
            confirmedAppointmentsData = data.confirmed_appointments || [];
            
            const confirmedPeriodEl = document.getElementById('confirmed-table-period');
            if (confirmedPeriodEl) {
                if (dateFilter && dateFilter.value) {
                    const displayDate = new Date(dateFilter.value);
                    confirmedPeriodEl.textContent = displayDate.toLocaleDateString('en-US', { 
                        month: 'long', 
                        day: 'numeric', 
                        year: 'numeric' 
                    });
                } else {
                    confirmedPeriodEl.textContent = 'All Upcoming';
                }
            }
        } else {
            confirmedAppointmentsData = [];
        }
    } catch (error) {
        console.error('Error loading confirmed appointments:', error);
        showMessage('Error loading confirmed appointments. Please try again.', 'error');
        confirmedAppointmentsData = [];
    }
}
async function loadCompletedAppointments() {
    try {
        const patientFilter = document.getElementById('patient-filter');
        const dateFilter = document.getElementById('date-filter');
        const dentistFilter = document.getElementById('dentist-filter');
        const timeFilter = document.getElementById('completed-time-filter');

        let dentistId = dentistFilter ? dentistFilter.value : CURRENT_DENTIST_ID;

        let params = new URLSearchParams({
            action: 'fetchCompleted',
            dentist_id: dentistId
        });
        if (patientFilter && patientFilter.value) {
            params.append('patient', patientFilter.value);
        }
        
        const today = new Date();
        
        if (timeFilter && timeFilter.value === 'all') {
            params.append('start_date', '2000-01-01');
            params.append('end_date', formatDateForAPI(today));
        } else if (timeFilter && timeFilter.value === '6months') {
            const sixMonthsAgo = new Date(today);
            sixMonthsAgo.setMonth(today.getMonth() - 6);
            params.append('start_date', formatDateForAPI(sixMonthsAgo));
            params.append('end_date', formatDateForAPI(today));
        } else {
            const thirtyDaysAgo = new Date(today);
            thirtyDaysAgo.setDate(today.getDate() - 30);
            params.append('start_date', formatDateForAPI(thirtyDaysAgo));
            params.append('end_date', formatDateForAPI(today));
        }

        if (dateFilter && dateFilter.value) {
            const filterDate = new Date(dateFilter.value);
            params.set('start_date', formatDateForAPI(filterDate));
            params.set('end_date', formatDateForAPI(filterDate));
        }
        const apiUrl = `${API_BASE_URL}?${params}`;
        const response = await fetch(apiUrl);
        if (!response.ok) {
            const text = await response.text();
            console.error('Server response:', text.substring(0, 200));
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json();
        if (data.success) {
            completedAppointmentsData = data.completed_appointments || [];
            if (dateFilter && dateFilter.value) {
                const displayDate = new Date(dateFilter.value);
                const periodText = displayDate.toLocaleDateString('en-US', { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
                document.getElementById('completed-table-period').textContent = periodText;
            } else if (timeFilter) {
                if (timeFilter.value === 'all') {
                    document.getElementById('completed-table-period').textContent = 'All Time';
                } else if (timeFilter.value === '6months') {
                    document.getElementById('completed-table-period').textContent = 'Last 6 Months';
                } else {
                    document.getElementById('completed-table-period').textContent = 'Last 30 Days';
                }
            } else {
                document.getElementById('completed-table-period').textContent = 'Last 30 Days';
            }
        } else {
            completedAppointmentsData = [];
        }
    } catch (error) {
        console.error('Error loading completed appointments:', error);
        showMessage('Error loading completed appointments. Please try again.', 'error');
        completedAppointmentsData = [];
    }
}
async function loadAllAppointments() {
    try {
        const dentistFilter = document.getElementById('dentist-filter');
        const allStatusFilter = document.getElementById('all-status-filter');
        const patientFilter = document.getElementById('patient-filter');
        const dateFilter = document.getElementById('date-filter');
        const searchInput = document.getElementById('search-all-appointments');
        
        let dentistId = dentistFilter ? dentistFilter.value : CURRENT_DENTIST_ID;
        const hideNoShowCheckbox = document.getElementById('hide-no-show-checkbox');
        let hideNoShow = hideNoShowCheckbox ? hideNoShowCheckbox.checked : true;

        let params = new URLSearchParams({
            action: 'fetchAll',
            dentist_id: dentistId,
            hide_no_show: hideNoShow
        });
        if (allStatusFilter && allStatusFilter.value !== 'all') {
            params.append('status', allStatusFilter.value);
        }
        if (searchInput && searchInput.value) {
            params.append('search', searchInput.value);
        }
        const apiUrl = `${API_BASE_URL}?${params}`;
        const response = await fetch(apiUrl);
        if (!response.ok) {
            const text = await response.text();
            console.error('Server response:', text.substring(0, 200));
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json();
        if (data.success) {
            allAppointmentsData = data.all_appointments || [];
        } else {
            allAppointmentsData = [];
        }
    } catch (error) {
        console.error('Error loading all appointments:', error);
        showMessage('Error loading appointments. Please try again.', 'error');
        allAppointmentsData = [];
    }
}

async function loadCalendarAppointments() {
    try {
        const dentistFilter = document.getElementById('dentist-filter');
        let dentistId = dentistFilter ? dentistFilter.value : CURRENT_DENTIST_ID;
        
        let params = new URLSearchParams({
            action: 'fetchCalendar',
            dentist_id: dentistId
        });
        const apiUrl = `${API_BASE_URL}?${params}`;
        const response = await fetch(apiUrl);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        
        const data = await response.json();
        if (data.success) {
            calendarAppointmentsData = data.calendar_appointments || [];
        } else {
            calendarAppointmentsData = [];
        }
    } catch (error) {
        console.error('Error loading calendar appointments:', error);
        calendarAppointmentsData = [];
    }
}
function formatDateForAPI(date) {
    return date.toISOString().split('T')[0];
}
function updateCalendarView() {
    const calendarGrid = document.getElementById('calendarGrid');
    if (!calendarGrid) return;
    calendarGrid.innerHTML = '';
    calendarGrid.classList.remove('week-view', 'day-view');
    if (currentView === 'today') {
        renderDayView();
    } else if (currentView === 'week') {
        renderWeekView();
    } else {
        renderMonthView();
    }
    updatePeriodDisplay();
}
function renderMonthView() {
    const calendarGrid = document.getElementById('calendarGrid');
    const year = currentPeriodStart.getFullYear();
    const month = currentPeriodStart.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const firstDayOfWeek = firstDay.getDay();
    const daysInMonth = lastDay.getDate();
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    for (let i = firstDayOfWeek - 1; i >= 0; i--) {
        const day = new Date(year, month - 1, prevMonthLastDay - i);
        const dayElement = createCalendarDay(day, true);
        calendarGrid.appendChild(dayElement);
    }
    for (let i = 1; i <= daysInMonth; i++) {
        const day = new Date(year, month, i);
        const dayElement = createCalendarDay(day, false);
        calendarGrid.appendChild(dayElement);
    }
    const totalCells = 42;
    const daysSoFar = firstDayOfWeek + daysInMonth;
    const daysNextMonth = totalCells - daysSoFar;
    for (let i = 1; i <= daysNextMonth; i++) {
        const day = new Date(year, month + 1, i);
        const dayElement = createCalendarDay(day, true);
        calendarGrid.appendChild(dayElement);
    }
}
function renderWeekView() {
    const calendarGrid = document.getElementById('calendarGrid');
    calendarGrid.classList.add('week-view');
    const startOfWeek = new Date(currentPeriodStart);
    const day = startOfWeek.getDay();
    const diff = startOfWeek.getDate() - day + (day === 0 ? -6 : 1);
    startOfWeek.setDate(diff);
    for (let i = 0; i < 7; i++) {
        const day = new Date(startOfWeek);
        day.setDate(startOfWeek.getDate() + i);
        const dayElement = createCalendarDay(day, false);
        calendarGrid.appendChild(dayElement);
    }
}
function renderDayView() {
    const calendarGrid = document.getElementById('calendarGrid');
    calendarGrid.classList.add('day-view');
    const dayElement = createCalendarDay(currentPeriodStart, false);
    calendarGrid.appendChild(dayElement);
}
function createCalendarDay(date, isOtherMonth) {
    const dayElement = document.createElement('div');
    dayElement.className = 'calendar-day';
    if (isOtherMonth) {
        dayElement.classList.add('other-month');
    }
    const today = new Date();
    if (date.getDate() === today.getDate() && 
        date.getMonth() === today.getMonth() && 
        date.getFullYear() === today.getFullYear()) {
        dayElement.classList.add('today');
    }
    const dayNumber = document.createElement('div');
    dayNumber.className = 'day-number';
    dayNumber.textContent = date.getDate();
    dayElement.appendChild(dayNumber);
    // FIX: Avoid toISOString() which shifts to UTC. Use local components for YYYY-MM-DD.
    const dateStr = `${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}-${date.getDate().toString().padStart(2, '0')}`;
    const dayAppointments = calendarAppointmentsData
        .filter(appt => {
            try {
                const apptDate = appt.date || appt.appointment_date;
                return apptDate === dateStr;
            } catch (e) {
                return false;
            }
        });
    if (dayAppointments.length > 0) {
        const appointmentsContainer = document.createElement('div');
        appointmentsContainer.className = 'day-appointments';
        dayAppointments.slice(0, 3).forEach(appt => {
            const apptElement = document.createElement('div');
            const statusClass = appt.status || 'confirmed';
            apptElement.className = `appointment-badge ${statusClass}`;
            apptElement.innerHTML = `
                <div class="appointment-time">${formatTimeForCalendar(appt.time || appt.appointment_time)}</div>
                <div class="appointment-patient">${appt.patient || 'Patient'}</div>
            `;
            apptElement.addEventListener('click', () => {
                showAppointmentDetails(appt.appointment_id);
            });
            appointmentsContainer.appendChild(apptElement);
        });
        if (dayAppointments.length > 3) {
            const moreIndicator = document.createElement('div');
            moreIndicator.className = 'more-appointments';
            moreIndicator.textContent = `+${dayAppointments.length - 3} more`;
            appointmentsContainer.appendChild(moreIndicator);
        }
        dayElement.appendChild(appointmentsContainer);
    }
    return dayElement;
}
function formatTimeForCalendar(timeStr) {
    if (!timeStr) return '';
    try {
        if (timeStr.includes(':')) {
            const timeParts = timeStr.split(' ');
            if (timeParts.length === 2) {
                return timeStr;
            } else {
                const [hours, minutes] = timeStr.split(':');
                const hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const displayHour = hour % 12 || 12;
                const cleanMinutes = minutes.length > 2 ? minutes.substring(0, 2) : minutes;
                return `${displayHour}:${cleanMinutes} ${ampm}`;
            }
        }
        return timeStr;
    } catch (e) {
        return timeStr;
    }
}
function updatePeriodDisplay() {
    const periodElement = document.getElementById('selected-period');
    if (!periodElement) return;
    let periodText = '';
    switch (currentView) {
        case 'today':
            periodText = currentPeriodStart.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            break;
        case 'week':
            const weekStart = new Date(currentPeriodStart);
            const weekDay = weekStart.getDay();
            const diff = weekStart.getDate() - weekDay + (weekDay === 0 ? -6 : 1);
            weekStart.setDate(diff);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6);
            periodText = `${weekStart.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${weekEnd.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
            break;
        default:
            periodText = currentPeriodStart.toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
            });
    }
    periodElement.textContent = periodText;
}
function updateConfirmedTable() {
    const tableBody = document.getElementById('confirmedAppointmentsTableBody');
    const pagination = document.getElementById('confirmedTablePagination');
    if (!tableBody) return;
    if (confirmedAppointmentsData.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="9" class="no-appointments">
                    <i class="fas fa-calendar-check"></i>
                    <p>No confirmed appointments found</p>
                </td>
            </tr>
        `;
        if (pagination) pagination.style.display = 'none';
        return;
    }
    if (pagination) {
        pagination.style.display = 'flex';
        updatePagination('confirmed', confirmedAppointmentsData.length);
    }
    const startIndex = (confirmedCurrentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, confirmedAppointmentsData.length);
    const currentAppointments = confirmedAppointmentsData.slice(startIndex, endIndex);
    let html = '';
    currentAppointments.forEach(appointment => {
        const isCompleted = appointment.status === 'completed';
        const isPending = appointment.status === 'pending';
        const isConfirmed = appointment.status === 'confirmed';
        const statusClass = appointment.status || 'confirmed';
        const statusText = statusClass.charAt(0).toUpperCase() + statusClass.slice(1).replace('_', ' ');
        const canManage = (appointment.dentist_id == CURRENT_DENTIST_ID) || IS_SUPER_ADMIN;
        
        let actionButtons = `
            <button class="action-btn-small view" data-id="${appointment.appointment_id}" title="View Details">
                <i class="fas fa-eye"></i> View
            </button>
        `;
        
        if (isPending && canManage) {
            actionButtons += `
                <button class="action-btn-small confirm" data-id="${appointment.appointment_id}" title="Confirm Appointment">
                    <i class="fas fa-check"></i> 
                </button>
                <button class="action-btn-small cancel" data-id="${appointment.appointment_id}" title="Cancel Appointment">
                    <i class="fas fa-times"></i> 
                </button>
            `;
        } else if (isPending && !canManage) {
            actionButtons += `
                <button class="action-btn-small confirm disabled" data-id="${appointment.appointment_id}" title="You can only confirm your own appointments">
                    <i class="fas fa-check"></i> 
                </button>
                <button class="action-btn-small cancel disabled" data-id="${appointment.appointment_id}" title="You can only cancel your own appointments">
                    <i class="fas fa-times"></i> 
                </button>
            `;
        } else if (isConfirmed) {
            if (!isCompleted && canManage) {
                actionButtons += `
                    <button class="action-btn-small edit" data-id="${appointment.appointment_id}" title="Edit Appointment">
                        <i class="fas fa-edit"></i> 
                    </button>
                    <button class="action-btn-small complete" data-id="${appointment.appointment_id}" title="Mark as Completed">
                        <i class="fas fa-check"></i> 
                    </button>
                    <button class="action-btn-small cancel" data-id="${appointment.appointment_id}" title="Cancel Appointment">
                        <i class="fas fa-times"></i> 
                    </button>
                `;
            } else {
                actionButtons += `
                    <button class="action-btn-small edit disabled" data-id="${appointment.appointment_id}" title="${isCompleted ? 'Cannot edit completed appointments' : 'You can only edit your own appointments'}">
                        <i class="fas fa-edit"></i> 
                    </button>
                    <button class="action-btn-small complete disabled" data-id="${appointment.appointment_id}" title="You can only complete your own appointments">
                        <i class="fas fa-check"></i> 
                    </button>
                `;
            }
        }

        html += `
            <tr>
                <td class="appointment-id">${appointment.appointment_id || 'N/A'}</td>
                <td class="patient-id">${appointment.client_id || 'N/A'}</td>
                <td class="patient-name">
                    <div class="patient-info" style="display: flex; align-items: center; gap: 10px;">
                        <div class="patient-avatar" style="width: 32px; height: 32px; flex-shrink: 0; border-radius: 50%; overflow: hidden; background-color: var(--light-accent); display: flex; align-items: center; justify-content: center;">
                            ${(() => {
                                let displayImage = appointment.patient_image || '';
                                if (displayImage && !displayImage.includes('uploads/')) {
                                    displayImage = 'uploads/avatar/' + displayImage;
                                }
                                return displayImage ? 
                                '<img src="' + (window.URL_ROOT || '') + displayImage + '" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.parentElement.innerHTML=\'<i class=\\\'fas fa-user\\\' style=\\\'color: var(--secondary); font-size: 14px;\\\'></i>\';">' : 
                                '<i class="fas fa-user" style="color: var(--secondary); font-size: 14px;"></i>';
                            })()}
                        </div>
                        <div class="patient-details">
                            <span style="font-weight: 500;">${appointment.patient || 'Unknown Patient'}</span>
                        </div>
                    </div>
                </td>
                <td class="appointment-date">${appointment.date_display || formatDateForDisplay(appointment.date)}</td>
                <td class="appointment-time time-display-fix">${formatTimeForTable(appointment.time)}</td>
                <td class="service-name">${appointment.service || 'Dental Service'}</td>
                <td>
                    <span class="payment-type cash">
                        ${appointment.payment_type ? appointment.payment_type.toUpperCase() : 'CASH'}
                    </span>
                </td>
                <td>
                    <span class="status-badge ${statusClass}">
                        ${statusText}
                    </span>
                </td>
                <td>
                    <div class="table-actions">
                        ${actionButtons}
                    </div>
                </td>
            </tr>
        `;
    });
    tableBody.innerHTML = html;
    if (pagination) {
        document.getElementById('confirmedTableStart').textContent = startIndex + 1;
        document.getElementById('confirmedTableEnd').textContent = endIndex;
        document.getElementById('confirmedTableTotal').textContent = confirmedAppointmentsData.length;
    }
    setTimeout(() => {
        document.querySelectorAll('#confirmedAppointmentsTableBody .action-btn-small.view').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const appointmentId = e.currentTarget.getAttribute('data-id');
                showAppointmentDetails(appointmentId);
            });
        });
        document.querySelectorAll('#confirmedAppointmentsTableBody .action-btn-small.edit').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (e.currentTarget.classList.contains('disabled')) {
                    showMessage(e.currentTarget.getAttribute('title') || 'This action is not available', 'warning');
                    return;
                }
                const appointmentId = e.currentTarget.getAttribute('data-id');
                startEditAppointment(appointmentId);
            });
        });
        document.querySelectorAll('#confirmedAppointmentsTableBody .action-btn-small.confirm').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (e.currentTarget.classList.contains('disabled')) {
                    showMessage(e.currentTarget.getAttribute('title') || 'This action is not available', 'warning');
                    return;
                }
                const appointmentId = e.currentTarget.getAttribute('data-id');
                updateAppointmentStatus(appointmentId, 'confirmed');
            });
        });
        document.querySelectorAll('#confirmedAppointmentsTableBody .action-btn-small.cancel').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (e.currentTarget.classList.contains('disabled')) {
                    showMessage(e.currentTarget.getAttribute('title') || 'This action is not available', 'warning');
                    return;
                }
                const appointmentId = e.currentTarget.getAttribute('data-id');
                updateAppointmentStatus(appointmentId, 'cancelled');
            });
        });
        document.querySelectorAll('#confirmedAppointmentsTableBody .action-btn-small.complete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (e.currentTarget.classList.contains('disabled')) {
                    showMessage(e.currentTarget.getAttribute('title') || 'This action is not available', 'warning');
                    return;
                }
                const appointmentId = e.currentTarget.getAttribute('data-id');
                // Open modal instead of direct completion to ensure time tracking is filled
                showAppointmentDetails(appointmentId);
            });
        });
    }, 100);
}
function updateCompletedTable() {
    const tableBody = document.getElementById('completedAppointmentsTableBody');
    const pagination = document.getElementById('completedTablePagination');
    if (!tableBody) return;
    if (completedAppointmentsData.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="no-appointments">
                    <i class="fas fa-check-circle"></i>
                    <p>No completed appointments found</p>
                </td>
            </tr>
        `;
        if (pagination) pagination.style.display = 'none';
        return;
    }
    if (pagination) {
        pagination.style.display = 'flex';
        updatePagination('completed', completedAppointmentsData.length);
    }
    const startIndex = (completedCurrentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, completedAppointmentsData.length);
    const currentAppointments = completedAppointmentsData.slice(startIndex, endIndex);
    let html = '';
    currentAppointments.forEach(appointment => {
        html += `
            <tr>
                <td class="appointment-id">${appointment.appointment_id || 'N/A'}</td>
                <td class="patient-id">${appointment.client_id || 'N/A'}</td>
                <td class="patient-name">
                    <div class="patient-info" style="display: flex; align-items: center; gap: 10px;">
                        <div class="patient-avatar" style="width: 32px; height: 32px; flex-shrink: 0; border-radius: 50%; overflow: hidden; background-color: var(--light-accent); display: flex; align-items: center; justify-content: center;">
                            ${(() => {
                                let displayImage = appointment.patient_image || '';
                                if (displayImage && !displayImage.includes('uploads/')) {
                                    displayImage = 'uploads/avatar/' + displayImage;
                                }
                                return displayImage ? 
                                '<img src="' + (window.URL_ROOT || '') + displayImage + '" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.parentElement.innerHTML=\'<i class=\\\'fas fa-user\\\' style=\\\'color: var(--secondary); font-size: 14px;\\\'></i>\';">' : 
                                '<i class="fas fa-user" style="color: var(--secondary); font-size: 14px;"></i>';
                            })()}
                        </div>
                        <div class="patient-details">
                            <span style="font-weight: 500;">${appointment.patient || 'Unknown Patient'}</span>
                        </div>
                    </div>
                </td>
                <td class="appointment-date">${appointment.date_display || formatDateForDisplay(appointment.appointment_date || appointment.date)}</td>
                <td class="service-name">${appointment.service || 'Dental Service'}</td>
                <td class="duration">${appointment.duration || 30} min</td>
                <td>
                    <span class="payment-type cash">
                        ${appointment.payment_type ? appointment.payment_type.toUpperCase() : 'CASH'}
                    </span>
                </td>
                <td>
                    <div class="table-actions">
                        <button class="action-btn-small view" data-id="${appointment.appointment_id}" title="View Details">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="action-btn-small edit disabled" data-id="${appointment.appointment_id}" title="Cannot edit completed appointments" disabled>
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        ${(appointment.dentist_id == CURRENT_DENTIST_ID || IS_SUPER_ADMIN) ? `
                        <button class="action-btn-small followup" data-id="${appointment.appointment_id}" title="Schedule Follow-up">
                            <i class="fas fa-calendar-plus"></i> Follow-up
                        </button>
                        ` : `
                        <button class="action-btn-small followup disabled" data-id="${appointment.appointment_id}" title="You can only schedule follow-ups for your own patients" disabled>
                            <i class="fas fa-calendar-plus"></i> Follow-up
                        </button>
                        `}
                    </div>
                </td>
            </tr>
        `;
    });
    tableBody.innerHTML = html;
    if (pagination) {
        document.getElementById('completedTableStart').textContent = startIndex + 1;
        document.getElementById('completedTableEnd').textContent = endIndex;
        document.getElementById('completedTableTotal').textContent = completedAppointmentsData.length;
    }
    setTimeout(() => {
        document.querySelectorAll('.action-btn-small.view').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const appointmentId = e.currentTarget.getAttribute('data-id');
                showAppointmentDetails(appointmentId);
            });
        });
        document.querySelectorAll('.action-btn-small.followup').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const appointmentId = e.currentTarget.getAttribute('data-id');
                currentAppointmentId = appointmentId;
                showAppointmentDetails(appointmentId);
                setTimeout(() => {
                    const appointment = completedAppointmentsData.find(a => a.appointment_id === appointmentId);
                    if (appointment && appointment.status === 'completed') {
                        document.getElementById('followupSection').style.display = 'block';
                        updateFollowupServiceDropdown();
                        if (appointment.service_id) {
                            const serviceSelect = document.getElementById('followupService');
                            if (serviceSelect) {
                                serviceSelect.value = appointment.service_id;
                            }
                        }
                    }
                }, 500);
            });
        });
    }, 100);
}
function updateAllAppointmentsTable() {
    const tableBody = document.getElementById('allAppointmentsTableBody');
    const pagination = document.getElementById('allTablePagination');
    if (!tableBody) return;
    if (allAppointmentsData.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="10" class="no-appointments">
                    <i class="fas fa-calendar-alt"></i>
                    <p>No appointments found</p>
                </td>
            </tr>
        `;
        if (pagination) pagination.style.display = 'none';
        return;
    }
    if (pagination) {
        pagination.style.display = 'flex';
        updatePagination('all', allAppointmentsData.length);
    }
    const startIndex = (allCurrentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, allAppointmentsData.length);
    const currentAppointments = allAppointmentsData.slice(startIndex, endIndex);
    let html = '';
    currentAppointments.forEach(appointment => {
        let statusClass = appointment.status || 'pending';
        let statusText = (appointment.status || 'Pending').charAt(0).toUpperCase() + 
                         (appointment.status || 'pending').slice(1);
        const isCompleted = appointment.status === 'completed';
        const isPending = appointment.status === 'pending';
        const isConfirmed = appointment.status === 'confirmed';
        const isNoShow = appointment.status === 'no_show';
        const isCancelled = appointment.status === 'cancelled';
        const appointmentDate = new Date(appointment.date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const isPastAppointment = appointmentDate < today;
        const canManage = (appointment.dentist_id == CURRENT_DENTIST_ID) || IS_SUPER_ADMIN;
        let actionButtons = '';
        if (isPastAppointment && !isCompleted && !isCancelled && !isNoShow) {
            actionButtons = `
                <button class="action-btn-small view" data-id="${appointment.appointment_id}" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
            `;
        } else if (isPending) {
            actionButtons = `
                <button class="action-btn-small view" data-id="${appointment.appointment_id}" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="action-btn-small confirm ${!canManage ? 'disabled' : ''}" data-id="${appointment.appointment_id}" title="${canManage ? 'Confirm Appointment' : 'You can only confirm your own appointments'}">
                    <i class="fas fa-check"></i>
                </button>
                <button class="action-btn-small cancel ${!canManage ? 'disabled' : ''}" data-id="${appointment.appointment_id}" title="${canManage ? 'Cancel Appointment' : 'You can only cancel your own appointments'}">
                    <i class="fas fa-times"></i>
                </button>
            `;
        } else if (isConfirmed) {
            actionButtons = `
                <button class="action-btn-small view" data-id="${appointment.appointment_id}" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="action-btn-small complete ${!canManage ? 'disabled' : ''}" data-id="${appointment.appointment_id}" title="${canManage ? 'Mark as Completed' : 'You can only complete your own appointments'}">
                    <i class="fas fa-check"></i>
                </button>
                <button class="action-btn-small cancel ${!canManage ? 'disabled' : ''}" data-id="${appointment.appointment_id}" title="${canManage ? 'Cancel Appointment' : 'You can only cancel your own appointments'}">
                    <i class="fas fa-times"></i>
                </button>
            `;
        } else if (isCompleted) {
            actionButtons = `
                <button class="action-btn-small view" data-id="${appointment.appointment_id}" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="action-btn-small followup ${!canManage ? 'disabled' : ''}" data-id="${appointment.appointment_id}" title="${canManage ? 'Schedule Follow-up' : 'You can only schedule follow-ups for your own patients'}">
                    <i class="fas fa-calendar-plus"></i>
                </button>
            `;
        } else {
            actionButtons = `
                <button class="action-btn-small view" data-id="${appointment.appointment_id}" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
            `;
        }
        html += `
            <tr>
                <td class="appointment-id">${appointment.appointment_id || 'N/A'}</td>
                <td class="patient-id">${appointment.client_id || 'N/A'}</td>
                <td class="patient-name">
                    <div class="patient-info" style="display: flex; align-items: center; gap: 10px;">
                        <div class="patient-avatar" style="width: 32px; height: 32px; flex-shrink: 0; border-radius: 50%; overflow: hidden; background-color: var(--light-accent); display: flex; align-items: center; justify-content: center;">
                            ${(() => {
                                let displayImage = appointment.patient_image || '';
                                if (displayImage && !displayImage.includes('uploads/')) {
                                    displayImage = 'uploads/avatar/' + displayImage;
                                }
                                return displayImage ? 
                                '<img src="' + (window.URL_ROOT || '') + displayImage + '" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.parentElement.innerHTML=\'<i class=\\\'fas fa-user\\\' style=\\\'color: var(--secondary); font-size: 14px;\\\'></i>\';">' : 
                                '<i class="fas fa-user" style="color: var(--secondary); font-size: 14px;"></i>';
                            })()}
                        </div>
                        <div class="patient-details">
                            <span style="font-weight: 500;">${appointment.patient || 'Unknown Patient'}</span>
                        </div>
                    </div>
                </td>
                <td class="appointment-date">${appointment.date_display || formatDateForDisplay(appointment.date)}</td>
                <td class="appointment-time time-display-fix">${formatTimeForTable(appointment.time)}</td>
                <td class="service-name">${appointment.service || 'Dental Service'}</td>
                <td>
                    <span class="payment-type cash">
                        ${appointment.payment_type ? appointment.payment_type.toUpperCase() : 'CASH'}
                    </span>
                </td>
                <td>
                    <span class="status-badge ${statusClass}">
                        ${statusText}
                    </span>
                </td>
                <td class="appointment-date">${formatDateForDisplay(appointment.created_at)}</td>
                <td>
                    <div class="table-actions">
                        ${actionButtons}
                    </div>
                </td>
            </tr>
        `;
    });
    tableBody.innerHTML = html;
    if (pagination) {
        document.getElementById('allTableStart').textContent = startIndex + 1;
        document.getElementById('allTableEnd').textContent = endIndex;
        document.getElementById('allTableTotal').textContent = allAppointmentsData.length;
    }
    setTimeout(() => {
        document.querySelectorAll('#allAppointmentsTableBody .action-btn-small.view').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const appointmentId = e.currentTarget.getAttribute('data-id');
                showAppointmentDetails(appointmentId);
            });
        });
        document.querySelectorAll('#allAppointmentsTableBody .action-btn-small.confirm').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (e.currentTarget.classList.contains('disabled')) {
                    showMessage(e.currentTarget.getAttribute('title') || 'This action is not available', 'warning');
                    return;
                }
                const appointmentId = e.currentTarget.getAttribute('data-id');
                updateAppointmentStatus(appointmentId, 'confirmed');
            });
        });
        document.querySelectorAll('#allAppointmentsTableBody .action-btn-small.cancel').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (e.currentTarget.classList.contains('disabled')) {
                    showMessage(e.currentTarget.getAttribute('title') || 'This action is not available', 'warning');
                    return;
                }
                const appointmentId = e.currentTarget.getAttribute('data-id');
                updateAppointmentStatus(appointmentId, 'cancelled');
            });
        });
        document.querySelectorAll('#allAppointmentsTableBody .action-btn-small.complete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (e.currentTarget.classList.contains('disabled')) {
                    showMessage(e.currentTarget.getAttribute('title') || 'This action is not available', 'warning');
                    return;
                }
                const appointmentId = e.currentTarget.getAttribute('data-id');
                // Open modal instead of direct completion to ensure time tracking is filled
                showAppointmentDetails(appointmentId);
            });
        });
        document.querySelectorAll('#allAppointmentsTableBody .action-btn-small.followup').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (e.currentTarget.classList.contains('disabled')) {
                    showMessage(e.currentTarget.getAttribute('title') || 'This action is not available', 'warning');
                    return;
                }
                const appointmentId = e.currentTarget.getAttribute('data-id');
                currentAppointmentId = appointmentId;
                showAppointmentDetails(appointmentId);
                setTimeout(() => {
                    const appointment = allAppointmentsData.find(a => a.appointment_id === appointmentId);
                    if (appointment && appointment.status === 'completed') {
                        document.getElementById('followupSection').style.display = 'block';
                        updateFollowupServiceDropdown();
                        if (appointment.service_id) {
                            const serviceSelect = document.getElementById('followupService');
                            if (serviceSelect) {
                                serviceSelect.value = appointment.service_id;
                            }
                        }
                    }
                }, 500);
            });
        });
    }, 100);
}
function formatTimeForTable(timeStr) {
    if (!timeStr) return '';
    try {
        let cleanTime = timeStr.trim();
        const hasAMPM = /(AM|PM)/i.test(cleanTime);
        if (hasAMPM) {
            cleanTime = cleanTime.replace(/\s+/g, ' ').trim();
            const ampmMatch = cleanTime.match(/(AM|PM)/gi);
            if (ampmMatch && ampmMatch.length > 1) {
                const timePart = cleanTime.split(' ')[0];
                const ampmPart = ampmMatch[ampmMatch.length - 1];
                return `${timePart} ${ampmPart}`;
            }
            return cleanTime;
        } else {
            if (cleanTime.includes(':')) {
                const [hours, minutes] = cleanTime.split(':');
                const hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const displayHour = hour % 12 || 12;
                const cleanMinutes = minutes.length > 2 ? minutes.substring(0, 2) : minutes;
                return `${displayHour}:${cleanMinutes} ${ampm}`;
            }
            return cleanTime;
        }
    } catch (e) {
        console.error('Error formatting time:', e);
        return timeStr;
    }
}
function updatePagination(type, totalItems) {
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const pageNumbers = document.getElementById(`${type}PageNumbers`);
    const prevBtn = document.getElementById(`prev${type.charAt(0).toUpperCase() + type.slice(1)}Page`);
    const nextBtn = document.getElementById(`next${type.charAt(0).toUpperCase() + type.slice(1)}Page`);
    if (!pageNumbers || !prevBtn || !nextBtn) return;
    let currentPage;
    if (type === 'confirmed') {
        currentPage = confirmedCurrentPage;
        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages;
    } else if (type === 'completed') {
        currentPage = completedCurrentPage;
        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages;
    } else if (type === 'all') {
        currentPage = allCurrentPage;
        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages;
    }
    let html = '';
    for (let i = 1; i <= totalPages; i++) {
        const isActive = (type === 'confirmed' && i === confirmedCurrentPage) || 
                        (type === 'completed' && i === completedCurrentPage) ||
                        (type === 'all' && i === allCurrentPage);
        html += `<div class="page-number ${isActive ? 'active' : ''}" data-page="${i}">${i}</div>`;
    }
    pageNumbers.innerHTML = html;
    pageNumbers.querySelectorAll('.page-number').forEach(pageBtn => {
        pageBtn.addEventListener('click', () => {
            const page = parseInt(pageBtn.getAttribute('data-page'));
            if (type === 'confirmed') {
                confirmedCurrentPage = page;
                updateConfirmedTable();
            } else if (type === 'completed') {
                completedCurrentPage = page;
                updateCompletedTable();
            } else if (type === 'all') {
                allCurrentPage = page;
                updateAllAppointmentsTable();
            }
        });
    });
}
function formatDateForDisplay(dateStr) {
    if (!dateStr) return '';
    try {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    } catch (e) {
        return dateStr;
    }
}
async function updateAppointmentStatus(appointmentId, newStatus) {
    if (!appointmentId || !newStatus) return;
    let actionText = '';
    let actionType = 'warning';
    switch(newStatus) {
        case 'confirmed':
            actionText = 'Are you sure you want to confirm this appointment?';
            actionType = 'warning';
            break;
        case 'cancelled':
            actionText = 'Are you sure you want to cancel this appointment?';
            actionType = 'error';
            break;
        case 'completed':
            actionText = 'Are you sure you want to mark this appointment as completed?';
            actionType = 'success';
            break;
        default:
            actionText = `Are you sure you want to change the status to ${newStatus}?`;
    }
    showConfirmation(
        actionText,
        actionType,
        async function() {
            try {
                const response = await fetch(`${API_BASE_URL}?action=updateStatus`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        appointment_id: appointmentId,
                        status: newStatus
                    })
                });
                const data = await response.json();
                if (data.success) {
                    showMessage(`Appointment ${newStatus} successfully!`, 'success');
                    await loadConfirmedAppointments();
                    await loadCompletedAppointments();
                    await loadAllAppointments();
                    await loadBookedTimeSlots();
                    updateCalendarView();
                    updateConfirmedTable();
                    updateCompletedTable();
                    updateAllAppointmentsTable();
                } else {
                    showMessage('Error: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error updating appointment status:', error);
                showMessage('Error updating appointment status', 'error');
            }
        }
    );
}
async function startEditAppointment(appointmentId) {
    try {
        if (!appointmentId) {
            showMessage('No appointment ID provided', 'error');
            return;
        }
        await showAppointmentDetails(appointmentId);
        enableEditMode();
    } catch (error) {
        console.error('Error starting edit appointment:', error);
        showMessage('Error loading appointment for edit', 'error');
    }
}
function enableEditMode() {
    if (!currentAppointmentDetails) return;
    const appointmentDate = new Date(currentAppointmentDetails.date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (appointmentDate < today) {
        showMessage('Cannot edit past appointments', 'error');
        return;
    }
    if (currentAppointmentDetails.status === 'completed') {
        showMessage('Cannot edit completed appointments', 'error');
        return;
    }
    isEditing = true;
    const editFormContainer = document.getElementById('editFormContainer');
    editFormContainer.classList.add('active');
    const editBtn = document.getElementById('editAppointmentBtn');
    editBtn.innerHTML = '<i class="fas fa-times"></i> Cancel Edit';
    editBtn.classList.remove('btn-warning');
    editBtn.classList.add('btn-error');
    document.getElementById('completeAppointmentBtn').style.display = 'none';
    populateEditForm();
    showMessage('You are now in edit mode. Make your changes and click "Save Changes" when done.', 'info');
}
function populateEditForm() {
    if (!currentAppointmentDetails) return;
    const editFormGrid = document.getElementById('editFormGrid');
    editFormFields = {};
    let formHtml = '';
    const today = new Date().toISOString().split('T')[0];
    const currentDate = currentAppointmentDetails.date.split('T')[0];
    formHtml += `
        <div class="edit-form-group">
            <label for="editDate">Date</label>
            <input type="date" id="editDate" class="edit-form-control" value="${currentDate}" min="${today}">
        </div>
    `;
    const timeValue = currentAppointmentDetails.time_display ? 
        convertTimeTo24Hour(currentAppointmentDetails.time_display) : 
        '09:00';
    formHtml += `
        <div class="edit-form-group">
            <label for="editTime">Time</label>
            <select id="editTime" class="edit-form-control">
                ${generateTimeOptions(currentDate, timeValue)}
            </select>
        </div>
    `;
    formHtml += `
        <div class="edit-form-group">
            <label for="editService">Service</label>
            <select id="editService" class="edit-form-control">
                ${generateServiceOptions(currentAppointmentDetails.service_id)}
            </select>
        </div>
    `;
    formHtml += `
        <div class="edit-form-group">
            <label for="editPaymentType">Payment Type</label>
            <select id="editPaymentType" class="edit-form-control">
                <option value="cash" ${currentAppointmentDetails.payment_type === 'cash' ? 'selected' : ''}>CASH</option>
                <option value="card" ${currentAppointmentDetails.payment_type === 'card' ? 'selected' : ''}>CARD</option>
                <option value="insurance" ${currentAppointmentDetails.payment_type === 'insurance' ? 'selected' : ''}>INSURANCE</option>
            </select>
        </div>
    `;
    if (currentAppointmentDetails.status !== 'completed') {
        formHtml += `
            <div class="edit-form-group">
                <label for="editStatus">Status</label>
                <select id="editStatus" class="edit-form-control">
                    <option value="pending" ${currentAppointmentDetails.status === 'pending' ? 'selected' : ''}>Pending</option>
                    <option value="confirmed" ${currentAppointmentDetails.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                    <option value="cancelled" ${currentAppointmentDetails.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                </select>
            </div>
        `;
    }
    formHtml += `
        <div class="edit-form-group full-width">
            <label for="editClientNotes">Client Notes</label>
            <textarea id="editClientNotes" class="edit-form-control" rows="3">${currentAppointmentDetails.client_notes || ''}</textarea>
        </div>
    `;
    editFormGrid.innerHTML = formHtml;
    editFormFields = {
        date: document.getElementById('editDate'),
        time: document.getElementById('editTime'),
        service: document.getElementById('editService'),
        paymentType: document.getElementById('editPaymentType'),
        status: document.getElementById('editStatus'),
        clientNotes: document.getElementById('editClientNotes')
    };

    editFormFields.date.addEventListener('change', function() {
        const selectedDate = this.value;
        const timeSelect = document.getElementById('editTime');
        const currentTime = timeSelect.value;
        timeSelect.innerHTML = generateTimeOptions(selectedDate, currentTime);
    });
}
function generateTimeOptions(selectedDate, selectedTime = '') {
    let options = '';
    const date = new Date(selectedDate);
    const dayOfWeek = date.getDay();
    const workingHours = WORKING_HOURS[dayOfWeek];
    if (!workingHours) {
        return '<option value="">Not available (Sunday)</option>';
    }
    for (let hour = workingHours.start; hour <= workingHours.end; hour++) {
        const time24 = `${hour.toString().padStart(2, '0')}:00`;
        const time12 = hour > 12 ? `${hour - 12}:00 PM` : hour === 12 ? `12:00 PM` : `${hour}:00 AM`;
        const dateKey = selectedDate;
        const isBooked = bookedTimeSlots[dateKey] && 
                        bookedTimeSlots[dateKey].includes(time24) &&
                        time24 !== convertTimeTo24Hour(currentAppointmentDetails.time_display);
        const isSelected = selectedTime === time24;
        const disabled = isBooked ? 'disabled' : '';
        const selected = isSelected ? 'selected' : '';
        const unavailableClass = isBooked ? 'time-slot-unavailable' : '';
        options += `<option value="${time24}" ${selected} ${disabled} class="${unavailableClass}">${time12}</option>`;
    }
    return options;
}
function generateServiceOptions(selectedServiceId = '') {
    let options = '<option value="">Select Service</option>';
    servicesData.forEach(service => {
        const selected = service.id == selectedServiceId ? 'selected' : '';
        options += `<option value="${service.id}" ${selected}>${service.name}</option>`;
    });
    return options;
}
function convertTimeTo24Hour(time12h) {
    if (!time12h) return '09:00';
    const [time, modifier] = time12h.split(' ');
    let [hours, minutes] = time.split(':');
    if (hours === '12') {
        hours = '00';
    }
    if (modifier === 'PM') {
        hours = parseInt(hours, 10) + 12;
    }
    return `${hours.toString().padStart(2, '0')}:${minutes || '00'}`;
}
async function saveEditedAppointment() {
    if (!currentAppointmentId || !currentAppointmentDetails) {
        showMessage('No appointment selected', 'error');
        return;
    }
    const appointmentDate = new Date(currentAppointmentDetails.date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (appointmentDate < today) {
        showMessage('Cannot edit past appointments', 'error');
        disableEditMode();
        return;
    }
    if (currentAppointmentDetails.status === 'completed') {
        showMessage('Cannot edit completed appointments', 'error');
        disableEditMode();
        return;
    }
    try {
        const updatedData = {
            appointment_id: currentAppointmentId,
            appointment_date: editFormFields.date.value,
            appointment_time: editFormFields.time.value + ':00',
            service_id: editFormFields.service.value,
            payment_type: editFormFields.paymentType.value,
            status: editFormFields.status ? editFormFields.status.value : currentAppointmentDetails.status,
            client_notes: editFormFields.clientNotes.value,
            admin_notes: document.getElementById('adminNotes').value || ''
        };
        const selectedDate = new Date(updatedData.appointment_date);
        if (selectedDate < today) {
            showMessage('Cannot book appointments in the past', 'error');
            return;
        }
        showConfirmation(
            'Are you sure you want to save these changes?',
            'warning',
            async function() {
                try {
                    const response = await fetch(`${API_BASE_URL}?action=updateAppointment`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(updatedData)
                    });
                    const data = await response.json();
                    if (data.success) {
                        showMessage('Appointment updated successfully!', 'success');
                        disableEditMode();
                        closeModal();
                        await loadConfirmedAppointments();
                        await loadCompletedAppointments();
                        await loadAllAppointments();
                        await loadBookedTimeSlots();
                        updateCalendarView();
                        updateConfirmedTable();
                        updateCompletedTable();
                        updateAllAppointmentsTable();
                    } else {
                        showMessage('Error: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error saving appointment:', error);
                    showMessage('Error saving appointment changes', 'error');
                }
            }
        );
    } catch (error) {
        console.error('Error preparing appointment data:', error);
        showMessage('Error preparing appointment data', 'error');
    }
}
function disableEditMode() {
    isEditing = false;
    const editFormContainer = document.getElementById('editFormContainer');
    editFormContainer.classList.remove('active');
    const editBtn = document.getElementById('editAppointmentBtn');
    editBtn.innerHTML = '<i class="fas fa-edit"></i> Edit Appointment';
    editBtn.classList.remove('btn-error');
    editBtn.classList.add('btn-warning');
    if (currentAppointmentDetails && currentAppointmentDetails.status === 'confirmed') {
        document.getElementById('completeAppointmentBtn').style.display = 'inline-flex';
    }
    editFormFields = {};
}
async function showAppointmentDetails(appointmentId) {
    try {
        if (!appointmentId) {
            showMessage('No appointment ID provided', 'error');
            return;
        }

        // Always use action=details to get full data including feedback
        const response = await fetch(`${API_BASE_URL}?action=details&appointment_id=${appointmentId}`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        if (data.success) {
            currentAppointmentDetails = data.appointment;
            currentAppointmentId = appointmentId;
            
            if (isEditing) {
                disableEditMode();
            }

            updateModalDetails(data.appointment);
            
            const modal = document.getElementById('appointmentModal');
            const overlay = document.querySelector('.overlay');
            modal.classList.add('active');
            overlay.classList.add('active');
        } else {
            showMessage('Failed to load appointment details: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error loading appointment details:', error);
        showMessage('Error loading appointment details. Please try again.', 'error');
    }
}
function updateModalDetails(appointment) {
    document.getElementById('modalAppointmentId').textContent = appointment.appointment_id || 'N/A';
    document.getElementById('modalPatient').textContent = appointment.patient || 'Unknown';
    document.getElementById('modalPatientId').textContent = appointment.client_id || 'N/A';
    const services = appointment.service ? appointment.service.split(', ') : ['Dental Service'];
    if (services.length > 1) {
        let serviceHtml = '<ul class="modal-service-list">';
        services.forEach(s => {
            serviceHtml += `<li><i class="fas fa-check-circle"></i> ${s}</li>`;
        });
        serviceHtml += '</ul>';
        document.getElementById('modalService').innerHTML = serviceHtml;
    } else {
        document.getElementById('modalService').textContent = services[0];
    }
    document.getElementById('modalDateTime').textContent = 
        `${appointment.date_display || formatDateForDisplay(appointment.date)} at ${formatTimeForTable(appointment.time || appointment.appointment_time)}`;
    document.getElementById('modalPaymentType').textContent = appointment.payment_type ? appointment.payment_type.toUpperCase() : 'CASH';
    const durationRow = document.getElementById('durationRow');
    const modalDuration = document.getElementById('modalDuration');
    const timeTrackingSection = document.getElementById('timeTrackingSection');
    if (appointment.status === 'completed') {
        durationRow.style.display = 'flex';
        modalDuration.textContent = `${appointment.duration || 30} minutes`;
        timeTrackingSection.style.display = 'grid';
        document.getElementById('appointmentDuration').value = appointment.duration || 30;
        if (document.getElementById('startTime')) {
            document.getElementById('startTime').value = appointment.actual_start_time || '';
        }
        if (document.getElementById('endTime')) {
            document.getElementById('endTime').value = appointment.actual_end_time || '';
        }
    } else {
        durationRow.style.display = 'none';
        modalDuration.textContent = '';
        if (appointment.status === 'confirmed') {
            timeTrackingSection.style.display = 'grid';
            document.getElementById('appointmentDuration').value = 30;
            const startTimeInput = document.getElementById('startTime');
            if (startTimeInput && appointment.time) {
                startTimeInput.value = convertTimeTo24Hour(appointment.time);
            }
        } else {
            timeTrackingSection.style.display = 'none';
        }
    }
    document.getElementById('modalDentist').textContent = appointment.dentist || DENTIST_FULL_NAME;
    const modalStatus = document.getElementById('modalStatus');
    modalStatus.className = 'status-badge ' + (appointment.status || 'pending');
    modalStatus.textContent = (appointment.status || 'pending').charAt(0).toUpperCase() + (appointment.status || 'pending').slice(1).replace('_', ' ');
    document.getElementById('modalClientNotes').textContent = appointment.client_notes || 'No client notes';
    
    // Client Feedback Display - ORGANIZED VIEW
    const feedbackSection = document.getElementById('modalFeedbackSection');
    const feedbackRatingStars = document.getElementById('modalFeedbackRatingStars');
    const feedbackComment = document.getElementById('modalFeedbackComment');
    
    if (appointment.feedback && appointment.feedback.rating) {
        feedbackSection.style.display = 'block';
        const rating = parseInt(appointment.feedback.rating);
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            starsHtml += `<i class="${i <= rating ? 'fas' : 'far'} fa-star"></i>`;
        }
        if (feedbackRatingStars) feedbackRatingStars.innerHTML = starsHtml;
        if (feedbackComment) feedbackComment.textContent = `"${appointment.feedback.comment || 'No comment provided.'}"`;
    } else {
        feedbackSection.style.display = 'none';
    }

    document.getElementById('adminNotes').value = appointment.admin_notes || '';
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('followupDate').min = tomorrow.toISOString().split('T')[0];
    document.getElementById('followupDate').value = '';
    document.getElementById('modalFollowupInfo').textContent = 'Not scheduled';
    document.getElementById('availabilityResult').style.display = 'none';
    document.getElementById('scheduleFollowup').style.display = 'none';
    document.getElementById('startTime').value = '';
    const canManage = (appointment.dentist_id == CURRENT_DENTIST_ID) || IS_SUPER_ADMIN;
    const appointmentDate = new Date(appointment.date);
    const todayDate = new Date();
    todayDate.setHours(0, 0, 0, 0);
    const isPastAppointment = appointmentDate < todayDate;
    const isCompleted = appointment.status === 'completed';
    const isConfirmed = appointment.status === 'confirmed';
    
    const followupSection = document.getElementById('followupSection');
    const completeBtn = document.getElementById('completeAppointmentBtn');
    const editBtn = document.getElementById('editAppointmentBtn');
    
    if (isCompleted) {
        followupSection.style.display = 'block';
        completeBtn.style.display = 'none';
        editBtn.classList.add('disabled');
        editBtn.title = 'Cannot edit completed appointments';
        
        // Follow-up restriction
        const scheduleFollowupBtn = document.getElementById('scheduleFollowup');
        if (scheduleFollowupBtn) {
            scheduleFollowupBtn.classList.toggle('disabled', !canManage);
            scheduleFollowupBtn.title = canManage ? 'Schedule Follow-up' : 'You can only schedule follow-ups for your own patients';
        }
    } else if (isConfirmed) {
        followupSection.style.display = 'none';
        completeBtn.style.display = 'inline-flex';
        completeBtn.classList.toggle('disabled', !canManage);
        completeBtn.title = canManage ? 'Mark as Completed' : 'You can only complete your own appointments';
        
        editBtn.classList.toggle('disabled', !canManage || isPastAppointment);
        editBtn.title = !canManage ? 'You can only edit your own appointments' : (isPastAppointment ? 'Cannot edit past appointments' : 'Edit Appointment');
    } else {
        followupSection.style.display = 'none';
        completeBtn.style.display = 'none';
        editBtn.classList.toggle('disabled', !canManage || isPastAppointment);
        editBtn.title = !canManage ? 'You can only edit your own appointments' : (isPastAppointment ? 'Cannot edit past appointments' : 'Edit Appointment');
        
        // Also check for confirm/cancel buttons if they exist in modal
        const confirmBtn = document.getElementById('confirmAppointmentBtn');
        const cancelBtn = document.getElementById('cancelAppointmentBtn');
        if (confirmBtn) {
            confirmBtn.classList.toggle('disabled', !canManage);
            confirmBtn.title = canManage ? 'Confirm Appointment' : 'You can only confirm your own appointments';
        }
        if (cancelBtn) {
            cancelBtn.classList.toggle('disabled', !canManage);
            cancelBtn.title = canManage ? 'Cancel Appointment' : 'You can only cancel your own appointments';
        }
    }
    const editFormContainer = document.getElementById('editFormContainer');
    editFormContainer.classList.remove('active');
}
function showConfirmation(message, type = 'warning', confirmCallback) {
    const modal = document.getElementById('confirmationModal');
    const icon = document.getElementById('confirmationIcon');
    const text = document.getElementById('confirmationText');
    icon.className = 'confirmation-icon ' + type;
    icon.innerHTML = type === 'warning' ? '<i class="fas fa-exclamation-triangle"></i>' :
                     type === 'success' ? '<i class="fas fa-check-circle"></i>' :
                     type === 'error' ? '<i class="fas fa-times-circle"></i>' :
                     '<i class="fas fa-question-circle"></i>';
    text.textContent = message;
    confirmationCallback = confirmCallback;
    modal.classList.add('active');
}
function showMessage(message, type = 'info') {
    const existing = document.querySelector('.message-container');
    if (existing) existing.remove();
    const messageDiv = document.createElement('div');
    messageDiv.className = `message-container message-${type}`;
    messageDiv.innerHTML = `
        <div class="message-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
            <button class="message-close"><i class="fas fa-times"></i></button>
        </div>
    `;
    document.body.appendChild(messageDiv);
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.style.opacity = '0';
            setTimeout(() => {
                if (messageDiv.parentNode) messageDiv.remove();
            }, 300);
        }
    }, 5000);
    messageDiv.querySelector('.message-close').addEventListener('click', () => {
        if (messageDiv.parentNode) messageDiv.remove();
    });
}
document.addEventListener('DOMContentLoaded', function() {
    updateTimeDisplay();
    setInterval(updateTimeDisplay, 1000);
    initCalendar();

    const dateFilter = document.getElementById('date-filter');
    const statusFilter = document.getElementById('status-filter');
    const dentistFilter = document.getElementById('dentist-filter');
    const allStatusFilter = document.getElementById('all-status-filter');
    const patientFilter = document.getElementById('patient-filter');
    const searchAllInput = document.getElementById('search-all-appointments');
    const searchAllBtn = document.getElementById('search-all-btn');
    const clearAllSearchBtn = document.getElementById('clear-all-search');
    const timeFilter = document.getElementById('completed-time-filter');
    
    // Utility to wait before firing input events
    const debounce = (func, delay) => {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                func.apply(null, args);
            }, delay);
        };
    };
    
    if (patientFilter) {
        patientFilter.addEventListener('input', debounce(function() {
            loadConfirmedAppointments().then(updateConfirmedTable);
            loadCompletedAppointments().then(updateCompletedTable);
        }, 500));
    }
    
    if (timeFilter) {
        timeFilter.addEventListener('change', function() {
            loadCompletedAppointments().then(updateCompletedTable);
        });
    }
    
    if (dateFilter) {
        dateFilter.addEventListener('change', function() {
            loadConfirmedAppointments().then(updateConfirmedTable);
        });
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            loadConfirmedAppointments().then(updateConfirmedTable);
        });
    }

    if (allStatusFilter) {
        allStatusFilter.addEventListener('change', function() {
            loadAllAppointments().then(updateAllAppointmentsTable);
        });
    }

    const hideNoShowCheckbox = document.getElementById('hide-no-show-checkbox');
    if (hideNoShowCheckbox) {
        hideNoShowCheckbox.addEventListener('change', function() {
            loadAllAppointments().then(updateAllAppointmentsTable);
        });
    }

    if (dentistFilter) {
        dentistFilter.addEventListener('change', function() {
            loadConfirmedAppointments().then(updateConfirmedTable);
            loadCompletedAppointments().then(updateCompletedTable);
            loadAllAppointments().then(updateAllAppointmentsTable);
            loadCalendarAppointments().then(updateCalendarView);
        });
    }

    if (searchAllBtn && searchAllInput) {
        searchAllBtn.addEventListener('click', function() {
            loadAllAppointments().then(updateAllAppointmentsTable);
        });
        searchAllInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loadAllAppointments().then(updateAllAppointmentsTable);
            }
        });
    }

    if (clearAllSearchBtn && searchAllInput) {
        clearAllSearchBtn.addEventListener('click', function() {
            searchAllInput.value = '';
            if (allStatusFilter) allStatusFilter.value = 'all';
            loadAllAppointments().then(updateAllAppointmentsTable);
        });
    }

    document.getElementById('apply-filter')?.addEventListener('click', async function() {
        try {
            await loadConfirmedAppointments();
            await loadCompletedAppointments();
            await loadAllAppointments();
            await loadBookedTimeSlots();
            updateCalendarView();
            updateConfirmedTable();
            updateCompletedTable();
            updateAllAppointmentsTable();
            showMessage('Filters applied successfully', 'success');
        } catch (error) {
            console.error('Error applying filters:', error);
            showMessage('Error applying filters', 'error');
        }
    });
    document.getElementById('clear-filter')?.addEventListener('click', async function() {
        try {
            document.getElementById('status-filter').value = 'all';
            document.getElementById('date-filter').value = '';
            document.getElementById('patient-filter').value = '';
            const dentistFilter = document.getElementById('dentist-filter');
            if (dentistFilter) dentistFilter.value = 'all';
            currentView = 'month';
            currentPeriodStart = new Date();
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('active');
                if (tab.getAttribute('data-period') === 'month') {
                    tab.classList.add('active');
                }
            });
            await loadConfirmedAppointments();
            await loadCompletedAppointments();
            await loadAllAppointments();
            await loadBookedTimeSlots();
            updateCalendarView();
            updateConfirmedTable();
            updateCompletedTable();
            updateAllAppointmentsTable();
            showMessage('Filters cleared', 'success');
        } catch (error) {
            console.error('Error clearing filters:', error);
            showMessage('Error clearing filters', 'error');
        }
    });
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', async function() {
            try {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentView = this.getAttribute('data-period');
                currentPeriodStart = new Date();
                updateCalendarView();
                showMessage(`View changed to ${currentView}`, 'info');
            } catch (error) {
                console.error('Error changing view:', error);
                showMessage('Error changing view', 'error');
            }
        });
    });
    document.getElementById('prev-period')?.addEventListener('click', function() {
        if (currentView === 'month') {
            currentPeriodStart.setMonth(currentPeriodStart.getMonth() - 1);
        } else if (currentView === 'week') {
            currentPeriodStart.setDate(currentPeriodStart.getDate() - 7);
        }
        updateCalendarView();
    });
    document.getElementById('next-period')?.addEventListener('click', function() {
        if (currentView === 'month') {
            currentPeriodStart.setMonth(currentPeriodStart.getMonth() + 1);
        } else if (currentView === 'week') {
            currentPeriodStart.setDate(currentPeriodStart.getDate() + 7);
        }
        updateCalendarView();
    });
    document.getElementById('view-today')?.addEventListener('click', function() {
        currentPeriodStart = new Date();
        currentView = 'today';
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('active');
            if (tab.getAttribute('data-period') === 'today') {
                tab.classList.add('active');
            }
        });
        updateCalendarView();
        showMessage('Viewing today', 'info');
    });
    document.getElementById('prevConfirmedPage')?.addEventListener('click', function() {
        if (confirmedCurrentPage > 1) {
            confirmedCurrentPage--;
            updateConfirmedTable();
        }
    });
    document.getElementById('nextConfirmedPage')?.addEventListener('click', function() {
        const totalPages = Math.ceil(confirmedAppointmentsData.length / itemsPerPage);
        if (confirmedCurrentPage < totalPages) {
            confirmedCurrentPage++;
            updateConfirmedTable();
        }
    });
    document.getElementById('prevCompletedPage')?.addEventListener('click', function() {
        if (completedCurrentPage > 1) {
            completedCurrentPage--;
            updateCompletedTable();
        }
    });
    document.getElementById('nextCompletedPage')?.addEventListener('click', function() {
        const totalPages = Math.ceil(completedAppointmentsData.length / itemsPerPage);
        if (completedCurrentPage < totalPages) {
            completedCurrentPage++;
            updateCompletedTable();
        }
    });
    document.getElementById('prevAllPage')?.addEventListener('click', function() {
        if (allCurrentPage > 1) {
            allCurrentPage--;
            updateAllAppointmentsTable();
        }
    });
    document.getElementById('nextAllPage')?.addEventListener('click', function() {
        const totalPages = Math.ceil(allAppointmentsData.length / itemsPerPage);
        if (allCurrentPage < totalPages) {
            allCurrentPage++;
            updateAllAppointmentsTable();
        }
    });
    document.getElementById('search-all-appointments')?.addEventListener('keyup', async function(e) {
        if (e.key === 'Enter') {
            await loadAllAppointments();
            allCurrentPage = 1;
            updateAllAppointmentsTable();
        }
    });
    document.getElementById('search-all-btn')?.addEventListener('click', async function() {
        await loadAllAppointments();
        allCurrentPage = 1;
        updateAllAppointmentsTable();
    });
    document.getElementById('clear-all-search')?.addEventListener('click', async function() {
        document.getElementById('search-all-appointments').value = '';
        await loadAllAppointments();
        allCurrentPage = 1;
        updateAllAppointmentsTable();
    });
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
    }
    document.getElementById('modalClose')?.addEventListener('click', closeModal);
    document.getElementById('closeModal')?.addEventListener('click', closeModal);
    document.getElementById('appointmentModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    document.getElementById('confirmAction')?.addEventListener('click', function() {
        if (confirmationCallback) {
            confirmationCallback();
        }
        document.getElementById('confirmationModal').classList.remove('active');
        confirmationCallback = null;
    });
    document.getElementById('cancelAction')?.addEventListener('click', function() {
        document.getElementById('confirmationModal').classList.remove('active');
        confirmationCallback = null;
    });
    document.getElementById('editAppointmentBtn')?.addEventListener('click', function(e) {
        if (this.classList.contains('disabled')) {
            showMessage(this.getAttribute('title') || 'This action is not available', 'warning');
            return;
        }
        if (isEditing) {
            disableEditMode();
            showMessage('Edit cancelled', 'info');
        } else {
            if (currentAppointmentDetails) {
                const appointmentDate = new Date(currentAppointmentDetails.date);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (appointmentDate < today) {
                    showMessage('Cannot edit past appointments', 'error');
                    return;
                }
                if (currentAppointmentDetails.status === 'completed') {
                    showMessage('Cannot edit completed appointments', 'error');
                    return;
                }
            }
            enableEditMode();
        }
    });
    document.getElementById('saveEditBtn')?.addEventListener('click', function() {
        saveEditedAppointment();
    });
    document.getElementById('cancelEditBtn')?.addEventListener('click', function() {
        disableEditMode();
        showMessage('Edit cancelled', 'info');
    });
    document.getElementById('completeAppointmentBtn')?.addEventListener('click', function(e) {
        if (this.classList.contains('disabled')) {
            showMessage(this.getAttribute('title') || 'This action is not available', 'warning');
            return;
        }
        if (!currentAppointmentId || !currentAppointmentDetails) {
            showMessage('No appointment selected', 'error');
            return;
        }
        const adminNotes = document.getElementById('adminNotes')?.value || '';
        const duration = document.getElementById('appointmentDuration')?.value || 30;
        const startTime = document.getElementById('startTime')?.value;
        if (!startTime) {
            showMessage('Please enter start time', 'error');
            return;
        }
        showConfirmation(
            'Are you sure you want to mark this appointment as completed?',
            'warning',
            async function() {
                try {
                    const response = await fetch(`${API_BASE_URL}?action=complete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            appointment_id: currentAppointmentId,
                            admin_notes: adminNotes,
                            duration: duration,
                            start_time: startTime,
                            end_time: document.getElementById('endTime')?.value || ''
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showMessage('Appointment marked as completed!', 'success');
                        closeModal();
                        await loadConfirmedAppointments();
                        await loadCompletedAppointments();
                        await loadAllAppointments();
                        await loadBookedTimeSlots();
                        updateCalendarView();
                        updateConfirmedTable();
                        updateCompletedTable();
                        updateAllAppointmentsTable();
                    } else {
                        showMessage('Error: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error completing appointment:', error);
                    showMessage('Error completing appointment', 'error');
                }
            }
        );
    });
    document.getElementById('checkAvailability')?.addEventListener('click', async function() {
        const date = document.getElementById('followupDate')?.value;
        const time = document.getElementById('followupTime')?.value;
        if (!date || !time) {
            showMessage('Please select both date and time', 'error');
            return;
        }
        if (currentAppointmentDetails && currentAppointmentDetails.status !== 'completed') {
            showMessage('Only completed appointments can schedule follow-ups', 'error');
            return;
        }
        const selectedDate = new Date(date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate < today) {
            showMessage('Cannot schedule appointments in the past', 'error');
            return;
        }
        const dayOfWeek = selectedDate.getDay();
        if (dayOfWeek === 0) {
            showMessage('Sundays are not available', 'error');
            return;
        }
        const workingHours = WORKING_HOURS[dayOfWeek];
        // FIX: Correctly determine AM/PM based on the time dropdown value
        // The time dropdown shows 12-hour format times like "5:00 PM"
        // So we need to check if it already contains AM/PM
        let time24;
        if (time.includes('AM') || time.includes('PM')) {
            // Time already has AM/PM
            time24 = convertTimeTo24Hour(time);
        } else {
            // Time is just "5:00" - need to check if it's in the morning or afternoon
            // For followup times, we'll assume afternoon for hours 1-5, morning for 6-11
            const hour = parseInt(time.split(':')[0]);
            const ampm = (hour >= 1 && hour <= 5) ? 'PM' : 'AM';
            time24 = convertTimeTo24Hour(`${time} ${ampm}`);
        }
        
        const hour = parseInt(time24.split(':')[0]);
        if (hour < workingHours.start || hour >= workingHours.end) {
            const startTime12 = workingHours.start > 12 ? `${workingHours.start - 12}:00 PM` : workingHours.start === 12 ? `12:00 PM` : `${workingHours.start}:00 AM`;
            const endTime12 = workingHours.end > 12 ? `${workingHours.end - 12}:00 PM` : workingHours.end === 12 ? `12:00 PM` : `${workingHours.end}:00 AM`;
            showMessage(`Working hours for this day are ${startTime12} to ${endTime12}`, 'error');
            return;
        }
        try {
            const response = await fetch(`${API_BASE_URL}?action=checkAvailability&date=${date}&time=${time24}`);
            const data = await response.json();
            const resultDiv = document.getElementById('availabilityResult');
            if (data.success) {
                if (data.available) {
                    resultDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
                    resultDiv.className = 'availability-result available';
                    document.getElementById('scheduleFollowup').style.display = 'inline-flex';
                } else {
                    resultDiv.innerHTML = `<i class="fas fa-times-circle"></i> ${data.message}`;
                    resultDiv.className = 'availability-result unavailable';
                    document.getElementById('scheduleFollowup').style.display = 'none';
                }
                resultDiv.style.display = 'block';
            } else {
                showMessage('Error: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error checking availability:', error);
            showMessage('Error checking availability', 'error');
        }
    });
    document.getElementById('scheduleFollowup')?.addEventListener('click', function(e) {
        if (this.classList.contains('disabled')) {
            showMessage(this.getAttribute('title') || 'This action is not available', 'warning');
            return;
        }
        if (!currentAppointmentId) {
            showMessage('No appointment selected', 'error');
            return;
        }
        if (currentAppointmentDetails && currentAppointmentDetails.status !== 'completed') {
            showMessage('Only completed appointments can schedule follow-ups', 'error');
            return;
        }
        const date = document.getElementById('followupDate')?.value;
        const time = document.getElementById('followupTime')?.value;
        const serviceSelect = document.getElementById('followupService');
        const serviceId = serviceSelect ? serviceSelect.value : null;
        if (!date || !time) {
            showMessage('Please select date and time', 'error');
            return;
        }
        if (!serviceId) {
            showMessage('Please select a service for the follow-up appointment', 'error');
            return;
        }
        showConfirmation(
            'Are you sure you want to schedule a follow-up appointment?',
            'warning',
            async function() {
                try {
                    // FIX: Correctly determine AM/PM for the time
                    let time24;
                    if (time.includes('AM') || time.includes('PM')) {
                        time24 = convertTimeTo24Hour(time);
                    } else {
                        const hour = parseInt(time.split(':')[0]);
                        const ampm = (hour >= 1 && hour <= 5) ? 'PM' : 'AM';
                        time24 = convertTimeTo24Hour(`${time} ${ampm}`);
                    }
                    
                    const response = await fetch(`${API_BASE_URL}?action=updateFollowup`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            appointment_id: currentAppointmentId,
                            followup_date: date,
                            followup_time: time24 + ':00',
                            service_id: serviceId
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showMessage('Follow-up scheduled successfully!', 'success');
                        document.getElementById('modalFollowupInfo').textContent = 
                            `${date} at ${time}`;
                        document.getElementById('scheduleFollowup').style.display = 'none';
                        await loadCompletedAppointments();
                        await loadAllAppointments();
                        await loadBookedTimeSlots();
                        updateCompletedTable();
                        updateAllAppointmentsTable();
                    } else {
                        showMessage('Error: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error scheduling follow-up:', error);
                    showMessage('Error scheduling follow-up', 'error');
                }
            }
        );
    });
    document.getElementById('startTime')?.addEventListener('change', calculateEndTime);
    document.getElementById('appointmentDuration')?.addEventListener('change', calculateEndTime);
    document.getElementById('printReceipt')?.addEventListener('click', function() {
        if (currentAppointmentId) {
            showMessage('Printing receipt for appointment: ' + currentAppointmentId, 'info');
        }
    });
});
function calculateEndTime() {
    const startTime = document.getElementById('startTime')?.value;
    const duration = document.getElementById('appointmentDuration')?.value;
    if (startTime && duration) {
        const [hours, minutes] = startTime.split(':').map(Number);
        const startDate = new Date();
        startDate.setHours(hours, minutes, 0, 0);
        const endDate = new Date(startDate.getTime() + (duration * 60000));
        const endHours = endDate.getHours().toString().padStart(2, '0');
        const endMinutes = endDate.getMinutes().toString().padStart(2, '0');
        document.getElementById('endTime').value = `${endHours}:${endMinutes}`;
    }
}
function closeModal() {
    const modal = document.getElementById('appointmentModal');
    const overlay = document.querySelector('.overlay');
    if (modal) modal.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    document.getElementById('followupSection').style.display = 'none';
    if (isEditing) {
        disableEditMode();
    }
    currentAppointmentId = null;
    currentAppointmentDetails = null;
}
