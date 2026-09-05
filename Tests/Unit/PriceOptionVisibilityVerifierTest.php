<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityContext;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityPlan;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\Write\PriceOptionVisibilityVerifier;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityContext.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityPlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/Write/PriceOptionVisibilityExecutionResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/Write/PriceOptionVisibilityVerifier.php';

$failures = 0;
$verifier = new PriceOptionVisibilityVerifier();

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

function plan(): PriceOptionVisibilityPlan
{
    return new PriceOptionVisibilityPlan(
        uid: 40,
        pid: 10,
        publicUuid: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        tstamp: 1700000000,
        placementUid: 30,
        menuUid: 5,
        categoryUid: 20,
        itemUid: 50,
        currentHidden: 0,
        requestedHidden: 1,
        menuTitle: 'Dinner Menu',
        categoryTitle: 'Apply Gate 14',
        itemTitle: 'Apply Probe 14',
        label: 'Small',
        formattedAmount: '3.13',
        fingerprint: str_repeat('a', 64),
    );
}

function ctx(bool $hidden = true, array $overrides = []): PriceOptionVisibilityContext
{
    $base = [
        'uid' => 40,
        'pid' => 10,
        'publicUuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'tstamp' => 1700000001,
        'label' => 'Small',
        'amountMinor' => 313,
        'formattedAmount' => '3.13',
        'hidden' => $hidden,
        'placementUid' => 30,
        'sorting' => 256,
        'menuUid' => 5,
        'menuTitle' => 'Dinner Menu',
        'categoryUid' => 20,
        'categoryTitle' => 'Apply Gate 14',
        'itemUid' => 50,
        'itemTitle' => 'Apply Probe 14',
    ];
    $row = array_merge($base, $overrides);

    return new PriceOptionVisibilityContext(
        uid: $row['uid'],
        pid: $row['pid'],
        publicUuid: $row['publicUuid'],
        tstamp: $row['tstamp'],
        label: $row['label'],
        amountMinor: $row['amountMinor'],
        formattedAmount: $row['formattedAmount'],
        hidden: $row['hidden'],
        placementUid: $row['placementUid'],
        sorting: $row['sorting'],
        menuUid: $row['menuUid'],
        menuTitle: $row['menuTitle'],
        categoryUid: $row['categoryUid'],
        categoryTitle: $row['categoryTitle'],
        itemUid: $row['itemUid'],
        itemTitle: $row['itemTitle'],
    );
}

$ok = $verifier->verify(plan(), ctx(true), [], false);
assertTrue($ok->outcome === 'updated' && $ok->dataHandlerAttempted, 'exact requested hidden => updated');

$wrongHidden = $verifier->verify(plan(), ctx(false), [], false);
assertTrue($wrongHidden->outcome === 'failed', 'wrong hidden => failed');

$graphMismatch = $verifier->verify(plan(), ctx(true, ['menuUid' => 99]), [], false);
assertTrue(
    $graphMismatch->outcome === 'failed'
    && in_array('graphIdentityMismatch', $graphMismatch->diagnostics, true),
    'graph mismatch => failed'
);

$uuidMismatch = $verifier->verify(
    plan(),
    ctx(true, ['publicUuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb']),
    [],
    false
);
assertTrue($uuidMismatch->outcome === 'failed', 'uuid mismatch => failed');

$pidMismatch = $verifier->verify(plan(), ctx(true, ['pid' => 99]), [], false);
assertTrue($pidMismatch->outcome === 'failed', 'pid mismatch => failed');

$relationMismatch = $verifier->verify(plan(), ctx(true, ['placementUid' => 99]), [], false);
assertTrue($relationMismatch->outcome === 'failed', 'relation mismatch => failed');

$errorLog = $verifier->verify(plan(), ctx(true), ['some DataHandler error'], false);
assertTrue($errorLog->outcome === 'partialFailure', 'DataHandler errorLog prevents clean success');

$threwUncertain = $verifier->verify(plan(), ctx(true), [], true);
assertTrue(
    $threwUncertain->outcome === 'partialFailure'
    && in_array('visibilityUpdateException', $threwUncertain->diagnostics, true),
    'process threw + requested hidden present => partialFailure'
);

$threwFailed = $verifier->verify(plan(), ctx(false), [], true);
assertTrue($threwFailed->outcome === 'failed', 'process threw + wrong hidden => failed');

$readFail = $verifier->verify(plan(), null, [], false);
assertTrue(
    $readFail->outcome === 'failed'
    && in_array('verificationReadFailed', $readFail->diagnostics, true),
    'read-back failure => failed'
);

$writer = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/Write/RestaurantPriceOptionVisibilityWriter.php') ?: '';
assertTrue(
    preg_match('/catch\s*\(\s*\\\\?Throwable\)\s*\{[\s\S]*?dataHandlerAttempted:\s*false[\s\S]*?writePreparationFailed/s', $writer) === 1,
    'construction/start failure path returns no process attempt'
);

echo $failures === 0
    ? "\nAll PriceOptionVisibilityVerifier tests passed.\n"
    : "\n{$failures} PriceOptionVisibilityVerifier test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
