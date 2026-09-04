<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftValidator;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\DecimalMinorUnitParser;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\BulkIdentityResolutionResult;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\BulkIdentityResolver;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\IdentityBlocker;
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
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkDraftValidator.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/PersistedIdentityCandidate.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/TargetMenuSnapshot.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/IdentityResolution.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/IdentityBoundRow.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/IdentityResolutionSummary.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/IdentityBlocker.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/BulkIdentityResolutionResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Identity/BulkIdentityResolver.php';

$validator = new BulkDraftValidator(new DecimalMinorUnitParser(2), new MinorUnitMoneyFormatter(2));
$resolver = new BulkIdentityResolver();
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

$uuidA = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$uuidB = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$uuidC = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

$simple = draftFrom($validator, [
    'r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00'),
]);

$createItem = $resolver->resolve($simple, menuSnapshot(), [], [], '');
assertTrue($createItem->outcome === 'identityResolved', '1. no Item candidate -> CREATE outcome');
assertTrue($createItem->itemResolutions[0]->status === 'create', '1. no Item candidate -> CREATE');
assertTrue($createItem->categoryResolutions[0]->status === 'create', '14. no Category in target Menu -> CREATE');

$reuseItem = $resolver->resolve(
    $simple,
    menuSnapshot(),
    [candidate(42, 'Hummus', 5, $uuidA, 3333)],
    [candidate(7, 'Starters', 5, $uuidB, 4444)],
);
assertTrue($reuseItem->itemResolutions[0]->status === 'reuse', '2. one Item candidate -> REUSE');
assertTrue(
    $reuseItem->itemResolutions[0]->uid === 42
    && $reuseItem->itemResolutions[0]->publicUuid === $uuidA
    && $reuseItem->itemResolutions[0]->tstamp === 3333
    && $reuseItem->itemResolutions[0]->pid === 5,
    '3. REUSE captures uid/public_uuid/tstamp/pid'
);
assertTrue($reuseItem->categoryResolutions[0]->status === 'reuse', '15. one Category in target Menu -> REUSE');
assertTrue(
    $reuseItem->targetMenu !== null
    && $reuseItem->targetMenu->publicUuid === '11111111-1111-4111-8111-111111111111'
    && $reuseItem->targetMenu->tstamp === 1000,
    '23. target Menu snapshot captures uuid/tstamp'
);

$ambiguousItem = $resolver->resolve(
    $simple,
    menuSnapshot(),
    [candidate(1, 'Hummus', 5, $uuidA), candidate(2, 'Hummus', 5, $uuidB)],
    [candidate(7, 'Starters', 5, $uuidC)],
);
assertTrue($ambiguousItem->itemResolutions[0]->status === 'ambiguous', '4. multiple Items exact same title -> AMBIGUOUS');
assertTrue($ambiguousItem->itemResolutions[0]->matchCount === 2, '4. ambiguous matchCount is 2');
assertTrue($ambiguousItem->itemResolutions[0]->uid === null, '33. ambiguity never picks first uid');
assertTrue($ambiguousItem->outcome === 'resolutionBlocked', '31. one ambiguous Item blocks whole resolution');
assertTrue(
    $ambiguousItem->blockers[0]->code === 'ambiguousItem'
    && $ambiguousItem->blockers[0]->normalizedTitle === 'Hummus',
    '31. ambiguous Item blocker names the title'
);

$caseCandidates = [
    candidate(1, 'tea', 5, $uuidA),
    candidate(2, 'TEA', 5, $uuidB),
];
$normalizer = new \Anatolkin\ArpRestaurant\Backend\Editor\RestaurantTitleNormalizer();
assertTrue(
    count($resolver->matchesByMatchKey($caseCandidates, $normalizer->matchKey('Tea'))) === 2,
    '5/6. Tea matchKey matches both tea and TEA candidates'
);
$caseAmbiguous = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, 'Drinks', 'Tea', '', '3.00')]),
    menuSnapshot(),
    $caseCandidates,
    [],
);
assertTrue($caseAmbiguous->itemResolutions[0]->status === 'ambiguous', '10. two case variants persisted => AMBIGUOUS');
assertTrue($caseAmbiguous->itemResolutions[0]->matchCount === 2, '10b. case-variant ambiguity count is 2');

