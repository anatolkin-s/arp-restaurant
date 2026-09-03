<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\DataHandling\CopiedTranslationUuidDecision;

require dirname(__DIR__, 2) . '/Classes/Backend/DataHandling/CopiedTranslationUuidDecision.php';

$decision = new CopiedTranslationUuidDecision();
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

$sourceMenuUuid = '6d915fce-506f-4ae4-9370-e46260343cbc';
$copiedMenuEnUuid = 'fc2bfb3b-7525-4c70-ae33-72415f4f7c7a';

$copiedMenu = [
    5 => [
        'uid' => 5,
        'sys_language_uid' => 0,
        'l10n_parent' => 0,
        'public_uuid' => $copiedMenuEnUuid,
    ],
    6 => [
        'uid' => 6,
        'sys_language_uid' => 1,
        'l10n_parent' => 5,
        'public_uuid' => $copiedMenuEnUuid,
    ],
];

$menuUpdates = $decision->decideAlignments($copiedMenu);
assertTrue($copiedMenuEnUuid !== $sourceMenuUuid, '1. default copy UUID is not the source UUID');
assertTrue($menuUpdates === [], '1. default-language copy UUID is not rewritten');
assertTrue(($copiedMenu[6]['public_uuid'] ?? '') === $copiedMenuEnUuid, '2. already-aligned Menu translation needs no write');

$copiedCategory = [
    4 => [
        'uid' => 4,
        'sys_language_uid' => 0,
        'l10n_parent' => 0,
        'public_uuid' => '303f7012-401d-4a76-b4b3-88265ecb510d',
    ],
    5 => [
        'uid' => 5,
        'sys_language_uid' => 1,
        'l10n_parent' => 4,
        'public_uuid' => '523a1754-8f93-48d5-8108-31494a6826cd',
    ],
];
$categoryUpdates = $decision->decideAlignments($copiedCategory);
assertTrue(
    ($categoryUpdates[5] ?? null) === '303f7012-401d-4a76-b4b3-88265ecb510d',
    '2. copied RU Category UUID is aligned to copied EN UUID'
);
assertTrue(!isset($categoryUpdates[4]), '1. copied EN Category UUID is left as Core generated it');

$copiedPlacement = [
    4 => [
        'uid' => 4,
        'sys_language_uid' => 0,
        'l10n_parent' => 0,
        'public_uuid' => '78de7a91-fc60-4a99-9e1f-6570d2107a18',
    ],
    5 => [
        'uid' => 5,
        'sys_language_uid' => 1,
        'l10n_parent' => 4,
        'public_uuid' => '061a80dd-38f3-4109-ba44-d794f3b5009a',
        'item' => 1,
    ],
];
$placementUpdates = $decision->decideAlignments($copiedPlacement);
assertTrue(
    ($placementUpdates[5] ?? null) === '78de7a91-fc60-4a99-9e1f-6570d2107a18',
    '2. copied RU Placement UUID is aligned to copied EN UUID'
);
assertTrue(!isset($placementUpdates['item']), '6. Placement.item is not part of the UUID correction');

$copiedPriceOption = [
    5 => [
        'uid' => 5,
        'sys_language_uid' => 0,
        'l10n_parent' => 0,
        'public_uuid' => '87e97430-901d-4e4c-bb9e-f1b7d93fae03',
    ],
    6 => [
        'uid' => 6,
        'sys_language_uid' => 1,
        'l10n_parent' => 5,
        'public_uuid' => '2d72a04b-65d3-497e-93ad-611399e73d52',
    ],
];
$priceUpdates = $decision->decideAlignments($copiedPriceOption);
assertTrue(
    ($priceUpdates[6] ?? null) === '87e97430-901d-4e4c-bb9e-f1b7d93fae03',
    '2. copied RU PriceOption UUID is aligned to copied EN UUID'
);

$originalGroup = [
    1 => [
        'uid' => 1,
        'sys_language_uid' => 0,
        'l10n_parent' => 0,
        'public_uuid' => '60ba1a75-3f6b-4a3c-affe-a203a7a45b78',
    ],
    2 => [
        'uid' => 2,
        'sys_language_uid' => 1,
        'l10n_parent' => 1,
        'public_uuid' => '60ba1a75-3f6b-4a3c-affe-a203a7a45b78',
    ],
];
assertTrue($decision->decideAlignments($originalGroup) === [], '3. original translation group is unchanged when already aligned');

$mixedOriginalAndCopy = $originalGroup + $copiedCategory;
$mixedUpdates = $decision->decideAlignments($mixedOriginalAndCopy);
assertTrue(!isset($mixedUpdates[1]) && !isset($mixedUpdates[2]), '3. original translation group UIDs are not rewritten beside a copy');
assertTrue(($mixedUpdates[5] ?? null) === '303f7012-401d-4a76-b4b3-88265ecb510d', '3. only the copied translation is aligned in a mixed set');

$orphanTranslation = [
    9 => [
        'uid' => 9,
        'sys_language_uid' => 1,
        'l10n_parent' => 1,
        'public_uuid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
    ],
];
assertTrue($decision->decideAlignments($orphanTranslation) === [], '3. translation whose default parent was not copied is ignored');

$localizedItem = [
    1 => [
        'uid' => 1,
        'sys_language_uid' => 0,
        'l10n_parent' => 0,
        'public_uuid' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
    ],
    2 => [
        'uid' => 2,
        'sys_language_uid' => 1,
        'l10n_parent' => 1,
        'public_uuid' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
    ],
];
assertTrue($decision->decideAlignments($localizedItem) === [], '4. Translate/localization already sharing UUID is not rewritten');

assertTrue(!$decision->isRestaurantTable('tt_content'), '5. unrelated tables are ignored');
assertTrue($decision->isRestaurantTable('tx_arprestaurant_domain_model_item'), '5. ARP Item table is in scope');

$copiedItem = [
    8 => [
        'uid' => 8,
        'sys_language_uid' => 0,
        'l10n_parent' => 0,
        'public_uuid' => '11111111-1111-1111-1111-111111111111',
    ],
    9 => [
        'uid' => 9,
        'sys_language_uid' => 1,
        'l10n_parent' => 8,
        'public_uuid' => '22222222-2222-2222-2222-222222222222',
    ],
];
$itemUpdates = $decision->decideAlignments($copiedItem);
assertTrue(($itemUpdates[9] ?? null) === '11111111-1111-1111-1111-111111111111', '2. copied RU Item UUID is aligned to copied EN UUID');
assertTrue(array_keys($itemUpdates) === [9], '6. lifecycle correction emits only public_uuid updates keyed by copied translation uid');

$secondPass = $decision->decideAlignments([
    4 => $copiedCategory[4],
    5 => [
        'uid' => 5,
        'sys_language_uid' => 1,
        'l10n_parent' => 4,
        'public_uuid' => '303f7012-401d-4a76-b4b3-88265ecb510d',
    ],
]);
assertTrue($secondPass === [], 'alignment is idempotent once UUIDs already match');

if ($failures > 0) {
    echo "\n{$failures} failing assertion(s)\n";
    exit(1);
}

echo "\nAll CopiedTranslationUuidDecision tests passed.\n";
exit(0);
