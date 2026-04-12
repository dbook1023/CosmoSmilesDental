const fs = require('fs');
const path = require('path');

// ==========================================
// ADMIN FILES - Add env.php + fix JS src
// ==========================================
const adminFiles = [
    'admin-records.php',
    'admin-services.php', 
    'admin-staff.php',
    'admin-messages.php'
];

adminFiles.forEach(f => {
    const p = path.join('public/admin', f);
    let c = fs.readFileSync(p, 'utf8');
    
    // Add env.php inclusion if missing
    if (!c.includes('config/env.php')) {
        c = c.replace(
            /require_once __DIR__ \. '\/\.\.\/\.\.\/config\/database\.php';/,
            "require_once __DIR__ . '/../../config/database.php';\nrequire_once __DIR__ . '/../../config/env.php';"
        );
    }
    
    fs.writeFileSync(p, c);
    console.log('[env.php added]', f);
});

// Fix JS src paths in specific admin files
const adminJsFixes = {
    'admin-appointments.php': [
        ['src="../assets/js/admin-appointments.js"', "src=\"<?php echo clean_url('public/assets/js/admin-appointments.js'); ?>\""]
    ],
    'admin-patients.php': [
        ['src="../assets/js/admin-patient.js"', "src=\"<?php echo clean_url('public/assets/js/admin-patient.js'); ?>\""]
    ],
    'admin-records.php': [
        // These have cache-busting query strings, need special handling
    ]
};

Object.entries(adminJsFixes).forEach(([file, replacements]) => {
    const p = path.join('public/admin', file);
    let c = fs.readFileSync(p, 'utf8');
    
    replacements.forEach(([from, to]) => {
        c = c.replace(from, to);
    });
    
    fs.writeFileSync(p, c);
    console.log('[JS fixed]', file);
});

// Special handling for admin-records.php (has ?v= cache busting)
{
    const p = 'public/admin/admin-records.php';
    let c = fs.readFileSync(p, 'utf8');
    
    c = c.replace(
        /src="\.\.\/assets\/js\/admin-records\.js\?v=<\?php echo time\(\); \?>"/g,
        "src=\"<?php echo clean_url('public/assets/js/admin-records.js'); ?>?v=<?php echo time(); ?>\""
    );
    c = c.replace(
        /src="\.\.\/assets\/js\/odontogram\.js\?v=<\?php echo time\(\); \?>"/g,
        "src=\"<?php echo clean_url('public/assets/js/odontogram.js'); ?>?v=<?php echo time(); ?>\""
    );
    
    fs.writeFileSync(p, c);
    console.log('[JS fixed] admin-records.php (with cache busting)');
}

// ==========================================
// STAFF FILES - Fix logo image paths
// ==========================================
const staffFiles = [
    'staff-appointments.php',
    'staff-dashboard.php',
    'staff-messages.php',
    'staff-patients.php',
    'staff-records.php',
    'staff-reminders.php',
    'staff-settings.php'
];

staffFiles.forEach(f => {
    const p = path.join('public/staff', f);
    let c = fs.readFileSync(p, 'utf8');
    
    // Fix logo image path
    c = c.replace(
        /src="\.\.\/assets\/images\/logo-main-white-1\.png"/g,
        "src=\"<?php echo clean_url('public/assets/images/logo-main-white-1.png'); ?>\""
    );
    
    // Fix any remaining JS src paths  
    c = c.replace(
        /src="\.\.\/assets\/js\/([a-zA-Z0-9_.-]+)\.js"/g,
        "src=\"<?php echo clean_url('public/assets/js/$1.js'); ?>\""
    );
    
    // Fix any remaining CSS href paths
    c = c.replace(
        /href="\.\.\/assets\/css\/([a-zA-Z0-9_.-]+)\.css"/g,
        "href=\"<?php echo clean_url('public/assets/css/$1.css'); ?>\""
    );

    // Fix any remaining image paths
    c = c.replace(
        /src="\.\.\/assets\/images\/([a-zA-Z0-9_.-]+\.(png|jpg|jpeg|svg|gif|webp))"/g,
        "src=\"<?php echo clean_url('public/assets/images/$1'); ?>\""
    );

    fs.writeFileSync(p, c);
    console.log('[Staff fixed]', f);
});

