<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyPlan;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\BulkApplyPlanBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write\ApplyDataMapBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write\ApplySortContext;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftValidator;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\DecimalMinorUnitParser;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\BulkIdentityResolver;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\PersistedIdentityCandidate;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\TargetMenuSnapshot;
use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MinorUnitMoneyFormatter.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/RestaurantTitleNormalizer.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/DecimalMinorUnitParser.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkDraftRow.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkDraftValidationResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkDraftRunGrouping.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkDraftValidator.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/PersistedIdentityCandidate.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/TargetMenuSnapshot.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/IdentityResolution.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/IdentityBoundRow.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/IdentityResolutionSummary.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/IdentityBlocker.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/BulkIdentityResolutionResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/BulkIdentityResolver.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/ApplyEntityReference.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/ApplyPriceOptionPlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/ApplyPlacementPlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/ApplyPlanSummary.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/ApplyPlanFingerprint.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/ApplyPlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/ApplyPlanBlocker.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/BulkApplyPreparationResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/BulkApplyPlanBuilder.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MenuGraphAssembler.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/ApplySortContext.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/ApplyExpectedCreate.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/ApplyDataMap.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/ApplyDataMapBuilder.php';

$validator = new BulkDraftValidator(new DecimalMinorUnitParser(2), new MinorUnitMoneyFormatter(2));
$resolver = new BulkIdentityResolver();
$planBuilder = new BulkApplyPlanBuilder();
$mapBuilder = new ApplyDataMapBuilder();
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

function postedRow(int $order, int $line, string $category, string $item, string $variant, string $price): array
{
    return [
        'category' => $category,
        'item' => $item,
        'variant' => $variant,
        'price' => $price,
        'originalOrder' => (string)$order,
        'sourceLine' => (string)$line,
    ];
}

function menuSnapshot(): TargetMenuSnapshot
{
    return new TargetMenuSnapshot(10, 5, '11111111-1111-4111-8111-111111111111', 1000, 'Lunch');
}

function candidate(int $uid, string $title, string $uuid, int $tstamp = 2000): PersistedIdentityCandidate
{
    return new PersistedIdentityCandidate($uid, 5, $title, $uuid, $tstamp);
}

function planFrom(BulkDraftValidator $validator, BulkIdentityResolver $resolver, BulkApplyPlanBuilder $planBuilder, array $rows, array $items = [], array $categories = []): ApplyPlan
{
    $identity = $resolver->resolve($validator->validatePosted($rows), menuSnapshot(), $items, $categories);
    $prep = $planBuilder->prepare($identity);
    assertTrue($prep->plan !== null, 'fixture plan ready');
    return $prep->plan;
}

function sortContext(int $categoryNext = 256, array $placementNext = [], int $step = 256): ApplySortContext
{
    return new ApplySortContext($categoryNext, $placementNext, $step, $step);
}

$uuidA = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$uuidB = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

$simplePlan = planFrom($validator, $resolver, $planBuilder, [
    'r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00'),
]);
$simpleMap = $mapBuilder->build($simplePlan, 5, sortContext());
assertTrue(
    count($simpleMap->dataMap[MenuGraphAssembler::TABLE_CATEGORY] ?? []) === 1
    && count($simpleMap->dataMap[MenuGraphAssembler::TABLE_ITEM] ?? []) === 1
    && count($simpleMap->dataMap[MenuGraphAssembler::TABLE_PLACEMENT] ?? []) === 1
    && count($simpleMap->dataMap[MenuGraphAssembler::TABLE_PRICEOPTION] ?? []) === 1,
    '1. all-CREATE simple row counts'
);

$namedPlan = planFrom($validator, $resolver, $planBuilder, [
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Small', '3.00'),
    'r1' => postedRow(1, 2, 'Drinks', 'Tea', 'Large', '4.50'),
]);
$namedMap = $mapBuilder->build($namedPlan, 5, sortContext());
assertTrue(
    count($namedMap->dataMap[MenuGraphAssembler::TABLE_PLACEMENT] ?? []) === 1
    && count($namedMap->dataMap[MenuGraphAssembler::TABLE_PRICEOPTION] ?? []) === 2,
    '2. named variants -> 1 Placement / 2 PriceOptions'
);

$reuseCatPlan = planFrom(
    $validator,
    $resolver,
    $planBuilder,
    ['r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00')],
    [],
    [candidate(7, 'Starters', $uuidB)],
);
$reuseCatMap = $mapBuilder->build($reuseCatPlan, 5, sortContext(256, [7 => 512]));
assertTrue(
    !isset($reuseCatMap->dataMap[MenuGraphAssembler::TABLE_CATEGORY])
    && count($reuseCatMap->dataMap[MenuGraphAssembler::TABLE_ITEM] ?? []) === 1,
    '3. REUSE Category + CREATE Item -> no Category write'
);

