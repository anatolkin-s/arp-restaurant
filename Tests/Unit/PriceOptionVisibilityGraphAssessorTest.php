<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditGraphAssessor;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityGraphAssessor;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MinorUnitMoneyFormatter.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditBlocker.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditContext.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditLoadResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditGraphAssessor.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityBlocker.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityContext.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityLoadResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityGraphAssessor.php';

$assessor = new PriceOptionVisibilityGraphAssessor(
    new PriceOptionEditGraphAssessor(new MinorUnitMoneyFormatter(2)),
);
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
        'amount' => 313,
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
        'title' => 'Apply Gate 14',
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
        'title' => 'Apply Probe 14',
        'hidden' => 0,
        'sys_language_uid' => 0,
    ], $overrides);
}

function menu(array $overrides = []): array
{
    return array_merge([
        'uid' => 5,
        'pid' => 10,
        'title' => 'Dinner Menu',
        'hidden' => 0,
        'sys_language_uid' => 0,
    ], $overrides);
}

$ok = $assessor->assess(10, 5, row(), placement(), category(), item(), menu());
assertTrue($ok->outcome === 'loaded' && $ok->context !== null, '1. correct Menu graph accepted');
assertTrue(
    $ok->context?->uid === 40
    && $ok->context?->pid === 10
    && $ok->context?->hidden === false
    && $ok->context?->menuUid === 5
    && $ok->context?->placementUid === 30
    && $ok->context?->categoryUid === 20
    && $ok->context?->itemUid === 50,
    '1b. visibility context carries graph + hidden=0'
);

$hiddenOk = $assessor->assess(10, 5, row(['hidden' => 1]), placement(), category(), item(), menu());
assertTrue(
    $hiddenOk->outcome === 'loaded' && $hiddenOk->context?->hidden === true,
    '1c. hidden PriceOption on correct graph is loaded'
);

$wrongPid = $assessor->assess(10, 5, row(['pid' => 99]), placement(), category(), item(), menu());
assertTrue(
    $wrongPid->outcome === 'blocked'
    && ($wrongPid->blockers[0]->code ?? '') === 'wrongPid',
    '2. wrong pid blocked'
);

$wrongMenu = $assessor->assess(10, 5, row(), placement(), category(['menu' => 99]), item(), menu(['uid' => 99]));
assertTrue(
    $wrongMenu->outcome === 'blocked'
    && ($wrongMenu->blockers[0]->code ?? '') === 'wrongMenu',
    '3. wrong Menu blocked'
);

$wrongSelected = $assessor->assess(10, 7, row(), placement(), category(), item(), menu());
assertTrue(
    $wrongSelected->outcome === 'blocked'
    && ($wrongSelected->blockers[0]->code ?? '') === 'wrongMenu',
    '3b. selected Menu mismatch blocked'
);

$brokenPlacement = $assessor->assess(10, 5, row(), null, category(), item(), menu());
assertTrue(
    $brokenPlacement->outcome === 'blocked'
    && ($brokenPlacement->blockers[0]->code ?? '') === 'brokenPlacement',
    '4. wrong Placement relationship blocked'
);

$mismatchedPlacement = $assessor->assess(
    10,
    5,
    row(['placement' => 99]),
    placement(),
    category(),
    item(),
    menu(),
);
assertTrue(
    $mismatchedPlacement->outcome === 'blocked'
    && ($mismatchedPlacement->blockers[0]->code ?? '') === 'brokenPlacement',
    '4b. PriceOption.placement mismatch blocked'
);

$missing = $assessor->assess(10, 5, null, placement(), category(), item(), menu());
assertTrue(
    $missing->outcome === 'blocked'
    && ($missing->blockers[0]->code ?? '') === 'missingPriceOption',
    '5. deleted/missing PriceOption blocked'
);

$ambiguous = $assessor->assess(10, 5, row(['hidden' => 2]), placement(), category(), item(), menu());
assertTrue(
    $ambiguous->outcome === 'blocked'
    && ($ambiguous->blockers[0]->code ?? '') === 'ambiguousHidden',
    '6. non-binary stored hidden fails closed'
);

echo $failures === 0
    ? "\nAll PriceOptionVisibilityGraphAssessor tests passed.\n"
    : "\n{$failures} PriceOptionVisibilityGraphAssessor test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
