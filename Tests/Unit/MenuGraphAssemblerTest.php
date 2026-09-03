<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use Anatolkin\ArpRestaurant\Backend\Editor\MinorUnitMoneyFormatter;
use Anatolkin\ArpRestaurant\Backend\Editor\RecordEditUrlBuilder;

require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MinorUnitMoneyFormatter.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/RecordEditUrlBuilder.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/ViewModel/PriceOptionRow.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/ViewModel/PlacementGroup.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/ViewModel/CategorySection.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/ViewModel/MenuTab.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/ViewModel/MenuDetail.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/ViewModel/EditorScreen.php';
require dirname(__DIR__, 2) . '/Classes/Backend/Editor/MenuGraphAssembler.php';

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

final class StaticEditUrlBuilder implements RecordEditUrlBuilder
{
    public function build(string $table, int $uid): ?string
    {
        return $table . ':' . $uid;
    }
}

$formatter = new MinorUnitMoneyFormatter(2);
assertTrue($formatter->format(2300) === '23.00', '6. 2300 minor units format as 23.00');
assertTrue($formatter->format(900) === '9.00', '6. 900 minor units format as 9.00');
assertTrue($formatter->format(1250) === '12.50', '6. 1250 minor units format as 12.50');
assertTrue($formatter->format(0) === '0.00', '6. zero formats as 0.00');
assertTrue((new MinorUnitMoneyFormatter(0))->format(23) === '23', '6. formatter scale is injected, not hardcoded / 100');

$assembler = new MenuGraphAssembler($formatter);
$now = 1_700_000_000;
$editUrls = new StaticEditUrlBuilder();

$simple = $assembler->assemble(
    10,
    'Storage',
    [['uid' => 2, 'title' => 'Lunch', 'hidden' => 0, 'starttime' => 0, 'endtime' => 0]],
    [['uid' => 3, 'title' => 'Mains', 'menu' => 2, 'sorting' => 10, 'hidden' => 0]],
    [['uid' => 4, 'category' => 3, 'item' => 1, 'sorting' => 10, 'hidden' => 0, 'starttime' => 0, 'endtime' => 0]],
    [['uid' => 1, 'title' => 'Atlantic Salmon', 'hidden' => 0]],
    [['uid' => 8, 'placement' => 4, 'label' => '', 'amount' => 2300, 'sorting' => 10, 'hidden' => 0]],
    2,
    $now,
    static fn (int $menuUid): string => '/menu/' . $menuUid,
    $editUrls,
);

$simplePlacement = $simple->selectedMenu->categories[0]->placements[0];
assertTrue($simplePlacement->itemTitle === 'Atlantic Salmon', '1. simple price keeps the Item title');
assertTrue(count($simplePlacement->priceOptions) === 1, '1. one PriceOption on the Placement');
assertTrue($simplePlacement->priceOptions[0]->displayLabel === '—', '1. empty label displays as em dash');
assertTrue($simplePlacement->priceOptions[0]->formattedAmount === '23.00', '1. simple amount uses isolated formatter');
assertTrue($simplePlacement->priceOptions[0]->amountMinor === 2300, '1. raw minor units are preserved beside display');
assertTrue($simple->menus[0]->editUrl === 'tx_arprestaurant_domain_model_menu:2', 'escape hatch Menu record_edit is built');
assertTrue($simple->selectedMenu->categories[0]->editUrl === 'tx_arprestaurant_domain_model_category:3', 'escape hatch Category record_edit is built');
assertTrue($simplePlacement->itemEditUrl === 'tx_arprestaurant_domain_model_item:1', 'escape hatch Item record_edit is built');

$variants = $assembler->assemble(
    10,
    'Storage',
    [['uid' => 2, 'title' => 'Lunch', 'hidden' => 0]],
    [['uid' => 3, 'title' => 'Mains', 'menu' => 2, 'sorting' => 10, 'hidden' => 0]],
    [['uid' => 4, 'category' => 3, 'item' => 7, 'sorting' => 10, 'hidden' => 0, 'starttime' => 0, 'endtime' => 0]],
    [['uid' => 7, 'title' => 'Pizza', 'hidden' => 0]],
    [
        ['uid' => 82, 'placement' => 4, 'label' => 'Large', 'amount' => 1800, 'sorting' => 20, 'hidden' => 0],
        ['uid' => 81, 'placement' => 4, 'label' => 'Small', 'amount' => 1200, 'sorting' => 10, 'hidden' => 0],
    ],
    2,
    $now,
);

