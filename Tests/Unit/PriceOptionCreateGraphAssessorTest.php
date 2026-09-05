<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreateGraphAssessor;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreateBlocker.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/ExistingPriceOptionSnapshot.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreateContext.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreateLoadResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreateGraphAssessor.php';

$assessor = new PriceOptionCreateGraphAssessor();
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

function uuid(string $seed = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'): string
{
    return $seed;
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
        'starttime' => 0,
        'endtime' => 0,
        'public_uuid' => '11111111-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'tstamp' => 1700000001,
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
        'public_uuid' => '22222222-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'tstamp' => 1700000002,
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
        'starttime' => 0,
        'endtime' => 0,
        'public_uuid' => '33333333-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'tstamp' => 1700000003,
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
        'public_uuid' => '44444444-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'tstamp' => 1700000004,
        'sys_language_uid' => 0,
    ], $overrides);
}

function option(array $overrides = []): array
{
    return array_merge([
        'uid' => 40,
        'pid' => 10,
        'placement' => 30,
        'label' => 'Small',
        'amount' => 313,
        'sorting' => 256,
        'hidden' => 0,
        'public_uuid' => uuid(),
        'tstamp' => 1700000000,
        'sys_language_uid' => 0,
    ], $overrides);
}

$ok = $assessor->assess(10, 5, placement(), category(), item(), menu(), [option()]);
assertTrue($ok->outcome === 'loaded' && $ok->context !== null, 'correct pid/Menu/Category/Placement/Item => loaded');
assertTrue(
    $ok->context?->placementUid === 30
    && $ok->context?->menuUid === 5
    && $ok->context?->categoryUid === 20
    && $ok->context?->itemUid === 50
    && $ok->context?->existingPriceCount === 1
    && $ok->context?->existingPriceOptions[0]->label === 'Small',
    'context snapshot includes parent + existing PriceOption'
);

$wrongPid = $assessor->assess(10, 5, placement(['pid' => 99]), category(), item(), menu(), []);
assertTrue(($wrongPid->blockers[0]->code ?? '') === 'wrongPid', 'wrong pid => blocked');

$wrongMenu = $assessor->assess(10, 5, placement(), category(['menu' => 99]), item(), menu(['uid' => 99]), []);
assertTrue(($wrongMenu->blockers[0]->code ?? '') === 'wrongMenu', 'wrong Menu => blocked');

$selectedMismatch = $assessor->assess(10, 7, placement(), category(), item(), menu(), []);
assertTrue(($selectedMismatch->blockers[0]->code ?? '') === 'wrongMenu', 'selected Menu mismatch => blocked');

$placementOtherCategory = $assessor->assess(
    10,
    5,
    placement(['category' => 99]),
    category(),
    item(),
    menu(),
    []
);
assertTrue(($placementOtherCategory->blockers[0]->code ?? '') === 'brokenCategory', 'Placement not in Category => blocked');

$categoryOtherMenu = $assessor->assess(
    10,
    5,
    placement(),
    category(['menu' => 8]),
    item(),
    menu(),
    []
);
assertTrue(($categoryOtherMenu->blockers[0]->code ?? '') === 'wrongMenu', 'Category not in Menu => blocked');

$missingItem = $assessor->assess(10, 5, placement(), category(), null, menu(), []);
assertTrue(($missingItem->blockers[0]->code ?? '') === 'missingItem', 'missing Item => blocked');

$translatedPlacement = $assessor->assess(10, 5, placement(['sys_language_uid' => 1]), category(), item(), menu(), []);
assertTrue(($translatedPlacement->blockers[0]->code ?? '') === 'translatedPlacement', 'translated Placement => blocked');

$translatedMenu = $assessor->assess(10, 5, placement(), category(), item(), menu(['sys_language_uid' => 1]), []);
assertTrue(($translatedMenu->blockers[0]->code ?? '') === 'translatedMenu', 'translated parent Menu => blocked');

$missingPlacement = $assessor->assess(10, 5, null, category(), item(), menu(), []);
assertTrue(($missingPlacement->blockers[0]->code ?? '') === 'inaccessiblePlacement', 'deleted/missing Placement => blocked');

$missingUuid = $assessor->assess(10, 5, placement(['public_uuid' => '']), category(), item(), menu(), []);
assertTrue(($missingUuid->blockers[0]->code ?? '') === 'missingPublicUuid', 'missing required public_uuid snapshot => fail closed');

$foreignOption = $assessor->assess(10, 5, placement(), category(), item(), menu(), [
    option(),
    option(['uid' => 99, 'placement' => 88, 'public_uuid' => '55555555-bbbb-4ccc-8ddd-eeeeeeeeeeee']),
]);
assertTrue(
    $foreignOption->outcome === 'loaded'
    && $foreignOption->context?->existingPriceCount === 1
    && $foreignOption->context?->existingPriceOptions[0]->uid === 40,
    'only PriceOptions belonging to selected Placement enter context'
);

$missingOptionUuid = $assessor->assess(10, 5, placement(), category(), item(), menu(), [
    option(['public_uuid' => '']),
]);
assertTrue(($missingOptionUuid->blockers[0]->code ?? '') === 'missingPublicUuid', 'existing PriceOption missing public_uuid => fail closed');

echo $failures === 0
    ? "\nAll PriceOptionCreateGraphAssessor tests passed.\n"
    : "\n{$failures} PriceOptionCreateGraphAssessor test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
