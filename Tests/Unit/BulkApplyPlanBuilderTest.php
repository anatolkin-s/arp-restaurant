<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyEntityReference;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyPlacementPlan;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyPlan;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyPlanBlocker;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyPlanFingerprint;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyPlanSummary;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyPriceOptionPlan;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\BulkApplyPlanBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\BulkApplyPreparationResult;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftValidator;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\DecimalMinorUnitParser;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\BulkIdentityResolver;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\IdentityBoundRow;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\IdentityResolution;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\IdentityResolutionSummary;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\PersistedIdentityCandidate;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\TargetMenuSnapshot;
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

$validator = new BulkDraftValidator(new DecimalMinorUnitParser(2), new MinorUnitMoneyFormatter(2));
$resolver = new BulkIdentityResolver();
$builder = new BulkApplyPlanBuilder();
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

function menuSnapshot(
    int $uid = 10,
    int $pid = 5,
    string $uuid = '11111111-1111-4111-8111-111111111111',
    int $tstamp = 1000,
    string $title = 'Lunch Menu',
): TargetMenuSnapshot {
    return new TargetMenuSnapshot($uid, $pid, $uuid, $tstamp, $title);
}

function candidate(
    int $uid,
    string $title,
    int $pid = 5,
    string $uuid = '22222222-2222-4222-8222-222222222222',
    int $tstamp = 2000,
): PersistedIdentityCandidate {
    return new PersistedIdentityCandidate($uid, $pid, $title, $uuid, $tstamp);
}

function draftFrom(BulkDraftValidator $validator, array $rows)
{
    return $validator->validatePosted($rows);
}

$uuidSalmon = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$uuidBad = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

// 1. simple all-empty row -> one Placement + one PriceOption
$simple = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00')]),
    menuSnapshot(),
    [],
    [],
);
$simplePlan = $builder->prepare($simple);
assertTrue($simplePlan->outcome === 'applyReady', '1/14. identityResolved -> ApplyReady');
assertTrue($simplePlan->plan !== null && count($simplePlan->plan->placements) === 1, '1. one Placement');
assertTrue(
    $simplePlan->plan !== null
    && count($simplePlan->plan->placements[0]->priceOptions) === 1
    && $simplePlan->plan->placements[0]->priceOptions[0]->label === ''
    && $simplePlan->plan->placements[0]->priceOptions[0]->amountMinor === 800,
    '1. one empty-label PriceOption amountMinor 800'
);

// 2. Tea Small + tea Large -> one Placement + two PriceOptions
$tea = $resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Small', '3.00'),
        'r1' => postedRow(1, 2, 'drinks', 'tea', 'Large', '4.50'),
    ]),
    menuSnapshot(),
    [],
    [],
);
$teaPlan = $builder->prepare($tea);
assertTrue(
    $teaPlan->plan !== null
    && count($teaPlan->plan->placements) === 1
    && count($teaPlan->plan->placements[0]->priceOptions) === 2,
    '2. Tea Small+Large -> one Placement + two PriceOptions'
);
assertTrue(
    $teaPlan->plan !== null
    && $teaPlan->plan->placements[0]->priceOptions[0]->label === 'Small'
    && $teaPlan->plan->placements[0]->priceOptions[1]->label === 'Large'
    && $teaPlan->plan->placements[0]->priceOptions[0]->amountMinor === 300
    && $teaPlan->plan->placements[0]->priceOptions[1]->amountMinor === 450,
    '10/11. Variant labels exact; amountMinor integers'
);
assertTrue(
    $teaPlan->plan !== null
    && count($teaPlan->plan->categories) === 1
    && count($teaPlan->plan->items) === 1
    && $teaPlan->plan->categories[0]->localRef === $teaPlan->plan->placements[0]->categoryLocalRef
    && $teaPlan->plan->items[0]->localRef === $teaPlan->plan->placements[0]->itemLocalRef,
    '22. Drinks/drinks and Tea/tea share normalized refs'
);

// 3. non-consecutive same logical Item -> separate Placements
$nonConsecutive = $resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Drinks', 'Tea', '', '3.00'),
        'r1' => postedRow(1, 2, 'Starters', 'Soup', '', '5.00'),
        'r2' => postedRow(2, 3, 'Drinks', 'Tea', '', '4.00'),
    ]),
    menuSnapshot(),
    [],
    [],
);
$nonConPlan = $builder->prepare($nonConsecutive);
assertTrue(
    $nonConPlan->plan !== null && count($nonConPlan->plan->placements) === 3,
    '3. non-consecutive same Item -> separate Placements'
);
assertTrue(
    $nonConPlan->plan !== null && count($nonConPlan->plan->items) === 2,
    '5. one CREATE Item shared across Placements still one Item entity'
);
assertTrue(
    $nonConPlan->plan !== null && count($nonConPlan->plan->categories) === 2,
    '6. CREATE Categories appear once each'
);

