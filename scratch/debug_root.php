<?php
require_once __DIR__ . '/../config/env.php';
echo "URL_ROOT: " . URL_ROOT . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "Project Root detected as: " . getProjectRoot() . "\n";
