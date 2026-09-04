<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write\ApplyDataMap;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write\ApplyExpectedCreate;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write\ApplyWriteVerifier;
use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MenuGraphAssembler.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/ApplyExpectedCreate.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/ApplyDataMap.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/ApplyExecutionResult.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/ApplyPublicUuid.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Apply/Write/ApplyWriteVerifier.php';

$failures = 0;
$verifier = new ApplyWriteVerifier();

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

$itemToken = 'NEWarpIabc';
$catToken = 'NEWarpCabc';
$placeToken = 'NEWarpPabc';
$priceToken = 'NEWarpOabc';

$dataMap = new ApplyDataMap(
    dataMap: [],
    localRefToNewToken: [
        'i:hummus' => $itemToken,
        'c:starters' => $catToken,
        'p:0' => $placeToken,
        'po:r0' => $priceToken,
    ],
    expectedCreates: [
        new ApplyExpectedCreate(MenuGraphAssembler::TABLE_ITEM, $itemToken, 'i:hummus', 'item', [
            'pid' => 5,
            'title' => 'Hummus',
            'sys_language_uid' => 0,
        ]),
        new ApplyExpectedCreate(MenuGraphAssembler::TABLE_CATEGORY, $catToken, 'c:starters', 'category', [
            'pid' => 5,
            'title' => 'Starters',
            'menu' => 10,
            'sys_language_uid' => 0,
        ]),
        new ApplyExpectedCreate(MenuGraphAssembler::TABLE_PLACEMENT, $placeToken, 'p:0', 'placement', [
            'pid' => 5,
            'category' => $catToken,
            'item' => $itemToken,
            'sys_language_uid' => 0,
        ]),
        new ApplyExpectedCreate(MenuGraphAssembler::TABLE_PRICEOPTION, $priceToken, 'po:r0', 'priceoption', [
            'pid' => 5,
            'placement' => $placeToken,
            'label' => '',
            'amount' => 800,
            'sys_language_uid' => 0,
        ]),
    ],
);

$subst = [
    $itemToken => 101,
    $catToken => 201,
    $placeToken => 301,
    $priceToken => 401,
];
$substTable = [
    $itemToken => MenuGraphAssembler::TABLE_ITEM,
    $catToken => MenuGraphAssembler::TABLE_CATEGORY,
    $placeToken => MenuGraphAssembler::TABLE_PLACEMENT,
    $priceToken => MenuGraphAssembler::TABLE_PRICEOPTION,
];
$uuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$rows = [
    MenuGraphAssembler::TABLE_ITEM . ':101' => [
        'uid' => 101, 'pid' => 5, 'sys_language_uid' => 0, 'deleted' => 0,
        'public_uuid' => $uuid, 'title' => 'Hummus',
    ],
    MenuGraphAssembler::TABLE_CATEGORY . ':201' => [
        'uid' => 201, 'pid' => 5, 'sys_language_uid' => 0, 'deleted' => 0,
        'public_uuid' => $uuid, 'title' => 'Starters', 'menu' => 10,
    ],
    MenuGraphAssembler::TABLE_PLACEMENT . ':301' => [
        'uid' => 301, 'pid' => 5, 'sys_language_uid' => 0, 'deleted' => 0,
        'public_uuid' => $uuid, 'category' => 201, 'item' => 101,
    ],
    MenuGraphAssembler::TABLE_PRICEOPTION . ':401' => [
        'uid' => 401, 'pid' => 5, 'sys_language_uid' => 0, 'deleted' => 0,
        'public_uuid' => $uuid, 'placement' => 301, 'label' => '', 'amount' => 800,
    ],
];

$ok = $verifier->verify($dataMap, $subst, $substTable, [], $rows, 5);
assertTrue($ok->outcome === 'applied', '1. all expected records verify -> applied');

$missing = $verifier->verify($dataMap, [$itemToken => 101], $substTable, [], $rows, 5);
assertTrue($missing->outcome === 'partialFailure' || $missing->outcome === 'failed', '2. missing NEW mapping detected');
assertTrue(in_array('missingNewMapping:category', $missing->diagnostics, true)
    || in_array('missingNewMapping:placement', $missing->diagnostics, true)
    || in_array('missingNewMapping:priceoption', $missing->diagnostics, true), '2. missing mapping diagnostic');

$wrongTable = $substTable;
$wrongTable[$itemToken] = MenuGraphAssembler::TABLE_CATEGORY;
$wt = $verifier->verify($dataMap, $subst, $wrongTable, [], $rows, 5);
assertTrue(in_array('wrongTableMapping:item', $wt->diagnostics, true), '3. wrong table mapping detected');

