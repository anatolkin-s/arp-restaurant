<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityPermissionPreflight;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityPermissionPreflight.php';

$preflight = new PriceOptionVisibilityPermissionPreflight();
$failures = 0;

function assertTrue(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "PASS  {$message}\n";
        return;
    }
    ++$failures;
    echo "FAIL  {$message}\n";
}

assertTrue(
    $preflight->blocker(0, false, true, true, true) === '',
    '1. live + content edit + table modify + hidden field OK'
);

assertTrue(
    $preflight->blocker(0, false, true, false, true) === 'tablesModifyDenied',
    '2. PriceOption table modify required'
);

assertTrue(
    $preflight->blocker(0, false, true, true, false) === 'fieldModifyDenied',
    '3. hidden exclude field denied => blocked'
);

assertTrue(
    $preflight->blocker(0, true, false, true, false) === '',
    '4. admin is not blocked by exclude-field or CONTENT_EDIT flags'
);

assertTrue(
    $preflight->blocker(1, false, true, true, true) === 'nonLiveWorkspace',
    '5. non-live workspace blocked'
);

assertTrue(
    $preflight->blocker(0, false, false, true, true) === 'pageContentEditDenied',
    '6. CONTENT_EDIT required for non-admin'
);

echo $failures === 0
    ? "\nAll PriceOptionVisibilityPermissionPreflight tests passed.\n"
    : "\n{$failures} PriceOptionVisibilityPermissionPreflight test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
