<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftRow;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftValidationResult;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftValidator;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkMenuParser;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkMenuRow;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\DecimalMinorUnitParser;
use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MinorUnitMoneyFormatter.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/RestaurantTitleNormalizer.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/DecimalMinorUnitParser.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkMenuRow.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkMenuPreviewSection.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkMenuParseResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkMenuParser.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkDraftRow.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkDraftValidationResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/BulkDraftValidator.php';

$validator = new BulkDraftValidator(new DecimalMinorUnitParser(2), new MinorUnitMoneyFormatter(2));
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

$trimmed = $validator->validatePosted([
    'r0' => postedRow(0, 2, '  Starters  ', '  Hummus  ', '', '8.00'),
]);
assertTrue($trimmed->isDraftValid(), 'trimmed row is draft-valid');
assertTrue($trimmed->rows[0]->category === 'Starters', 'edited Category is trimmed');
assertTrue($trimmed->rows[0]->item === 'Hummus', 'edited Item is trimmed');
assertTrue($trimmed->rows[0]->variant === '', 'blank Variant remains valid');

$collapsed = $validator->validatePosted([
    'r0' => postedRow(0, 2, '  Atlantic   salmon  ', "Tea\xC2\xA0Cup", '', '8.00'),
]);
assertTrue($collapsed->rows[0]->category === 'Atlantic salmon', 'Category collapses internal whitespace');
assertTrue($collapsed->rows[0]->item === 'Tea Cup', 'Item collapses NBSP to ordinary space');
assertTrue($collapsed->rows[0]->category !== 'atlantic salmon', 'Category capitalization is preserved');

$twentyThree = $validator->validatePosted(['r0' => postedRow(0, 1, 'Mains', 'Salmon', '', '23')]);
assertTrue($twentyThree->rows[0]->amountMinor === 2300, 'edited 23 -> 2300');

$twentyThreePoint = $validator->validatePosted(['r0' => postedRow(0, 1, 'Mains', 'Salmon', '', '23.00')]);
assertTrue($twentyThreePoint->rows[0]->amountMinor === 2300, 'edited 23.00 -> 2300');

$fourFifty = $validator->validatePosted(['r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Large', '4.50')]);
assertTrue($fourFifty->rows[0]->amountMinor === 450, 'edited 4.50 -> 450');

$badPrice = $validator->validatePosted(['r0' => postedRow(0, 1, 'Mains', 'Salmon', '', 'abc')]);
assertTrue(!$badPrice->isDraftValid() && $badPrice->rows[0]->errors === ['invalidPrice'], 'invalid Price remains blocking');

$missingCategory = $validator->validatePosted(['r0' => postedRow(0, 1, '  ', 'Hummus', '', '8.00')]);
assertTrue(in_array('missingCategory', $missingCategory->rows[0]->errors, true), 'missing Category blocking');

$missingItem = $validator->validatePosted(['r0' => postedRow(0, 1, 'Starters', '', '', '8.00')]);
assertTrue(in_array('missingItem', $missingItem->rows[0]->errors, true), 'missing Item blocking');

$emptyVariants = $validator->validatePosted([
    'r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00'),
    'r1' => postedRow(1, 2, 'Starters', 'Hummus', '', '8.00'),
]);
assertTrue($emptyVariants->isDraftValid(), 'all-empty Variant run valid');
assertTrue(
    $emptyVariants->rows[0]->originalOrder === 0 && $emptyVariants->rows[1]->originalOrder === 1,
    'duplicate simple rows remain independent'
);

$namedVariants = $validator->validatePosted([
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Small', '3.00'),
    'r1' => postedRow(1, 2, 'Drinks', 'Tea', 'Large', '4.50'),
]);
assertTrue($namedVariants->isDraftValid(), 'all-named Variant run valid');
assertTrue(
    $namedVariants->rows[0]->warnings === [] && $namedVariants->rows[1]->warnings === [],
    'two named variants have no singleNamedVariant warning'
);

