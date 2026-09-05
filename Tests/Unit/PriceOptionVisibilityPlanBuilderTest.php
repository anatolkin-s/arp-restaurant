<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityContext;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityPlanBuilder;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityBlocker.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityContext.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityPlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityFingerprint.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityPreparationResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityPlanBuilder.php';

$builder = new PriceOptionVisibilityPlanBuilder();
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

function visibilityContext(bool $hidden): PriceOptionVisibilityContext
{
    return new PriceOptionVisibilityContext(
        uid: 40,
        pid: 10,
        publicUuid: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        tstamp: 1700000000,
        label: 'Small',
        amountMinor: 313,
        formattedAmount: '3.13',
        hidden: $hidden,
        placementUid: 30,
        sorting: 256,
        menuUid: 5,
        menuTitle: 'Dinner Menu',
        categoryUid: 20,
        categoryTitle: 'Apply Gate 14',
        itemUid: 50,
        itemTitle: 'Apply Probe 14',
    );
}

$toHidden = $builder->prepare(visibilityContext(false), 'hidden');
assertTrue(
    $toHidden->outcome === 'visibilityUpdateReady' && $toHidden->plan !== null,
    '1. visible → hidden READY'
);
assertTrue(
    $toHidden->plan?->currentHidden === 0
    && $toHidden->plan?->requestedHidden === 1
    && $toHidden->plan?->uid === 40
    && $toHidden->plan?->pid === 10
    && $toHidden->plan?->publicUuid === 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'
    && $toHidden->plan?->tstamp === 1700000000
    && $toHidden->plan?->placementUid === 30
    && $toHidden->plan?->menuUid === 5
    && $toHidden->plan?->categoryUid === 20
    && $toHidden->plan?->itemUid === 50
    && is_string($toHidden->plan?->fingerprint)
    && preg_match('/^[0-9a-f]{64}$/', $toHidden->plan->fingerprint) === 1,
    '1b. plan snapshot fields for visible → hidden'
);

$toVisible = $builder->prepare(visibilityContext(true), 'visible');
assertTrue(
    $toVisible->outcome === 'visibilityUpdateReady'
    && $toVisible->plan?->currentHidden === 1
    && $toVisible->plan?->requestedHidden === 0,
    '2. hidden → visible READY'
);

$visibleSame = $builder->prepare(visibilityContext(false), 'visible');
assertTrue(
    $visibleSame->outcome === 'noChanges' && $visibleSame->plan === null,
    '3. visible → visible noChanges'
);

$hiddenSame = $builder->prepare(visibilityContext(true), 'hidden');
assertTrue(
    $hiddenSame->outcome === 'noChanges' && $hiddenSame->plan === null,
    '4. hidden → hidden noChanges'
);

foreach (['foo', '', '1', '0', 'true', 'false', 'Visible', 'HIDDEN', '  hidden'] as $raw) {
    $blocked = $builder->prepare(visibilityContext(false), $raw);
    assertTrue(
        $blocked->outcome === 'preparationBlocked'
        && $blocked->plan === null
        && ($blocked->blockers[0]->code ?? '') === 'malformedVisibility',
        '5. malformed "' . ($raw === '' ? '(empty)' : $raw) . '" blocked'
    );
}

echo $failures === 0
    ? "\nAll PriceOptionVisibilityPlanBuilder tests passed.\n"
    : "\n{$failures} PriceOptionVisibilityPlanBuilder test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
