<?php
echo "CWD: " . getcwd() . "\n";
echo "File: " . __FILE__ . "\n";
require_once __DIR__ . '/../src/Controllers/AdminPatientController.php';
$c = new AdminPatientController();
echo "Stats: " . json_encode($c->getPatientStatistics()) . "\n";
?>
