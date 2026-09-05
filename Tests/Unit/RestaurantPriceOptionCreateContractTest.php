<?php

declare(strict_types=1);

/**
 * EDITOR-2D1: review-only add PriceOption under an existing Placement.
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
$reader = file_get_contents($root . '/Classes/Backend/Editor/PriceCreate/RestaurantPriceOptionCreateReader.php') ?: '';
$assembler = file_get_contents($root . '/Classes/Backend/Editor/MenuGraphAssembler.php') ?: '';
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$xlf = file_get_contents($root . '/Resources/Private/Language/locallang_mod_editor.xlf') ?: '';
$js = file_get_contents($root . '/Resources/Public/JavaScript/restaurant-editor-table.js') ?: '';
$guard = file_get_contents($root . '/Classes/Backend/Editor/BackendAccessGuard.php') ?: '';

$createDir = $root . '/Classes/Backend/Editor/PriceCreate';
$createBlob = '';
foreach (glob($createDir . '/*.php') ?: [] as $file) {
    $createBlob .= file_get_contents($file) ?: '';
}
assertTrue($createBlob !== '', 'PriceCreate package extractable');
assertTrue(
    is_dir($createDir)
    && !is_dir($createDir . '/Write')
    && !str_contains($createBlob, 'DataHandler')
    && !str_contains($createBlob, 'process_datamap')
    && !str_contains($createBlob, 'process_cmdmap')
    && !preg_match('/\b(insert|update|delete|executeStatement)\s*\(/i', $createBlob)
    && !preg_match('/\b(INSERT|UPDATE|DELETE)\b/', $createBlob),
    'I/no-write: PriceCreate package has no Write/ writer / mutation APIs'
);

assertTrue(
    str_contains($reader, 'executeQuery')
    && !preg_match('/\b(insert|update|delete|executeStatement)\s*\(/i', $reader)
    && str_contains($reader, "'placement'")
    && str_contains($reader, 'fetchPriceOptionsForPlacement'),
    'reader remains SELECT-only and scopes PriceOptions to selected Placement'
);

assertTrue(
    str_contains($controller, "PRICE_CREATE_REVIEW_ACTION = 'priceOptionCreateReview'")
    && str_contains($controller, 'priceCreateToken')
    && str_contains($template, 'name="priceOptionCreateReview"')
    && str_contains($template, 'name="priceCreateToken"')
    && !str_contains($template, 'name="priceCreateApplyToken"')
    && !str_contains($template, 'priceOptionCreateApply'),
    'I. dedicated review action/token; no create Apply token'
);

$reviewMethod = '';
if (preg_match(
    '/function processPriceOptionCreateReview\(.*?function buildPriceOptionCreatePanel/s',
    $controller,
    $reviewMatch
) === 1) {
    $reviewMethod = $reviewMatch[0];
}
assertTrue($reviewMethod !== '', 'create review handler extractable');
assertTrue(
    strpos($reviewMethod, 'validateToken') < strpos($reviewMethod, 'priceOptionCreatePermissionBlocker')
    && strpos($reviewMethod, 'priceOptionCreatePermissionBlocker') < strpos($reviewMethod, 'priceOptionCreateReader->load')
    && strpos($reviewMethod, 'priceOptionCreateReader->load') < strpos($reviewMethod, 'priceOptionCreatePlanBuilder->prepare'),
    'I. CSRF before permission before fresh graph load before plan'
);
assertTrue(
    !str_contains($reviewMethod, "body['public_uuid']")
    && !str_contains($reviewMethod, "body['tstamp']")
    && !str_contains($reviewMethod, "body['category']")
    && !str_contains($reviewMethod, "body['item']")
    && str_contains($reviewMethod, "\$body['label']")
    && str_contains($reviewMethod, "\$body['price']"),
    'I. plan rebuilt from POST label/price + fresh DB context; posted UUID/tstamp/relations ignored'
);
assertTrue(
    !preg_match('/\b(DataHandler|process_datamap|process_cmdmap|RedirectResponse)\b/', $reviewMethod)
    && !str_contains($reviewMethod, '303'),
    'I. no DataHandler and no PRG/write redirect'
);

$savedTable = '';
if (preg_match('/arp-editor-table--saved[\s\S]*?<\/table>/', $template, $tableMatch) === 1) {
    $savedTable = $tableMatch[0];
}
assertTrue($savedTable !== '', 'saved table extractable');

$priceRows = '';
if (preg_match(
    '/<f:for each="\{placement\.priceOptions\}" as="option" iteration="optionIteration">([\s\S]*?)<\/f:for>/',
    $savedTable,
    $rowsMatch
) === 1) {
    $priceRows = $rowsMatch[1];
}
assertTrue($priceRows !== '', 'PriceOption rows extractable');
assertTrue(
    str_contains($priceRows, 'optionIteration.isFirst')
    && substr_count($priceRows, 'data-arp-add-price-option="1"') === 1
    && str_contains($priceRows, 'placement.addPriceOptionUrl')
    && str_contains($priceRows, 'identifier="actions-plus"'),
    'J. exactly one Add PriceOption action per Placement (first row only)'
);

$emptyRow = '';
if (preg_match(
    '/<f:else>\s*<tr data-arp-price="">([\s\S]*?)<\/tr>/',
    $savedTable,
    $emptyMatch
) === 1) {
    $emptyRow = $emptyMatch[1];
}
assertTrue(
    $emptyRow !== ''
    && str_contains($emptyRow, 'data-arp-add-price-option="1"')
    && str_contains($emptyRow, 'placement.addPriceOptionUrl'),
    'J. zero-price Placement still gets Add PriceOption action'
);
assertTrue(
    str_contains($assembler, 'priceOptionCreateUrlBuilder($placementUid, $menuUid)')
    && str_contains($controller, "'priceOptionCreate' => \$placementUid"),
    'J. action URL uses placement uid + menu'
);
assertTrue(
    str_contains($xlf, 'id="priceCreate.addPriceOption"')
    && str_contains($xlf, 'Add price option')
    && str_contains($priceRows, 'title="{addPriceOptionLabel}"')
    && str_contains($priceRows, 'aria-label="{addPriceOptionLabel}"')
    && !preg_match('/data-arp-add-price-option="1"[^>]*>\s*<f:translate/', $priceRows),
    'J. accessible title/aria; no visible service text'
);
assertTrue(
    !preg_match('/<svg[\s\S]*?<\/svg>/', $priceRows . $emptyRow)
    && !str_contains($js, 'priceOptionCreate')
    && !str_contains($js, 'arp-price-create')
    && !str_contains($template, '<script'),
    'J. Core icon only; no custom JS/SVG'
);

$createPanel = '';
if (preg_match('/data-arp-price-create="1"[\s\S]*?<\/section>/', $template, $panelMatch) === 1) {
    $createPanel = $panelMatch[0];
}
assertTrue($createPanel !== '', 'create panel extractable');
assertTrue(
    substr_count($createPanel, 'id="arp-price-create-form"') === 1
    && substr_count($createPanel, 'name="label"') === 1
    && substr_count($createPanel, 'name="price"') === 1
    && preg_match('/<input\b[^>]*id="arp-price-create-label"[^>]*>/', $createPanel, $variantInput) === 1
    && !preg_match('/\bmaxlength\s*=/i', $variantInput[0]),
    'K. one authoritative form; exactly one Variant and one Price; no native maxlength'
);
assertTrue(
    str_contains($createPanel, 'name="priceOptionCreateReview"')
    && !str_contains($createPanel, 'priceOptionCreateApply')
    && !str_contains($createPanel, 'data-arp-price-create-save')
    && !str_contains($createPanel, 'btn-primary'),
    'K. Review addition only; no Save button for createReady'
);
assertTrue(
    str_contains($xlf, 'PRICE OPTION CREATE · READY')
    && str_contains($xlf, 'Nothing has been written yet.')
    && str_contains($xlf, 'This will create one PriceOption under the existing Placement.')
    && str_contains($xlf, 'Saving is not available in this step.')
    && str_contains($createPanel, 'priceCreate.nothingWritten')
    && str_contains($createPanel, 'priceCreate.scope')
    && str_contains($createPanel, 'priceCreate.savingNotAvailable'),
    'K. READY says nothing written; one PriceOption under existing Placement; no save'
);
assertTrue(
    str_contains($createPanel, 'priceCreate.openPlacementRecord')
    && str_contains($createPanel, 'priceCreate.cancel')
    && str_contains($xlf, 'Open placement record'),
    'K. Open placement record fallback and Cancel'
);

assertTrue(
    str_contains($controller, "'priceOptionCreate'")
    && !str_contains($reviewMethod, 'priceOptionEditReview')
    && !str_contains($reviewMethod, 'priceVisibilityToken')
    && !str_contains($createPanel, 'priceEditToken')
    && !str_contains($createPanel, 'priceVisibilityToken'),
    'create review does not reuse PriceEdit or Visibility tokens'
);

assertTrue(
    str_contains($guard, 'priceOptionCreatePermissionBlocker')
    && str_contains($reviewMethod, 'priceOptionCreatePermissionBlocker'),
    'permission preflight wired'
);

$blockerCard = '';
if (preg_match('/<f:if condition="\{priceCreate.blockers\}">([\s\S]*?)<\/f:if>/', $createPanel, $blockerMatch) === 1) {
    $blockerCard = $blockerMatch[1];
}
assertTrue(
    str_contains($blockerCard, 'class="arp-editor-blocker"')
    && str_contains($blockerCard, 'role="alert"')
    && str_contains($blockerCard, 'aria-live="polite"')
    && str_contains($blockerCard, 'identifier="status-dialog-warning"')
    && str_contains($blockerCard, 'aria-hidden="true"')
    && str_contains($blockerCard, 'priceCreate.blocker.heading')
    && str_contains($xlf, 'id="priceCreate.blocker.heading"')
    && str_contains($xlf, 'PRICE OPTION CANNOT BE ADDED'),
    'L. conditional prominent blocker has accessible alert, Core icon and localized heading'
);
assertTrue(
    str_contains($blockerCard, 'class="arp-editor-blocker__body"')
    && !str_contains($blockerCard, 'typo3-message')
    && !str_contains($blockerCard, 'arp-editor-stale-warning')
    && str_contains($blockerCard, '<f:switch expression="{priceCreateBlocker.code}">')
    && str_contains($blockerCard, 'value="duplicateVariant"><f:translate key="{lll}priceCreate.error.duplicateVariant"')
    && str_contains($blockerCard, 'value="simplePriceMustBecomeVariantFirst"><f:translate key="{lll}priceCreate.error.simplePriceMustBecomeVariantFirst"')
    && substr_count($blockerCard, '<f:case ') === 24,
    'L. all blocker-specific cases remain in dedicated card, without legacy bare message styling'
);
assertTrue(
    strpos($createPanel, 'priceCreate.heading') < strpos($createPanel, 'data-arp-price-create-blocker')
    && strpos($createPanel, 'data-arp-price-create-blocker') < strpos($createPanel, 'priceCreate.context.menu')
    && str_contains($createPanel, 'value="{priceCreate.submittedLabel}"')
    && str_contains($createPanel, 'value="{priceCreate.submittedPrice}"')
    && preg_match('/<button type="submit" name="priceOptionCreateReview" value="1" class="btn btn-default">/', $createPanel) === 1,
    'L. blocker precedes context; submitted fields and enabled Review remain available'
);
assertTrue(
    !str_contains($blockerCard, 'data-arp-price-create-plan')
    && str_contains($createPanel, '<f:if condition="{priceCreate.review} && {priceCreate.review.outcome} == \'createReady\' && {priceCreate.review.plan}">')
    && substr_count($createPanel, 'data-arp-price-create-blocker="1"') === 1
    && !str_contains($createPanel, '<svg')
    && !str_contains($createPanel, '<script'),
    'L. blocker and READY retain separate state gates; no custom SVG or JS'
);
$css = file_get_contents($root . '/Resources/Public/Css/restaurant-editor.css') ?: '';
$blockerCss = '';
if (preg_match('/\.arp-editor-blocker \{([^}]+)\}/', $css, $cssMatch) === 1) {
    $blockerCss = $cssMatch[1];
}
assertTrue(
    str_contains($blockerCss, 'background: var(--typo3-state-warning-bg, #fbf6e5)')
    && str_contains($blockerCss, 'border-left-width: 4px')
    && str_contains($blockerCss, 'padding: 0.75rem 0.9rem')
    && str_contains($blockerCss, 'margin: 0 0 1rem')
    && preg_match('/\.arp-editor-blocker__heading \{[^}]*font-weight: 700;[^}]*text-transform: uppercase;/s', $css) === 1,
    'L. dedicated warning card has tinted background, strong border, spacing and bold uppercase heading'
);

echo $failures === 0
    ? "\nAll RestaurantPriceOptionCreateContract tests passed.\n"
    : "\n{$failures} RestaurantPriceOptionCreateContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
