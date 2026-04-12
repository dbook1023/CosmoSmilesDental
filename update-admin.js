const fs = require('fs');

const files = [
    'admin-appointments.php',
    'admin-messages.php',
    'admin-patients.php',
    'admin-records.php',
    'admin-services.php',
    'admin-settings.php',
    'admin-staff.php'
];

files.forEach(f => {
    const p = 'public/admin/' + f;
    let c = fs.readFileSync(p, 'utf8');
    
    // Replace favicon
    c = c.replace(/<link rel="icon" type="image\/png" href="\.\.\/assets\/images\/logo1-white\.png">/g, 
        '<link rel="icon" type="image/png" href="<?php echo clean_url(\'public/assets/images/logo1-white.png\'); ?>">');
    
    // Replace css
    c = c.replace(/href="\.\.\/assets\/css\/([a-zA-Z0-9_-]+)\.css"/g, 
        'href="<?php echo clean_url(\'public/assets/css/$1.css\'); ?>"');
    
    fs.writeFileSync(p, c);
    console.log('Fixed:', f);
});
