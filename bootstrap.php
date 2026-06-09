<?php
// Bootstrap for flashcards-v2 tests
// Uses the v1 backup vendor for PHPUnit + PSR interfaces
// Loads v2 source files explicitly (must load before v1 autoloader finds v1 classes)

$v1Vendor = "/var/www/html/custom_apps/flashcards-v1-backup/vendor";
require_once $v1Vendor . "/autoload.php";

// Pre-load v2 source files in correct order (before autoloader can find v1)
$v2LibDir = "/tmp/flashcards-v2-test/lib/";
$v2TestDir = "/tmp/flashcards-v2-test/tests/";

// Load v2 SM2Algorithm first (it has different constants than v1)
require_once $v2LibDir . "Service/Algorithms/SM2Algorithm.php";
require_once $v2LibDir . "Service/SM2Service.php";
require_once $v2LibDir . "Service/CardParserService.php";
require_once $v2LibDir . "Service/CardSerializerService.php";

// Load test files  
require_once $v2TestDir . "Unit/Service/Algorithms/SM2AlgorithmTest.php";
require_once $v2TestDir . "Unit/Service/SM2ServiceTest.php";
require_once $v2TestDir . "Unit/Service/CardParserServiceTest.php";
require_once $v2TestDir . "Unit/Service/CardSerializerServiceTest.php";
require_once $v2TestDir . "Integration/ReviewPipelineTest.php";
require_once $v2TestDir . "Integration/ReviewSequenceTest.php";
