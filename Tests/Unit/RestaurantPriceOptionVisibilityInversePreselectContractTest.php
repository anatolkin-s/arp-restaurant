<?php

declare(strict_types=1);

/**
 * EDITOR-2C4.3: status-icon GET entry preselects the inverse visibility.
 * Review/Apply submittedVisibility is never inverted again.
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
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$statusPartial = file_get_contents($root . '/Resources/Private/Partials/RestaurantEditor/Status.html') ?: '';
$assembler = file_get_contents($root . '/Classes/Backend/Editor/MenuGraphAssembler.php') ?: '';
$js = file_get_contents($root . '/Resources/Public/JavaScript/restaurant-editor-table.js') ?: '';
$xlf = file_get_contents($root . '/Resources/Private/Language/locallang_mod_editor.xlf') ?: '';
$writer = file_get_contents($root . '/Classes/Backend/Editor/Visibility/Write/RestaurantPriceOptionVisibilityWriter.php') ?: '';
$dataMap = file_get_contents($root . '/Classes/Backend/Editor/Visibility/Write/PriceOptionVisibilityDataMap.php') ?: '';
$fingerprint = file_get_contents($root . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityFingerprint.php') ?: '';
$verifier = file_get_contents($root . '/Classes/Backend/Editor/Visibility/Write/PriceOptionVisibilityVerifier.php') ?: '';

$panelMethod = '';
if (preg_match(
    '/function buildPriceOptionVisibilityPanel\([\s\S]*?function /',
    $controller,
    $panelMatch
) === 1) {
    $panelMethod = $panelMatch[0];
}
assertTrue($panelMethod !== '', 'buildPriceOptionVisibilityPanel extractable');

$reviewStateReturn = '';
$getBranch = '';
if (preg_match(
    '/if \(\$reviewState !== null\) \{([\s\S]*?return new PriceOptionVisibilityPanelView\([\s\S]*?\);)\s*\}([\s\S]*)/',
    $panelMethod,
    $branchMatch
) === 1) {
    $reviewStateReturn = $branchMatch[1];
    $getBranch = $branchMatch[2];
}
assertTrue($reviewStateReturn !== '' && $getBranch !== '', 'reviewState vs fresh GET branches extractable');

$freshInverse = static function (?bool $hidden): string {
    if ($hidden === null) {
        return '';
    }

    return $hidden ? 'visible' : 'hidden';
};
assertTrue(
    $freshInverse(false) === 'hidden'
    && str_contains($getBranch, "\$context->hidden ? 'visible' : 'hidden'")
    && preg_match(
        '/\$submittedVisibility = \$context !== null\s*\?\s*\(\$context->hidden \? \'visible\' : \'hidden\'\)\s*: \'\'/',
        $getBranch
    ) === 1
    && !str_contains($getBranch, "\$context->hidden ? 'hidden' : 'visible'"),
    '1. fresh GET visible context (hidden=false) preselects hidden'
);
assertTrue(
    $freshInverse(true) === 'visible'
    && str_contains($getBranch, "\$context->hidden ? 'visible' : 'hidden'"),
    '2. fresh GET hidden context (hidden=true) preselects visible'
);

assertTrue(
    str_contains($reviewStateReturn, "submittedVisibility: \$reviewState['submittedVisibility']")
    && !str_contains($reviewStateReturn, '$context->hidden')
    && !str_contains($reviewStateReturn, "'visible' : 'hidden'")
    && !str_contains($reviewStateReturn, "'hidden' : 'visible'"),
    '3/4. reviewState submittedVisibility is preserved, not inverted'
);
assertTrue(
    str_contains($reviewStateReturn, "submittedVisibility: \$reviewState['submittedVisibility']")
    && !preg_match(
        '/submittedVisibility:\s*\$reviewState\[\'submittedVisibility\'\] === \'visible\' \? \'hidden\'/',
        $reviewStateReturn
    ),
    '3. reviewState visible remains visible'
);
assertTrue(
    str_contains($reviewStateReturn, "submittedVisibility: \$reviewState['submittedVisibility']")
    && !preg_match(
        '/submittedVisibility:\s*\$reviewState\[\'submittedVisibility\'\] === \'hidden\' \? \'visible\'/',
        $reviewStateReturn
    ),
    '4. reviewState hidden remains hidden'
);

$select = '';
if (preg_match(
    '/<select id="arp-price-visibility" name="visibility">([\s\S]*?)<\/select>/',
    $template,
    $selectMatch
) === 1) {
    $select = $selectMatch[1];
}
assertTrue(
    $select !== ''
    && str_contains($select, '<f:switch expression="{priceVisibility.submittedVisibility}">')
    && str_contains($select, '<f:case value="visible">')
    && str_contains($select, '<f:case value="hidden">')
    && !str_contains($select, '{f:if('),
    '5. template still uses submittedVisibility switch from 2C4.2'
);

$visibleCase = '';
$hiddenCase = '';
if (preg_match('/<f:case value="visible">([\s\S]*?)<\/f:case>/', $select, $visibleMatch) === 1) {
    $visibleCase = $visibleMatch[1];
}
if (preg_match('/<f:case value="hidden">([\s\S]*?)<\/f:case>/', $select, $hiddenMatch) === 1) {
    $hiddenCase = $hiddenMatch[1];
}
assertTrue(
    $freshInverse(true) === 'visible'
    && str_contains($visibleCase, '<option value="visible" selected="selected">')
    && !str_contains($visibleCase, '<option value="hidden" selected')
    && str_contains($reviewStateReturn, "submittedVisibility: \$reviewState['submittedVisibility']"),
    '6. Hidden -> Visible: fresh GET select Visible; after Review select remains Visible'
);
assertTrue(
    $freshInverse(false) === 'hidden'
    && str_contains($hiddenCase, '<option value="hidden" selected="selected">')
    && !str_contains($hiddenCase, '<option value="visible" selected')
    && str_contains($reviewStateReturn, "submittedVisibility: \$reviewState['submittedVisibility']"),
    '7. Visible -> Hidden: fresh GET select Hidden; after Review select remains Hidden'
);

assertTrue(
    !str_contains($getBranch, 'DataHandler')
    && !str_contains($getBranch, 'process_datamap')
    && !str_contains($getBranch, 'process_cmdmap')
    && !str_contains($getBranch, 'priceOptionVisibilityWriter')
    && !str_contains($panelMethod, 'priceOptionVisibilityWriter'),
    '8. no automatic DataHandler/write on GET panel build'
);

$mainDispatch = '';
if (preg_match(
    '/\$visibilityReviewState = null;([\s\S]*?)\$editUrlBuilder = new BackendRecordEditUrlBuilder/',
    $controller,
    $dispatchMatch
) === 1) {
    $mainDispatch = $dispatchMatch[1];
}
assertTrue($mainDispatch !== '', 'main visibility dispatch extractable');
assertTrue(
    str_contains($mainDispatch, "isset(\$body['priceOptionVisibilityApply'])")
    && str_contains($mainDispatch, "isset(\$body['priceOptionVisibilityReview'])")
    && !str_contains($mainDispatch, "getQueryParams()['priceOptionVisibility']")
    && str_contains($getBranch, 'review: null')
    && !str_contains($getBranch, 'priceOptionVisibilityPlanBuilder')
    && !str_contains($getBranch, 'priceOptionVisibilityReview')
    && !str_contains($getBranch, 'priceOptionVisibilityApply')
    && str_contains($statusPartial, 'href="{reviewUrl}"')
    && !str_contains($statusPartial, 'priceOptionVisibilityReview')
    && !str_contains($statusPartial, 'priceOptionVisibilityApply'),
    '9. no automatic review/save on status-icon GET'
);

assertTrue(
    !str_contains($js, 'arp-price-visibility')
    && !str_contains($js, 'submittedVisibility')
    && !str_contains($js, 'priceOptionVisibility')
    && !str_contains($statusPartial, '<script')
    && !str_contains($select, '<script'),
    '10. no JS workaround'
);

assertTrue(
    str_contains($assembler, '$actionable = !$ambiguous && (!$placementHidden || $optionHidden)')
    && str_contains($assembler, 'Scheduled / item-hidden / parent-hidden')
    && preg_match(
        '/value="scheduled"[\s\S]*?reviewUrl/s',
        $statusPartial
    ) !== 1,
    '11. status actionability rules unchanged; scheduled stays non-action'
);

assertTrue(
    str_contains($writer, 'process_datamap')
    && str_contains($dataMap, "'hidden'")
    && str_contains($fingerprint, 'price-option-visibility-v1')
    && str_contains($verifier, 'PriceOptionVisibilityVerifier'),
    '12. write/fingerprint/verifier files remain the existing pipeline'
);

assertTrue(
    str_contains($xlf, 'Visible — hide price option')
    && str_contains($xlf, 'Hidden — show price option')
    && str_contains($statusPartial, 'priceVisibility.entry.visible')
    && str_contains($statusPartial, 'priceVisibility.entry.hidden')
    && substr_count($statusPartial, '<span class="arp-editor-sr">{statusLabel}</span>') >= 5
    && !str_contains($statusPartial, '{reviewLabel}</span>'),
    'tooltip/aria describe hide/show; visually-hidden status text stays Visible/Hidden/Scheduled'
);

echo $failures === 0
    ? "\nAll RestaurantPriceOptionVisibilityInversePreselectContract tests passed.\n"
    : "\n{$failures} RestaurantPriceOptionVisibilityInversePreselectContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
