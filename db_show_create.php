<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SHOW CREATE TABLE site_content");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
