<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdatePlan;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdateValues;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\Write\PriceOptionUpdateDataMapBuilder;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MenuGraphAssembler.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdateValues.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdatePlan.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/Write/PriceOptionUpdateDataMapBuilder.php';

$failures = 0;
$builder = new PriceOptionUpdateDataMapBuilder();

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
    fingerprint: str_repeat('a', 64),
);

$map = $builder->build($plan);
$table = MenuGraphAssembler::TABLE_PRICEOPTION;

assertTrue(array_keys($map) === [$table], 'only PriceOption table is written');
assertTrue(array_keys($map[$table]) === [40], 'numeric existing uid key');
assertTrue(
    array_keys($map[$table][40]) === ['label', 'amount']
    && $map[$table][40]['label'] === 'Small test'
    && $map[$table][40]['amount'] === 514
    && is_int($map[$table][40]['amount']),
    'exactly label + amount fields; amount is int minor units'
);

$json = json_encode($map);
assertTrue(
    !str_contains($json, 'public_uuid')
    && !str_contains($json, '"pid"')
    && !str_contains($json, 'placement')
    && !str_contains($json, 'sorting')
    && !str_contains($json, 'sys_language_uid')
    && !str_contains($json, 'hidden')
    && !str_contains($json, 'deleted')
    && !str_contains($json, 'tx_arprestaurant_domain_model_category')
    && !str_contains($json, 'tx_arprestaurant_domain_model_item')
    && !str_contains($json, 'tx_arprestaurant_domain_model_menu')
    && !str_contains($json, 'tx_arprestaurant_domain_model_placement'),
    'no forbidden fields or other tables'
);

$threw = false;
try {
    $builder->build(new PriceOptionUpdatePlan(
        uid: 0,
        pid: 10,
        publicUuid: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        tstamp: 1,
        placementUid: 1,
        menuUid: 1,
        categoryUid: 1,
        itemUid: 1,
        before: new PriceOptionUpdateValues('', 0, '0.00'),
        after: new PriceOptionUpdateValues('x', 1, '0.01'),
        menuTitle: '',
        categoryTitle: '',
        itemTitle: '',
        fingerprint: str_repeat('b', 64),
    ));
} catch (InvalidArgumentException) {
    $threw = true;
}
assertTrue($threw, 'non-positive uid rejected');

$writer = file_get_contents(dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit/Write/RestaurantPriceOptionUpdateWriter.php') ?: '';
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

$priceEditRoot = dirname(__DIR__, 2) . '/Classes/Backend/Editor/PriceEdit';
$otherWriters = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($priceEditRoot)) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    if (str_ends_with($path, '/Write/RestaurantPriceOptionUpdateWriter.php')) {
        continue;
    }
    $src = file_get_contents($file->getPathname()) ?: '';
    if (str_contains($src, 'process_datamap(') || str_contains($src, 'makeInstance(DataHandler')) {
        ++$otherWriters;
        echo "NOTE DataHandler call outside writer: {$path}\n";
    }
}
assertTrue($otherWriters === 0, 'writer is sole DataHandler boundary in PriceEdit');

echo $failures === 0 ? "\nAll PriceOptionUpdateDataMap tests passed.\n" : "\n{$failures} PriceOptionUpdateDataMap test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