$singleNamed = $validator->validatePosted(['r0' => postedRow(0, 1, 'Drinks', 'Tea2', 'Large', '4.50')]);
assertTrue($singleNamed->isDraftValid(), 'single named Variant run remains valid');
assertTrue($singleNamed->rows[0]->warnings === ['singleNamedVariant'], 'single named Variant run has warning');
assertTrue($singleNamed->invalidCount === 0, 'warnings do not increase invalidCount');
assertTrue(is_array($singleNamed->rows[0]->warnings), 'BulkDraftRow warnings remains an array/list');
assertTrue(!method_exists(BulkDraftRow::class, 'hasWarnings'), 'no hasWarnings() accessor can shadow Fluid warnings');

$itemSplit = $validator->validatePosted([
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Small', '3.00'),
    'r1' => postedRow(1, 2, 'Drinks', 'Tea2', 'Large', '4.50'),
]);
assertTrue($itemSplit->isDraftValid(), 'Item rename split remains DraftValid');
assertTrue($itemSplit->rows[0]->isValid() && $itemSplit->rows[1]->isValid(), 'Item rename split rows remain valid');
assertTrue(
    $itemSplit->rows[0]->warnings === ['singleNamedVariant']
    && $itemSplit->rows[1]->warnings === ['singleNamedVariant'],
    'Item rename splits a two-variant run into singleNamedVariant advisories'
);

$categorySplit = $validator->validatePosted([
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Small', '3.00'),
    'r1' => postedRow(1, 2, 'Bar', 'Tea', 'Large', '4.50'),
]);
assertTrue(
    $categorySplit->isDraftValid()
    && $categorySplit->rows[0]->warnings === ['singleNamedVariant']
    && $categorySplit->rows[1]->warnings === ['singleNamedVariant'],
    'Category rename equivalently splits named Variant runs'
);

$duplicateNamed = $validator->validatePosted([
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', 'Small', '3.00'),
    'r1' => postedRow(1, 2, 'Drinks', 'Tea', 'Small', '4.50'),
]);
assertTrue(!$duplicateNamed->isDraftValid(), 'duplicate named Variant in same run is blocking');
assertTrue(
    in_array('duplicateVariant', $duplicateNamed->rows[0]->errors, true)
    && in_array('duplicateVariant', $duplicateNamed->rows[1]->errors, true),
    'duplicateVariant is on both duplicate rows'
);

$duplicateTrim = $validator->validatePosted([
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', '  Small  ', '3.00'),
    'r1' => postedRow(1, 2, 'Drinks', 'Tea', 'Small', '4.50'),
]);
assertTrue(
    in_array('duplicateVariant', $duplicateTrim->rows[0]->errors, true)
    && $duplicateTrim->rows[0]->variant === 'Small',
    'duplicate Variant comparison uses trimmed values'
);

$mixed = $validator->validatePosted([
    'r0' => postedRow(0, 1, 'Drinks', 'Tea', '', '3.00'),
    'r1' => postedRow(1, 2, 'Drinks', 'Tea', 'Large', '4.50'),
]);
assertTrue(!$mixed->isDraftValid(), 'mixed empty/named Variant run blocked');
assertTrue(
    in_array('mixedVariantRun', $mixed->rows[0]->errors, true)
    && in_array('mixedVariantRun', $mixed->rows[1]->errors, true),
    'mixed Variant error is on affected rows'
);

$splitRuns = $validator->validatePosted([
    'r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00'),
    'r1' => postedRow(1, 2, 'Mains', 'Salmon', '', '23.00'),
    'r2' => postedRow(2, 3, 'Starters', 'Hummus', 'Small', '9.00'),
]);
assertTrue($splitRuns->isDraftValid(), 'non-consecutive same Category+Item creates separate runs');

$preserved = $validator->validatePosted([
    'r0' => postedRow(0, 7, 'Starters', 'Hummus', '', '8.00'),
]);
assertTrue($preserved->rows[0]->sourceLine === 7, 'sourceLine preserved');
assertTrue($preserved->rows[0]->originalOrder === 0, 'canonical originalOrder preserved');

$shuffled = $validator->validatePosted([
    'r1' => postedRow(1, 3, 'Mains', 'Salmon', '', '23.00'),
    'r0' => postedRow(0, 2, 'Starters', 'Hummus', '', '8.00'),
]);
assertTrue(
    $shuffled->rows[0]->item === 'Hummus' && $shuffled->rows[1]->item === 'Salmon',
    'submitted row array order cannot change semantic order'
);

