document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const generalError = document.getElementById('generalError');
    const successMessage = document.getElementById('successMessage');
    let isSubmitting = false;

    // Form elements
    const loginInput = document.getElementById('loginInput');
    const loginInputGroup = document.getElementById('loginInputGroup');
    const loginError = document.getElementById('loginError');

    // Password toggle functionality
    const togglePassword = document.getElementById('toggleLoginPassword');
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const passwordInput = document.getElementById('loginPassword');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }

    // Form submission
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (isSubmitting) return;
        
        // Reset errors
        clearErrors();
        
        // Validate form
        if (!validateForm()) {
            return;
        }
        
        // Determine login type (email or client_id)
        const loginValue = loginInput.value.trim();
        const loginType = detectLoginType(loginValue);
        
        // Disable submit button and show loading state
        isSubmitting = true;
        setLoading(true);
        
        // Get reCAPTCHA token if available
        let recaptchaToken = null;
        if (typeof grecaptcha !== 'undefined' && typeof RECAPTCHA_SITE_KEY !== 'undefined') {
            try {
                recaptchaToken = await new Promise((resolve) => {
                    grecaptcha.ready(async () => {
                        const token = await grecaptcha.execute(RECAPTCHA_SITE_KEY, {action: 'login'});
                        resolve(token);
                    });
                });
            } catch (e) {
                console.error('reCAPTCHA error:', e);
            }
        }
        
        // Create FormData object
        const formData = new FormData(this);
        formData.append('login_type', loginType);
        formData.append('login_value', loginValue);
        if (recaptchaToken) {
            formData.append('recaptcha_token', recaptchaToken);
        }
        
        try {
            // Send AJAX request
            const response = await fetch('login.php', {
                method: 'POST',
                body: formData
            });
            
            // Check if response is JSON
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                const text = await response.text();
                console.error('Response is not JSON:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response');
            }
            
            const result = await response.json();
            
            if (result.success) {
                showSuccess(result.message);
                // Redirect after 1.5 seconds
                setTimeout(() => {
                    window.location.href = result.redirect || '../index.php';
                }, 1500);
            } else {
                displayErrors(result.errors);
                isSubmitting = false;
                setLoading(false);
            }
        } catch (error) {
            console.error('Error:', error);
            showGeneralError('Network error. Please check your connection and try again.');
            isSubmitting = false;
            setLoading(false);
        }
    });

    function detectLoginType(value) {
        // If it looks like an email, treat it as email
        if (value.includes('@')) {
            return 'email';
        }
        // If it starts with PAT (case-insensitive), treat it as client_id
        if (/^PAT/i.test(value)) {
            return 'client_id';
        }
        // Default to email for everything else
        return 'email';
    }

    function validateForm() {
        let isValid = true;
        
        // Get input value
        const inputValue = loginInput.value.trim();
        
        if (!inputValue) {
            showFieldError(loginInputGroup, loginError, 'Email or Client ID is required');
            isValid = false;
        } else {
            const loginType = detectLoginType(inputValue);
            
            if (loginType === 'email' && !isValidEmail(inputValue)) {
                showFieldError(loginInputGroup, loginError, 'Please enter a valid email address');
                isValid = false;
            } else if (loginType === 'client_id' && !isValidClientId(inputValue)) {
                showFieldError(loginInputGroup, loginError, 'Client ID should start with PAT followed by numbers (e.g., PAT0001)');
                isValid = false;
            } else {
                clearFieldError(loginInputGroup, loginError);
            }
        }
        
        // Validate password
        const passwordInput = document.getElementById('loginPassword');
        const passwordValue = passwordInput.value;
        const passwordError = document.getElementById('passwordError');
        const passwordGroup = document.getElementById('passwordGroup');
        
        if (!passwordValue) {
            showFieldError(passwordGroup, passwordError, 'Password is required');
            isValid = false;
        } else {
            clearFieldError(passwordGroup, passwordError);
        }
        
        return isValid;
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function isValidClientId(clientId) {
        // Accepts PAT followed by numbers (PAT0001, PAT1234, etc.)
        const clientIdRegex = /^PAT\d+$/i;
        return clientIdRegex.test(clientId);
    }

    function showFieldError(group, errorElement, message) {
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
        if (group) {
            group.classList.add('error');
        }
    }

    function clearFieldError(group, errorElement) {
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }
        if (group) {
            group.classList.remove('error');
        }
    }

    function displayErrors(errors) {
        clearErrors();
        
        if (errors.general) {
            showGeneralError(errors.general);
        }
        
        // Display field-specific errors
        const errorMap = {
            email: { error: loginError, group: loginInputGroup },
            client_id: { error: loginError, group: loginInputGroup },
            password: { error: document.getElementById('passwordError'), group: document.getElementById('passwordGroup') }
        };
        
        for (const [field, { error, group }] of Object.entries(errorMap)) {
            if (errors[field] && error && group) {
                showFieldError(group, error, errors[field]);
            }
        }
    }

    function clearErrors() {
        // Clear general error
        if (generalError) {
            generalError.textContent = '';
            generalError.style.display = 'none';
        }
        
        // Clear success message
        if (successMessage) {
            successMessage.textContent = '';
            successMessage.style.display = 'none';
        }
        
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

    function showGeneralError(message) {
        if (generalError) {
            generalError.textContent = message;
            generalError.style.display = 'block';
        }
    }

    function showSuccess(message) {
        if (successMessage) {
            successMessage.textContent = message;
            successMessage.style.display = 'block';
        }
    }

    function setLoading(loading) {
        if (loading) {
            loginForm.classList.add('loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Signing In...';
        } else {
            loginForm.classList.remove('loading');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Sign In';
        }
    }

    // Real-time validation on blur
    if (loginInput) {
        loginInput.addEventListener('blur', function() {
            const inputValue = this.value.trim();
            
            if (!inputValue) {
                showFieldError(loginInputGroup, loginError, 'Email or Client ID is required');
            } else {
                const loginType = detectLoginType(inputValue);
                
                if (loginType === 'email' && !isValidEmail(inputValue)) {
                    showFieldError(loginInputGroup, loginError, 'Please enter a valid email address');
                } else if (loginType === 'client_id' && !isValidClientId(inputValue)) {
                    showFieldError(loginInputGroup, loginError, 'Client ID should start with PAT followed by numbers');
                } else {
                    clearFieldError(loginInputGroup, loginError);
                }
            }
        });
        
        // Clear error on input
        loginInput.addEventListener('input', function() {
            clearFieldError(loginInputGroup, loginError);
        });
    }

    const passwordInput = document.getElementById('loginPassword');
    if (passwordInput) {
        passwordInput.addEventListener('blur', function() {
            const passwordValue = this.value;
            const passwordError = document.getElementById('passwordError');
            const passwordGroup = document.getElementById('passwordGroup');
            
            if (!passwordValue) {
                showFieldError(passwordGroup, passwordError, 'Password is required');
            } else {
                clearFieldError(passwordGroup, passwordError);
            }
        });
        
        // Clear error on input
        passwordInput.addEventListener('input', function() {
            const passwordError = document.getElementById('passwordError');
            const passwordGroup = document.getElementById('passwordGroup');
            clearFieldError(passwordGroup, passwordError);
        });
    }

    // Auto-focus on first field
    if (loginInput) {
        loginInput.focus();
    }
});