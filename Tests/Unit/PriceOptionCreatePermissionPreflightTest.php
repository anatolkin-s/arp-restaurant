<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePermissionPreflight;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreatePermissionPreflight.php';

$preflight = new PriceOptionCreatePermissionPreflight();
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
    $preflight->blocker(0, false, true, true, true, true, true) === '',
    'live + CONTENT_EDIT + PriceOption tables_modify + fields => allowed'
);

assertTrue(
    $preflight->blocker(1, false, true, true, true, true, true) === 'nonLiveWorkspace',
    'non-live blocked'
);

assertTrue(
    $preflight->blocker(0, false, false, true, true, true, true) === 'pageContentEditDenied',
    'CONTENT_EDIT denied blocked'
);

assertTrue(
    $preflight->blocker(0, false, true, false, true, true, true) === 'tablesModifyDenied',
    'PriceOption tables_modify denied blocked'
);

assertTrue(
    $preflight->blocker(0, false, true, true, false, true, true) === 'fieldModifyDenied',
    'label excluded blocked'
);

assertTrue(
    $preflight->blocker(0, false, true, true, true, false, true) === 'fieldModifyDenied',
    'amount excluded blocked'
);

assertTrue(
    $preflight->blocker(0, false, true, true, true, true, false) === 'fieldModifyDenied',
    'placement excluded blocked'
);

assertTrue(
    $preflight->blocker(0, true, false, true, false, false, false) === '',
    'admin is not blocked by CONTENT_EDIT or exclude-field flags'
);

$source = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/BackendAccessGuard.php') ?: '';
$createMethod = '';
if (preg_match(
    '/function priceOptionCreatePermissionBlocker\([\s\S]*?function canModifyField/',
    $source,
    $match
) === 1) {
    $createMethod = $match[0];
}
assertTrue($createMethod !== '', 'create permission guard extractable');
assertTrue(
    str_contains($createMethod, 'TABLE_PRICEOPTION')
    && !str_contains($createMethod, 'TABLE_MENU')
    && !str_contains($createMethod, 'TABLE_CATEGORY')
    && !str_contains($createMethod, 'TABLE_ITEM')
    && !str_contains($createMethod, 'TABLE_PLACEMENT')
    && !str_contains($createMethod, "'hidden'"),
    'no Menu/Category/Item/Placement tables_modify requirement; hidden not required'
);

echo $failures === 0
    ? "\nAll PriceOptionCreatePermissionPreflight tests passed.\n"
    : "\n{$failures} PriceOptionCreatePermissionPreflight test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