// 4. duplicate simple empty-variant rows remain separate Placements
$dupEmpty = $resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Mains', 'Steak', '', '20.00'),
        'r1' => postedRow(1, 2, 'Mains', 'Steak', '', '20.00'),
    ]),
    menuSnapshot(),
    [],
    [],
);
$dupPlan = $builder->prepare($dupEmpty);
assertTrue(
    $dupPlan->plan !== null && count($dupPlan->plan->placements) === 2,
    '4. duplicate empty-variant rows -> separate Placements'
);

// 7/8/9 sample: Atlantic Salmon REUSE
$sample = $resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Small', '3.00'),
        'r1' => postedRow(1, 2, 'drinks', 'tea', 'Large', '4.50'),
        'r2' => postedRow(2, 3, 'Starters', 'Atlantic Salmon', '', '23.00'),
    ]),
    menuSnapshot(),
    [candidate(99, 'Atlantic Salmon', 5, $uuidSalmon, 7777)],
    [],
);
$samplePlan = $builder->prepare($sample);
assertTrue($samplePlan->outcome === 'applyReady', 'sample ApplyReady');
assertTrue(
    $samplePlan->plan !== null
    && $samplePlan->plan->summary->createCategories === 2
    && $samplePlan->plan->summary->createItems === 1
    && $samplePlan->plan->summary->createPlacements === 2
    && $samplePlan->plan->summary->createPriceOptions === 3
    && $samplePlan->plan->summary->reuseCategories === 0
    && $samplePlan->plan->summary->reuseItems === 1,
    'sample Create 2/1/2/3 Reuse 0/1'
);
assertTrue(
    $samplePlan->plan !== null
    && $samplePlan->plan->items[0]->status === 'create'
    && $samplePlan->plan->items[1]->status === 'reuse'
    && $samplePlan->plan->items[1]->uid === 99
    && $samplePlan->plan->items[1]->publicUuid === $uuidSalmon
    && $samplePlan->plan->items[1]->tstamp === 7777
    && $samplePlan->plan->items[1]->pid === 5
    && $samplePlan->plan->items[1]->canonicalTitle === 'Atlantic Salmon'
    && $samplePlan->plan->items[1]->displayTitle === 'Atlantic Salmon',
    '7/9. REUSE Item preserves snapshot; not CREATE'
);

$reuseCat = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00')]),
    menuSnapshot(),
    [],
    [candidate(7, 'Starters', 5, $uuidBad, 4444)],
);
$reuseCatPlan = $builder->prepare($reuseCat);
assertTrue(
    $reuseCatPlan->plan !== null
    && $reuseCatPlan->plan->categories[0]->status === 'reuse'
    && $reuseCatPlan->plan->categories[0]->uid === 7
    && $reuseCatPlan->plan->categories[0]->publicUuid === $uuidBad
    && $reuseCatPlan->plan->categories[0]->tstamp === 4444
    && $reuseCatPlan->plan->categories[0]->pid === 5
    && $reuseCatPlan->plan->categories[0]->canonicalTitle === 'Starters'
    && $reuseCatPlan->plan->summary->createCategories === 0,
    '8/9. REUSE Category preserves snapshot; not CREATE'
);

assertTrue(
    $samplePlan->plan !== null
    && $samplePlan->plan->placements[0]->priceOptions[0]->sourceLine === 1
    && $samplePlan->plan->placements[0]->priceOptions[0]->originalOrder === 0
    && $samplePlan->plan->placements[1]->priceOptions[0]->sourceLine === 3
    && $samplePlan->plan->targetMenu->uid === 10
    && $samplePlan->plan->targetMenu->tstamp === 1000,
    '12/13. sourceLine/originalOrder and target Menu retained'
);

// 15/16 ambiguous cannot ApplyReady
$ambiguous = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, 'Starters', 'Atlantic Salmon', '', '23.00')]),
    menuSnapshot(),
    [candidate(1, 'Atlantic Salmon', 5, $uuidSalmon), candidate(2, 'Atlantic Salmon', 5, $uuidBad)],
    [],
);
$ambPlan = $builder->prepare($ambiguous);
assertTrue(
    $ambiguous->outcome === 'resolutionBlocked'
    && $ambPlan->outcome === 'preparationBlocked'
    && $ambPlan->plan === null,
    '15/16. ambiguous identity -> preparationBlocked, no plan'
);

