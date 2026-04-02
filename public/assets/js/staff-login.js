document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const generalError = document.getElementById('generalError');
    let isSubmitting = false;

    // Password toggle functionality
    const togglePassword = document.getElementById('togglePassword');
    if (togglePassword) {
        togglePassword.addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }

    // Form submission
    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (isSubmitting) return;

        // Reset errors
        clearErrors();

        // Disable submit button and show loading state
        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Signing In...';

        // Create FormData object
        // Get reCAPTCHA token if available
        let recaptchaToken = null;
        if (typeof grecaptcha !== 'undefined' && typeof RECAPTCHA_SITE_KEY !== 'undefined') {
            try {
                recaptchaToken = await new Promise((resolve) => {
                    grecaptcha.ready(async () => {
                        const token = await grecaptcha.execute(RECAPTCHA_SITE_KEY, {action: 'staff_login'});
                        resolve(token);
                    });
                });
            } catch (e) {
                console.error('reCAPTCHA error:', e);
            }
        }
        
        const formData = new FormData(this);
        if (recaptchaToken) {
            formData.append('recaptcha_token', recaptchaToken);
        }

        // Send AJAX request
        fetch('controllers/StaffLoginController.php', {
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
                    // Show success message in form AND floating message
                    showFormMessage(data.message, 'success');
                    showFloatingMessage(data.message, 'success');

                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    // Show errors in form fields
                    displayErrors(data.errors);
                    isSubmitting = false;
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Sign In';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFormMessage('Network error. Please check your connection and try again.', 'error');
                showFloatingMessage('Network error. Please check your connection and try again.', 'error');
                isSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sign In';
            });
    });

    function displayErrors(errors) {
        for (const field in errors) {
            if (field === 'general') {
                showFormMessage(errors[field], 'error');
                showFloatingMessage(errors[field], 'error');
            } else {
                const errorElement = document.getElementById(field + 'Error');
                const inputGroup = document.getElementById(field + 'Group');

                if (errorElement && inputGroup) {
                    errorElement.textContent = errors[field];
                    errorElement.style.display = 'block';
                    inputGroup.classList.add('error');
                }
            }
        }
    }

    function clearErrors() {
        // Clear general error
        generalError.textContent = '';
        generalError.style.display = 'none';
        generalError.className = 'general-error';

        // Clear field errors
        const errorElements = document.querySelectorAll('.error-message');
        errorElements.forEach(element => {
            element.textContent = '';
            element.style.display = 'none';
        });

        const inputGroups = document.querySelectorAll('.form-group');
        inputGroups.forEach(group => {
            group.classList.remove('error');
        });
    }

    function showFormMessage(message, type) {
        generalError.textContent = message;
        generalError.className = `general-error ${type}`;
        generalError.style.display = 'block';
    }

    function showFloatingMessage(text, type) {
        // Remove existing floating messages
        const existingMessages = document.querySelectorAll('.message');
        existingMessages.forEach(msg => msg.remove());

        // Create new floating message
        const message = document.createElement('div');
        message.className = `message ${type}`;
        message.textContent = text;

        document.body.appendChild(message);

        // Remove message after 5 seconds
        setTimeout(() => {
            message.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (message.parentNode) {
                    message.parentNode.removeChild(message);
                }
            }, 300);
        }, 5000);
    }

    // Real-time validation
    const staffIdInput = document.getElementById('staffId');
    const passwordInput = document.getElementById('password');

    if (staffIdInput) {
        staffIdInput.addEventListener('blur', validateStaffId);
    }

    if (passwordInput) {
        passwordInput.addEventListener('blur', validatePassword);
    }

    function validateStaffId() {
        const staffId = staffIdInput.value.trim();
        const staffIdError = document.getElementById('staffIdError');
        const staffIdGroup = document.getElementById('staffIdGroup');

        if (!staffId) {
            showFieldError(staffIdGroup, staffIdError, 'Staff ID is required');
            return false;
        } else {
            clearFieldError(staffIdGroup, staffIdError);
            return true;
        }
    }

    function validatePassword() {
        const password = passwordInput.value;
        const passwordError = document.getElementById('passwordError');
        const passwordGroup = document.getElementById('passwordGroup');

        if (!password) {
            showFieldError(passwordGroup, passwordError, 'Password is required');
            return false;
        } else {
            clearFieldError(passwordGroup, passwordError);
            return true;
        }
    }

    function showFieldError(group, errorElement, message) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        group.classList.add('error');
    }

    function clearFieldError(group, errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
        group.classList.remove('error');
    }
});