$wrongPidRows = $rows;
$wrongPidRows[MenuGraphAssembler::TABLE_ITEM . ':101']['pid'] = 9;
$wp = $verifier->verify($dataMap, $subst, $substTable, [], $wrongPidRows, 5);
assertTrue(in_array('wrongPid:item', $wp->diagnostics, true), '4. wrong pid detected');

$langRows = $rows;
$langRows[MenuGraphAssembler::TABLE_ITEM . ':101']['sys_language_uid'] = 1;
$wl = $verifier->verify($dataMap, $subst, $substTable, [], $langRows, 5);
assertTrue(in_array('nonzeroLanguage:item', $wl->diagnostics, true), '5. nonzero language detected');

$uuidRows = $rows;
$uuidRows[MenuGraphAssembler::TABLE_ITEM . ':101']['public_uuid'] = '';
$wu = $verifier->verify($dataMap, $subst, $substTable, [], $uuidRows, 5);
assertTrue(in_array('invalidUuid:item', $wu->diagnostics, true), '6. missing/invalid uuid detected');

$menuRows = $rows;
$menuRows[MenuGraphAssembler::TABLE_CATEGORY . ':201']['menu'] = 99;
$wm = $verifier->verify($dataMap, $subst, $substTable, [], $menuRows, 5);
assertTrue(in_array('fieldMismatch:category:menu', $wm->diagnostics, true), '7. Category wrong Menu detected');

$catRows = $rows;
$catRows[MenuGraphAssembler::TABLE_PLACEMENT . ':301']['category'] = 999;
$wc = $verifier->verify($dataMap, $subst, $substTable, [], $catRows, 5);
assertTrue(in_array('relationMismatch:placement:category', $wc->diagnostics, true), '8. Placement wrong Category detected');

$itemRows = $rows;
$itemRows[MenuGraphAssembler::TABLE_PLACEMENT . ':301']['item'] = 999;
$wi = $verifier->verify($dataMap, $subst, $substTable, [], $itemRows, 5);
assertTrue(in_array('relationMismatch:placement:item', $wi->diagnostics, true), '9. Placement wrong Item detected');

$placeRows = $rows;
$placeRows[MenuGraphAssembler::TABLE_PRICEOPTION . ':401']['placement'] = 999;
$wpl = $verifier->verify($dataMap, $subst, $substTable, [], $placeRows, 5);
assertTrue(in_array('relationMismatch:priceoption:placement', $wpl->diagnostics, true), '10. Price wrong Placement detected');

$amtRows = $rows;
$amtRows[MenuGraphAssembler::TABLE_PRICEOPTION . ':401']['amount'] = 1;
$wa = $verifier->verify($dataMap, $subst, $substTable, [], $amtRows, 5);
assertTrue(in_array('fieldMismatch:priceoption:amount', $wa->diagnostics, true), '11. Price wrong amount detected');

$labelRows = $rows;
$labelRows[MenuGraphAssembler::TABLE_PRICEOPTION . ':401']['label'] = 'X';
$wlab = $verifier->verify($dataMap, $subst, $substTable, [], $labelRows, 5);
assertTrue(in_array('fieldMismatch:priceoption:label', $wlab->diagnostics, true), '12. Price wrong label detected');

$partialRows = $rows;
unset($partialRows[MenuGraphAssembler::TABLE_PRICEOPTION . ':401']);
$partial = $verifier->verify($dataMap, $subst, $substTable, [], $partialRows, 5);
assertTrue($partial->outcome === 'partialFailure', '13. some verified + some missing => partial');

$err = $verifier->verify($dataMap, $subst, $substTable, ['dh boom'], $rows, 5);
assertTrue($err->outcome !== 'applied', '14. DataHandler errorLog non-empty => not applied');

$none = $verifier->verify($dataMap, [], [], ['boom'], [], 5);
assertTrue($none->outcome === 'failed', '14b. nothing verified => failed');

foreach ([
    \Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write\ApplyExecutionResult::class,
    ApplyExpectedCreate::class,
    ApplyDataMap::class,
] as $className) {
    $ref = new ReflectionClass($className);
    foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        $name = $property->getName();
        $suffix = ucfirst($name);
        assertTrue(
            !$ref->hasMethod('get' . $suffix)
            && !$ref->hasMethod('is' . $suffix)
            && !$ref->hasMethod('has' . $suffix),
            'Fluid ObjectAccess: ' . $className . '::$' . $name
        );
    }
}

echo $failures === 0 ? "\nAll ApplyWriteVerifier tests passed.\n" : "\n{$failures} ApplyWriteVerifier test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
