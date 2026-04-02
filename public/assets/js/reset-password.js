// public/assets/js/reset-password.js
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetPasswordForm');
    const submitBtn = document.getElementById('submitBtn');
    const generalError = document.getElementById('generalError');
    const successMessage = document.getElementById('successMessage');
    
    // Toggle Password Visibility
    const toggles = [
        { id: 'togglePassword', inputId: 'password' },
        { id: 'toggleConfirmPassword', inputId: 'confirm_password' }
    ];
    
    toggles.forEach(t => {
        const btn = document.getElementById(t.id);
        const input = document.getElementById(t.inputId);
        if (btn && input) {
            btn.addEventListener('click', function() {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }
    });

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Strict Validation Logic
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecialChar = /[^A-Za-z0-9]/.test(password);
            
            if (password.length < 8) {
                showError('passwordError', 'Password must be at least 8 characters long');
                return;
            }
            if (!hasUpperCase || !hasLowerCase || !hasNumber || !hasSpecialChar) {
                showError('passwordError', 'Password must contain uppercase, lowercase, number, and special character');
                return;
            }
            if (password !== confirmPassword) {
                showError('confirmPasswordError', 'Passwords do not match');
                return;
            }
            
            // Clear errors
            document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
            generalError.style.display = 'none';
            
            // Loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating...';
            
            try {
                const formData = new FormData(form);
                formData.append('action', 'reset_password');
                
                const response = await fetch('controllers/PasswordResetController.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    successMessage.textContent = result.message;
                    successMessage.style.display = 'block';
                    form.style.display = 'none';
                    // Redirect to login after 3 seconds
                    setTimeout(() => {
                        window.location.href = 'client/login.php';
                    }, 3000);
                } else {
                    generalError.textContent = result.message;
                    generalError.style.display = 'block';
                }
            } catch (error) {
                generalError.textContent = 'A system error occurred. Please try again later.';
                generalError.style.display = 'block';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update Password';
            }
        });
    }

    function showError(elementId, message) {
        const el = document.getElementById(elementId);
        if (el) {
            el.textContent = message;
            el.style.display = 'block';
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});
