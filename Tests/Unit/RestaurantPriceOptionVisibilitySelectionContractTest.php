<?php

declare(strict_types=1);

/**
 * EDITOR-2C4.2: after Review, the live visibility select must keep the
 * validated submitted intent — not reset to the current DB/BEFORE state.
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

function extractSelectMarkup(string $template): string
{
    if (preg_match(
        '/<select id="arp-price-visibility" name="visibility">([\s\S]*?)<\/select>/',
        $template,
        $match
    ) !== 1) {
        return '';
    }

    return $match[1];
}

function extractSwitchCase(string $select, string $value): string
{
    if (preg_match(
        '/<f:case value="' . preg_quote($value, '/') . '">([\s\S]*?)<\/f:case>/',
        $select,
        $match
    ) !== 1) {
        return '';
    }

    return $match[1];
}

/**
 * @return array<string, string>
 */
function optionOpeningTags(string $caseMarkup): array
{
    $tags = [];
    if (preg_match_all('/<option\b[^>]*>/', $caseMarkup, $matches) !== false) {
        foreach ($matches[0] as $tag) {
            if (preg_match('/\bvalue="([^"]+)"/', $tag, $valueMatch) === 1) {
                $tags[$valueMatch[1]] = $tag;
            }
        }
    }

    return $tags;
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$controller = file_get_contents($root . '/Classes/Backend/Controller/RestaurantEditorController.php') ?: '';
$js = file_get_contents($root . '/Resources/Public/JavaScript/restaurant-editor-table.js') ?: '';
$writer = file_get_contents($root . '/Classes/Backend/Editor/Visibility/Write/RestaurantPriceOptionVisibilityWriter.php') ?: '';
$dataMap = file_get_contents($root . '/Classes/Backend/Editor/Visibility/Write/PriceOptionVisibilityDataMap.php') ?: '';
$fingerprint = file_get_contents($root . '/Classes/Backend/Editor/Visibility/PriceOptionVisibilityFingerprint.php') ?: '';
$verifier = file_get_contents($root . '/Classes/Backend/Editor/Visibility/Write/PriceOptionVisibilityVerifier.php') ?: '';
$statusPartial = file_get_contents($root . '/Resources/Private/Partials/RestaurantEditor/Status.html') ?: '';

$visibilityPanel = '';
if (preg_match('/data-arp-price-visibility="1"[\s\S]*?<\/section>/', $template, $panelMatch) === 1) {
    $visibilityPanel = $panelMatch[0];
}
assertTrue($visibilityPanel !== '', 'visibility panel extractable');

assertTrue(
    substr_count($template, 'id="arp-price-visibility-form"') === 1
    && substr_count($visibilityPanel, 'id="arp-price-visibility-form"') === 1,
    '1. exactly one authoritative visibility form id'
);
assertTrue(
    substr_count($template, 'name="visibility"') === 1
    && substr_count($visibilityPanel, 'name="visibility"') === 1
    && preg_match('/<select id="arp-price-visibility" name="visibility">/', $visibilityPanel) === 1,
    '2. exactly one name="visibility" on the live select'
);

$select = extractSelectMarkup($template);
assertTrue($select !== '', 'visibility select extractable');
assertTrue(
    str_contains($select, '<f:switch expression="{priceVisibility.submittedVisibility}">')
    && str_contains($select, '<f:case value="visible">')
    && str_contains($select, '<f:case value="hidden">'),
    '3. selected rendering is a switch on submittedVisibility'
);
assertTrue(
    !str_contains($select, 'priceVisibility.context.hidden')
    && !str_contains($select, 'review.plan.currentHidden')
    && !str_contains($select, 'review.plan.requestedHidden'),
    '3b. select selection is not derived from BEFORE/current hidden'
);

$visibleCase = extractSwitchCase($select, 'visible');
$hiddenCase = extractSwitchCase($select, 'hidden');
assertTrue($visibleCase !== '' && $hiddenCase !== '', 'visible/hidden select cases extractable');

$visibleOptions = optionOpeningTags($visibleCase);
$hiddenOptions = optionOpeningTags($hiddenCase);
assertTrue(
    array_keys($visibleOptions) === ['visible', 'hidden']
    && array_keys($hiddenOptions) === ['visible', 'hidden'],
    'each submittedVisibility case renders both options in Visible-then-Hidden order'
);

assertTrue(
    str_contains($visibleOptions['visible'] ?? '', 'selected="selected"')
    && !str_contains($visibleOptions['hidden'] ?? '', 'selected')
    && substr_count($visibleCase, 'selected="selected"') === 1
    && substr_count($visibleCase, '<option') === 2,
    '4. visible case: visible selected, hidden not selected'
);
assertTrue(
    str_contains($hiddenOptions['hidden'] ?? '', 'selected="selected"')
    && !str_contains($hiddenOptions['visible'] ?? '', 'selected')
    && substr_count($hiddenCase, 'selected="selected"') === 1
    && substr_count($hiddenCase, '<option') === 2,
    '5. hidden case: hidden selected, visible not selected'
);

assertTrue(
    !preg_match('/<option\b[^>]*\{f:if\(/', $template)
    && !preg_match('/f:if\(condition:\s*priceVisibility\.submittedVisibility/', $template)
    && !str_contains($select, '{f:if('),
    '6. rejects inline f:if selected-attribute construction'
);

$reviewCard = '';
if (preg_match(
    '/data-arp-visibility-update-plan="1"[\s\S]*?<\/div>\s*<\/f:if>/',
    $visibilityPanel,
    $cardMatch
) === 1) {
    $reviewCard = $cardMatch[0];
}
assertTrue($reviewCard !== '', 'READY card extractable');
assertTrue(
    str_contains($reviewCard, 'priceVisibility.review.plan.currentHidden')
    && str_contains($reviewCard, 'priceVisibility.review.plan.requestedHidden')
    && !str_contains($reviewCard, 'priceVisibility.submittedVisibility')
    && !str_contains($reviewCard, 'name="visibility"'),
    '7/8. READY Before/After come from plan current/requested hidden, independent of live select'
);
assertTrue(
    str_contains($select, '{priceVisibility.submittedVisibility}')
    && str_contains($visibleCase, 'selected="selected"')
    && str_contains($hiddenCase, 'selected="selected"'),
    '7b. Hidden→Visible review keeps submitted Visible selected while READY can show Before Hidden / After Visible'
);
assertTrue(
    str_contains($select, '{priceVisibility.submittedVisibility}')
    && !str_contains($select, 'priceVisibility.context.hidden'),
    '8b. Visible→Hidden review keeps submitted Hidden selected while READY can show Before Visible / After Hidden'
);

assertTrue(
    preg_match(
        '/data-arp-visibility-save="1"[^>]*form="arp-price-visibility-form"|form="arp-price-visibility-form"[^>]*data-arp-visibility-save="1"/',
        $reviewCard
    ) === 1,
    '9. Save visibility targets the authoritative form'
);
assertTrue(
    preg_match(
        '/data-arp-visibility-save-refreshed="1"[^>]*form="arp-price-visibility-form"|form="arp-price-visibility-form"[^>]*data-arp-visibility-save-refreshed="1"/',
        $reviewCard
    ) === 1,
    '10. Save refreshed visibility targets the same form'
);

assertTrue(
    preg_match('/<input[^>]*name="visibility"/', $template) !== 1
    && !str_contains($reviewCard, '<form')
    && !str_contains($reviewCard, 'name="visibility"')
    && substr_count($visibilityPanel, '<form') === 1,
    '11. no shadow visibility field and no second form'
);

assertTrue(
    !str_contains($js, 'arp-price-visibility')
    && !str_contains($js, 'submittedVisibility')
    && !str_contains($js, 'priceOptionVisibility')
    && !str_contains($select, '<script')
    && !str_contains($visibilityPanel, '<script'),
    '12. no JS workaround for visibility selection'
);

$reviewMethod = '';
if (preg_match(
    '/function processPriceOptionVisibilityReview\(.*?function buildPriceOptionVisibilityPanel/s',
    $controller,
    $reviewMatch
) === 1) {
    $reviewMethod = $reviewMatch[0];
}
$panelMethod = '';
if (preg_match(
    '/function buildPriceOptionVisibilityPanel\([\s\S]*?function /',
    $controller,
    $panelMatch
) === 1) {
    $panelMethod = $panelMatch[0];
}
assertTrue($reviewMethod !== '' && $panelMethod !== '', 'review + panel builders extractable');
assertTrue(
    str_contains($reviewMethod, "\$submittedVisibility = (string)(\$body['visibility'] ?? '')")
    && str_contains($reviewMethod, "'submittedVisibility' => \$submittedVisibility")
    && !str_contains($reviewMethod, '$submittedVisibility = $load->context->hidden')
    && !str_contains($reviewMethod, '$submittedVisibility = $context->hidden')
    && !str_contains($reviewMethod, '$context->hidden ? \'hidden\' : \'visible\''),
    'review state keeps POST submittedVisibility; does not reset to context.hidden'
);
assertTrue(
    str_contains($panelMethod, "submittedVisibility: \$reviewState['submittedVisibility']")
    && preg_match(
        '/if \(\$reviewState !== null\) \{[\s\S]*submittedVisibility: \$reviewState\[\'submittedVisibility\'\]/',
        $panelMethod
    ) === 1,
    'post-review panel uses review submittedVisibility, not a fresh context.hidden default'
);

assertTrue(
    str_contains($writer, 'process_datamap')
    && str_contains($dataMap, "'hidden'")
    && str_contains($fingerprint, 'price-option-visibility-v1')
    && str_contains($verifier, 'PriceOptionVisibilityVerifier')
    && str_contains($statusPartial, 'overlay="overlay-hidden"'),
    'existing write/fingerprint/icon contracts remain in place'
);

echo $failures === 0
    ? "\nAll RestaurantPriceOptionVisibilitySelectionContract tests passed.\n"
    : "\n{$failures} RestaurantPriceOptionVisibilitySelectionContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