$dupOrder = $validator->validatePosted([
    'r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00'),
    'r1' => postedRow(0, 2, 'Mains', 'Salmon', '', '23.00'),
]);
assertTrue($dupOrder->globalError === 'invalidOriginalOrder' && $dupOrder->rows === [], 'duplicate originalOrder rejected');
assertTrue(is_string($dupOrder->globalError), 'BulkDraftValidationResult globalError remains a string');
assertTrue(!method_exists(BulkDraftValidationResult::class, 'hasGlobalError'), 'no hasGlobalError() accessor can shadow Fluid globalError');

$gapOrder = $validator->validatePosted([
    'r0' => postedRow(0, 1, 'Starters', 'Hummus', '', '8.00'),
    'r1' => postedRow(2, 2, 'Mains', 'Salmon', '', '23.00'),
]);
assertTrue($gapOrder->globalError === 'invalidOriginalOrder', 'non-canonical originalOrder rejected');

$malformed = $validator->validatePosted(['r0' => ['not' => 'a-row']]);
assertTrue($malformed->globalError === 'malformedDraft', 'malformed draft keeps exact global error code');

$emptyDraft = $validator->validatePosted([]);
assertTrue($emptyDraft->globalError === 'emptyDraft', 'empty draft keeps exact global error code');

$tooMany = [];
for ($i = 0; $i < 201; ++$i) {
    $tooMany['r' . $i] = postedRow($i, $i + 1, 'Starters', 'Item' . $i, '', '1.00');
}
$limited = $validator->validatePosted($tooMany, 200, 65536);
assertTrue($limited->globalError === 'tooManyRows' && $limited->rows === [], '>200 rows rejected');

$oversize = $validator->validatePosted(
    ['r0' => postedRow(0, 1, str_repeat('a', 20), 'Hummus', '', '8.00')],
    200,
    10
);
assertTrue($oversize->globalError === 'inputTooLarge' && $oversize->rows === [], 'aggregate size limit rejected');

foreach ([$dupOrder, $malformed, $emptyDraft, $limited, $oversize] as $globalErrorResult) {
    assertTrue(
        is_string($globalErrorResult->globalError) && $globalErrorResult->globalError !== '',
        'global-error validation results contain a non-empty string error code'
    );
}
$parsed = $validator->fromParsedRows([
    new BulkMenuRow(2, 'Drinks', 'Tea', '', '3.00', 300, '3.00', []),
    new BulkMenuRow(3, 'Drinks', 'Tea', 'Large', '4.50', 450, '4.50', []),
]);
assertTrue(!$parsed->isDraftValid() && in_array('mixedVariantRun', $parsed->rows[0]->errors, true), 'fromParsedRows applies mixed Variant runs');

$parser = new BulkMenuParser(new DecimalMinorUnitParser(2), new MinorUnitMoneyFormatter(2));
$source = "Category\tItem\tVariant\tPrice\nDrinks\tTea\tSmall\t3.00\nDrinks\tTea\tLarge\t4.50\n";
$fromTsv = $parser->parse($source);
assertTrue(!$fromTsv->hasGlobalError() && count($fromTsv->rows) === 2, 'reset source TSV parses');
$editedAway = $validator->validatePosted([
    'r0' => postedRow(0, 2, 'Drinks', 'Tea', 'Small', '3.00'),
    'r1' => postedRow(1, 3, 'Drinks', 'Tea2', 'Large', '4.50'),
]);
assertTrue($editedAway->rows[1]->item === 'Tea2', 'edited Item differs from source');
$reset = $validator->fromParsedRows($fromTsv->rows);
assertTrue(
    $reset->rows[0]->item === 'Tea'
    && $reset->rows[0]->variant === 'Small'
    && $reset->rows[1]->item === 'Tea'
    && $reset->rows[1]->variant === 'Large'
    && $reset->isDraftValid(),
    'Reset-from-parsed source reconstructs original values'
);

if ($failures > 0) {
    echo "\n{$failures} failing assertion(s)\n";
    exit(1);
}

echo "\nAll BulkDraftValidator tests passed.\n";
exit(0);