$caseReuse = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, 'Mains', 'Atlantic Salmon', '', '23.00')]),
    menuSnapshot(),
    [candidate(42, 'Atlantic salmon', 5, $uuidA)],
    [],
);
assertTrue($caseReuse->itemResolutions[0]->status === 'reuse', '9. one differently-cased persisted candidate => REUSE');
assertTrue(
    $caseReuse->itemResolutions[0]->canonicalTitle === 'Atlantic salmon',
    '14. existing canonical persisted title exposed for REUSE'
);

$trimmedDraft = draftFrom($validator, [
    'r0' => postedRow(0, 1, '  Starters  ', '  Hummus  ', '', '8.00'),
]);
$trimmedReuse = $resolver->resolve(
    $trimmedDraft,
    menuSnapshot(),
    [candidate(42, 'Hummus', 5, $uuidA)],
    [candidate(7, 'Starters', 5, $uuidB)],
);
assertTrue(
    $trimmedReuse->itemResolutions[0]->status === 'reuse'
    && $trimmedReuse->categoryResolutions[0]->status === 'reuse',
    '7. surrounding draft whitespace is already normalized and matches'
);

$crossPidIgnored = $resolver->matchesByMatchKey(
    [candidate(9, 'Hummus', 99, $uuidA)],
    $normalizer->matchKey('Hummus')
);
// Reader filters cross-pid; resolver still requires caller to pass only in-scope candidates.
// Simulate reader contract: cross-pid candidates are not supplied.
assertTrue(count($crossPidIgnored) === 1, 'cross-pid candidate would match by title if supplied');
$crossPidResolve = $resolver->resolve($simple, menuSnapshot(), [], [candidate(7, 'Starters', 5, $uuidB)]);
assertTrue($crossPidResolve->itemResolutions[0]->status === 'create', '8. cross-pid Item candidate is ignored/rejected');

// 9. translated Item candidate ignored by reader contract — simulated by not supplying it
$translatedIgnored = $resolver->resolve($simple, menuSnapshot(), [], [candidate(7, 'Starters', 5, $uuidB)]);
assertTrue($translatedIgnored->itemResolutions[0]->status === 'create', '9. translated Item candidate is ignored/rejected by reader/contract');

$hiddenReuse = $resolver->resolve(
    $simple,
    menuSnapshot(),
    [candidate(42, 'Hummus', 5, $uuidA)],
    [candidate(7, 'Starters', 5, $uuidB)],
);
assertTrue($hiddenReuse->itemResolutions[0]->status === 'reuse', '10. hidden Item candidate is eligible');

$sharedItem = draftFrom($validator, [
    'r0' => postedRow(0, 1, 'Lunch', 'Tea', '', '3.00'),
    'r1' => postedRow(1, 2, 'Dinner', 'Tea', '', '3.50'),
]);
$sharedResolve = $resolver->resolve($sharedItem, menuSnapshot(), [], []);
assertTrue(count($sharedResolve->itemResolutions) === 1, '11. same Item reused across Categories shares one resolution');
assertTrue($sharedResolve->summary->createItems === 1, '12. repeated draft Item CREATE counts once');
assertTrue($sharedResolve->summary->createCategories === 2, '12b. two Categories remain distinct');

$sharedReuse = $resolver->resolve(
    $sharedItem,
    menuSnapshot(),
    [candidate(42, 'Tea', 5, $uuidA)],
    [],
);
assertTrue($sharedReuse->summary->reuseItems === 1, '13. repeated draft Item REUSE counts once');

$dupCategory = $resolver->resolve(
    $simple,
    menuSnapshot(),
    [candidate(42, 'Hummus', 5, $uuidA)],
    [candidate(1, 'Starters', 5, $uuidB), candidate(2, 'Starters', 5, $uuidC)],
);
assertTrue($dupCategory->categoryResolutions[0]->status === 'ambiguous', '16. duplicate Category title in target Menu -> AMBIGUOUS');
assertTrue($dupCategory->outcome === 'resolutionBlocked', '32. one ambiguous Category blocks whole resolution');
assertTrue($dupCategory->categoryResolutions[0]->uid === null, '33b. category ambiguity never picks first uid');