$variantOptions = $variants->selectedMenu->categories[0]->placements[0]->priceOptions;
assertTrue(count($variantOptions) === 2, '2. one Placement owns both variants');
assertTrue($variantOptions[0]->label === 'Small' && $variantOptions[1]->label === 'Large', '2. PriceOptions stay ordered Small then Large');
assertTrue($variantOptions[0]->formattedAmount === '12.00' && $variantOptions[1]->formattedAmount === '18.00', '2. variant prices are formatted independently of mapping');

$reused = $assembler->assemble(
    10,
    'Storage',
    [
        ['uid' => 20, 'title' => 'Dinner', 'hidden' => 0],
        ['uid' => 10, 'title' => 'Lunch', 'hidden' => 0],
    ],
    [
        ['uid' => 31, 'title' => 'Entrees', 'menu' => 20, 'sorting' => 10, 'hidden' => 0],
        ['uid' => 30, 'title' => 'Mains', 'menu' => 10, 'sorting' => 10, 'hidden' => 0],
    ],
    [
        ['uid' => 41, 'category' => 31, 'item' => 1, 'sorting' => 10, 'hidden' => 0, 'starttime' => 0, 'endtime' => 0],
        ['uid' => 40, 'category' => 30, 'item' => 1, 'sorting' => 10, 'hidden' => 0, 'starttime' => 0, 'endtime' => 0],
    ],
    [['uid' => 1, 'title' => 'Atlantic Salmon', 'hidden' => 0]],
    [
        ['uid' => 51, 'placement' => 41, 'label' => '', 'amount' => 2900, 'sorting' => 10, 'hidden' => 0],
        ['uid' => 50, 'placement' => 40, 'label' => '', 'amount' => 2300, 'sorting' => 10, 'hidden' => 0],
    ],
    10,
    $now,
);

assertTrue($reused->menus[0]->title === 'Dinner' && $reused->menus[1]->title === 'Lunch', '5. menus are ordered by title');
$lunchPlacement = $reused->selectedMenu->categories[0]->placements[0];
assertTrue($lunchPlacement->itemTitle === 'Atlantic Salmon', '3. Lunch Placement reuses the canonical Item');
assertTrue($lunchPlacement->priceOptions[0]->formattedAmount === '23.00', '3. Lunch price stays on its Placement');

$dinner = $assembler->assemble(
    10,
    'Storage',
    [
        ['uid' => 20, 'title' => 'Dinner', 'hidden' => 0],
        ['uid' => 10, 'title' => 'Lunch', 'hidden' => 0],
    ],
    [
        ['uid' => 31, 'title' => 'Entrees', 'menu' => 20, 'sorting' => 10, 'hidden' => 0],
        ['uid' => 30, 'title' => 'Mains', 'menu' => 10, 'sorting' => 10, 'hidden' => 0],
    ],
    [
        ['uid' => 41, 'category' => 31, 'item' => 1, 'sorting' => 10, 'hidden' => 0, 'starttime' => 0, 'endtime' => 0],
        ['uid' => 40, 'category' => 30, 'item' => 1, 'sorting' => 10, 'hidden' => 0, 'starttime' => 0, 'endtime' => 0],
    ],
    [['uid' => 1, 'title' => 'Atlantic Salmon', 'hidden' => 0]],
    [
        ['uid' => 51, 'placement' => 41, 'label' => '', 'amount' => 2900, 'sorting' => 10, 'hidden' => 0],
        ['uid' => 50, 'placement' => 40, 'label' => '', 'amount' => 2300, 'sorting' => 10, 'hidden' => 0],
    ],
    20,
    $now,
);
assertTrue($dinner->selectedMenu->categories[0]->placements[0]->priceOptions[0]->formattedAmount === '29.00', '3. Dinner price is a second Placement of the same Item');

$duplicates = $assembler->assemble(
    10,
    'Storage',
    [['uid' => 2, 'title' => 'Lunch', 'hidden' => 0]],
    [['uid' => 3, 'title' => 'Mains', 'menu' => 2, 'sorting' => 10, 'hidden' => 0]],
    [
        ['uid' => 5, 'category' => 3, 'item' => 1, 'sorting' => 20, 'hidden' => 0, 'starttime' => 0, 'endtime' => 0],
        ['uid' => 4, 'category' => 3, 'item' => 1, 'sorting' => 10, 'hidden' => 0, 'starttime' => 0, 'endtime' => 0],
    ],
    [['uid' => 1, 'title' => 'Burger', 'hidden' => 0]],
    [
        ['uid' => 9, 'placement' => 5, 'label' => 'Second listing', 'amount' => 1300, 'sorting' => 10, 'hidden' => 0],
        ['uid' => 8, 'placement' => 4, 'label' => 'First listing', 'amount' => 900, 'sorting' => 10, 'hidden' => 0],
    ],
    2,
    $now,
);
$duplicateGroups = $duplicates->selectedMenu->categories[0]->placements;
assertTrue(count($duplicateGroups) === 2, '4. two Placements for the same Category+Item stay two UI groups');
assertTrue($duplicateGroups[0]->uid === 4 && $duplicateGroups[1]->uid === 5, '4. duplicate Placements keep their own identity and sorting');
assertTrue($duplicateGroups[0]->itemTitle === 'Burger' && $duplicateGroups[1]->itemTitle === 'Burger', '4. both groups still show the reused Item');

