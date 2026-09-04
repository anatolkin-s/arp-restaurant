<?php

declare(strict_types=1);

/**
 * Static CSS guard: danger identity badges must not fill with
 * --typo3-state-danger-bg (poor contrast on TYPO3 14 backends).
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

assertTrue(is_string($css) && $css !== '', 'restaurant-editor.css is readable');

$badgeBlockPattern = '/\.arp-editor-identity-badge--ambiguous,\s*\n\.arp-editor-identity-badge--inaccessible\s*\{([^}]+)\}/s';
assertTrue(preg_match($badgeBlockPattern, (string)$css, $matches) === 1, 'danger badge rule block is present');

$block = $matches[1] ?? '';
assertTrue(
    preg_match('/background\s*:\s*[^;]*--typo3-state-danger-bg/', $block) !== 1,
    'danger badge background declaration does not use state-danger-bg'
);
assertTrue(
    preg_match('/background\s*:\s*var\(--typo3-surface/', $block) === 1,
    'danger badge uses neutral/backend surface background'
);
assertTrue(str_contains($block, '--typo3-state-danger-text-color'), 'danger badge keeps danger text color');
assertTrue(str_contains($block, '--typo3-state-danger-border-color'), 'danger badge keeps danger border color');

echo $failures === 0 ? "\nAll RestaurantEditorCssContrast tests passed.\n" : "\n{$failures} RestaurantEditorCssContrast test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
