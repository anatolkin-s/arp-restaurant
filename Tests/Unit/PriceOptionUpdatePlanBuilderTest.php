<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\DecimalMinorUnitParser;
use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditContext;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdatePlanBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\RestaurantTitleNormalizer;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MinorUnitMoneyFormatter.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/RestaurantTitleNormalizer.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Bulk/DecimalMinorUnitParser.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditBlocker.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditContext.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdateValues.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdateFingerprint.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdatePlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdatePreparationResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdatePlanBuilder.php';

$builder = new PriceOptionUpdatePlanBuilder(
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

function context(string $label = 'Small', int $amountMinor = 311): PriceOptionEditContext
{
    return new PriceOptionEditContext(
        uid: 40,
        pid: 10,
        publicUuid: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        tstamp: 1700000000,
        label: $label,
        amountMinor: $amountMinor,
        formattedAmount: (new MinorUnitMoneyFormatter(2))->format($amountMinor),
        placementUid: 30,
        sorting: 256,
        menuUid: 5,
        menuTitle: 'Lunch',
        categoryUid: 20,
        categoryTitle: 'Mains',
        itemUid: 50,
        itemTitle: 'Soup',
    );
}

$labelOnly = $builder->prepare(context(), 'Medium', '3.11');
assertTrue($labelOnly->outcome === 'updateReady' && $labelOnly->plan !== null, '9. label-only change -> updateReady');
assertTrue($labelOnly->plan?->after->label === 'Medium' && $labelOnly->plan?->after->amountMinor === 311, '9b. after label changed');

$amountOnly = $builder->prepare(context(), 'Small', '3.25');
assertTrue($amountOnly->outcome === 'updateReady' && $amountOnly->plan !== null, '10. amount-only change -> updateReady');
assertTrue($amountOnly->plan?->after->amountMinor === 325, '10b. after amount 325');

$both = $builder->prepare(context(), 'Large', '4.50');
assertTrue($both->outcome === 'updateReady' && $both->plan !== null, '11. both change -> updateReady');
assertTrue($both->plan?->after->label === 'Large' && $both->plan?->after->amountMinor === 450, '11b. both after values');

$blank = $builder->prepare(context('Small', 311), '', '3.11');
assertTrue($blank->outcome === 'updateReady' && $blank->plan?->after->label === '', '12. blank label allowed');

$ascii255 = str_repeat('a', 255);
$ok255 = $builder->prepare(context('Small', 311), $ascii255, '3.25');
assertTrue($ok255->outcome === 'updateReady' && $ok255->plan?->after->label === $ascii255, '12b. 255 ASCII chars -> allowed');

$ascii256 = str_repeat('a', 256);
$blocked256 = $builder->prepare(context('Small', 311), $ascii256, '3.25');
assertTrue(
    $blocked256->outcome === 'preparationBlocked'
    && ($blocked256->blockers[0]->code ?? '') === 'labelTooLong',
    '12c. 256 ASCII chars -> preparationBlocked / labelTooLong'
);

$unicode255 = str_repeat('é', 255);
$okUnicode255 = $builder->prepare(context('Small', 311), $unicode255, '3.25');
assertTrue(
    $okUnicode255->outcome === 'updateReady'
    && $okUnicode255->plan?->after->label === $unicode255,
    '12d. 255 Unicode characters -> allowed'
);

$unicode256 = str_repeat('é', 256);
$blockedUnicode256 = $builder->prepare(context('Small', 311), $unicode256, '3.25');
assertTrue(
    $blockedUnicode256->outcome === 'preparationBlocked'
    && ($blockedUnicode256->blockers[0]->code ?? '') === 'labelTooLong',
    '12e. 256 Unicode characters -> blocked'
);

$padded255 = str_repeat('a', 255) . '   ';
$normalizedPad = $builder->prepare(context('Small', 311), $padded255, '3.25');
assertTrue(
    $normalizedPad->outcome === 'updateReady'
    && $normalizedPad->plan?->after->label === str_repeat('a', 255),
    '12f. whitespace normalization happens before length comparison'
);

$twentyThree = $builder->prepare(context('Small', 100), 'Small', '23');
assertTrue($twentyThree->plan?->after->amountMinor === 2300, '13. 23 -> 2300');

$fourFifty = $builder->prepare(context('Small', 100), 'Small', '4.50');
assertTrue($fourFifty->plan?->after->amountMinor === 450, '14. 4.50 -> 450');

foreach (
    [
        ['23,00', 'invalidPrice'],
        ['-1', 'negativePrice'],
        ['4.501', 'tooManyDecimals'],
        ['', 'missingPrice'],
        ['$4.50', 'invalidPrice'],
    ] as [$raw, $code]
) {
    $blocked = $builder->prepare(context(), 'Small', $raw);
    assertTrue(
        $blocked->outcome === 'preparationBlocked'
        && ($blocked->blockers[0]->code ?? '') === $code,
        "15. " . ($raw === '' ? '(empty)' : $raw) . " blocked as {$code}"
    );
}

$noChange = $builder->prepare(context('Small', 311), '  Small  ', '3.11');
assertTrue($noChange->outcome === 'noChanges' && $noChange->plan === null, '16. identical normalized values -> noChanges');

$preserve = $builder->prepare(context('Small', 311), 'Medium', '3.25');
assertTrue(
    $preserve->plan?->before->label === 'Small'
    && $preserve->plan?->before->amountMinor === 311
    && $preserve->plan?->before->formattedAmount === '3.11',
    '17. before snapshot preserved'
);
assertTrue(
    $preserve->plan?->after->label === 'Medium'
    && $preserve->plan?->after->amountMinor === 325
    && $preserve->plan?->after->formattedAmount === '3.25',
    '18. after values normalized'
);
assertTrue(
    $preserve->plan?->uid === 40
    && $preserve->plan?->pid === 10
    && $preserve->plan?->publicUuid === 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'
    && $preserve->plan?->tstamp === 1700000000
    && $preserve->plan?->placementUid === 30
    && $preserve->plan?->menuUid === 5
    && $preserve->plan?->categoryUid === 20
    && $preserve->plan?->itemUid === 50
    && is_string($preserve->plan?->fingerprint)
    && preg_match('/^[0-9a-f]{64}$/', $preserve->plan->fingerprint) === 1,
    '19. public_uuid/tstamp/pid/graph uids + fingerprint carried into plan'
);

echo $failures === 0 ? "\nAll PriceOptionUpdatePlanBuilder tests passed.\n" : "\n{$failures} PriceOptionUpdatePlanBuilder test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
