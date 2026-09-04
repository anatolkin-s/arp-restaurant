<?php

declare(strict_types=1);

/**
 * Clickable PriceOption visibility icons must keep semantic status text
 * available to restaurant-editor-table.js via cell.innerText.
 */

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

$root = dirname(__DIR__, 2);
$statusPartial = file_get_contents($root . '/Resources/Private/Partials/RestaurantEditor/Status.html') ?: '';
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$js = file_get_contents($root . '/Resources/Public/JavaScript/restaurant-editor-table.js') ?: '';
$css = file_get_contents($root . '/Resources/Public/Css/restaurant-editor.css') ?: '';

$savedTable = '';
if (preg_match('/arp-editor-table--saved[\s\S]*?<\/table>/', $template, $tableMatch)) {
    $savedTable = $tableMatch[0];
}
assertTrue($savedTable !== '', 'saved table extractable');
assertTrue(
    str_contains($savedTable, 'data-arp-sort="status"')
    && str_contains($savedTable, 'data-arp-col="status"'),
    'status column remains sortable/searchable'
);

$srRule = '';
if (preg_match('/\.arp-editor-sr\s*\{([^}]+)\}/s', $css, $srMatch)) {
    $srRule = $srMatch[1];
}
assertTrue(
    $srRule !== ''
    && str_contains($srRule, 'clip:')
    && !preg_match('/display\s*:\s*none/i', $srRule),
    'visually hidden status text remains in the layout for innerText'
);

foreach (['visible', 'hidden'] as $statusKey) {
    $case = '';
    if (preg_match('/value="' . $statusKey . '"[\s\S]*?<\/f:case>/', $statusPartial, $caseMatch)) {
        $case = $caseMatch[0];
    }
    assertTrue($case !== '', "{$statusKey} case extractable");
    assertTrue(
        substr_count($case, '<span class="arp-editor-sr">{statusLabel}</span>') === 2,
        "{$statusKey}: clickable and non-action branches both expose semantic hidden text"
    );
    assertTrue(
        str_contains($case, 'priceVisibility.entry.')
        && !str_contains($case, '<span class="arp-editor-sr">{reviewLabel}</span>'),
        "{$statusKey}: search/sort text stays Visible/Hidden, not the review tooltip"
    );
}

assertTrue(
    preg_match(
        '/value="scheduled"[\s\S]*?<span class="arp-editor-sr">\{statusLabel\}<\/span>/s',
        $statusPartial
    ) === 1
    && !preg_match('/value="scheduled"[\s\S]*?reviewUrl/s', $statusPartial),
    'scheduled remains non-action with semantic hidden text'
);

assertTrue(
    preg_match(
        '/function cellSearchText\([\s\S]*?return cell\.innerText/s',
        $js
    ) === 1,
    'search still uses cell.innerText'
);

echo $failures === 0
    ? "\nAll RestaurantEditorVisibilityStatusSemanticsContract tests passed.\n"
    : "\n{$failures} RestaurantEditorVisibilityStatusSemanticsContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
