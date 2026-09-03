<?php

declare(strict_types=1);

$failures = 0;
foreach (glob(__DIR__ . '/Unit/*Test.php') ?: [] as $testFile) {
    passthru('php ' . escapeshellarg($testFile), $exitCode);
    if ($exitCode !== 0) {
        $failures++;
    }
}

exit($failures > 0 ? 1 : 0);
