<?php

declare(strict_types=1);

/**
 * Bounded contract: iconized status cells still expose semantic text to
 * restaurant-editor-table.js textValue/searchText via cell.innerText.
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
$css = file_get_contents($root . '/Resources/Public/Css/restaurant-editor.css') ?: '';
$js = file_get_contents($root . '/Resources/Public/JavaScript/restaurant-editor-table.js') ?: '';

$srRule = '';
if (preg_match('/\.arp-editor-sr\s*\{([^}]+)\}/s', $css, $srMatch)) {
    $srRule = $srMatch[1];
}
assertTrue($srRule !== '', 'arp-editor-sr CSS rule extractable');
assertTrue(
    str_contains($srRule, 'clip:')
    && str_contains($srRule, 'position: absolute')
    && !preg_match('/display\s*:\s*none/i', $srRule)
    && !preg_match('/visibility\s*:\s*hidden/i', $srRule),
    'arp-editor-sr is clipped (not display:none) so innerText remains available'
);

assertTrue(
    preg_match(
        '/function cellSearchText\([\s\S]*?return cell\.innerText/s',
        $js
    ) === 1,
    'searchText path uses cell.innerText for non-control cells'
);
assertTrue(
    preg_match(
        '/function textValue\([\s\S]*?data-arp-sort-value[\s\S]*?return \(cell\.innerText/s',
        $js
    ) === 1,
    'textValue falls back to cell.innerText when no data-arp-sort-value'
);
assertTrue(
    str_contains($js, "data-arp-col=\"' + column + '\"")
    || str_contains($js, 'data-arp-col="\' + column + \'"')
    || str_contains($js, "[data-arp-col=\"' + column + '\"]"),
    'textValue reads named data-arp-col cells including status'
);
assertTrue(
    str_contains($js, "data-arp-sort=\"status\"") === false
    && str_contains(
        file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '',
        'data-arp-sort="status"'
    ),
    'Status column remains sortable via data-arp-sort=status'
);

foreach (['visible', 'hidden', 'scheduled'] as $statusKey) {
    assertTrue(
        preg_match(
            '/value="' . $statusKey . '"[\s\S]*?arp-editor-status--icon[\s\S]*?<span class="arp-editor-sr">\{statusLabel\}<\/span>/s',
            $statusPartial
        ) === 1,
        "{$statusKey}: semantic label is in DOM text for innerText-based sort/search"
    );
}

assertTrue(
    !str_contains($js, 'aria-label')
    && !str_contains($js, 'getAttribute(\'title\')')
    && !str_contains($js, 'Visible')
    && !str_contains($js, 'Hidden')
    && !str_contains($js, 'Scheduled'),
    'JS has no English status special-case; relies on generic innerText'
);

echo $failures === 0
    ? "\nAll RestaurantEditorStatusTableSemanticsContract tests passed.\n"
    : "\n{$failures} RestaurantEditorStatusTableSemanticsContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
