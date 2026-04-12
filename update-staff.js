const fs = require('fs');

const files = [
    'staff-appointments.php',
    'staff-dashboard.php',
    'staff-messages.php',
    'staff-patients.php',
    'staff-records.php',
    'staff-reminders.php',
    'staff-settings.php'
];

files.forEach(f => {
    const p = 'public/staff/' + f;
    let c = fs.readFileSync(p, 'utf8');
    
    // Replace favicon
    c = c.replace(/<link rel="icon" type="image\/png" href="\.\.\/assets\/images\/logo1-white\.png">/g, 
        '<link rel="icon" type="image/png" href="<?php echo clean_url(\'public/assets/images/logo1-white.png\'); ?>">');
    
    // Replace css
    c = c.replace(/href="\.\.\/assets\/css\/([a-zA-Z0-9_-]+)\.css"/g, 
        'href="<?php echo clean_url(\'public/assets/css/$1.css\'); ?>"');

    // Replace JS
    c = c.replace(/src="\.\.\/assets\/js\/([a-zA-Z0-9_-]+)\.js"/g, 
        'src="<?php echo clean_url(\'public/assets/js/$1.js\'); ?>"');
    
    // Include env.php
    if (!c.includes('config/env.php')) {
        c = c.replace(/require_once __DIR__ \. '\/\.\.\/\.\.\/config\/database\.php';/g, 
            "require_once __DIR__ . '/../../config/database.php';\r\nrequire_once __DIR__ . '/../../config/env.php';");
    }

    fs.writeFileSync(p, c);
    console.log('Fixed:', f);
});
