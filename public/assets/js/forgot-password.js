// public/assets/js/forgot-password.js
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('forgotPasswordForm');
    const submitBtn = document.getElementById('submitBtn');
    const generalError = document.getElementById('generalError');
    const successMessage = document.getElementById('successMessage');
    let isSubmitting = false;
    
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (isSubmitting) return;
            
            // Reset messages
            generalError.style.display = 'none';
            successMessage.style.display = 'none';
            
            // Get form data
            const formData = new FormData(form);
            formData.append('action', 'request_reset');
            
            // Log for debugging if needed
            // Loading state
            isSubmitting = true;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';

            // Get reCAPTCHA token
            let recaptchaToken = null;
            if (typeof grecaptcha !== 'undefined' && typeof RECAPTCHA_SITE_KEY !== 'undefined') {
                try {
                    recaptchaToken = await new Promise((resolve) => {
                        grecaptcha.ready(async () => {
                            const token = await grecaptcha.execute(RECAPTCHA_SITE_KEY, {action: 'password_reset'});
                            resolve(token);
                        });
                    });
                } catch (e) {
                    console.error('reCAPTCHA error:', e);
                }
            }
            
            if (recaptchaToken) {
                formData.append('recaptcha_token', recaptchaToken);
            }
            
            try {
                const response = await fetch('controllers/PasswordResetController.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    successMessage.textContent = result.message;
                    successMessage.style.display = 'block';
                    form.reset();
                } else {
                    generalError.textContent = result.message || 'An error occurred. Please try again.';
                    generalError.style.display = 'block';
                }
            } catch (error) {
                console.error('Error:', error);
                generalError.textContent = 'A system error occurred. Please try again later.';
                generalError.style.display = 'block';
            } finally {
                isSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Reset Link';
            }
        });
    }
});
