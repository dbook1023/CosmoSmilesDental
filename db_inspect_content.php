<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT page_name, content_key, content_value FROM site_content WHERE content_value LIKE '%img_%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
