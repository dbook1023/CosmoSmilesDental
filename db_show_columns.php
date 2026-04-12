<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SHOW COLUMNS FROM site_content");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