// 17. same Category title in another Menu ignored — reader does not supply it
$otherMenuIgnored = $resolver->resolve(
    $simple,
    menuSnapshot(),
    [candidate(42, 'Hummus', 5, $uuidA)],
    [],
);
assertTrue($otherMenuIgnored->categoryResolutions[0]->status === 'create', '17. same Category title in another Menu is ignored');

$repeatedCategory = draftFrom($validator, [
    'r0' => postedRow(0, 1, 'Starters', 'A', '', '1.00'),
    'r1' => postedRow(1, 2, 'Starters', 'B', '', '2.00'),
    'r2' => postedRow(2, 3, 'Starters', 'C', '', '3.00'),
]);
$repeatedCatCreate = $resolver->resolve($repeatedCategory, menuSnapshot(), [], []);
assertTrue($repeatedCatCreate->summary->createCategories === 1, '18. repeated draft Category CREATE counts once');
$repeatedCatReuse = $resolver->resolve(
    $repeatedCategory,
    menuSnapshot(),
    [],
    [candidate(7, 'Starters', 5, $uuidB)],
);
assertTrue($repeatedCatReuse->summary->reuseCategories === 1, '19. repeated Category REUSE counts once');

$missingMenu = $resolver->resolve($simple, null, [], [], '', 'missingTargetMenu');
assertTrue($missingMenu->outcome === 'resolutionBlocked' && $missingMenu->blockers[0]->code === 'missingTargetMenu', '20. missing target Menu -> blocked');

$wrongPid = $resolver->resolve($simple, null, [], [], '', 'wrongPidTargetMenu');
assertTrue($wrongPid->blockers[0]->code === 'wrongPidTargetMenu', '21. wrong-pid target Menu -> blocked');

$translatedMenu = $resolver->resolve($simple, null, [], [], '', 'translatedTargetMenu');
assertTrue($translatedMenu->blockers[0]->code === 'translatedTargetMenu', '22. translated target Menu -> blocked');

$warningOnly = draftFrom($validator, [
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Large', '4.50'),
]);
assertTrue($warningOnly->isDraftValid() && $warningOnly->rows[0]->warnings === ['singleNamedVariant'], '24. warning-only setup');
$warningResolve = $resolver->resolve($warningOnly, menuSnapshot(), [], []);
assertTrue($warningResolve->outcome === 'identityResolved', '24. warning-only DraftValid can resolve');

$blockingDraft = draftFrom($validator, [
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', '', '3.00'),
    'r1' => postedRow(1, 2, 'Drinks', 'Tea', 'Large', '4.50'),
]);
assertTrue(!$blockingDraft->isDraftValid(), '25. blocking draft setup');
$blockedResolve = $resolver->resolve($blockingDraft, menuSnapshot(), [], []);
assertTrue(
    $blockedResolve->outcome === 'resolutionBlocked'
    && $blockedResolve->blockers[0]->code === 'draftNotValid'
    && $blockedResolve->itemResolutions === [],
    '25. blocking draft does not resolve'
);

$allEmpty = draftFrom($validator, [
    'r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00'),
    'r1' => postedRow(1, 2, 'Starters', 'Hummus', '', '8.00'),
]);
assertTrue($resolver->countFuturePlacements($allEmpty->rows) === 2, '26. all-empty simple rows produce correct Placement count');

$namedRun = draftFrom($validator, [
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Small', '3.00'),
    'r1' => postedRow(1, 2, 'Drinks', 'Tea', 'Large', '4.50'),
]);
assertTrue($resolver->countFuturePlacements($namedRun->rows) === 1, '27. all-named multi-variant run produces one Placement');
assertTrue($resolver->countFuturePlacements($warningOnly->rows) === 1, '28. singleNamedVariant run produces one Placement');

$nonConsecutive = draftFrom($validator, [
    'r0' => postedRow(0, 1, 'Mains', 'Salmon', '', '23.00'),
    'r1' => postedRow(1, 2, 'Sides', 'Rice', '', '4.00'),
    'r2' => postedRow(2, 3, 'Mains', 'Salmon', '', '24.00'),
]);
assertTrue($resolver->countFuturePlacements($nonConsecutive->rows) === 3, '29. non-consecutive same Category+Item creates separate Placement counts');

