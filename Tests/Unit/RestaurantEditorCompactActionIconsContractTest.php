<?php

declare(strict_types=1);

/**
 * Static contract: compact saved-table row actions/status use TYPO3 Core icons.
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
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$categoryCell = file_get_contents($root . '/Resources/Private/Partials/RestaurantEditor/CategoryCell.html') ?: '';
$statusPartial = file_get_contents($root . '/Resources/Private/Partials/RestaurantEditor/Status.html') ?: '';
$iconLink = file_get_contents($root . '/Resources/Private/Partials/RestaurantEditor/IconActionLink.html') ?: '';
$xlf = file_get_contents($root . '/Resources/Private/Language/locallang_mod_editor.xlf') ?: '';
$css = file_get_contents($root . '/Resources/Public/Css/restaurant-editor.css') ?: '';
$js = file_get_contents($root . '/Resources/Public/JavaScript/restaurant-editor-table.js') ?: '';

$savedTable = '';
if (preg_match(
    '/arp-editor-table--saved[\s\S]*?<\/table>/',
    $template,
    $tableMatch
)) {
    $savedTable = $tableMatch[0];
}
assertTrue($savedTable !== '', 'saved table markup extractable');

assertTrue(
    !str_contains($categoryCell, 'button.openRecord')
    && !preg_match('/>\s*<f:translate key="\{lll\}button\.openRecord"\/>/', $categoryCell),
    'Category row no longer renders visible "Open record" action text'
);
assertTrue(
    str_contains($categoryCell, 'category.editUrl')
    && str_contains($categoryCell, 'action.openCategoryRecord')
    && str_contains($categoryCell, "icon: 'actions-open'"),
    'Category record action still exists'
);

assertTrue(
    !preg_match('/<f:translate key="\{lll\}button\.openItem"\/>/', $savedTable),
    'Item row no longer renders visible "Open item" action text'
);
assertTrue(
    str_contains($savedTable, 'placement.itemEditUrl')
    && str_contains($savedTable, 'action.openItemRecord')
    && str_contains($savedTable, "icon: 'actions-open'"),
    'Item record action still exists'
);

assertTrue(
    !preg_match(
        '/data-arp-edit-price="1"[^>]*>\s*<f:translate key="\{lll\}priceEdit\.editPrice"\/>/',
        $savedTable
    )
    && !preg_match(
        '/data-arp-edit-price="1">\s*<f:translate/',
        $savedTable
    ),
    'Price row no longer renders visible "Edit price" text'
);
assertTrue(
    str_contains($savedTable, 'option.editPriceUrl')
    && str_contains($savedTable, 'data-arp-edit-price="1"')
    && str_contains($savedTable, 'priceEdit.editPrice')
    && str_contains($savedTable, 'identifier="actions-open"'),
    'PriceEdit URL/action still exists'
);
assertTrue(
    str_contains(
        file_get_contents($root . '/Classes/Backend/Controller/RestaurantEditorController.php') ?: '',
        "'priceOption' => \$optionUid"
    ),
    'PriceEdit routing is unchanged'
);

assertTrue(
    str_contains($statusPartial, 'identifier="actions-eye"')
    && str_contains($statusPartial, "value=\"visible\"")
    && str_contains($statusPartial, 'status.visible'),
    'visible icon still uses actions-eye'
);
$visibleStatusCase = '';
if (preg_match('/value="visible"[\s\S]*?<\/f:case>/', $statusPartial, $visibleStatusMatch)) {
    $visibleStatusCase = $visibleStatusMatch[0];
}
assertTrue(
    $visibleStatusCase !== ''
    && str_contains($visibleStatusCase, 'identifier="actions-eye"')
    && !str_contains($visibleStatusCase, 'overlay-hidden'),
    'visible status uses actions-eye with no overlay-hidden'
);
$hiddenStatusCase = '';
if (preg_match('/value="hidden"[\s\S]*?<\/f:case>/', $statusPartial, $hiddenStatusMatch)) {
    $hiddenStatusCase = $hiddenStatusMatch[0];
}
assertTrue(
    $hiddenStatusCase !== ''
    && str_contains($hiddenStatusCase, 'identifier="actions-eye"')
    && str_contains($hiddenStatusCase, 'overlay="overlay-hidden"')
    && !str_contains($hiddenStatusCase, 'actions-edit-hide')
    && !str_contains($hiddenStatusCase, 'actions-edit-unhide'),
    'hidden status uses actions-eye + overlay-hidden, not edit-hide/unhide'
);
assertTrue(
    str_contains($statusPartial, 'identifier="actions-clock"'),
    'scheduled icon still uses actions-clock'
);

foreach (['visible', 'hidden', 'scheduled'] as $statusKey) {
    assertTrue(
        preg_match(
            '/value="' . $statusKey . '"[\s\S]*?<span class="arp-editor-sr">\{statusLabel\}<\/span>/s',
            $statusPartial
        ) === 1,
        "{$statusKey} iconized status contains visually hidden localized text"
    );
}
assertTrue(
    preg_match(
        '/value="scheduled"[\s\S]*?title="\{statusLabel\}"[\s\S]*?aria-label="\{statusLabel\}"/s',
        $statusPartial
    ) === 1,
    'scheduled status title/aria-label remain present'
);
foreach (['visible', 'hidden'] as $statusKey) {
    $case = '';
    if (preg_match('/value="' . $statusKey . '"[\s\S]*?<\/f:case>/', $statusPartial, $caseMatch)) {
        $case = $caseMatch[0];
    }
    assertTrue(
        $case !== ''
        && str_contains($case, 'title="{statusLabel}"')
        && str_contains($case, 'aria-label="{statusLabel}"')
        && str_contains($case, 'title="{reviewLabel}"')
        && str_contains($case, 'aria-label="{reviewLabel}"'),
        "{$statusKey} keeps non-action title/aria-label and clickable review labels"
    );
}

assertTrue(
    !preg_match(
        '/arp-editor-status--(?:visible|hidden|scheduled)"[^>]*>\s*\{statusLabel\}/',
        $statusPartial
    )
    && preg_match_all('/arp-editor-status--icon/', $statusPartial) >= 3,
    'visible textual label is NOT rendered as ordinary visible service text'
);
assertTrue(
    preg_match(
        '/value="itemHidden">\s*<span class="arp-editor-status arp-editor-status--itemHidden">\s*<f:translate key="\{lll\}status\.itemHidden"\/>/s',
        $statusPartial
    ) === 1
    && !preg_match('/value="itemHidden"[\s\S]*?core:icon/', $statusPartial)
    && !preg_match('/value="itemHidden"[\s\S]*?arp-editor-sr/', $statusPartial),
    'itemHidden textual fallback remains unchanged'
);

assertTrue(
    str_contains($iconLink, 'title="{label}"')
    && str_contains($iconLink, 'aria-label="{label}"')
    && str_contains($savedTable, 'title="{editPriceLabel}"')
    && str_contains($savedTable, 'aria-label="{editPriceLabel}"')
    && str_contains($statusPartial, 'aria-label="{statusLabel}"'),
    'every icon-only action has accessible title/label'
);

assertTrue(
    str_contains($template, 'action.openMenuRecord')
    && str_contains($template, 'menuTab.editUrl')
    && !preg_match(
        '/menuTab\.editUrl[\s\S]{0,200}<f:translate key="\{lll\}button\.openRecord"\/>/',
        $template
    ),
    'menu record action is iconized'
);

assertTrue(
    str_contains($categoryCell, 'category.editUrl')
    && str_contains($savedTable, 'placement.itemEditUrl')
    && str_contains($template, 'menuTab.editUrl'),
    'record_edit URLs are unchanged'
);
assertTrue(
    str_contains($template, 'data-arp-editor-search="1"'),
    'saved-table search hook retained'
);
assertTrue(
    str_contains($savedTable, 'data-arp-sort=')
    && str_contains($template, 'data-arp-editor-reset="1"'),
    'saved-table sort/reset hooks unchanged'
);

$coreIcons = ['actions-open', 'actions-eye', 'overlay-hidden', 'actions-clock'];
foreach ($coreIcons as $iconId) {
    assertTrue(
        str_contains($categoryCell . $savedTable . $statusPartial . $iconLink, $iconId)
        || str_contains($statusPartial, $iconId)
        || str_contains($iconLink, '{icon}'),
        "Core icon identifier present: {$iconId}"
    );
}
assertTrue(
    !preg_match('/<svg[\s\S]*?<\/svg>/', $categoryCell . $savedTable . $statusPartial . $iconLink)
    && str_contains($iconLink, 'core:icon')
    && str_contains($statusPartial, 'core:icon'),
    'selected icons are TYPO3 Core icons, not custom SVG markup'
);
assertTrue(
    str_contains($css, '.arp-editor-icon-action')
    && !str_contains($js, 'tooltip')
    && !str_contains($js, 'Tooltip'),
    'compact action CSS present; no tooltip JS'
);
assertTrue(
    str_contains($xlf, 'Open category record')
    && str_contains($xlf, 'Open item record')
    && str_contains($xlf, 'Open menu record')
    && str_contains($xlf, 'id="priceEdit.editPrice"')
    && preg_match('/id="status\.visible">\s*<source>Visible<\/source>/s', $xlf) === 1,
    'accessible title localization keys present'
);

echo $failures === 0
    ? "\nAll RestaurantEditorCompactActionIconsContract tests passed.\n"
    : "\n{$failures} RestaurantEditorCompactActionIconsContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
