<?php

declare(strict_types=1);

/**
 * Static CSS guard: identity status pills use neutral surface fills with
 * semantic text/border only. Do not fill with TYPO3 state *-bg tokens
 * (version-dependent contrast on TYPO3 13/14).
 */

$cssPath = dirname(__DIR__, 2) . '/Resources/Public/Css/restaurant-editor.css';
$css = file_get_contents($cssPath);
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

/**
 * @return string declaration block body
 */
function extractRuleBlock(string $css, string $selectorPattern): string
{
    $pattern = '/' . $selectorPattern . '\s*\{([^}]+)\}/s';
    if (preg_match($pattern, $css, $matches) !== 1) {
        return '';
    }

    return $matches[1];
}

assertTrue(is_string($css) && $css !== '', 'restaurant-editor.css is readable');
$css = (string)$css;

$create = extractRuleBlock($css, '\.arp-editor-identity-badge--create');
assertTrue($create !== '', 'CREATE badge rule block is present');
assertTrue(
    preg_match('/background\s*:\s*[^;]*--typo3-state-info-bg/', $create) !== 1,
    'CREATE background does not use state-info-bg'
);
assertTrue(
    preg_match('/background\s*:\s*var\(--typo3-surface/', $create) === 1,
    'CREATE uses neutral/backend surface background'
);
assertTrue(str_contains($create, '--typo3-state-info-text-color'), 'CREATE keeps info text color');
assertTrue(str_contains($create, '--typo3-state-info-border-color'), 'CREATE keeps info border color');

$reuse = extractRuleBlock($css, '\.arp-editor-identity-badge--reuse');
assertTrue($reuse !== '', 'REUSE badge rule block is present');
assertTrue(
    preg_match('/background\s*:\s*[^;]*--typo3-state-success-bg/', $reuse) !== 1,
    'REUSE background does not use state-success-bg'
);
assertTrue(
    preg_match('/background\s*:\s*var\(--typo3-surface/', $reuse) === 1,
    'REUSE uses neutral/backend surface background'
);
assertTrue(str_contains($reuse, '--typo3-state-success-text-color'), 'REUSE keeps success text color');
assertTrue(str_contains($reuse, '--typo3-state-success-border-color'), 'REUSE keeps success border color');

$danger = extractRuleBlock(
    $css,
    '\.arp-editor-identity-badge--ambiguous,\s*\n\.arp-editor-identity-badge--inaccessible'
);
assertTrue($danger !== '', 'AMBIGUOUS/INACCESSIBLE badge rule block is present');
assertTrue(
    preg_match('/background\s*:\s*[^;]*--typo3-state-danger-bg/', $danger) !== 1,
    'AMBIGUOUS/INACCESSIBLE background does not use state-danger-bg'
);
assertTrue(
    preg_match('/background\s*:\s*var\(--typo3-surface/', $danger) === 1,
    'AMBIGUOUS/INACCESSIBLE uses neutral/backend surface background'
);
assertTrue(str_contains($danger, '--typo3-state-danger-text-color'), 'AMBIGUOUS/INACCESSIBLE keeps danger text color');
assertTrue(str_contains($danger, '--typo3-state-danger-border-color'), 'AMBIGUOUS/INACCESSIBLE keeps danger border color');

echo $failures === 0 ? "\nAll RestaurantEditorCssContrast tests passed.\n" : "\n{$failures} RestaurantEditorCssContrast test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
