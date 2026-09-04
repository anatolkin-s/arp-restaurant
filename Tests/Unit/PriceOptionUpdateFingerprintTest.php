<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdateFingerprint;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdatePlan;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdateValues;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdateValues.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdatePlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdateFingerprint.php';

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

function fingerprintArgs(array $overrides = []): array
{
    return array_merge([
        'uid' => 40,
        'pid' => 10,
        'publicUuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'tstamp' => 1700000000,
        'placementUid' => 30,
        'menuUid' => 5,
        'categoryUid' => 20,
        'itemUid' => 50,
        'beforeLabel' => 'Small',
        'beforeAmountMinor' => 513,
        'afterLabel' => 'Small test',
        'afterAmountMinor' => 514,
    ], $overrides);
}

function compute(array $args): string
{
    return PriceOptionUpdateFingerprint::compute(
        $args['uid'],
        $args['pid'],
        $args['publicUuid'],
        $args['tstamp'],
        $args['placementUid'],
        $args['menuUid'],
        $args['categoryUid'],
        $args['itemUid'],
        $args['beforeLabel'],
        $args['beforeAmountMinor'],
        $args['afterLabel'],
        $args['afterAmountMinor'],
    );
}

$base = fingerprintArgs();
$fp1 = compute($base);
$fp2 = compute($base);

assertTrue(
    $fp1 === $fp2
    && preg_match('/^[0-9a-f]{64}$/', $fp1) === 1
    && $fp1 === strtolower($fp1),
    '1. identical plan => identical lowercase 64-hex fingerprint'
);

assertTrue(compute(fingerprintArgs(['uid' => 41])) !== $fp1, '2. uid change => fingerprint changes');
assertTrue(compute(fingerprintArgs(['pid' => 11])) !== $fp1, '3. pid change => changes');
assertTrue(
    compute(fingerprintArgs(['publicUuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'])) !== $fp1,
    '4. public_uuid change => changes'
);
assertTrue(compute(fingerprintArgs(['tstamp' => 1700000001])) !== $fp1, '5. tstamp change => changes');
assertTrue(compute(fingerprintArgs(['placementUid' => 31])) !== $fp1, '6. placementUid change => changes');
assertTrue(compute(fingerprintArgs(['menuUid' => 6])) !== $fp1, '7. menuUid change => changes');
assertTrue(compute(fingerprintArgs(['categoryUid' => 21])) !== $fp1, '8. categoryUid change => changes');
assertTrue(compute(fingerprintArgs(['itemUid' => 51])) !== $fp1, '9. itemUid change => changes');
assertTrue(compute(fingerprintArgs(['beforeLabel' => 'Large'])) !== $fp1, '10. before label change => changes');
assertTrue(compute(fingerprintArgs(['beforeAmountMinor' => 600])) !== $fp1, '11. before amount change => changes');
assertTrue(compute(fingerprintArgs(['afterLabel' => 'Large'])) !== $fp1, '12. after label change => changes');
assertTrue(compute(fingerprintArgs(['afterAmountMinor' => 600])) !== $fp1, '13. after amount change => changes');

$plan = new PriceOptionUpdatePlan(
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
    fingerprint: $fp1,
);
$fromPlan = PriceOptionUpdateFingerprint::fromPlan($plan);
assertTrue($fromPlan === $fp1, '14a. fromPlan matches compute');

$otherTitles = new PriceOptionUpdatePlan(
    uid: 40,
    pid: 10,
    publicUuid: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
    tstamp: 1700000000,
    placementUid: 30,
    menuUid: 5,
    categoryUid: 20,
    itemUid: 50,
    before: new PriceOptionUpdateValues('Small', 513, 'DISPLAY-ONLY-BEFORE'),
    after: new PriceOptionUpdateValues('Small test', 514, 'DISPLAY-ONLY-AFTER'),
    menuTitle: 'OTHER MENU TITLE',
    categoryTitle: 'OTHER CATEGORY',
    itemTitle: 'OTHER ITEM',
    fingerprint: 'ignored',
);
assertTrue(
    PriceOptionUpdateFingerprint::fromPlan($otherTitles) === $fp1,
    '14. formattedAmount-only/display-title changes do NOT define authority'
);

$source = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdateFingerprint.php') ?: '';
assertTrue(
    str_contains($source, "'version' => 'price-option-update-v1'")
    && str_contains($source, 'hash(')
    && str_contains($source, 'sha256'),
    'payload version marker price-option-update-v1 + sha256'
);

echo $failures === 0 ? "\nAll PriceOptionUpdateFingerprint tests passed.\n" : "\n{$failures} PriceOptionUpdateFingerprint test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
