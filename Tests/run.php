<?php

declare(strict_types=1);

$testFile = __DIR__ . '/Unit/CopiedTranslationUuidDecisionTest.php';
passthru('php ' . escapeshellarg($testFile), $exitCode);
exit($exitCode);
