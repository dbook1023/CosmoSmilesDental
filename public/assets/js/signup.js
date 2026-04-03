document.addEventListener('DOMContentLoaded', function() {
    // Multi-step form state
    let currentStep = 1;
    const totalSteps = 5;
    
    // OTP verification state
    let emailVerified = false;
    let phoneVerified = false;
    let emailResendTimer = null;
    let phoneResendTimer = null;
    
    // Terms scroll state
    let termsRead = false;
    let isSubmitting = false;
    
    // Form elements
    const signupForm = document.getElementById('signupForm');
    const submitBtn = document.getElementById('submitBtn');
    const generalError = document.getElementById('generalError');
    const successMessage = document.getElementById('successMessage');
    
    // Input elements
    const firstNameInput = document.getElementById('firstName');
    const lastNameInput = document.getElementById('lastName');
    const birthdateInput = document.getElementById('birthdate');
    const phoneInput = document.getElementById('phone');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const termsCheckbox = document.getElementById('terms');
    const parentalConsentHidden = document.getElementById('parental_consent');
    
    // Error elements
    const firstNameError = document.getElementById('firstNameError');
    const lastNameError = document.getElementById('lastNameError');
    const birthdateError = document.getElementById('birthdateError');
    const phoneError = document.getElementById('phoneError');
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');
    const confirmPasswordError = document.getElementById('confirmPasswordError');
    const termsError = document.getElementById('termsError');
    const parentalConsentError = document.getElementById('parentalConsentError');
    
    // Group elements
    const firstNameGroup = document.getElementById('firstNameGroup');
    const lastNameGroup = document.getElementById('lastNameGroup');
    const birthdateGroup = document.getElementById('birthdateGroup');
    const phoneGroup = document.getElementById('phoneGroup');
    const emailGroup = document.getElementById('emailGroup');
    const passwordGroup = document.getElementById('passwordGroup');
    const confirmPasswordGroup = document.getElementById('confirmPasswordGroup');
    const termsGroup = document.getElementById('termsGroup');
    const parentalConsentGroup = document.getElementById('parentalConsentGroup');
    
    // Password toggles
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    
    // Signature pad elements
    const signatureCanvas = document.getElementById('signatureCanvas');
    const clearSignatureBtn = document.getElementById('clearSignatureBtn');
    
    // Terms scroll elements
    const termsScrollContent = document.getElementById('termsScrollContent');
    const termsScrollIndicator = document.getElementById('termsScrollIndicator');
    const termsCheckboxContainer = document.getElementById('termsCheckboxContainer');
    
    // OTP elements
    const emailOtpContainer = document.getElementById('emailOtpContainer');
    const phoneOtpContainer = document.getElementById('phoneOtpContainer');
    const emailOtpTarget = document.getElementById('emailOtpTarget');
    const phoneOtpTarget = document.getElementById('phoneOtpTarget');
    const emailOtpError = document.getElementById('emailOtpError');
    const phoneOtpError = document.getElementById('phoneOtpError');
    const emailOtpSuccess = document.getElementById('emailOtpSuccess');
    const phoneOtpSuccess = document.getElementById('phoneOtpSuccess');
    const verifyEmailOtpBtn = document.getElementById('verifyEmailOtpBtn');
    const verifyPhoneOtpBtn = document.getElementById('verifyPhoneOtpBtn');
    const resendEmailOtpBtn = document.getElementById('resendEmailOtpBtn');
    const resendPhoneOtpBtn = document.getElementById('resendPhoneOtpBtn');
    const emailResendTimerEl = document.getElementById('emailResendTimer');
    const phoneResendTimerEl = document.getElementById('phoneResendTimer');
    const emailOtpNextBtn = document.getElementById('emailOtpNextBtn');
    const phoneOtpNextBtn = document.getElementById('phoneOtpNextBtn');
    
    // OTP Controller path
    const OTP_CONTROLLER_URL = '../controllers/OTPController.php';
    
    // Initialize everything
    initMultiStepForm();
    setupPasswordToggles();
    setupInputValidations();
    setupFormSubmission();
    setupOTPInputs();
    setupOTPButtons();
    setupSignaturePad();
    setupTermsScroll();
    
    function initMultiStepForm() {
        document.querySelectorAll('.btn-next').forEach(button => {
            button.addEventListener('click', function() {
                const nextStep = parseInt(this.getAttribute('data-next'));
                
                if (currentStep === 2 && nextStep === 3) {
                    if (validateStep(2)) {
                        navigateToStep(3);
                        sendEmailOTP();
                    }
                    return;
                }
                
                if (currentStep === 3 && nextStep === 4) {
                    if (emailVerified) {
                        navigateToStep(4);
                        sendPhoneOTP();
                    }
                    return;
                }
                
                if (currentStep === 4 && nextStep === 5) {
                    if (phoneVerified) {
                        navigateToStep(5);
                    }
                    return;
                }
                
                if (validateStep(currentStep)) {
                    navigateToStep(nextStep);
                }
            });
        });
        
        document.querySelectorAll('.btn-prev').forEach(button => {
            button.addEventListener('click', function() {
                const prevStep = parseInt(this.getAttribute('data-prev'));
                navigateToStep(prevStep);
            });
        });
    }
    
    // ========================
    // Signature Pad
    // ========================
    function setupSignaturePad() {
        if (!signatureCanvas) return;
        
        const ctx = signatureCanvas.getContext('2d');
        let isDrawing = false;
        let hasSignature = false;
        
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#03074f';
        
        function getCoordinates(e) {
            const rect = signatureCanvas.getBoundingClientRect();
            let clientX = e.clientX;
            let clientY = e.clientY;
            
            if (e.touches && e.touches.length > 0) {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            }
            
            const scaleX = signatureCanvas.width / rect.width;
            const scaleY = signatureCanvas.height / rect.height;
            
            return {
                x: (clientX - rect.left) * scaleX,
                y: (clientY - rect.top) * scaleY
            };
        }
        
        function startDrawing(e) {
            e.preventDefault();
            isDrawing = true;
            const pos = getCoordinates(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        }
        
        function draw(e) {
            if (!isDrawing) return;
            e.preventDefault();
            const pos = getCoordinates(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            hasSignature = true;
            
            parentalConsentHidden.value = '1';
            if (parentalConsentError) {
                parentalConsentError.style.display = 'none';
            }
        }
        
        function stopDrawing() {
            if (isDrawing) {
                ctx.closePath();
                isDrawing = false;
            }
        }
        
        signatureCanvas.addEventListener('mousedown', startDrawing);
        signatureCanvas.addEventListener('mousemove', draw);
        window.addEventListener('mouseup', stopDrawing);
        
        signatureCanvas.addEventListener('touchstart', startDrawing, {passive: false});
        signatureCanvas.addEventListener('touchmove', draw, {passive: false});
        window.addEventListener('touchend', stopDrawing);
        window.addEventListener('touchcancel', stopDrawing);
        
        window.clearSignatureCanvas = function() {
            ctx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
            hasSignature = false;
            parentalConsentHidden.value = '0';
        };
        
        if (clearSignatureBtn) {
            clearSignatureBtn.addEventListener('click', () => {
                window.clearSignatureCanvas();
                if (parentalConsentError) {
                    parentalConsentError.style.display = 'none';
                    parentalConsentError.textContent = '';
                }
            });
        }
        
        window.getSignatureData = function() {
            if (!hasSignature) return null;
            return signatureCanvas.toDataURL('image/png');
        };
    }
    
    function dataURLtoBlob(dataurl) {
        let arr = dataurl.split(','), mime = arr[0].match(/:(.*?);/)[1];
        let bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
        while(n--){
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new Blob([u8arr], {type:mime});
    }
    
    // ========================
    // Terms Scroll Detection
    // ========================
    function setupTermsScroll() {
        if (!termsScrollContent) return;
        
        // Add disabled look initially
        if (termsCheckboxContainer) {
            termsCheckboxContainer.classList.add('disabled-look');
        }
        
        termsScrollContent.addEventListener('scroll', function() {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            // Check if scrolled near the bottom (within 20px)
            if (scrollTop + clientHeight >= scrollHeight - 20) {
                termsRead = true;
                termsCheckbox.disabled = false;
                if (termsCheckboxContainer) {
                    termsCheckboxContainer.classList.remove('disabled-look');
                }
                if (termsScrollIndicator) {
                    termsScrollIndicator.classList.add('hidden');
                }
            }
        });
    }
    
    // ========================
    // Password Toggles
    // ========================
    function setupPasswordToggles() {
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                togglePasswordVisibility(passwordInput, this);
            });
        }
        
        if (toggleConfirmPassword && confirmPasswordInput) {
            toggleConfirmPassword.addEventListener('click', function() {
                togglePasswordVisibility(confirmPasswordInput, this);
            });
        }
    }
    
    // ========================
    // Input Validations
    // ========================
    function setupInputValidations() {
        const inputs = [
            { input: firstNameInput, error: firstNameError, group: firstNameGroup, validator: validateFirstName },
            { input: lastNameInput, error: lastNameError, group: lastNameGroup, validator: validateLastName },
            { input: birthdateInput, error: birthdateError, group: birthdateGroup, validator: validateBirthdate },
            { input: phoneInput, error: phoneError, group: phoneGroup, validator: validatePhone },
            { input: emailInput, error: emailError, group: emailGroup, validator: validateEmail },
            { input: passwordInput, error: passwordError, group: passwordGroup, validator: validatePassword },
            { input: confirmPasswordInput, error: confirmPasswordError, group: confirmPasswordGroup, validator: validateConfirmPassword }
        ];
        
        inputs.forEach(({ input, error, group, validator }) => {
            if (input && error) {
                input.addEventListener('input', () => {
                    hideError(error, group);
                    hideGeneralError();
                });
                
                input.addEventListener('blur', () => {
                    const errorMsg = validator();
                    if (errorMsg) {
                        showError(error, group, errorMsg);
                    } else {
                        hideError(error, group);
                    }
                });
            }
        });
        
        if (termsCheckbox && termsError) {
            termsCheckbox.addEventListener('change', () => {
                hideFieldError(termsError, termsGroup);
                hideGeneralError();
            });
        }
        
        if (birthdateInput) {
            birthdateInput.addEventListener('change', function() {
                checkParentalConsentRequirement();
            });
        }
        
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                formatPhoneNumber(e.target);
            });
        }
        
        if (passwordInput && confirmPasswordInput) {
            passwordInput.addEventListener('input', () => {
                const errorMsg = validateConfirmPassword();
                if (errorMsg) {
                    showError(confirmPasswordError, confirmPasswordGroup, errorMsg);
                } else {
                    hideError(confirmPasswordError, confirmPasswordGroup);
                }
            });
            
            confirmPasswordInput.addEventListener('input', () => {
                const errorMsg = validateConfirmPassword();
                if (errorMsg) {
                    showError(confirmPasswordError, confirmPasswordGroup, errorMsg);
                } else {
                    hideError(confirmPasswordError, confirmPasswordGroup);
                }
            });
        }
    }
    
    // ========================
    // Form Submission
    // ========================
    function setupFormSubmission() {
        signupForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (isSubmitting) return;
            
            clearAllErrors();
            
            if (!emailVerified) {
                showGeneralError('Please verify your email address first.');
                navigateToStep(3);
                return;
            }
            
            if (!phoneVerified) {
                showGeneralError('Please verify your phone number first.');
                navigateToStep(4);
                return;
            }
            
            const isValid = validateAllSteps();
            
            if (isValid) {
                isSubmitting = true;
                await submitForm();
            } else {
                for (let step = 1; step <= 2; step++) {
                    if (!validateStep(step, true)) {
                        navigateToStep(step);
                        break;
                    }
                }
            }
        });
    }
    
    // ========================
    // OTP Input Setup
    // ========================
    function setupOTPInputs() {
        [emailOtpContainer, phoneOtpContainer].forEach(container => {
            if (!container) return;
            const digits = container.querySelectorAll('.otp-digit');
            
            digits.forEach((digit, index) => {
                digit.addEventListener('input', function(e) {
                    const value = this.value.replace(/\D/g, '');
                    this.value = value.slice(0, 1);
                    
                    if (value && index < digits.length - 1) {
                        digits[index + 1].focus();
                    }
                    
                    if (this.value) {
                        this.classList.add('filled');
                        this.classList.remove('error');
                    } else {
                        this.classList.remove('filled');
                    }
                });
                
                digit.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        digits[index - 1].focus();
                        digits[index - 1].value = '';
                        digits[index - 1].classList.remove('filled');
                    }
                });
                
                digit.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    
                    for (let i = 0; i < Math.min(pastedData.length, digits.length); i++) {
                        digits[i].value = pastedData[i];
                        digits[i].classList.add('filled');
                        digits[i].classList.remove('error');
                    }
                    
                    const focusIndex = Math.min(pastedData.length, digits.length - 1);
                    digits[focusIndex].focus();
                });
            });
        });
    }
    
    function getOTPCode(container) {
        const digits = container.querySelectorAll('.otp-digit');
        let code = '';
        digits.forEach(d => code += d.value);
        return code;
    }
    
    function clearOTPInputs(container) {
        const digits = container.querySelectorAll('.otp-digit');
        digits.forEach(d => {
            d.value = '';
            d.classList.remove('filled', 'error', 'success');
        });
        if (digits.length > 0) digits[0].focus();
    }
    
    function setOTPError(container) {
        const digits = container.querySelectorAll('.otp-digit');
        digits.forEach(d => d.classList.add('error'));
    }
    
    function setOTPSuccess(container) {
        const digits = container.querySelectorAll('.otp-digit');
        digits.forEach(d => {
            d.classList.remove('error', 'filled');
            d.classList.add('success');
            d.disabled = true;
        });
    }
    
    // ========================
    // OTP Send & Verify
    // ========================
    function setupOTPButtons() {
        if (verifyEmailOtpBtn) {
            verifyEmailOtpBtn.addEventListener('click', async function() {
                const code = getOTPCode(emailOtpContainer);
                if (code.length !== 6) {
                    emailOtpError.textContent = 'Please enter the complete 6-digit code.';
                    emailOtpError.style.display = 'block';
                    setOTPError(emailOtpContainer);
                    return;
                }
                
                emailOtpError.style.display = 'none';
                this.disabled = true;
                this.textContent = 'Verifying...';
                
                try {
                    const response = await fetch(OTP_CONTROLLER_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'verify_email_otp',
                            email: emailInput.value.trim(),
                            otp_code: code
                        })
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        emailVerified = true;
                        setOTPSuccess(emailOtpContainer);
                        emailOtpSuccess.classList.add('active');
                        this.textContent = '✓ Email Verified';
                        this.classList.add('verified');
                        emailOtpNextBtn.disabled = false;
                        
                        if (emailResendTimer) clearInterval(emailResendTimer);
                        resendEmailOtpBtn.style.display = 'none';
                        emailResendTimerEl.style.display = 'none';
                    } else {
                        emailOtpError.textContent = result.message;
                        emailOtpError.style.display = 'block';
                        setOTPError(emailOtpContainer);
                        this.disabled = false;
                        this.textContent = 'Verify Email';
                    }
                } catch (err) {
                    emailOtpError.textContent = 'Network error. Please try again.';
                    emailOtpError.style.display = 'block';
                    this.disabled = false;
                    this.textContent = 'Verify Email';
                }
            });
        }
        
        if (verifyPhoneOtpBtn) {
            verifyPhoneOtpBtn.addEventListener('click', async function() {
                const code = getOTPCode(phoneOtpContainer);
                if (code.length !== 6) {
                    phoneOtpError.textContent = 'Please enter the complete 6-digit code.';
                    phoneOtpError.style.display = 'block';
                    setOTPError(phoneOtpContainer);
                    return;
                }
                
                phoneOtpError.style.display = 'none';
                this.disabled = true;
                this.textContent = 'Verifying...';
                
                try {
                    const response = await fetch(OTP_CONTROLLER_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'verify_phone_otp',
                            phone: phoneInput.value.trim(),
                            otp_code: code
                        })
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        phoneVerified = true;
                        setOTPSuccess(phoneOtpContainer);
                        phoneOtpSuccess.classList.add('active');
                        this.textContent = '✓ Phone Verified';
                        this.classList.add('verified');
                        phoneOtpNextBtn.disabled = false;
                        
                        if (phoneResendTimer) clearInterval(phoneResendTimer);
                        resendPhoneOtpBtn.style.display = 'none';
                        phoneResendTimerEl.style.display = 'none';
                    } else {
                        phoneOtpError.textContent = result.message;
                        phoneOtpError.style.display = 'block';
                        setOTPError(phoneOtpContainer);
                        this.disabled = false;
                        this.textContent = 'Verify Phone';
                    }
                } catch (err) {
                    phoneOtpError.textContent = 'Network error. Please try again.';
                    phoneOtpError.style.display = 'block';
                    this.disabled = false;
                    this.textContent = 'Verify Phone';
                }
            });
        }
        
        if (resendEmailOtpBtn) {
            resendEmailOtpBtn.addEventListener('click', function() {
                sendEmailOTP();
                clearOTPInputs(emailOtpContainer);
                emailOtpError.style.display = 'none';
            });
        }
        
        if (resendPhoneOtpBtn) {
            resendPhoneOtpBtn.addEventListener('click', function() {
                sendPhoneOTP();
                clearOTPInputs(phoneOtpContainer);
                phoneOtpError.style.display = 'none';
            });
        }
    }
    
    async function sendEmailOTP() {
        if (emailVerified) return;
        
        const email = emailInput.value.trim();
        emailOtpTarget.textContent = email;
        
        startResendCountdown('email');
        
        try {
            const response = await fetch(OTP_CONTROLLER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'send_email_otp',
                    email: email,
                    firstName: firstNameInput.value.trim()
                })
            });
            const result = await response.json();
            
            if (!result.success) {
                emailOtpError.textContent = result.message;
                emailOtpError.style.display = 'block';
            }
        } catch (err) {
            emailOtpError.textContent = 'Failed to send verification code. Please try again.';
            emailOtpError.style.display = 'block';
        }
        
        const firstDigit = emailOtpContainer.querySelector('.otp-digit');
        if (firstDigit) firstDigit.focus();
    }
    
    async function sendPhoneOTP() {
        if (phoneVerified) return;
        
        const phone = phoneInput.value.trim();
        phoneOtpTarget.textContent = phone.replace(/(\d{4})(\d{3})(\d{4})/, '$1 $2 $3');
        
        startResendCountdown('phone');
        
        try {
            const response = await fetch(OTP_CONTROLLER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'send_phone_otp',
                    phone: phone
                })
            });
            const result = await response.json();
            
            if (!result.success) {
                phoneOtpError.textContent = result.message;
                phoneOtpError.style.display = 'block';
            }
        } catch (err) {
            phoneOtpError.textContent = 'Failed to send verification code. Please try again.';
            phoneOtpError.style.display = 'block';
        }
        
        const firstDigit = phoneOtpContainer.querySelector('.otp-digit');
        if (firstDigit) firstDigit.focus();
    }
    
    function startResendCountdown(type) {
        let seconds = 60;
        const timerEl = type === 'email' ? emailResendTimerEl : phoneResendTimerEl;
        const resendBtn = type === 'email' ? resendEmailOtpBtn : resendPhoneOtpBtn;
        
        resendBtn.disabled = true;
        resendBtn.style.display = 'none';
        timerEl.style.display = 'inline';
        timerEl.innerHTML = `Resend code in <strong>${seconds}s</strong>`;
        
        if (type === 'email' && emailResendTimer) clearInterval(emailResendTimer);
        if (type === 'phone' && phoneResendTimer) clearInterval(phoneResendTimer);
        
        const interval = setInterval(() => {
            seconds--;
            timerEl.innerHTML = `Resend code in <strong>${seconds}s</strong>`;
            
            if (seconds <= 0) {
                clearInterval(interval);
                timerEl.style.display = 'none';
                resendBtn.style.display = 'inline';
                resendBtn.disabled = false;
            }
        }, 1000);
        
        if (type === 'email') emailResendTimer = interval;
        else phoneResendTimer = interval;
    }
    
    // ========================
    // Parental Consent
    // ========================
    function checkParentalConsentRequirement() {
        const birthdateValue = birthdateInput.value;
        if (!birthdateValue) {
            parentalConsentGroup.style.display = 'none';
            return;
        }
        
        const birthDate = new Date(birthdateValue);
        const today = new Date();
        const age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        const adjustedAge = monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate()) ? age - 1 : age;
        
        if (adjustedAge < 18) {
            parentalConsentGroup.style.display = 'block';
        } else {
            parentalConsentGroup.style.display = 'none';
            parentalConsentHidden.value = '0';
            if (window.clearSignatureCanvas) {
                window.clearSignatureCanvas();
            }
            if (parentalConsentError) {
                parentalConsentError.style.display = 'none';
            }
        }
    }
    
    // ========================
    // Navigation
    // ========================
    function navigateToStep(step) {
        document.getElementById(`step-${currentStep}`).classList.remove('active');
        document.getElementById(`step-${step}`).classList.add('active');
        updateStepIndicators(step);
        currentStep = step;
    }
    
    function updateStepIndicators(activeStep) {
        document.querySelectorAll('.step').forEach(step => {
            const stepNumber = parseInt(step.getAttribute('data-step'));
            step.classList.remove('active', 'completed');
            
            if (stepNumber === activeStep) {
                step.classList.add('active');
            } else if (stepNumber < activeStep) {
                step.classList.add('completed');
            }
        });
    }
    
    // ========================
    // Validation
    // ========================
    function validateStep(step, silent = false) {
        let isValid = true;
        let errorMessage = null;
        
        switch(step) {
            case 1:
                errorMessage = validateFirstName();
                if (errorMessage) {
                    if (!silent) showError(firstNameError, firstNameGroup, errorMessage);
                    isValid = false;
                }
                errorMessage = validateLastName();
                if (errorMessage) {
                    if (!silent) showError(lastNameError, lastNameGroup, errorMessage);
                    isValid = false;
                }
                errorMessage = validateBirthdate();
                if (errorMessage) {
                    if (!silent) showError(birthdateError, birthdateGroup, errorMessage);
                    isValid = false;
                }
                errorMessage = validatePhone();
                if (errorMessage) {
                    if (!silent) showError(phoneError, phoneGroup, errorMessage);
                    isValid = false;
                }
                errorMessage = validateParentalConsent();
                if (errorMessage) {
                    if (!silent) {
                        parentalConsentError.textContent = errorMessage;
                        parentalConsentError.style.display = 'block';
                    }
                    isValid = false;
                }
                break;
                
            case 2:
                errorMessage = validateEmail();
                if (errorMessage) {
                    if (!silent) showError(emailError, emailGroup, errorMessage);
                    isValid = false;
                }
                errorMessage = validatePassword();
                if (errorMessage) {
                    if (!silent) showError(passwordError, passwordGroup, errorMessage);
                    isValid = false;
                }
                errorMessage = validateConfirmPassword();
                if (errorMessage) {
                    if (!silent) showError(confirmPasswordError, confirmPasswordGroup, errorMessage);
                    isValid = false;
                }
                errorMessage = validateTerms();
                if (errorMessage) {
                    if (!silent) {
                        termsError.textContent = errorMessage;
                        termsError.style.display = 'block';
                    }
                    isValid = false;
                }
                break;
        }
        
        return isValid;
    }
    
    function validateAllSteps() {
        let isValid = true;
        for (let step = 1; step <= 2; step++) {
            if (!validateStep(step, true)) {
                isValid = false;
            }
        }
        return isValid;
    }
    
    function validateFirstName() {
        const value = firstNameInput.value.trim();
        if (!value) return 'First name is required';
        if (!/^[a-zA-Z ]+$/.test(value)) return 'Only letters and spaces allowed';
        if (value.length < 2) return 'First name must be at least 2 characters';
        return null;
    }
    
    function validateLastName() {
        const value = lastNameInput.value.trim();
        if (!value) return 'Last name is required';
        if (!/^[a-zA-Z ]+$/.test(value)) return 'Only letters and spaces allowed';
        if (value.length < 2) return 'Last name must be at least 2 characters';
        return null;
    }
    
    function validateBirthdate() {
        const value = birthdateInput.value;
        if (!value) return 'Date of birth is required';
        return null;
    }
    
    function validatePhone() {
        const value = phoneInput.value.trim();
        if (!value) return 'Phone number is required';
        if (!/^09[0-9]{9}$/.test(value)) return 'Please enter a valid Philippine mobile number (09XXXXXXXXX)';
        return null;
    }
    
    function validateEmail() {
        const value = emailInput.value.trim();
        if (!value) return 'Email is required';
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Please enter a valid email address';
        return null;
    }
    
    function validatePassword() {
        const value = passwordInput.value;
        if (!value) return 'Password is required';
        if (value.length < 8) return 'Password must be at least 8 characters long';
        if (!/[A-Z]/.test(value)) return 'Password must contain at least one uppercase letter';
        if (!/[a-z]/.test(value)) return 'Password must contain at least one lowercase letter';
        if (!/[0-9]/.test(value)) return 'Password must contain at least one number';
        return null;
    }
    
    function validateConfirmPassword() {
        const value = confirmPasswordInput.value;
        const password = passwordInput.value;
        if (!value) return 'Please confirm your password';
        if (password !== value) return 'Passwords do not match';
        return null;
    }
    
    function validateTerms() {
        if (!termsRead) return 'Please scroll through and read the Terms of Service and Privacy Policy first';
        if (!termsCheckbox.checked) return 'You must agree to the Terms of Service and Privacy Policy';
        return null;
    }
    
    function validateParentalConsent() {
        const birthdateValue = birthdateInput.value;
        if (!birthdateValue) return null;
        
        const birthDate = new Date(birthdateValue);
        const today = new Date();
        const age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        const adjustedAge = monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate()) ? age - 1 : age;
        
        if (adjustedAge < 18) {
            if (parentalConsentHidden.value === '0') {
                return 'Please draw your parental consent signature';
            }
        }
        return null;
    }
    
    // ========================
    // Utility Functions
    // ========================
    function togglePasswordVisibility(input, button) {
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        
        const icon = button.querySelector('i');
        if (type === 'password') {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }
    
    function formatPhoneNumber(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length > 0) {
            value = value.substring(0, 11);
        }
        input.value = value;
    }
    
    function showError(errorElement, groupElement, message) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        groupElement.classList.add('error');
    }
    
    function hideError(errorElement, groupElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
        groupElement.classList.remove('error');
    }
    
    function hideFieldError(errorElement, groupElement) {
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }
        if (groupElement) {
            groupElement.classList.remove('error');
        }
    }
    
    function showGeneralError(message) {
        generalError.textContent = message;
        generalError.style.display = 'block';
    }
    
    function hideGeneralError() {
        generalError.textContent = '';
        generalError.style.display = 'none';
    }
    
    function showSuccess(message) {
        successMessage.textContent = message;
        successMessage.style.display = 'block';
    }
    
    function hideSuccess() {
        successMessage.style.display = 'none';
    }
    
    function clearAllErrors() {
        hideError(firstNameError, firstNameGroup);
        hideError(lastNameError, lastNameGroup);
        hideError(birthdateError, birthdateGroup);
        hideError(phoneError, phoneGroup);
        hideError(emailError, emailGroup);
        hideError(passwordError, passwordGroup);
        hideError(confirmPasswordError, confirmPasswordGroup);
        hideFieldError(termsError, termsGroup);
        if (parentalConsentError) {
            parentalConsentError.textContent = '';
            parentalConsentError.style.display = 'none';
        }
        hideGeneralError();
    }
    
    function setLoading(loading) {
        if (loading) {
            signupForm.classList.add('loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Creating Account...';
        } else {
            signupForm.classList.remove('loading');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
        }
    }
    
    // ========================
    // Form Submission (FormData for file upload)
    // ========================
    async function submitForm() {
        setLoading(true);
        hideGeneralError();
        hideSuccess();
        
        try {
            // Get reCAPTCHA token if available
            let recaptchaToken = null;
            if (typeof grecaptcha !== 'undefined' && typeof RECAPTCHA_SITE_KEY !== 'undefined') {
                try {
                    recaptchaToken = await new Promise((resolve) => {
                        grecaptcha.ready(async () => {
                            const token = await grecaptcha.execute(RECAPTCHA_SITE_KEY, {action: 'signup'});
                            resolve(token);
                        });
                    });
                } catch (e) {
                    console.error('reCAPTCHA error:', e);
                }
            }

            // Use FormData to support file upload
            const formData = new FormData(signupForm);
            
            // Ensure terms value is set
            formData.set('terms', termsCheckbox.checked ? '1' : '0');
            
            // Append signature pad data if minor
            if (parentalConsentGroup.style.display !== 'none' && window.getSignatureData) {
                const sigData = window.getSignatureData();
                if (sigData) {
                    formData.append('parental_signature', dataURLtoBlob(sigData), 'signature.png');
                }
            }
            
            if (recaptchaToken) {
                formData.append('recaptcha_token', recaptchaToken);
            }
            
            const response = await fetch('signup.php', {
                method: 'POST',
                body: formData
            });
            
            const responseText = await response.text();
            
            try {
                const result = JSON.parse(responseText);
                
                if (result.success) {
                    showSuccess(result.message);
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 3000);
                } else {
                    displayErrors(result.errors);
                }
            } catch (jsonError) {
                console.error('Server returned non-JSON response:', responseText.substring(0, 500));
                showGeneralError('Server error. Please check console for details.');
            }
            
        } catch (error) {
            console.error('Network error:', error);
            showGeneralError('Network error. Please check your connection and try again.');
        } finally {
            isSubmitting = false;
            setLoading(false);
        }
    }
    
    function displayErrors(errors) {
        clearAllErrors();
        
        if (errors.general) {
            showGeneralError(errors.general);
        }
        
        const errorMap = {
            firstName: { error: firstNameError, group: firstNameGroup },
            lastName: { error: lastNameError, group: lastNameGroup },
            birthdate: { error: birthdateError, group: birthdateGroup },
            phone: { error: phoneError, group: phoneGroup },
            email: { error: emailError, group: emailGroup },
            password: { error: passwordError, group: passwordGroup },
            confirmPassword: { error: confirmPasswordError, group: confirmPasswordGroup },
            terms: { error: termsError, group: termsGroup }
        };
        
        for (const [field, { error, group }] of Object.entries(errorMap)) {
            if (errors[field] && error && group) {
                showError(error, group, errors[field]);
            }
        }
        
        if (errors.parental_consent && parentalConsentError) {
            parentalConsentError.textContent = errors.parental_consent;
            parentalConsentError.style.display = 'block';
            checkParentalConsentRequirement();
        }
    }
    
    // Auto-focus on first field
    if (firstNameInput) {
        firstNameInput.focus();
    }
    
    // Set date constraints
    if (birthdateInput) {
        const today = new Date();
        const maxDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        birthdateInput.max = maxDate.toISOString().split('T')[0];
        
        const minDate = new Date(today.getFullYear() - 120, today.getMonth(), today.getDate());
        birthdateInput.min = minDate.toISOString().split('T')[0];
    }
});