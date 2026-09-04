<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditGraphAssessor;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MinorUnitMoneyFormatter.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditBlocker.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditContext.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditLoadResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditGraphAssessor.php';

$assessor = new PriceOptionEditGraphAssessor(new MinorUnitMoneyFormatter(2));
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

function row(array $overrides = []): array
{
    return array_merge([
        'uid' => 40,
        'pid' => 10,
        'placement' => 30,
        'label' => 'Small',
        'amount' => 311,
        'sorting' => 256,
        'hidden' => 0,
        'public_uuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'tstamp' => 1700000000,
        'sys_language_uid' => 0,
    ], $overrides);
}

function placement(array $overrides = []): array
{
    return array_merge([
        'uid' => 30,
        'pid' => 10,
        'category' => 20,
        'item' => 50,
        'sorting' => 128,
        'hidden' => 0,
        'sys_language_uid' => 0,
    ], $overrides);
}

function category(array $overrides = []): array
{
    return array_merge([
        'uid' => 20,
        'pid' => 10,
        'menu' => 5,
        'title' => 'Mains',
        'sorting' => 64,
        'hidden' => 0,
        'sys_language_uid' => 0,
    ], $overrides);
}

function item(array $overrides = []): array
{
    return array_merge([
        'uid' => 50,
        'pid' => 10,
        'title' => 'Soup',
        'hidden' => 0,
        'sys_language_uid' => 0,
    ], $overrides);
}

function menu(array $overrides = []): array
{
    return array_merge([
        'uid' => 5,
        'pid' => 10,
        'title' => 'Lunch',
        'hidden' => 0,
        'sys_language_uid' => 0,
    ], $overrides);
}

$ok = $assessor->assess(10, 5, row(), placement(), category(), item(), menu());
assertTrue($ok->outcome === 'loaded' && $ok->context !== null, '1. correct pid/Menu graph -> editable context');
assertTrue(
    $ok->context?->uid === 40
    && $ok->context?->publicUuid === 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'
    && $ok->context?->amountMinor === 311
    && $ok->context?->menuUid === 5
    && $ok->context?->categoryTitle === 'Mains'
    && $ok->context?->itemTitle === 'Soup',
    '1b. context fields populated from graph'
);

$wrongMenu = $assessor->assess(10, 5, row(), placement(), category(['menu' => 99]), item(), menu(['uid' => 99]));
assertTrue(
    $wrongMenu->outcome === 'blocked'
    && ($wrongMenu->blockers[0]->code ?? '') === 'wrongMenu',
    '2. same pid but wrong Menu -> blocked'
);

$wrongSelected = $assessor->assess(10, 7, row(), placement(), category(), item(), menu());
assertTrue(
    $wrongSelected->outcome === 'blocked'
    && ($wrongSelected->blockers[0]->code ?? '') === 'wrongMenu',
    '2b. selected Menu mismatch -> blocked'
);

$wrongPid = $assessor->assess(10, 5, row(['pid' => 99]), placement(), category(), item(), menu());
assertTrue(
    $wrongPid->outcome === 'blocked'
    && ($wrongPid->blockers[0]->code ?? '') === 'wrongPid',
    '3. wrong pid -> blocked'
);

$translated = $assessor->assess(10, 5, row(['sys_language_uid' => 1]), placement(), category(), item(), menu());
assertTrue(
    $translated->outcome === 'blocked'
    && ($translated->blockers[0]->code ?? '') === 'translatedPriceOption',
    '4. translated PriceOption -> blocked'
);

$missing = $assessor->assess(10, 5, null, placement(), category(), item(), menu());
assertTrue(
    $missing->outcome === 'blocked'
    && ($missing->blockers[0]->code ?? '') === 'missingPriceOption',
    '5. missing/deleted PriceOption -> blocked'
);

$badUuid = $assessor->assess(10, 5, row(['public_uuid' => '']), placement(), category(), item(), menu());
assertTrue(
    $badUuid->outcome === 'blocked'
    && ($badUuid->blockers[0]->code ?? '') === 'missingPublicUuid',
    '6. missing public_uuid -> blocked'
);

$brokenPlacement = $assessor->assess(10, 5, row(), null, category(), item(), menu());
assertTrue(
    $brokenPlacement->outcome === 'blocked'
    && ($brokenPlacement->blockers[0]->code ?? '') === 'brokenPlacement',
    '7. broken Placement relation -> blocked'
);

$brokenCategory = $assessor->assess(10, 5, row(), placement(), null, item(), menu());
assertTrue(
    $brokenCategory->outcome === 'blocked'
    && ($brokenCategory->blockers[0]->code ?? '') === 'brokenCategory',
    '7b. broken Category relation -> blocked'
);

$brokenItem = $assessor->assess(10, 5, row(), placement(), category(), null, menu());
assertTrue(
    $brokenItem->outcome === 'blocked'
    && ($brokenItem->blockers[0]->code ?? '') === 'brokenPlacement',
    '7c. missing Item on Placement -> blocked'
);

echo $failures === 0 ? "\nAll PriceOptionEditGraphAssessor tests passed.\n" : "\n{$failures} PriceOptionEditGraphAssessor test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
