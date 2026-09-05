<?php

declare(strict_types=1);

/**
 * Bounded contract checks for EDITOR-2C3 PriceOption visibility review (no write).
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
$controller = file_get_contents($root . '/Classes/Backend/Controller/RestaurantEditorController.php') ?: '';
$guard = file_get_contents($root . '/Classes/Backend/Editor/BackendAccessGuard.php') ?: '';
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$statusPartial = file_get_contents($root . '/Resources/Private/Partials/RestaurantEditor/Status.html') ?: '';
$xlf = file_get_contents($root . '/Resources/Private/Language/locallang_mod_editor.xlf') ?: '';
$visibilityDir = $root . '/Classes/Backend/Editor/Visibility';

$visibilityBlob = '';
foreach (glob($visibilityDir . '/*.php') ?: [] as $file) {
    $visibilityBlob .= file_get_contents($file) ?: '';
}

assertTrue(
    str_contains($statusPartial, 'identifier="actions-eye"')
    && str_contains($statusPartial, 'overlay="overlay-hidden"')
    && str_contains($statusPartial, 'data-arp-edit-visibility="1"')
    && !str_contains($statusPartial, 'actions-edit-hide')
    && !str_contains($statusPartial, 'actions-edit-unhide'),
    '1. Core icon entry point for visibility review'
);

$visibleCase = '';
if (preg_match('/value="visible"[\s\S]*?<\/f:case>/', $statusPartial, $visibleMatch)) {
    $visibleCase = $visibleMatch[0];
}
$hiddenCase = '';
if (preg_match('/value="hidden"[\s\S]*?<\/f:case>/', $statusPartial, $hiddenMatch)) {
    $hiddenCase = $hiddenMatch[0];
}
assertTrue($visibleCase !== '' && $hiddenCase !== '', 'visible/hidden status cases extractable');
assertTrue(
    str_contains($visibleCase, 'identifier="actions-eye"')
    && !str_contains($visibleCase, 'overlay-hidden'),
    '1b. visible status: actions-eye, no overlay-hidden'
);
assertTrue(
    str_contains($hiddenCase, 'identifier="actions-eye"')
    && str_contains($hiddenCase, 'overlay="overlay-hidden"')
    && !str_contains($hiddenCase, 'actions-edit-hide')
    && !str_contains($hiddenCase, 'actions-edit-unhide'),
    '1c. hidden status: actions-eye + overlay-hidden, not edit-hide/unhide'
);
assertTrue(
    str_contains($visibleCase, 'priceVisibility.entry.visible')
    && str_contains($visibleCase, 'title="{reviewLabel}"')
    && str_contains($visibleCase, 'aria-label="{reviewLabel}"')
    && str_contains($hiddenCase, 'priceVisibility.entry.hidden')
    && str_contains($hiddenCase, 'title="{reviewLabel}"')
    && str_contains($hiddenCase, 'aria-label="{reviewLabel}"'),
    '2. clickable status uses review tooltip / aria-label'
);
assertTrue(
    str_contains($xlf, 'id="priceVisibility.entry.visible"')
    && str_contains($xlf, 'Visible — hide price option')
    && str_contains($xlf, 'id="priceVisibility.entry.hidden"')
    && str_contains($xlf, 'Hidden — show price option'),
    '2b. tooltip copy is localized, not row clutter'
);
assertTrue(
    !str_contains($template, 'Edit visibility')
    && !preg_match('/>\s*<f:translate key="\{lll\}priceVisibility\.review"\/>\s*<\/a>/', $template),
    '2c. no textual Edit visibility on every row'
);

assertTrue(
    str_contains($controller, "PRICE_VISIBILITY_REVIEW_ACTION = 'priceOptionVisibilityReview'")
    && str_contains($controller, 'priceVisibilityToken')
    && str_contains($template, 'name="priceOptionVisibilityReview"')
    && str_contains($template, 'name="priceVisibilityToken"'),
    '3. dedicated review token/action'
);

$visibilityPanel = '';
if (preg_match(
    '/data-arp-price-visibility="1"[\s\S]*?<\/section>/',
    $template,
    $panelMatch
)) {
    $visibilityPanel = $panelMatch[0];
}
assertTrue($visibilityPanel !== '', 'visibility panel extractable');
assertTrue(
    !str_contains($visibilityPanel, 'name="priceEditToken"')
    && !str_contains($visibilityPanel, 'name="priceEditApplyToken"')
    && !str_contains($visibilityBlob, 'priceOptionEditReview')
    && !str_contains($visibilityBlob, 'priceEditToken')
    && !str_contains($visibilityBlob, 'priceEditApplyToken')
    && !str_contains($visibilityBlob, 'priceOptionEditApply'),
    '3b. visibility panel/package does not reuse price-edit tokens'
);
$savedTableRows = '';
if (preg_match('/arp-editor-table--saved[\s\S]*?<\/table>/', $template, $savedMatch)) {
    $savedTableRows = $savedMatch[0];
}
assertTrue($savedTableRows !== '', 'saved table extractable for visibility save isolation');
assertTrue(
    !str_contains($savedTableRows, 'priceVisibility.save')
    && !str_contains($savedTableRows, 'name="priceOptionVisibilityApply"')
    && !str_contains($visibilityPanel, 'name="priceOptionEditApply"'),
    '4. no Save visibility control on saved-table rows'
);
assertTrue(
    !str_contains($visibilityPanel, 'process_datamap')
    && !str_contains($visibilityPanel, 'DataHandler')
    && !str_contains($visibilityPanel, 'name="priceEditApplyToken"')
    && str_contains($visibilityPanel, 'name="priceOptionVisibilityReview"'),
    '5. no DataHandler/write form in visibility panel'
);
assertTrue(
    str_contains($visibilityPanel, 'priceVisibility.cancel')
    && str_contains($visibilityPanel, 'href="{priceVisibility.cancelUrl}"'),
    '6. Cancel present'
);
assertTrue(
    str_contains($visibilityPanel, 'priceVisibility.openFullRecord')
    && str_contains($visibilityPanel, 'priceVisibility.context.recordEditUrl'),
    '7. Open full record present'
);

$reviewCard = '';
if (preg_match(
    '/data-arp-visibility-update-plan="1"[\s\S]*?<\/div>\s*<\/f:if>/',
    $visibilityPanel,
    $cardMatch
)) {
    $reviewCard = $cardMatch[0];
}
assertTrue($reviewCard !== '', 'READY card extractable');
assertTrue(
    str_contains($reviewCard, 'priceVisibility.nothingWritten')
    && str_contains($xlf, 'Nothing has been written yet.'),
    '8. READY copy says nothing written'
);
assertTrue(
    str_contains($reviewCard, 'data-arp-visibility-scope="1"')
    && str_contains($reviewCard, 'priceVisibility.scope')
    && str_contains($xlf, 'Changing this field affects this PriceOption only.')
    && !str_contains($xlf, 'Make item visible'),
    '9. scope copy says PriceOption only'
);
assertTrue(
    str_contains($reviewCard, 'form="arp-price-visibility-form"')
    && str_contains($reviewCard, 'name="priceOptionVisibilityApply"')
    && str_contains($reviewCard, 'priceVisibility.save')
    && str_contains($reviewCard, 'priceVisibility.nothingWritten')
    && !str_contains($reviewCard, 'name="visibility"')
    && !str_contains($reviewCard, 'name="confirmedFingerprint"')
    && !str_contains($reviewCard, '<form'),
    '10. READY Save targets authoritative form; no shadow visibility/fingerprint in card'
);

$reviewMethod = '';
if (preg_match(
    '/function processPriceOptionVisibilityReview\(.*?function buildPriceOptionVisibilityPanel/s',
    $controller,
    $methodMatch
)) {
    $reviewMethod = $methodMatch[0];
}
assertTrue($reviewMethod !== '', 'visibility review handler extractable');
assertTrue(
    !preg_match('/\b(DataHandler|process_datamap|process_cmdmap|RedirectResponse)\b/', $reviewMethod),
    '11. review handler has no DataHandler / write PRG'
);
assertTrue(
    str_contains($controller, "'priceOptionVisibility'")
    && str_contains($controller, "'priceOptionVisibility' => \$optionUid"),
    '12. GET priceOptionVisibility selection wired'
);

assertTrue(
    str_contains($guard, 'priceOptionVisibilityPermissionBlocker')
    && str_contains($guard, "'hidden'")
    && str_contains($controller, 'priceOptionVisibilityPermissionBlocker'),
    '13. permission preflight wired for hidden field'
);

assertTrue(
    str_contains($visibilityBlob, 'executeQuery')
    && !preg_match('/\b(insert|update|delete|executeStatement)\s*\(/i', $visibilityBlob)
    && !str_contains($visibilityBlob, 'DataHandler')
    && !str_contains($visibilityBlob, 'process_datamap')
    && !str_contains($visibilityBlob, 'process_cmdmap'),
    '14. visibility package is SELECT-only / no DataHandler'
);

$priceRowBlock = '';
if (preg_match(
    '/<f:for each=\"\{placement\.priceOptions\}\" as=\"option\">(.*?)<\/f:for>/s',
    $template,
    $rowMatch
)) {
    $priceRowBlock = $rowMatch[1] ?? '';
}
assertTrue($priceRowBlock !== '', 'PriceOption row block extractable');
assertTrue(
    str_contains($priceRowBlock, 'option.editVisibilityUrl')
    && str_contains($priceRowBlock, 'option.statusKeys'),
    '15. PriceOption rows own status/visibility entry'
);

$emptyPlacementRow = '';
if (preg_match(
    '/empty\.noPriceOptions"\/>[\s\S]*?<\/tr>/',
    $template,
    $emptyMatch
)) {
    $emptyPlacementRow = $emptyMatch[0];
}
assertTrue(
    $emptyPlacementRow !== ''
    && str_contains($emptyPlacementRow, 'placement.statusKeys')
    && !str_contains($emptyPlacementRow, 'editVisibilityUrl'),
    '16. empty PriceOption placeholder keeps non-action placement status'
);

echo $failures === 0
    ? "\nAll RestaurantPriceOptionVisibilityContract tests passed.\n"
    : "\n{$failures} RestaurantPriceOptionVisibilityContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
