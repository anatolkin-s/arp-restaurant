<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityFingerprint;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityPlan;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityPlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityFingerprint.php';

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
        'beforeHidden' => 0,
        'afterHidden' => 1,
    ], $overrides);
}

function compute(array $args): string
{
    return PriceOptionVisibilityFingerprint::compute(
        $args['uid'],
        $args['pid'],
        $args['publicUuid'],
        $args['tstamp'],
        $args['placementUid'],
        $args['menuUid'],
        $args['categoryUid'],
        $args['itemUid'],
        $args['beforeHidden'],
        $args['afterHidden'],
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
assertTrue(compute(fingerprintArgs(['beforeHidden' => 1])) !== $fp1, '10. before hidden change => changes');
assertTrue(compute(fingerprintArgs(['afterHidden' => 0])) !== $fp1, '11. after hidden change => changes');

$plan = new PriceOptionVisibilityPlan(
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
    fingerprint: $fp1,
);
assertTrue(PriceOptionVisibilityFingerprint::fromPlan($plan) === $fp1, '12. fromPlan matches compute');

$displayOnly = new PriceOptionVisibilityPlan(
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
    menuTitle: 'OTHER MENU',
    categoryTitle: 'OTHER CATEGORY',
    itemTitle: 'OTHER ITEM',
    label: 'DISPLAY-ONLY-LABEL',
    formattedAmount: '99.99',
    fingerprint: 'ignored',
);
assertTrue(
    PriceOptionVisibilityFingerprint::fromPlan($displayOnly) === $fp1,
    '13. display titles / formatted price / label do NOT define authority'
);

$source = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityFingerprint.php') ?: '';
assertTrue(
    str_contains($source, "'version' => 'price-option-visibility-v1'")
    && str_contains($source, 'hash(')
    && str_contains($source, 'sha256'),
    'payload version marker price-option-visibility-v1 + sha256'
);

echo $failures === 0
    ? "\nAll PriceOptionVisibilityFingerprint tests passed.\n"
    : "\n{$failures} PriceOptionVisibilityFingerprint test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