// ==========================================
// Check env.php in staff files  
// ==========================================
staffFiles.forEach(f => {
    const p = path.join('public/staff', f);
    let c = fs.readFileSync(p, 'utf8');
    
    if (!c.includes('config/env.php')) {
        // Try adding after database.php require
        if (c.includes("config/database.php")) {
            c = c.replace(
                /require_once __DIR__ \. '\/\.\.\/\.\.\/config\/database\.php';/,
                "require_once __DIR__ . '/../../config/database.php';\nrequire_once __DIR__ . '/../../config/env.php';"
            );
            fs.writeFileSync(p, c);
            console.log('[env.php added to staff]', f);
        } else {
            console.log('[WARNING: No database.php found to anchor env.php]', f);
        }
    }
});

// ==========================================
// PUBLIC ROOT FILES - Check remaining ones
// ==========================================
const publicFiles = ['index.php', 'about.php', 'services.php', 'contact.php', 'staff-login.php'];

publicFiles.forEach(f => {
    const p = path.join('public', f);
    if (!fs.existsSync(p)) { console.log('[SKIP - not found]', f); return; }
    let c = fs.readFileSync(p, 'utf8');
    
    // Fix favicon
    c = c.replace(
        /<link rel="icon" type="image\/png" href="assets\/images\/logo1-white\.png">/g,
        '<link rel="icon" type="image/png" href="<?php echo clean_url(\'public/assets/images/logo1-white.png\'); ?>">'
    );
    
    // Fix CSS paths like href="assets/css/..."
    c = c.replace(
        /href="assets\/css\/([a-zA-Z0-9_.-]+)\.css"/g,
        "href=\"<?php echo clean_url('public/assets/css/$1.css'); ?>\""
    );
    
    // Fix JS paths like src="assets/js/..."
    c = c.replace(
        /src="assets\/js\/([a-zA-Z0-9_.-]+)\.js"/g,
        "src=\"<?php echo clean_url('public/assets/js/$1.js'); ?>\""
    );
    
    // Fix image paths like src="assets/images/..."
    c = c.replace(
        /src="assets\/images\/([a-zA-Z0-9_.-]+\.(png|jpg|jpeg|svg|gif|webp))"/g,
        "src=\"<?php echo clean_url('public/assets/images/$1'); ?>\""
    );
    
    // Add env.php if missing
    if (!c.includes('config/env.php')) {
        if (c.includes("config/database.php")) {
            c = c.replace(
                /require_once __DIR__ \. '\/\.\.\/config\/database\.php';/,
                "require_once __DIR__ . '/../config/database.php';\nrequire_once __DIR__ . '/../config/env.php';"
            );
        }
    }
    
    fs.writeFileSync(p, c);
    console.log('[Public fixed]', f);
});

// ==========================================
// CLIENT FILES
// ==========================================
const clientFiles = ['appointments.php', 'patient-records.php', 'profile.php'];

clientFiles.forEach(f => {
    const p = path.join('public/client', f);
    if (!fs.existsSync(p)) { console.log('[SKIP - not found]', f); return; }
    let c = fs.readFileSync(p, 'utf8');
    
    // Fix relative paths from client/ depth
    c = c.replace(
        /<link rel="icon" type="image\/png" href="\.\.\/assets\/images\/logo1-white\.png">/g,
        '<link rel="icon" type="image/png" href="<?php echo clean_url(\'public/assets/images/logo1-white.png\'); ?>">'
    );
    
    c = c.replace(
        /href="\.\.\/assets\/css\/([a-zA-Z0-9_.-]+)\.css"/g,
        "href=\"<?php echo clean_url('public/assets/css/$1.css'); ?>\""
    );
    
    c = c.replace(
        /src="\.\.\/assets\/js\/([a-zA-Z0-9_.-]+)\.js"/g,
        "src=\"<?php echo clean_url('public/assets/js/$1.js'); ?>\""
    );
    
    c = c.replace(
        /src="\.\.\/assets\/images\/([a-zA-Z0-9_.-]+\.(png|jpg|jpeg|svg|gif|webp))"/g,
        "src=\"<?php echo clean_url('public/assets/images/$1'); ?>\""
    );
    
    // Add env.php if missing
    if (!c.includes('config/env.php')) {
        if (c.includes("config/database.php")) {
            c = c.replace(
                /require_once __DIR__ \. '\/\.\.\/\.\.\/config\/database\.php';/,
                "require_once __DIR__ . '/../../config/database.php';\nrequire_once __DIR__ . '/../../config/env.php';"
            );
        }
    }
    
    fs.writeFileSync(p, c);
    console.log('[Client fixed]', f);
});

console.log('\n=== ALL DONE ===');
