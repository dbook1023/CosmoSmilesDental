// profile.js
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    const toggleButtons = document.querySelectorAll('.toggle-password');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Password validation
    const newPasswordInput = document.getElementById('newPassword');
    const currentPasswordInput = document.getElementById('currentPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const passwordStrength = document.getElementById('passwordStrength');
    
    // Password requirement elements
    const reqLength = document.getElementById('req-length');
    const reqUppercase = document.getElementById('req-uppercase');
    const reqNumber = document.getElementById('req-number');
    const reqSpecial = document.getElementById('req-special');
    const reqDifferent = document.getElementById('req-different');
    
    // Error elements
    const currentPasswordError = document.getElementById('currentPasswordError');
    const newPasswordError = document.getElementById('newPasswordError');
    const confirmPasswordError = document.getElementById('confirmPasswordError');

    // Function to validate password
    function validatePassword(password) {
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };
        
        // Update requirement indicators
        if (reqLength) {
            reqLength.classList.toggle('valid', requirements.length);
            reqLength.classList.toggle('invalid', !requirements.length);
        }
        
        if (reqUppercase) {
            reqUppercase.classList.toggle('valid', requirements.uppercase);
            reqUppercase.classList.toggle('invalid', !requirements.uppercase);
        }
        
        if (reqNumber) {
            reqNumber.classList.toggle('valid', requirements.number);
            reqNumber.classList.toggle('invalid', !requirements.number);
        }
        
        if (reqSpecial) {
            reqSpecial.classList.toggle('valid', requirements.special);
            reqSpecial.classList.toggle('invalid', !requirements.special);
        }
        
        // Calculate strength
        let strength = 0;
        if (requirements.length) strength++;
        if (requirements.uppercase) strength++;
        if (requirements.number) strength++;
        if (requirements.special) strength++;
        
        // Update strength indicator
        if (passwordStrength) {
            passwordStrength.className = 'password-strength';
            
            if (password.length === 0) {
                passwordStrength.style.width = '0';
            } else if (strength <= 1) {
                passwordStrength.classList.add('weak');
                passwordStrength.style.width = '25%';
            } else if (strength === 2) {
                passwordStrength.classList.add('fair');
                passwordStrength.style.width = '50%';
            } else if (strength === 3) {
                passwordStrength.classList.add('good');
                passwordStrength.style.width = '75%';
            } else {
                passwordStrength.classList.add('strong');
                passwordStrength.style.width = '100%';
            }
        }
        
        return requirements;
    }

    // Check if new password is different from current
    function checkPasswordDifferent() {
        if (currentPasswordInput && newPasswordInput && 
            currentPasswordInput.value && newPasswordInput.value &&
            currentPasswordInput.value === newPasswordInput.value) {
            if (reqDifferent) {
                reqDifferent.classList.remove('valid');
                reqDifferent.classList.add('invalid');
            }
            return false;
        } else {
            if (reqDifferent) {
                reqDifferent.classList.remove('invalid');
                reqDifferent.classList.add('valid');
            }
            return true;
        }
    }

    // Show error function
    function showError(element, message) {
        if (element) {
            element.textContent = message;
            element.classList.add('show');
        }
    }

    // Clear error function
    function clearError(element) {
        if (element) {
            element.textContent = '';
            element.classList.remove('show');
        }
    }

    // Validate password on input
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            validatePassword(password);
            checkPasswordDifferent();
            clearError(newPasswordError);
            this.classList.remove('invalid');
        });
    }

    // Check password difference
    if (currentPasswordInput) {
        currentPasswordInput.addEventListener('input', function() {
            checkPasswordDifferent();
            clearError(currentPasswordError);
            this.classList.remove('invalid');
        });
    }

    // Validate password confirmation
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            if (newPasswordInput.value && this.value !== newPasswordInput.value) {
                showError(confirmPasswordError, "Passwords do not match");
                this.classList.add('invalid');
            } else {
                clearError(confirmPasswordError);
                this.classList.remove('invalid');
            }
        });
    }

    // Cancel button functionality for password
    const cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            const passwordForm = document.getElementById('passwordForm');
            if (passwordForm) {
                passwordForm.reset();
                
                // Reset requirement indicators
                [reqLength, reqUppercase, reqNumber, reqSpecial, reqDifferent].forEach(el => {
                    if (el) {
                        el.classList.remove('valid', 'invalid');
                    }
                });
                
                // Clear all errors
                [currentPasswordError, newPasswordError, confirmPasswordError].forEach(error => {
                    if (error) clearError(error);
                });
                
                // Remove invalid classes
                [currentPasswordInput, newPasswordInput, confirmPasswordInput].forEach(input => {
                    if (input) input.classList.remove('invalid');
                });
                
                // Reset strength indicator
                if (passwordStrength) {
                    passwordStrength.className = 'password-strength';
                    passwordStrength.style.width = '0';
                }
            }
        });
    }
    
    // Cancel button for personal info form
    const cancelPersonalBtn = document.getElementById('cancelPersonalBtn');
    if (cancelPersonalBtn) {
        cancelPersonalBtn.addEventListener('click', function() {
            window.location.reload();
        });
    }
    
    // Cancel button for address form
    const cancelAddressBtn = document.getElementById('cancelAddressBtn');
    if (cancelAddressBtn) {
        cancelAddressBtn.addEventListener('click', function() {
            window.location.reload();
        });
    }
    
    // Add scroll effect to navbar
    window.addEventListener('scroll', () => {
        const header = document.querySelector('header');
        if (window.scrollY > 100) {
            header.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
        } else {
            header.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
        }
    });
    
    // Tab switching functionality
    const tabButtons = document.querySelectorAll('.sidebar-item');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabId = button.getAttribute('data-tab');
            
            tabButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === `${tabId}-tab`) {
                    content.classList.add('active');
                }
            });
        });
    });
    
    // Image Upload Functionality
    const avatarEditBtn = document.getElementById('avatarEditBtn');
    const imageUploadModal = document.getElementById('imageUploadModal');
    const cancelUploadBtn = document.getElementById('cancelUploadBtn');
    const profileImageInput = document.getElementById('profileImageInput');
    const imagePreview = document.getElementById('imagePreview');
    const imageUploadForm = document.getElementById('imageUploadForm');
    
    if (avatarEditBtn) {
        avatarEditBtn.addEventListener('click', function() {
            imageUploadModal.classList.add('active');
        });
    }
    
    if (cancelUploadBtn) {
        cancelUploadBtn.addEventListener('click', function() {
            imageUploadModal.classList.remove('active');
            profileImageInput.value = '';
            imagePreview.innerHTML = '';
        });
    }
    
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.innerHTML = `<img src="${e.target.result}" class="preview-image" alt="Image preview">`;
                }
                reader.readAsDataURL(file);
            }
        });
    }
    
    if (imageUploadModal) {
        imageUploadModal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                profileImageInput.value = '';
                imagePreview.innerHTML = '';
            }
        });
    }
    
    // Helper function to get changed personal fields
    function getChangedPersonalFields() {
        const fields = [];
        const firstName = document.getElementById('first_name');
        const lastName = document.getElementById('last_name');
        const phone = document.getElementById('phone');
        const gender = document.getElementById('gender');
        
        const originalFirstName = firstName?.getAttribute('data-original') || '';
        const originalLastName = lastName?.getAttribute('data-original') || '';
        const originalPhone = phone?.getAttribute('data-original') || '';
        const originalGender = gender?.getAttribute('data-original') || '';
        
        // Check if fields have been changed (and they're not empty since they're required)
        if (firstName && firstName.value.trim() !== originalFirstName) {
            fields.push('first_name');
        }
        if (lastName && lastName.value.trim() !== originalLastName) {
            fields.push('last_name');
        }
        if (phone && phone.value.trim() !== originalPhone) {
            fields.push('phone');
        }
        if (gender && gender.value !== originalGender) {
            fields.push('gender');
        }
        
        return fields;
    }
    
    // Helper function to get changed address fields
    function getChangedAddressFields() {
        const fields = [];
        const addressLine1 = document.getElementById('address_line1');
        const addressLine2 = document.getElementById('address_line2');
        const city = document.getElementById('city');
        const state = document.getElementById('state');
        const postalCode = document.getElementById('postal_code');
        const country = document.getElementById('country');
        
        const originalAddressLine1 = addressLine1?.getAttribute('data-original') || '';
        const originalAddressLine2 = addressLine2?.getAttribute('data-original') || '';
        const originalCity = city?.getAttribute('data-original') || '';
        const originalState = state?.getAttribute('data-original') || '';
        const originalPostalCode = postalCode?.getAttribute('data-original') || '';
        const originalCountry = country?.getAttribute('data-original') || '';
        
        if (addressLine1 && addressLine1.value.trim() !== originalAddressLine1) {
            fields.push('address_line1');
        }
        if (addressLine2 && addressLine2.value.trim() !== originalAddressLine2) {
            fields.push('address_line2');
        }
        if (city && city.value.trim() !== originalCity) {
            fields.push('city');
        }
        if (state && state.value.trim() !== originalState) {
            fields.push('state');
        }
        if (postalCode && postalCode.value.trim() !== originalPostalCode) {
            fields.push('postal_code');
        }
        if (country && country.value.trim() !== originalCountry) {
            fields.push('country');
        }
        
        return fields;
    }
    
    // Validation functions for changed fields
    function validateChangedPersonalFields(changedFields) {
        let isValid = true;
        
        // Check for empty required fields
        const firstName = document.getElementById('first_name');
        const lastName = document.getElementById('last_name');
        const phone = document.getElementById('phone');
        const gender = document.getElementById('gender');
        
        const firstNameError = document.getElementById('firstNameError');
        const lastNameError = document.getElementById('lastNameError');
        const phoneError = document.getElementById('phoneError');
        
        // Clear previous errors
        [firstNameError, lastNameError, phoneError].forEach(error => {
            if (error) clearError(error);
        });
        
        // Validate first name if changed
        if (changedFields.includes('first_name')) {
            if (!firstName.value.trim()) {
                showError(firstNameError, 'First name cannot be empty');
                firstName.classList.add('invalid');
                isValid = false;
            }
        }
        
        // Validate last name if changed
        if (changedFields.includes('last_name')) {
            if (!lastName.value.trim()) {
                showError(lastNameError, 'Last name cannot be empty');
                lastName.classList.add('invalid');
                isValid = false;
            }
        }
        
        // Validate phone if changed
        if (changedFields.includes('phone')) {
            if (!phone.value.trim()) {
                showError(phoneError, 'Phone number cannot be empty');
                phone.classList.add('invalid');
                isValid = false;
            } else if (!/^[0-9+\-\s()]+$/.test(phone.value.trim())) {
                showError(phoneError, 'Please enter a valid phone number');
                phone.classList.add('invalid');
                isValid = false;
            }
        }
        
        // Validate gender if changed
        if (changedFields.includes('gender')) {
            if (!gender.value) {
                // This shouldn't happen with required field, but just in case
                showError(document.getElementById('genderError'), 'Please select a gender');
                isValid = false;
            }
        }
        
        return isValid;
    }
    
    function validateChangedAddressFields(changedFields) {
        let isValid = true;
        
        if (changedFields.includes('postal_code')) {
            const postalCode = document.getElementById('postal_code');
            const postalCodeError = document.getElementById('postalCodeError');
            
            clearError(postalCodeError);
            postalCode.classList.remove('invalid');
            
            if (postalCode.value.trim() !== '' && !/^[A-Za-z0-9\-\s]+$/.test(postalCode.value)) {
                showError(postalCodeError, 'Please enter a valid postal code');
                postalCode.classList.add('invalid');
                isValid = false;
            }
        }
        
        return isValid;
    }
    
    // Personal Info Confirmation Dialog
    const updatePersonalBtn = document.getElementById('updatePersonalBtn');
    const personalInfoConfirmationDialog = document.getElementById('personalInfoConfirmationDialog');
    const personalInfoDialogCancelBtn = document.getElementById('personalInfoDialogCancelBtn');
    const personalInfoDialogConfirmBtn = document.getElementById('personalInfoDialogConfirmBtn');
    const personalInfoForm = document.getElementById('personalInfoForm');
    const personalInfoDialogBody = document.getElementById('personalInfoDialogBody');
    
    if (updatePersonalBtn) {
        updatePersonalBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const changedFields = getChangedPersonalFields();
            
            if (changedFields.length === 0) {
                showMessageBox('info', 'No changes detected. Please modify the fields you want to update.');
                return;
            }
            
            if (!validateChangedPersonalFields(changedFields)) {
                return;
            }
            
            let fieldsList = changedFields.map(field => {
                const fieldNames = {
                    'first_name': 'First Name',
                    'last_name': 'Last Name',
                    'phone': 'Phone Number',
                    'gender': 'Gender'
                };
                return fieldNames[field] || field;
            }).join(', ');
            
            personalInfoDialogBody.innerHTML = `You are about to update the following fields: <strong>${fieldsList}</strong>. Are you sure?`;
            
            personalInfoConfirmationDialog.classList.add('show');
        });
    }
    
    if (personalInfoDialogCancelBtn) {
        personalInfoDialogCancelBtn.addEventListener('click', function() {
            personalInfoConfirmationDialog.classList.remove('show');
        });
    }
    
    if (personalInfoDialogConfirmBtn) {
        personalInfoDialogConfirmBtn.addEventListener('click', function() {
            personalInfoConfirmationDialog.classList.remove('show');
            personalInfoForm.submit();
        });
    }
    
    // Address Confirmation Dialog
    const updateAddressBtn = document.getElementById('updateAddressBtn');
    const addressConfirmationDialog = document.getElementById('addressConfirmationDialog');
    const addressDialogCancelBtn = document.getElementById('addressDialogCancelBtn');
    const addressDialogConfirmBtn = document.getElementById('addressDialogConfirmBtn');
    const addressForm = document.getElementById('addressForm');
    const addressDialogBody = document.getElementById('addressDialogBody');
    
    if (updateAddressBtn) {
        updateAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const changedFields = getChangedAddressFields();
            
            if (changedFields.length === 0) {
                showMessageBox('info', 'No changes detected. Please modify the fields you want to update.');
                return;
            }
            
            if (!validateChangedAddressFields(changedFields)) {
                return;
            }
            
            let fieldsList = changedFields.map(field => {
                const fieldNames = {
                    'address_line1': 'Address Line 1',
                    'address_line2': 'Address Line 2',
                    'city': 'City',
                    'state': 'State',
                    'postal_code': 'Postal Code',
                    'country': 'Country'
                };
                return fieldNames[field] || field;
            }).join(', ');
            
            addressDialogBody.innerHTML = `You are about to update the following fields: <strong>${fieldsList}</strong>. Are you sure?`;
            
            addressConfirmationDialog.classList.add('show');
        });
    }
    
    if (addressDialogCancelBtn) {
        addressDialogCancelBtn.addEventListener('click', function() {
            addressConfirmationDialog.classList.remove('show');
        });
    }
    
    if (addressDialogConfirmBtn) {
        addressDialogConfirmBtn.addEventListener('click', function() {
            addressConfirmationDialog.classList.remove('show');
            addressForm.submit();
        });
    }
    
    // Password Change Elements
    const passwordForm = document.getElementById('passwordForm');
    const updatePasswordBtn = document.getElementById('updatePasswordBtn');
    const passwordConfirmationDialog = document.getElementById('passwordConfirmationDialog');
    const dialogCancelBtn = document.getElementById('dialogCancelBtn');
    const dialogConfirmBtn = document.getElementById('dialogConfirmBtn');

    // Handle Password Form Submission
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validatePasswordForm()) {
                passwordConfirmationDialog.classList.add('show');
            }
        });
    }
    
    if (updatePasswordBtn) {
        updatePasswordBtn.addEventListener('click', function() {
            // Trigger form submission which will trigger our submit listener
            passwordForm.requestSubmit();
        });
    }
    
    if (dialogCancelBtn) {
        dialogCancelBtn.addEventListener('click', function() {
            passwordConfirmationDialog.classList.remove('show');
        });
    }
    
    if (dialogConfirmBtn) {
        dialogConfirmBtn.addEventListener('click', function() {
            passwordConfirmationDialog.classList.remove('show');
            // Use a flag to avoid infinite loop or just submit the native way
            const hiddenSubmit = document.createElement('input');
            hiddenSubmit.type = 'hidden';
            hiddenSubmit.name = 'change_password';
            hiddenSubmit.value = '1';
            passwordForm.appendChild(hiddenSubmit);
            passwordForm.submit();
        });
    }
    
    // Close dialogs when clicking outside
    [personalInfoConfirmationDialog, addressConfirmationDialog, passwordConfirmationDialog].forEach(dialog => {
        if (dialog) {
            dialog.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        }
    });
    
    // Password form validation function
    function validatePasswordForm() {
        let isValid = true;
        
        const currentPassword = document.getElementById('currentPassword');
        const newPassword = document.getElementById('newPassword');
        const confirmPassword = document.getElementById('confirmPassword');
        const currentPasswordError = document.getElementById('currentPasswordError');
        const newPasswordError = document.getElementById('newPasswordError');
        const confirmPasswordError = document.getElementById('confirmPasswordError');
        
        [currentPasswordError, newPasswordError, confirmPasswordError].forEach(error => {
            if (error) {
                error.textContent = '';
                error.classList.remove('show');
            }
        });
        
        [currentPassword, newPassword, confirmPassword].forEach(input => {
            if (input) input.classList.remove('invalid');
        });
        
        if (!currentPassword.value.trim()) {
            showError(currentPasswordError, 'Current password is required');
            currentPassword.classList.add('invalid');
            isValid = false;
        }
        
        if (!newPassword.value.trim()) {
            showError(newPasswordError, 'New password is required');
            newPassword.classList.add('invalid');
            isValid = false;
        }
        
        if (!confirmPassword.value.trim()) {
            showError(confirmPasswordError, 'Please confirm your new password');
            confirmPassword.classList.add('invalid');
            isValid = false;
        }
        
        if (isValid) {
            if (newPassword.value !== confirmPassword.value) {
                showError(confirmPasswordError, 'Passwords do not match');
                confirmPassword.classList.add('invalid');
                isValid = false;
            }
            
            const password = newPassword.value;
            const hasUpperCase = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecialChar = /[^A-Za-z0-9]/.test(password);
            
            if (password.length < 8) {
                showError(newPasswordError, 'Password must be at least 8 characters long');
                newPassword.classList.add('invalid');
                isValid = false;
            } else if (!hasUpperCase) {
                showError(newPasswordError, 'Password must contain at least one uppercase letter');
                newPassword.classList.add('invalid');
                isValid = false;
            } else if (!hasNumber) {
                showError(newPasswordError, 'Password must contain at least one number');
                newPassword.classList.add('invalid');
                isValid = false;
            } else if (!hasSpecialChar) {
                showError(newPasswordError, 'Password must contain at least one special character');
                newPassword.classList.add('invalid');
                isValid = false;
            }
            
            if (currentPassword.value === newPassword.value) {
                showError(newPasswordError, 'New password cannot be the same as current password');
                newPassword.classList.add('invalid');
                isValid = false;
            }
        }
        
        return isValid;
    }
});

// Show message box function (global)
function showMessageBox(type, message) {
    const container = document.getElementById('messageBoxContainer');
    if (!container) return;
    
    const messageId = 'message-' + Date.now();
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };
    
    const messageBox = document.createElement('div');
    messageBox.className = `message-box ${type}`;
    messageBox.id = messageId;
    messageBox.innerHTML = `
        <i class="fas ${icons[type]} message-icon"></i>
        <div class="message-content">${message}</div>
        <button class="message-close" onclick="closeMessageBox('${messageId}')">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(messageBox);
    
    setTimeout(() => {
        messageBox.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        closeMessageBox(messageId);
    }, 5000);
}

// Close message box function (global)
function closeMessageBox(id) {
    const messageBox = document.getElementById(id);
    if (messageBox) {
        messageBox.classList.remove('show');
        setTimeout(() => {
            if (messageBox.parentNode) {
                messageBox.parentNode.removeChild(messageBox);
            }
        }, 400);
    }
}