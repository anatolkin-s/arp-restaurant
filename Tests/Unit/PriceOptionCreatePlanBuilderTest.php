<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\DecimalMinorUnitParser;
use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\ExistingPriceOptionSnapshot;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreateContext;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePlanBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\RestaurantTitleNormalizer;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MinorUnitMoneyFormatter.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/RestaurantTitleNormalizer.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/DecimalMinorUnitParser.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreateBlocker.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/ExistingPriceOptionSnapshot.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreateContext.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreatePlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreatePreparationResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreatePlanBuilder.php';

$builder = new PriceOptionCreatePlanBuilder(
    new DecimalMinorUnitParser(2),
    new MinorUnitMoneyFormatter(2),
    new RestaurantTitleNormalizer(),
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

/**
 * @param list<ExistingPriceOptionSnapshot> $existing
 */
function createContext(array $existing = []): PriceOptionCreateContext
{
    return new PriceOptionCreateContext(
        pid: 10,
        menuUid: 5,
        menuPublicUuid: '44444444-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        menuTstamp: 10,
        menuTitle: 'Dinner Menu',
        categoryUid: 20,
        categoryPublicUuid: '22222222-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        categoryTstamp: 11,
        categoryTitle: 'Apply Gate 14',
        placementUid: 30,
        placementPublicUuid: '11111111-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        placementTstamp: 12,
        placementCategoryUid: 20,
        placementItemUid: 50,
        placementSorting: 128,
        placementHidden: 0,
        placementStarttime: 0,
        placementEndtime: 0,
        itemUid: 50,
        itemPublicUuid: '33333333-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        itemTstamp: 13,
        itemTitle: 'Apply Probe 14',
        itemHidden: 0,
        itemStarttime: 0,
        itemEndtime: 0,
        existingPriceOptions: $existing,
    );
}

function snap(string $label, int $sorting = 256, int $uid = 40): ExistingPriceOptionSnapshot
{
    return new ExistingPriceOptionSnapshot(
        uid: $uid,
        publicUuid: sprintf('aaaaaaaa-bbbb-4ccc-8ddd-%012d', $uid),
        tstamp: 1700000000,
        label: $label,
        amountMinor: 100,
        sorting: $sorting,
        hidden: 0,
    );
}

$emptyBlank = $builder->prepare(createContext(), '', '8.00');
assertTrue($emptyBlank->outcome === 'createReady' && $emptyBlank->plan !== null, 'C. empty Placement blank Variant + 8.00 => createReady');
assertTrue(
    $emptyBlank->plan?->label === ''
    && $emptyBlank->plan?->amountMinor === 800
    && $emptyBlank->plan?->formattedAmount === '8.00'
    && $emptyBlank->plan?->plannedSorting === 256,
    'C. amountMinor/formattedAmount/plannedSorting=256 for empty Placement'
);

$emptyNamed = $builder->prepare(createContext(), 'Family', '8.00');
assertTrue(
    $emptyNamed->outcome === 'createReady'
    && $emptyNamed->plan?->label === 'Family'
    && $emptyNamed->plan?->plannedSorting === 256,
    'C. empty Placement named Variant + 8.00 => createReady'
);

$namedExisting = createContext([snap('Small', 256, 40), snap('Large', 512, 41)]);
$family = $builder->prepare($namedExisting, 'Family', '7.99');
assertTrue($family->outcome === 'createReady' && $family->plan?->label === 'Family', 'D. named set Family => createReady');

$familyDisplay = $builder->prepare($namedExisting, "  Family\t", '7.99');
assertTrue(
    $familyDisplay->outcome === 'createReady'
    && $familyDisplay->plan?->label === 'Family',
    'D. family display spelling preserved after display normalization'
);

$blankNamed = $builder->prepare($namedExisting, '', '7.99');
assertTrue(($blankNamed->blockers[0]->code ?? '') === 'variantRequired', 'D. blank Variant => variantRequired');

$dupExact = $builder->prepare($namedExisting, 'Small', '7.99');
assertTrue(($dupExact->blockers[0]->code ?? '') === 'duplicateVariant', 'D. Small => duplicateVariant');

$dupCase = $builder->prepare($namedExisting, 'small', '7.99');
assertTrue(($dupCase->blockers[0]->code ?? '') === 'duplicateVariant', 'D. small => duplicateVariant');

$dupSpace = $builder->prepare($namedExisting, ' Small ', '7.99');
assertTrue(($dupSpace->blockers[0]->code ?? '') === 'duplicateVariant', 'D. " Small " => duplicateVariant');

$bang = $builder->prepare($namedExisting, 'Small!', '7.99');
assertTrue($bang->outcome === 'createReady' && $bang->plan?->label === 'Small!', 'D. Small! => allowed');

$simple = createContext([snap('', 256, 40)]);
$simpleBlank = $builder->prepare($simple, '', '9.00');
assertTrue(($simpleBlank->blockers[0]->code ?? '') === 'simplePriceMustBecomeVariantFirst', 'E. new blank against simple price => blocked');
$simpleNamed = $builder->prepare($simple, 'Large', '9.00');
assertTrue(($simpleNamed->blockers[0]->code ?? '') === 'simplePriceMustBecomeVariantFirst', 'E. new named against simple price => simplePriceMustBecomeVariantFirst');

$mixed = createContext([snap('', 256, 40), snap('Large', 512, 41)]);
$mixedAdd = $builder->prepare($mixed, 'Family', '7.99');
assertTrue(($mixedAdd->blockers[0]->code ?? '') === 'existingVariantSetInvalid', 'F. blank + named => existingVariantSetInvalid');

$twoBlanks = createContext([snap('', 256, 40), snap('  ', 512, 41)]);
$twoBlankAdd = $builder->prepare($twoBlanks, 'Family', '7.99');
assertTrue(($twoBlankAdd->blockers[0]->code ?? '') === 'existingVariantSetInvalid', 'F. multiple blanks => existingVariantSetInvalid');

$ascii255 = str_repeat('a', 255);
$ok255 = $builder->prepare(createContext(), $ascii255, '8.00');
assertTrue($ok255->outcome === 'createReady' && $ok255->plan?->label === $ascii255, 'G. 255 Unicode label accepted');

$ascii256 = str_repeat('a', 256);
$blocked256 = $builder->prepare(createContext(), $ascii256, '8.00');
assertTrue(($blocked256->blockers[0]->code ?? '') === 'labelTooLong', 'G. 256 blocked');

$twentyThree = $builder->prepare(createContext(), 'Family', '23');
assertTrue($twentyThree->plan?->amountMinor === 2300, 'G. 23 => 2300');

$fourFifty = $builder->prepare(createContext(), 'Family', '4.50');
assertTrue($fourFifty->plan?->amountMinor === 450, 'G. 4.50 => 450');

assertTrue(($builder->prepare(createContext(), 'Family', '')->blockers[0]->code ?? '') === 'missingPrice', 'G. empty price => missingPrice');
assertTrue(($builder->prepare(createContext(), 'Family', '-1')->blockers[0]->code ?? '') === 'negativePrice', 'G. negative price');
assertTrue(($builder->prepare(createContext(), 'Family', '23,00')->blockers[0]->code ?? '') === 'invalidPrice', 'G. comma decimal');
assertTrue(($builder->prepare(createContext(), 'Family', '$4.50')->blockers[0]->code ?? '') === 'invalidPrice', 'G. currency symbol');
assertTrue(($builder->prepare(createContext(), 'Family', '1.234')->blockers[0]->code ?? '') === 'tooManyDecimals', 'G. more than 2 decimal places');

$zero = $builder->prepare(createContext(), 'Family', '8.00');
assertTrue($zero->plan?->plannedSorting === 256, 'H. zero existing => 256');

$ordered = $builder->prepare(createContext([snap('Small', 256, 40), snap('Large', 512, 41)]), 'Family', '7.99');
assertTrue($ordered->plan?->plannedSorting === 768, 'H. existing sorting 256/512 => new 768');

$reversed = $builder->prepare(createContext([snap('Large', 512, 41), snap('Small', 256, 40)]), 'Family', '7.99');
assertTrue($reversed->plan?->plannedSorting === 768, 'H. ordering of fetched rows does not change result');

$overflow = $builder->prepare(createContext([snap('Small', PHP_INT_MAX, 40)]), 'Family', '7.99');
assertTrue(($overflow->blockers[0]->code ?? '') === 'sortingOverflow', 'H. overflow => sortingOverflow');

assertTrue(
    !str_contains(file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreatePlan.php') ?: '', 'public_uuid')
    || (
        !str_contains(file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreatePlanBuilder.php') ?: '', 'uniqid')
        && !str_contains(file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreatePlanBuilder.php') ?: '', 'time()')
        && !str_contains(file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceCreate/PriceOptionCreatePlanBuilder.php') ?: '', 'random_bytes')
    ),
    'plan does not mint uid/uuid/time for the future record'
);

echo $failures === 0
    ? "\nAll PriceOptionCreatePlanBuilder tests passed.\n"
    : "\n{$failures} PriceOptionCreatePlanBuilder test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
