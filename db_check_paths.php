<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT profile_image FROM clients WHERE profile_image != '' LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $db->query("SELECT content_value FROM site_content WHERE content_type = 'image' LIMIT 5");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
