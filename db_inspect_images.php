<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT content_value FROM site_content WHERE content_type = 'image'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