// 17 inaccessible
$inaccessible = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00')]),
    menuSnapshot(),
    [candidate(42, 'Hummus', 5, '', 3333)],
    [],
);
$inaccPlan = $builder->prepare($inaccessible);
assertTrue(
    $inaccPlan->outcome === 'preparationBlocked' && $inaccPlan->plan === null,
    '17. inaccessible identity cannot produce ApplyReady'
);

// 18 missing bound resolution
$missingBoundIdentity = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, 'A', 'B', '', '1.00')]),
    menuSnapshot(),
    [],
    [],
);
$brokenBound = new \Anatolkin\ArpRestaurant\Backend\Editor\Identity\BulkIdentityResolutionResult(
    outcome: 'identityResolved',
    draft: $missingBoundIdentity->draft,
    targetMenu: $missingBoundIdentity->targetMenu,
    categoryResolutions: $missingBoundIdentity->categoryResolutions,
    itemResolutions: $missingBoundIdentity->itemResolutions,
    boundRows: [new IdentityBoundRow('r0', null, $missingBoundIdentity->itemResolutions[0] ?? null)],
    blockers: [],
    summary: $missingBoundIdentity->summary,
);
$missingBoundPlan = $builder->prepare($brokenBound);
assertTrue(
    $missingBoundPlan->outcome === 'preparationBlocked'
    && ($missingBoundPlan->blockers[0]->code ?? '') === 'missingBoundResolution',
    '18. missing bound resolution fail closed'
);

// 19 structural count mismatch
$mismatch = new \Anatolkin\ArpRestaurant\Backend\Editor\Identity\BulkIdentityResolutionResult(
    outcome: 'identityResolved',
    draft: $simple->draft,
    targetMenu: $simple->targetMenu,
    categoryResolutions: $simple->categoryResolutions,
    itemResolutions: $simple->itemResolutions,
    boundRows: $simple->boundRows,
    blockers: [],
    summary: new IdentityResolutionSummary(9, 9, 9, 9, 9, 9, 0, 0),
);
$mismatchPlan = $builder->prepare($mismatch);
assertTrue(
    $mismatchPlan->outcome === 'preparationBlocked'
    && ($mismatchPlan->blockers[0]->code ?? '') === 'applyPlanInvariant',
    '19. structural count mismatch fail closed'
);

// 20 warning-only draft can become ApplyReady
$warningDraft = draftFrom($validator, [
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Large', '4.50'),
]);
assertTrue(
    $warningDraft->isDraftValid()
    && $warningDraft->rows[0]->warnings === ['singleNamedVariant'],
    '20. warning-only draft is DraftValid'
);
$warningIdentity = $resolver->resolve($warningDraft, menuSnapshot(), [], []);
$warningPlan = $builder->prepare($warningIdentity);
assertTrue($warningPlan->outcome === 'applyReady', '20. warning-only draft can become ApplyReady');

// 21 numeric-looking titles remain safe strings
$numeric = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, '42', '7', '', '1.00')]),
    menuSnapshot(),
    [],
    [],
);
$numericPlan = $builder->prepare($numeric);
assertTrue(
    $numericPlan->plan !== null
    && $numericPlan->plan->categories[0]->localRef === 'c:42'
    && $numericPlan->plan->items[0]->localRef === 'i:7'
    && is_string($numericPlan->plan->categories[0]->localRef),
    '21. numeric-looking titles remain safe string refs'
);

// 23 no sku / menu_code in Apply package sources
$applyDir = dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply';
$applyBlob = '';
foreach (glob($applyDir . '/*.php') ?: [] as $file) {
    $applyBlob .= file_get_contents($file) ?: '';
}
assertTrue(
    !preg_match('/\bItem\.sku\b|\bPlacement\.menu_code\b|\bmenu_code\b|\b->sku\b/', $applyBlob)
    && !preg_match('/\buse\s+[^;]*DataHandler\b/', $applyBlob)
    && !preg_match('/\bprocess_datamap\b|\bprocess_cmdmap\b|\bexecuteStatement\b/', $applyBlob)
    && !preg_match('/\b(insert|update|delete)\s*\(/i', $applyBlob),
    '23. Apply package has no sku/menu_code/write APIs'
);

// Fingerprint tests
$fp1 = $samplePlan->plan?->fingerprint ?? '';
$fp2 = $builder->prepare($sample)->plan?->fingerprint ?? '';
assertTrue($fp1 !== '' && $fp1 === $fp2 && preg_match('/^[a-f0-9]{64}$/', $fp1) === 1, 'fp1/10. identical plan => identical lowercase 64 hex');

$fpReorderedPayload = ApplyPlanFingerprint::compute(
    $sample->targetMenu,
    $samplePlan->plan->categories,
    $samplePlan->plan->items,
    $samplePlan->plan->placements,
);
assertTrue($fpReorderedPayload === $fp1, 'fp2. recomputed fingerprint stable for same originalOrder plan');