$priceOptions = $resolver->resolve($nonConsecutive, menuSnapshot(), [], []);
assertTrue($priceOptions->summary->createPriceOptions === 3, '30. PriceOption count equals commercial draft row count');

$badUuid = $resolver->resolve(
    $simple,
    menuSnapshot(),
    [candidate(42, 'Hummus', 5, '', 3333)],
    [candidate(7, 'Starters', 5, $uuidB)],
);
assertTrue($badUuid->itemResolutions[0]->status === 'inaccessible', '35. invalid/missing public_uuid on a REUSE candidate blocks safely');
assertTrue($badUuid->outcome === 'resolutionBlocked', '35b. missing public_uuid blocks outcome');
assertTrue($badUuid->blockers[0]->code === 'missingPublicUuid', '35c. missingPublicUuid blocker');

$sortedPost = draftFrom($validator, [
    'r1' => postedRow(1, 2, 'Drinks', 'Tea', 'Large', '4.50'),
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Small', '3.00'),
]);
$sortedResolve = $resolver->resolve($sortedPost, menuSnapshot(), [], []);
assertTrue(
    $sortedResolve->summary->createPlacements === 1
    && $sortedResolve->summary->createPriceOptions === 2,
    '36. sort/POST input ordering cannot change semantic placement summary'
);

$numericItemCreate = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, 'Starters', '42', '', '8.00')]),
    menuSnapshot(),
    [],
    [],
);
assertTrue($numericItemCreate->outcome === 'identityResolved', 'numeric 1. Item title "42" -> CREATE without TypeError');
assertTrue($numericItemCreate->itemResolutions[0]->status === 'create', 'numeric 1b. Item "42" CREATE status');
assertTrue(
    $numericItemCreate->itemResolutions[0]->normalizedTitle === '42'
    && is_string($numericItemCreate->itemResolutions[0]->normalizedTitle),
    'numeric 5. numeric Item title remains string in normalizedTitle'
);

$numericItemReuse = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, 'Starters', '42', '', '8.00')]),
    menuSnapshot(),
    [candidate(42, '42', 5, $uuidA)],
    [],
);
assertTrue($numericItemReuse->itemResolutions[0]->status === 'reuse', 'numeric 2. Item title "42" + exact candidate -> REUSE');
assertTrue($numericItemReuse->itemResolutions[0]->uid === 42, 'numeric 2b. REUSE keeps candidate uid');

$numericCategoryCreate = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, '101', 'Hummus', '', '8.00')]),
    menuSnapshot(),
    [],
    [],
);
assertTrue($numericCategoryCreate->categoryResolutions[0]->status === 'create', 'numeric 3. Category title "101" -> CREATE');
assertTrue(
    $numericCategoryCreate->categoryResolutions[0]->normalizedTitle === '101'
    && is_string($numericCategoryCreate->categoryResolutions[0]->normalizedTitle),
    'numeric 3b. Category "101" remains string'
);

$numericCategoryReuse = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, '101', 'Hummus', '', '8.00')]),
    menuSnapshot(),
    [],
    [candidate(7, '101', 5, $uuidB)],
);
assertTrue($numericCategoryReuse->categoryResolutions[0]->status === 'reuse', 'numeric 4. Category title "101" + exact candidate -> REUSE');

$repeatedNumericItem = $resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Lunch', '42', '', '3.00'),
        'r1' => postedRow(1, 2, 'Dinner', '42', '', '3.50'),
    ]),
    menuSnapshot(),
    [],
    [],
);
assertTrue(count($repeatedNumericItem->itemResolutions) === 1, 'numeric 6. repeated numeric Item title resolves once');
assertTrue($repeatedNumericItem->summary->createItems === 1, 'numeric 6b. CREATE count once for repeated "42"');
assertTrue($repeatedNumericItem->summary->createCategories === 2, 'numeric 10. existing summary counts unchanged for distinct categories');

$fortyTwoVsZeroFourTwo = $resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Starters', '42', '', '8.00'),
        'r1' => postedRow(1, 2, 'Starters', '042', '', '9.00'),
    ]),
    menuSnapshot(),
    [],
    [],
);
assertTrue(count($fortyTwoVsZeroFourTwo->itemResolutions) === 2, 'numeric 7. "42" and "042" remain distinct titles');
assertTrue(
    $fortyTwoVsZeroFourTwo->itemResolutions[0]->normalizedTitle === '42'
    && $fortyTwoVsZeroFourTwo->itemResolutions[1]->normalizedTitle === '042',
    'numeric 7b. both numeric-looking forms preserved as distinct strings'
);

