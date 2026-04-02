<?php if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== 'YOUR_SITE_KEY_HERE'): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
<script>
    const RECAPTCHA_SITE_KEY = "<?php echo RECAPTCHA_SITE_KEY; ?>";
</script>
<?php endif; ?>
