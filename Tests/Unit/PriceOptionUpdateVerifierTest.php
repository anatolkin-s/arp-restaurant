<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditContext;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdatePlan;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdateValues;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\Write\PriceOptionUpdateVerifier;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditContext.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdateValues.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdatePlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/Write/PriceOptionUpdateExecutionResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/Write/PriceOptionUpdateVerifier.php';

$failures = 0;
$verifier = new PriceOptionUpdateVerifier();

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

function plan(): PriceOptionUpdatePlan
{
    return new PriceOptionUpdatePlan(
        uid: 40,
        pid: 10,
        publicUuid: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        tstamp: 1700000000,
        placementUid: 30,
        menuUid: 5,
        categoryUid: 20,
        itemUid: 50,
        before: new PriceOptionUpdateValues('Small', 513, '5.13'),
        after: new PriceOptionUpdateValues('Small test', 514, '5.14'),
        menuTitle: 'Lunch',
        categoryTitle: 'Mains',
        itemTitle: 'Soup',
        fingerprint: str_repeat('a', 64),
    );
}

function ctx(
    string $label = 'Small test',
    int $amountMinor = 514,
    int $menuUid = 5,
): PriceOptionEditContext {
    return new PriceOptionEditContext(
        uid: 40,
        pid: 10,
        publicUuid: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        tstamp: 1700000001,
        label: $label,
        amountMinor: $amountMinor,
        formattedAmount: number_format($amountMinor / 100, 2, '.', ''),
        placementUid: 30,
        sorting: 256,
        menuUid: $menuUid,
        menuTitle: 'Lunch',
        categoryUid: 20,
        categoryTitle: 'Mains',
        itemUid: 50,
        itemTitle: 'Soup',
    );
}

$ok = $verifier->verify(plan(), ctx(), [], false);
assertTrue($ok->outcome === 'updated' && $ok->dataHandlerAttempted, 'C. exact readback => updated');

$unchanged = $verifier->verify(plan(), ctx('Small', 513), [], false);
assertTrue($unchanged->outcome === 'failed', 'D. process normal + no requested change => failed');

$partialLabel = $verifier->verify(plan(), ctx('Small test', 513), [], false);
assertTrue($partialLabel->outcome === 'partialFailure', 'E. one requested field persists => partialFailure');

$throwNone = $verifier->verify(plan(), ctx('Small', 513), [], true);
assertTrue(
    $throwNone->outcome === 'failed'
    && in_array('priceUpdateException', $throwNone->diagnostics, true),
    'F. process throws + nothing changed => failed'
);

$throwPartial = $verifier->verify(plan(), ctx('Small test', 513), [], true);
assertTrue(
    $throwPartial->outcome === 'partialFailure'
    && in_array('priceUpdateException', $throwPartial->diagnostics, true),
    'G. process throws + one field changed => partialFailure'
);

$errorLog = $verifier->verify(plan(), ctx(), ['some DataHandler error'], false);
assertTrue($errorLog->outcome === 'partialFailure', 'H. errorLog non-empty => never clean updated');

$readFail = $verifier->verify(plan(), null, [], false);
assertTrue(
    $readFail->outcome === 'failed'
    && in_array('verificationReadFailed', $readFail->diagnostics, true),
    'I. read-back failure => never clean updated'
);

$writer = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/Write/RestaurantPriceOptionUpdateWriter.php') ?: '';
assertTrue(
    preg_match('/catch\s*\(\s*\\\\?Throwable\)\s*\{[\s\S]*?dataHandlerAttempted:\s*false[\s\S]*?writePreparationFailed/s', $writer) === 1
    && str_contains($writer, 'dataHandlerAttempted: false'),
    'B. construction/start failure path returns no process attempt'
);

$controller = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Controller/RestaurantEditorController.php') ?: '';
assertTrue(
    preg_match(
        '/function processPriceOptionEditApply\(.*?function processPriceOptionEditReview/s',
        $controller,
        $applyBlock
    ) === 1,
    'apply handler extractable'
);
$block = $applyBlock[0] ?? '';
assertTrue(
    str_contains($block, "outcome === 'preparationBlocked'")
    && strpos($block, "outcome === 'preparationBlocked'") < strpos($block, 'priceOptionUpdateWriter->execute')
    && str_contains($block, "'writePreparationBlocked'")
    && strpos($block, "'writePreparationBlocked'") < strpos($block, 'priceOptionUpdateWriter->execute')
    && !str_contains(
        substr($block, 0, (int)strpos($block, 'priceOptionUpdateWriter->execute')),
        'new RedirectResponse'
    ),
    'A. pre-writer preparation failure => no writer / no PRG before execute'
);

echo $failures === 0 ? "\nAll PriceOptionUpdateVerifier tests passed.\n" : "\n{$failures} PriceOptionUpdateVerifier test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