$priceChangedDraft = draftFrom($validator, [
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Small', '3.50'),
    'r1' => postedRow(1, 2, 'drinks', 'tea', 'Large', '4.50'),
    'r2' => postedRow(2, 3, 'Starters', 'Atlantic Salmon', '', '23.00'),
]);
$priceChanged = $builder->prepare($resolver->resolve(
    $priceChangedDraft,
    menuSnapshot(),
    [candidate(99, 'Atlantic Salmon', 5, $uuidSalmon, 7777)],
    [],
));
assertTrue(($priceChanged->plan?->fingerprint ?? '') !== $fp1, 'fp3. Price change => fingerprint changes');

$variantChanged = $builder->prepare($resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Tall', '3.00'),
        'r1' => postedRow(1, 2, 'drinks', 'tea', 'Large', '4.50'),
        'r2' => postedRow(2, 3, 'Starters', 'Atlantic Salmon', '', '23.00'),
    ]),
    menuSnapshot(),
    [candidate(99, 'Atlantic Salmon', 5, $uuidSalmon, 7777)],
    [],
));
assertTrue(($variantChanged->plan?->fingerprint ?? '') !== $fp1, 'fp4. Variant change => fingerprint changes');

$menuTstamp = $builder->prepare($resolver->resolve(
    $sample->draft,
    menuSnapshot(tstamp: 9999),
    [candidate(99, 'Atlantic Salmon', 5, $uuidSalmon, 7777)],
    [],
));
assertTrue(($menuTstamp->plan?->fingerprint ?? '') !== $fp1, 'fp5. target Menu tstamp change => fingerprint changes');

$itemTstamp = $builder->prepare($resolver->resolve(
    $sample->draft,
    menuSnapshot(),
    [candidate(99, 'Atlantic Salmon', 5, $uuidSalmon, 8888)],
    [],
));
assertTrue(($itemTstamp->plan?->fingerprint ?? '') !== $fp1, 'fp6. reused Item tstamp change => fingerprint changes');

$itemUuid = $builder->prepare($resolver->resolve(
    $sample->draft,
    menuSnapshot(),
    [candidate(99, 'Atlantic Salmon', 5, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 7777)],
    [],
));
assertTrue(($itemUuid->plan?->fingerprint ?? '') !== $fp1, 'fp7. reused public_uuid change => fingerprint changes');

$titleChanged = $builder->prepare($resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Small', '3.00'),
        'r1' => postedRow(1, 2, 'drinks', 'tea', 'Large', '4.50'),
        'r2' => postedRow(2, 3, 'Starters', 'Atlantic Salmon Fillet', '', '23.00'),
    ]),
    menuSnapshot(),
    [],
    [],
));
assertTrue(($titleChanged->plan?->fingerprint ?? '') !== $fp1, 'fp8. CREATE title change => fingerprint changes');

$groupingChanged = $builder->prepare($resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Drinks', 'Tea', '', '3.00'),
        'r1' => postedRow(1, 2, 'drinks', 'tea', '', '4.50'),
        'r2' => postedRow(2, 3, 'Starters', 'Atlantic Salmon', '', '23.00'),
    ]),
    menuSnapshot(),
    [candidate(99, 'Atlantic Salmon', 5, $uuidSalmon, 7777)],
    [],
));
assertTrue(
    ($groupingChanged->plan?->summary->createPlacements ?? 0) === 3
    && ($groupingChanged->plan?->fingerprint ?? '') !== $fp1,
    'fp9. Placement grouping change => fingerprint changes'
);

// Fluid ObjectAccess guards
$dtoClasses = [
    ApplyEntityReference::class,
    ApplyPriceOptionPlan::class,
    ApplyPlacementPlan::class,
    ApplyPlanSummary::class,
    ApplyPlan::class,
    ApplyPlanBlocker::class,
    BulkApplyPreparationResult::class,
];
foreach ($dtoClasses as $className) {
    $ref = new ReflectionClass($className);
    foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        $name = $property->getName();
        $suffix = ucfirst($name);
        assertTrue(
            !$ref->hasMethod('get' . $suffix)
            && !$ref->hasMethod('is' . $suffix)
            && !$ref->hasMethod('has' . $suffix),
            'Fluid ObjectAccess: ' . $className . '::$' . $name . ' has no colliding accessor'
        );
    }
}
assertTrue(
    is_string((new ApplyPlanBlocker('applyPlanInvariant'))->code),
    'Fluid ObjectAccess: ApplyPlanBlocker::$code remains string'
);

echo $failures === 0 ? "\nAll BulkApplyPlanBuilder tests passed.\n" : "\n{$failures} BulkApplyPlanBuilder test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