$sorted = $assembler->assemble(
    10,
    'Storage',
    [['uid' => 2, 'title' => 'Lunch', 'hidden' => 0]],
    [
        ['uid' => 12, 'title' => 'Desserts', 'menu' => 2, 'sorting' => 20, 'hidden' => 0],
        ['uid' => 11, 'title' => 'Mains', 'menu' => 2, 'sorting' => 10, 'hidden' => 0],
    ],
    [
        ['uid' => 22, 'category' => 11, 'item' => 1, 'sorting' => 20, 'hidden' => 0, 'starttime' => 0, 'endtime' => 0],
        ['uid' => 21, 'category' => 11, 'item' => 2, 'sorting' => 10, 'hidden' => 0, 'starttime' => 0, 'endtime' => 0],
    ],
    [
        ['uid' => 2, 'title' => 'Soup', 'hidden' => 0],
        ['uid' => 1, 'title' => 'Steak', 'hidden' => 0],
    ],
    [
        ['uid' => 32, 'placement' => 21, 'label' => 'Bowl', 'amount' => 800, 'sorting' => 20, 'hidden' => 0],
        ['uid' => 31, 'placement' => 21, 'label' => 'Cup', 'amount' => 400, 'sorting' => 10, 'hidden' => 0],
    ],
    2,
    $now,
);
assertTrue($sorted->selectedMenu->categories[0]->title === 'Mains', '5. categories preserve sorting');
assertTrue($sorted->selectedMenu->categories[1]->title === 'Desserts', '5. later category remains after Mains');
assertTrue($sorted->selectedMenu->categories[0]->placements[0]->itemTitle === 'Soup', '5. placements preserve sorting');
assertTrue($sorted->selectedMenu->categories[0]->placements[0]->priceOptions[0]->label === 'Cup', '5. PriceOptions preserve sorting');

$emptyMenu = $assembler->assemble(
    10,
    'Storage',
    [['uid' => 2, 'title' => 'Lunch', 'hidden' => 0]],
    [],
    [],
    [],
    [],
    2,
    $now,
);
assertTrue($emptyMenu->emptyState === '' && $emptyMenu->selectedMenu !== null, '7. empty menu still selects the Menu tab');
assertTrue($emptyMenu->selectedMenu->categories === [], '7. empty menu has zero categories');

$emptyCategory = $assembler->assemble(
    10,
    'Storage',
    [['uid' => 2, 'title' => 'Lunch', 'hidden' => 0]],
    [['uid' => 3, 'title' => 'Mains', 'menu' => 2, 'sorting' => 10, 'hidden' => 0]],
    [],
    [],
    [],
    2,
    $now,
);
assertTrue($emptyCategory->selectedMenu->categories[0]->placements === [], '7. empty category keeps a section with no placements');

$noMenus = $assembler->assemble(10, 'Storage', [], [], [], [], [], 0, $now);
assertTrue($noMenus->emptyState === 'noMenus', '7. storage page with no menus uses the noMenus empty state');

$hidden = $assembler->assemble(
    10,
    'Storage',
    [['uid' => 2, 'title' => 'Lunch', 'hidden' => 0]],
    [['uid' => 3, 'title' => 'Mains', 'menu' => 2, 'sorting' => 10, 'hidden' => 0]],
    [['uid' => 4, 'category' => 3, 'item' => 1, 'sorting' => 10, 'hidden' => 1, 'starttime' => $now + 100, 'endtime' => 0]],
    [['uid' => 1, 'title' => 'Soup', 'hidden' => 1]],
    [['uid' => 8, 'placement' => 4, 'label' => '', 'amount' => 400, 'sorting' => 10, 'hidden' => 0]],
    2,
    $now,
);
assertTrue(
    in_array('hidden', $hidden->selectedMenu->categories[0]->placements[0]->statusKeys, true)
    && in_array('scheduled', $hidden->selectedMenu->categories[0]->placements[0]->statusKeys, true)
    && in_array('itemHidden', $hidden->selectedMenu->categories[0]->placements[0]->statusKeys, true),
    'hidden and scheduled records remain in the DTO instead of disappearing'
);

if ($failures > 0) {
    echo "\n{$failures} failing assertion(s)\n";
    exit(1);
}

echo "\nAll MenuGraphAssembler tests passed.\n";
exit(0);
