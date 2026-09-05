<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityPlan;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\Write\PriceOptionVisibilityDataMap;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MenuGraphAssembler.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityPlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/Write/PriceOptionVisibilityDataMap.php';

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

function visibilityPlan(int $uid = 40, int $requestedHidden = 1): PriceOptionVisibilityPlan
{
    return new PriceOptionVisibilityPlan(
        uid: $uid,
        pid: 10,
        publicUuid: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        tstamp: 1700000000,
        placementUid: 30,
        menuUid: 5,
        categoryUid: 20,
        itemUid: 50,
        currentHidden: $requestedHidden === 1 ? 0 : 1,
        requestedHidden: $requestedHidden,
        menuTitle: 'Dinner Menu',
        categoryTitle: 'Apply Gate 14',
        itemTitle: 'Apply Probe 14',
        label: 'Small',
        formattedAmount: '3.13',
        fingerprint: str_repeat('a', 64),
    );
}

$map = PriceOptionVisibilityDataMap::fromPlan(visibilityPlan())->payload;
$table = MenuGraphAssembler::TABLE_PRICEOPTION;

assertTrue(array_keys($map) === [$table], 'only PriceOption table is written');
assertTrue(array_keys($map[$table]) === [40], 'numeric existing uid key');
assertTrue(
    array_keys($map[$table][40]) === ['hidden']
    && $map[$table][40]['hidden'] === 1
    && is_int($map[$table][40]['hidden']),
    'exactly hidden field; integer 1'
);

$visibleMap = PriceOptionVisibilityDataMap::fromPlan(visibilityPlan(40, 0))->payload;
assertTrue(
    $visibleMap[$table][40]['hidden'] === 0
    && is_int($visibleMap[$table][40]['hidden']),
    'hidden integer 0'
);

$json = json_encode($map);
assertTrue(
    !str_contains($json, 'label')
    && !str_contains($json, 'amount')
    && !str_contains($json, 'public_uuid')
    && !str_contains($json, '"pid"')
    && !str_contains($json, 'placement')
    && !str_contains($json, 'sorting')
    && !str_contains($json, 'sys_language_uid')
    && !str_contains($json, 'starttime')
    && !str_contains($json, 'endtime')
    && !str_contains($json, 'tx_arprestaurant_domain_model_category')
    && !str_contains($json, 'tx_arprestaurant_domain_model_item')
    && !str_contains($json, 'tx_arprestaurant_domain_model_menu')
    && !str_contains($json, 'tx_arprestaurant_domain_model_placement'),
    'no forbidden fields or other tables'
);

$threwUid = false;
try {
    PriceOptionVisibilityDataMap::fromPlan(visibilityPlan(0, 1));
} catch (InvalidArgumentException) {
    $threwUid = true;
}
assertTrue($threwUid, 'non-positive uid rejected');

$writer = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility/Write/RestaurantPriceOptionVisibilityWriter.php') ?: '';
assertTrue(
    str_contains($writer, '->start(')
    && str_contains($writer, 'process_datamap()')
    && !str_contains($writer, 'process_cmdmap(')
    && !preg_match('/\b(Connection::update|executeStatement)\b/', $writer)
    && !str_contains($writer, 'beginTransaction')
    && !str_contains($writer, '->rollback(')
    && preg_match('/\$dataHandlerAttempted\s*=\s*true;\s*\$dataHandler->process_datamap\(\);/s', $writer) === 1,
    'writer: start + process_datamap; no cmdmap/SQL/transaction; attempt immediately before process'
);

$visibilityRoot = dirname(__DIR__, 2) . '/Classes/Backend/Editor/Visibility';
$otherWriters = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($visibilityRoot)) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    if (str_ends_with($path, '/Write/RestaurantPriceOptionVisibilityWriter.php')) {
        continue;
    }
    $src = file_get_contents($file->getPathname()) ?: '';
    if (str_contains($src, 'process_datamap(') || str_contains($src, 'makeInstance(DataHandler')) {
        ++$otherWriters;
        echo "NOTE DataHandler call outside writer: {$path}\n";
    }
}
assertTrue($otherWriters === 0, 'writer is sole DataHandler boundary in Visibility');

$priceEditWriter = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/Write/RestaurantPriceOptionUpdateWriter.php') ?: '';
assertTrue(
    str_contains($priceEditWriter, 'PriceOptionUpdatePlan')
    && !str_contains($priceEditWriter, 'PriceOptionVisibility')
    && !str_contains($writer, 'PriceOptionUpdatePlan'),
    'visibility writer is separate from PriceEdit writer'
);

echo $failures === 0
    ? "\nAll PriceOptionVisibilityDataMap tests passed.\n"
    : "\n{$failures} PriceOptionVisibilityDataMap test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