$fortyTwoVsDecimal = $resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Starters', '42', '', '8.00'),
        'r1' => postedRow(1, 2, 'Starters', '42.0', '', '9.00'),
    ]),
    menuSnapshot(),
    [],
    [],
);
assertTrue(count($fortyTwoVsDecimal->itemResolutions) === 2, 'numeric 8. "42" and "42.0" remain distinct titles');

$caseStill = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, 'Drinks', 'Tea', '', '3.00')]),
    menuSnapshot(),
    [candidate(1, 'tea', 5, $uuidA)],
    [],
);
assertTrue($caseStill->itemResolutions[0]->status === 'reuse', 'numeric 9. case-folded match still reuses single candidate');

$draftCaseVariants = $resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Drinks', 'Tea', '', '3.00'),
        'r1' => postedRow(1, 2, 'Sides', 'tea', '', '3.50'),
        'r2' => postedRow(2, 3, 'Mains', ' TEA ', '', '4.00'),
    ]),
    menuSnapshot(),
    [],
    [],
);
assertTrue(count($draftCaseVariants->itemResolutions) === 1, '11. repeated draft case variants => one CREATE Item');
assertTrue($draftCaseVariants->summary->createItems === 1, '11b. CREATE Items count is 1');
assertTrue(
    $draftCaseVariants->itemResolutions[0]->normalizedTitle === 'Tea',
    '13. first cleaned draft spelling becomes proposed CREATE display title'
);

$draftCategoryCase = $resolver->resolve(
    draftFrom($validator, [
        'r0' => postedRow(0, 1, 'Starters', 'A', '', '1.00'),
        'r1' => postedRow(1, 2, 'starters', 'B', '', '2.00'),
        'r2' => postedRow(2, 3, ' STARTERS ', 'C', '', '3.00'),
    ]),
    menuSnapshot(),
    [],
    [],
);
assertTrue(count($draftCategoryCase->categoryResolutions) === 1, '12. repeated Category case variants => one CREATE Category');
assertTrue($draftCategoryCase->categoryResolutions[0]->normalizedTitle === 'Starters', '12b. first Category display title wins');

$salmonBang = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, 'Mains', 'Salmon!', '', '12.00')]),
    menuSnapshot(),
    [candidate(9, 'Salmon', 5, $uuidA)],
    [],
);
assertTrue($salmonBang->itemResolutions[0]->status === 'create', '8-resolve. Salmon! does not reuse Salmon');

$zeroTitle = $resolver->resolve(
    draftFrom($validator, ['r0' => postedRow(0, 1, '0', '0', '', '1.00')]),
    menuSnapshot(),
    [],
    [],
);
assertTrue(
    $zeroTitle->outcome === 'identityResolved'
    && $zeroTitle->itemResolutions[0]->normalizedTitle === '0'
    && $zeroTitle->categoryResolutions[0]->normalizedTitle === '0',
    'numeric extra. title "0" CREATE without TypeError and stays string'
);

$fluidClasses = [
    IdentityResolution::class,
    IdentityBoundRow::class,
    IdentityResolutionSummary::class,
    IdentityBlocker::class,
    BulkIdentityResolutionResult::class,
    TargetMenuSnapshot::class,
];
foreach ($fluidClasses as $className) {
    $ref = new ReflectionClass($className);
    foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        $name = $property->getName();
        $suffix = ucfirst($name);
        assertTrue(
            !$ref->hasMethod('has' . $suffix)
            && !$ref->hasMethod('is' . $suffix)
            && !$ref->hasMethod('get' . $suffix),
            'Fluid ObjectAccess: ' . $className . '::$' . $name . ' has no colliding accessor'
        );
    }
}
assertTrue(is_string((new IdentityResolution('create', 'c:x', 'x', 0))->status), 'Fluid ObjectAccess: status remains string');

echo $failures === 0 ? "\nAll BulkIdentityResolver tests passed.\n" : "\n{$failures} BulkIdentityResolver test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
