<?php

declare(strict_types=1);

/**
 * Static contract checks for EDITOR-2C1 existing PriceOption edit review (no write).
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
$reader = file_get_contents($root . '/Classes/Backend/Editor/PriceEdit/RestaurantPriceOptionEditReader.php') ?: '';
$assessor = file_get_contents($root . '/Classes/Backend/Editor/PriceEdit/PriceOptionEditGraphAssessor.php') ?: '';
$builder = file_get_contents($root . '/Classes/Backend/Editor/PriceEdit/PriceOptionUpdatePlanBuilder.php') ?: '';
$guard = file_get_contents($root . '/Classes/Backend/Editor/BackendAccessGuard.php') ?: '';
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$priceEditDir = $root . '/Classes/Backend/Editor/PriceEdit';

$priceEditBlob = $assessor . $builder . $reader;
foreach (glob($priceEditDir . '/*.php') ?: [] as $file) {
    $priceEditBlob .= file_get_contents($file) ?: '';
}

assertTrue(
    str_contains($reader, 'executeQuery')
    && !preg_match('/\b(insert|update|delete|executeStatement)\s*\(/i', $reader)
    && !str_contains($reader, 'process_datamap')
    && !str_contains($reader, 'DataHandler'),
    '8. reader remains SELECT-only'
);

assertTrue(
    str_contains($controller, "PRICE_EDIT_REVIEW_ACTION = 'priceOptionEditReview'")
    && str_contains($controller, 'priceEditToken')
    && str_contains($template, 'name="priceOptionEditReview"')
    && str_contains($template, 'name="priceEditToken"'),
    '20. dedicated priceOptionEditReview action/token'
);

$reviewMethod = '';
if (preg_match('/function processPriceOptionEditReview\(.*?function buildPriceOptionEditPanel/s', $controller, $m)) {
    $reviewMethod = $m[0];
}
assertTrue($reviewMethod !== '', 'priceOptionEditReview handler extractable');
assertTrue(
    strpos($reviewMethod, 'validateToken') < strpos($reviewMethod, 'priceOptionEditReader->load')
    && strpos($reviewMethod, 'validateToken') < strpos($reviewMethod, 'priceOptionUpdatePlanBuilder->prepare'),
    '21. CSRF before preparation'
);
assertTrue(
    strpos($reviewMethod, 'priceOptionEditReader->load') < strpos($reviewMethod, 'priceOptionUpdatePlanBuilder->prepare'),
    '22. server re-read before plan'
);
assertTrue(
    !str_contains($reviewMethod, "body['public_uuid']")
    && !str_contains($reviewMethod, "body['tstamp']")
    && !str_contains($reviewMethod, "body['placement']")
    && !str_contains($reviewMethod, "body['amountMinor']"),
    '23. posted uuid/tstamp/relations are not authority'
);
assertTrue(
    !preg_match('/\b(DataHandler|process_datamap|process_cmdmap)\b/', $reviewMethod)
    && !preg_match('/\b(DataHandler|process_datamap|process_cmdmap|executeStatement)\b/', $priceEditBlob),
    '24. no DataHandler in price-edit package/controller branch'
);
assertTrue(
    !str_contains($reviewMethod, 'RedirectResponse')
    && !str_contains($reviewMethod, '303'),
    '25. no RedirectResponse/write PRG for review'
);

$priceRowBlock = '';
if (preg_match(
    '/placement\.priceOptions\}\" as=\"option\"(.*?)<\/f:for>\s*<f:then>\s*<f:else>/s',
    $template,
    $rowMatch
) !== 1) {
    preg_match(
        '/<f:for each=\"\{placement\.priceOptions\}\" as=\"option\">(.*?)<\/f:for>/s',
        $template,
        $rowMatch
    );
    $priceRowBlock = $rowMatch[1] ?? '';
} else {
    $priceRowBlock = $rowMatch[1] ?? '';
}
assertTrue($priceRowBlock !== '', 'PriceOption row block extractable');
assertTrue(
    str_contains($priceRowBlock, 'option.editPriceUrl')
    && str_contains($priceRowBlock, 'priceEdit.editPrice')
    && str_contains($priceRowBlock, 'data-arp-edit-price'),
    '26. Edit price shown only for real PriceOption rows'
);

$emptyPlacementRow = '';
if (preg_match(
    '/empty\.noPriceOptions"\/>[\s\S]*?<\/tr>/',
    $template,
    $emptyMatch
)) {
    $emptyPlacementRow = $emptyMatch[0];
}
assertTrue($emptyPlacementRow !== '', 'empty PriceOption placeholder row extractable');
assertTrue(
    substr_count($template, 'option.editPriceUrl') === 2
    && !str_contains($emptyPlacementRow, 'editPriceUrl')
    && !str_contains($emptyPlacementRow, 'data-arp-edit-price'),
    '26b. Edit price not shown on empty PriceOption placeholder rows'
);

$reviewCard = '';
if (preg_match(
    '/data-arp-price-update-plan="1"[\s\S]*?priceEdit\.savingNotAvailable[\s\S]*?<\/div>/',
    $template,
    $cardMatch
)) {
    $reviewCard = $cardMatch[0];
}
assertTrue($reviewCard !== '', 'review card extractable');
assertTrue(
    str_contains($reviewCard, 'priceEdit.nothingWritten'),
    '27. review card says Nothing has been written yet'
);
assertTrue(
    !str_contains($reviewCard, 'name="bulkApply"')
    && !str_contains($reviewCard, 'name="priceOptionEditReview"')
    && !preg_match('/<(button|input)[^>]*(Save|Update to menu|Apply to menu)/i', $reviewCard)
    && str_contains($reviewCard, 'priceEdit.savingNotAvailable'),
    '28. no Save/Apply write control in review card'
);
assertTrue(
    str_contains($template, 'data-arp-editor-search="1"')
    && str_contains($template, 'data-arp-sort=')
    && str_contains($template, 'data-arp-editor-reset="1"'),
    '29. saved-table search/sort hooks retained'
);

assertTrue(
    str_contains($guard, 'priceOptionEditPermissionBlocker')
    && str_contains($guard, "TABLE_PRICEOPTION")
    && str_contains($controller, 'priceOptionEditPermissionBlocker'),
    'permissions: PriceOption label/amount edit preflight present'
);
assertTrue(
    str_contains($controller, "'priceOption'")
    && str_contains($controller, "'priceOption' => \$optionUid"),
    'GET priceOption selection state wired'
);

echo $failures === 0 ? "\nAll RestaurantPriceOptionEditContract tests passed.\n" : "\n{$failures} RestaurantPriceOptionEditContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
