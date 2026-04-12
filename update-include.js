const fs = require('fs');

const path = 'public/admin/includes/admin-sidebar-css.php';
let c = fs.readFileSync(path, 'utf8');

// Replace favicon
c = c.replace(/<link rel="icon" type="image\/png" href="\.\.\/assets\/images\/logo1-white\.png">/g, 
    '<link rel="icon" type="image/png" href="<?php echo clean_url(\'public/assets/images/logo1-white.png\'); ?>">');

fs.writeFileSync(path, c);
console.log('Fixed include');
