<?php
require 'config/database.php';
$db = (new Database())->getConnection();

echo "Starting database path updates...\n";

// 1. Update site_content (Site Content Images)
$count1 = $db->exec("UPDATE site_content SET content_value = REPLACE(content_value, '/assets/images/dynamic/', 'public/uploads/content/') WHERE content_value LIKE '%/assets/images/dynamic/%'");
$count2 = $db->exec("UPDATE site_content SET content_value = REPLACE(content_value, 'assets/images/dynamic/', 'public/uploads/content/') WHERE content_value LIKE '%assets/images/dynamic/%'");
echo "Site content updated: " . ($count1 + $count2) . " rows.\n";

// 2. Update clients (Avatars)
$count3 = $db->exec("UPDATE clients SET profile_image = REPLACE(profile_image, 'uploads/avatar/', 'public/uploads/avatar/') WHERE profile_image LIKE 'uploads/avatar/%'");
echo "Client avatars updated: " . $count3 . " rows.\n";

$count4 = $db->exec("UPDATE clients SET profile_image = CONCAT('public/uploads/avatar/', profile_image) WHERE profile_image NOT LIKE 'public/%' AND profile_image NOT LIKE 'uploads/%' AND profile_image != '' AND profile_image IS NOT NULL");
echo "Client avatars (filenames only) updated: " . $count4 . " rows.\n";

echo "Database paths updated successfully.\n";