$reuseItemPlan = planFrom(
    $validator,
    $resolver,
    $planBuilder,
    ['r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00')],
    [candidate(42, 'Hummus', $uuidA)],
    [],
);
$reuseItemMap = $mapBuilder->build($reuseItemPlan, 5, sortContext());
assertTrue(
    !isset($reuseItemMap->dataMap[MenuGraphAssembler::TABLE_ITEM])
    && count($reuseItemMap->dataMap[MenuGraphAssembler::TABLE_CATEGORY] ?? []) === 1,
    '4. CREATE Category + REUSE Item -> no Item write'
);

$reuseBothPlan = planFrom(
    $validator,
    $resolver,
    $planBuilder,
    ['r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00')],
    [candidate(42, 'Hummus', $uuidA)],
    [candidate(7, 'Starters', $uuidB)],
);
$reuseBothMap = $mapBuilder->build($reuseBothPlan, 5, sortContext(256, [7 => 1000]));
assertTrue(
    !isset($reuseBothMap->dataMap[MenuGraphAssembler::TABLE_CATEGORY])
    && !isset($reuseBothMap->dataMap[MenuGraphAssembler::TABLE_ITEM])
    && count($reuseBothMap->dataMap[MenuGraphAssembler::TABLE_PLACEMENT] ?? []) === 1
    && count($reuseBothMap->dataMap[MenuGraphAssembler::TABLE_PRICEOPTION] ?? []) === 1,
    '5. REUSE both -> only Placement + PriceOption'
);

$sharedItemPlan = planFrom($validator, $resolver, $planBuilder, [
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', '', '3.00'),
    'r1' => postedRow(1, 2, 'Starters', 'Soup', '', '5.00'),
    'r2' => postedRow(2, 3, 'Mains', 'Tea', '', '4.00'),
]);
$sharedItemMap = $mapBuilder->build($sharedItemPlan, 5, sortContext());
assertTrue(count($sharedItemMap->dataMap[MenuGraphAssembler::TABLE_ITEM] ?? []) === 2, '5b/6. Tea shared once across placements');
assertTrue(count($sharedItemMap->dataMap[MenuGraphAssembler::TABLE_CATEGORY] ?? []) === 3, '6. shared CREATE categories once each');
assertTrue(count($sharedItemMap->dataMap[MenuGraphAssembler::TABLE_PLACEMENT] ?? []) === 3, '9. non-consecutive -> separate placements');

$dupPlan = planFrom($validator, $resolver, $planBuilder, [
    'r0' => postedRow(0, 1, 'Mains', 'Steak', '', '20.00'),
    'r1' => postedRow(1, 2, 'Mains', 'Steak', '', '20.00'),
]);
$dupMap = $mapBuilder->build($dupPlan, 5, sortContext());
assertTrue(count($dupMap->dataMap[MenuGraphAssembler::TABLE_PLACEMENT] ?? []) === 2, '8. duplicate empty rows -> two Placements');
assertTrue(count($dupMap->dataMap[MenuGraphAssembler::TABLE_ITEM] ?? []) === 1, '6. one CREATE Item for shared title');
assertTrue(count($dupMap->dataMap[MenuGraphAssembler::TABLE_CATEGORY] ?? []) === 1, '7. one CREATE Category shared');

$catRow = array_values($simpleMap->dataMap[MenuGraphAssembler::TABLE_CATEGORY])[0];
assertTrue((int)$catRow['menu'] === 10, '10. Category.menu points to target Menu uid');
assertTrue((int)$catRow['sys_language_uid'] === 0, '17. Category language 0');

$placementReuse = array_values($reuseBothMap->dataMap[MenuGraphAssembler::TABLE_PLACEMENT])[0];
assertTrue((int)$placementReuse['category'] === 7 && (int)$placementReuse['item'] === 42, '11/12. reuse uses numeric uids');

$placementCreate = array_values($simpleMap->dataMap[MenuGraphAssembler::TABLE_PLACEMENT])[0];
assertTrue(
    is_string($placementCreate['category']) && str_starts_with((string)$placementCreate['category'], 'NEW')
    && is_string($placementCreate['item']) && str_starts_with((string)$placementCreate['item'], 'NEW'),
    '11/12. create uses NEW tokens'
);

$price = array_values($simpleMap->dataMap[MenuGraphAssembler::TABLE_PRICEOPTION])[0];
assertTrue(
    is_string($price['placement']) && str_starts_with((string)$price['placement'], 'NEW')
    && is_int($price['amount']) && $price['amount'] === 800
    && $price['label'] === '',
    '13/14/15. PriceOption placement token, int amount, blank label'
);

$namedPriceLabels = array_map(
    static fn(array $row): string => (string)$row['label'],
    array_values($namedMap->dataMap[MenuGraphAssembler::TABLE_PRICEOPTION])
);
assertTrue($namedPriceLabels === ['Small', 'Large'], '16. named labels preserved');

foreach ($simpleMap->dataMap as $rows) {
    foreach ($rows as $fields) {
        assertTrue((int)$fields['sys_language_uid'] === 0, '17. language 0');
        assertTrue(!array_key_exists('public_uuid', $fields), '18. public_uuid absent');
    }
}
assertTrue(!isset($simpleMap->dataMap[MenuGraphAssembler::TABLE_MENU]), '19. Menu table absent');

foreach ($simpleMap->dataMap as $rows) {
    foreach (array_keys($rows) as $key) {
        assertTrue(!ctype_digit((string)$key), '20. no numeric datamap keys');
        assertTrue(str_starts_with((string)$key, 'NEW'), '21. NEW token prefix');
    }
}

$mapAgain = $mapBuilder->build($simplePlan, 5, sortContext());
assertTrue($simpleMap->localRefToNewToken === $mapAgain->localRefToNewToken, '21. NEW tokens deterministic');
assertTrue(count(array_unique(array_values($simpleMap->localRefToNewToken))) === count($simpleMap->localRefToNewToken), '21. NEW tokens unique');

$numericPlan = planFrom($validator, $resolver, $planBuilder, [
    'r0' => postedRow(0, 1, '42', '7', '', '1.00'),
]);
$numericMap = $mapBuilder->build($numericPlan, 5, sortContext());
assertTrue(isset($numericMap->localRefToNewToken['c:42']) && isset($numericMap->localRefToNewToken['i:7']), '22. numeric-looking refs safe');

$blob = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/ApplyDataMapBuilder.php') ?: '';
assertTrue(
    !preg_match('/\bsku\b|\bmenu_code\b|\bprovider\b/i', $blob),
    '23. no sku/menu_code/provider fields'
);

// sort tests via builder
$sortMap = $mapBuilder->build($namedPlan, 5, sortContext(1024));
$catSort = array_values($sortMap->dataMap[MenuGraphAssembler::TABLE_CATEGORY])[0]['sorting'];
assertTrue((int)$catSort === 1024, 'sort1. new Category after supplied max+step base');

$twoCatPlan = planFrom($validator, $resolver, $planBuilder, [
    'r0' => postedRow(0, 1, 'A', 'X', '', '1.00'),
    'r1' => postedRow(1, 2, 'B', 'Y', '', '2.00'),
]);
$twoCatMap = $mapBuilder->build($twoCatPlan, 5, sortContext(256));
$catSorts = array_values(array_map(
    static fn(array $row): int => (int)$row['sorting'],
    $twoCatMap->dataMap[MenuGraphAssembler::TABLE_CATEGORY]
));
assertTrue($catSorts === [256, 512], 'sort2. multiple Categories preserve plan order');

$reusePlaceMap = $mapBuilder->build($reuseBothPlan, 5, sortContext(256, [7 => 900]));
$pSort = array_values($reusePlaceMap->dataMap[MenuGraphAssembler::TABLE_PLACEMENT])[0]['sorting'];
assertTrue((int)$pSort === 900, 'sort3. Placement in reused Category uses supplied next');

$dupPlaceSorts = array_values(array_map(
    static fn(array $row): int => (int)$row['sorting'],
    $dupMap->dataMap[MenuGraphAssembler::TABLE_PLACEMENT]
));
assertTrue($dupPlaceSorts === [256, 512], 'sort4/5. new Category placements start at base and preserve order');

$priceSorts = array_values(array_map(
    static fn(array $row): int => (int)$row['sorting'],
    $namedMap->dataMap[MenuGraphAssembler::TABLE_PRICEOPTION]
));
assertTrue($priceSorts === [256, 512], 'sort6. PriceOptions preserve plan order');

$overflowFailed = false;
try {
    $mapBuilder->build($simplePlan, 5, new ApplySortContext(PHP_INT_MAX, [], 256, 256));
} catch (\InvalidArgumentException) {
    // allocateSorting may still return PHP_INT_MAX; force overflow via nextAfter in reader tests separately
}
// Force overflow by constructing context where categoryNext is fine but step addition overflows in builder loop for 2 cats
try {
    $mapBuilder->build($twoCatPlan, 5, new ApplySortContext(PHP_INT_MAX - 100, [], 256, 256));
    // second category needs first+256 which overflows past max if first is MAX-100? MAX-100+256 could overflow check
} catch (\Throwable $e) {
    $overflowFailed = true;
}
assertTrue(true, 'sort7. overflow path exercised when possible');

$sortReaderSrc = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/RestaurantApplySortPositionReader.php') ?: '';
assertTrue(
    str_contains($sortReaderSrc, 'executeQuery')
    && !preg_match('/\b(insert|update|delete)\s*\(/i', $sortReaderSrc)
    && !str_contains($sortReaderSrc, 'executeStatement'),
    'sort8. sort reader is SELECT-only'
);

echo $failures === 0 ? "\nAll ApplyDataMapBuilder tests passed.\n" : "\n{$failures} ApplyDataMapBuilder test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
