<?php

declare(strict_types=1);

/**
 * Static contract: DocHeader records navigation + compact vs full records copy.
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
$xlf = file_get_contents($root . '/Resources/Private/Language/locallang_mod_editor.xlf') ?: '';
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$js = file_get_contents($root . '/Resources/Public/JavaScript/restaurant-editor-table.js') ?: '';

$addListButton = '';
if (preg_match(
    '/private function addListButton\([\s\S]*?\n    \}/',
    $controller,
    $match
)) {
    $addListButton = $match[0];
}
assertTrue($addListButton !== '', 'addListButton method is extractable');

assertTrue(
    preg_match(
        '/id="button\.openList">\s*<source>Open all records<\/source>/s',
        $xlf
    ) === 1,
    'visible label = "Open all records"'
);
assertTrue(
    preg_match(
        '/id="button\.openList\.title">\s*<source>Open the full TYPO3 records view for this page, including all record types\.<\/source>/s',
        $xlf
    ) === 1
    && str_contains($addListButton, 'button.openList.title')
    && str_contains($addListButton, "setShowLabelText(false)")
    && str_contains($addListButton, 'actions-info'),
    'explanatory hover/help copy exists (icon-only title affordance)'
);
assertTrue(
    preg_match(
        '/id="readonly\.notice">\s*<source>Compact restaurant view\./s',
        $xlf
    ) === 1
    && str_contains($template, 'readonly.notice'),
    'module description contains "Compact restaurant view"'
);
assertTrue(
    str_contains($xlf, 'full TYPO3 records view of this page')
    && str_contains($xlf, 'Open all records'),
    'description explains full TYPO3 records view'
);
assertTrue(
    !preg_match('/id="readonly\.notice">[\s\S]*?DOMAIN-1A/', $xlf)
    && !str_contains(
        preg_match('/id="readonly\.notice">\s*<source>(.*?)<\/source>/s', $xlf, $noticeMatch)
            ? ($noticeMatch[1] ?? '')
            : 'DOMAIN-1A',
        'DOMAIN-1A'
    ),
    'DOMAIN-1A is no longer shown in user-facing module copy'
);
assertTrue(
    str_contains($addListButton, "'dispatch-action' => 'TYPO3.ModuleMenu.showModule'")
    || str_contains($addListButton, '"dispatch-action" => "TYPO3.ModuleMenu.showModule"'),
    'ModuleMenu dispatch unchanged'
);
assertTrue(
    str_contains($addListButton, "'web_list,&'")
    || str_contains($addListButton, '"web_list,&"'),
    'web_list unchanged'
);
assertTrue(
    str_contains($addListButton, "http_build_query(['id' => \$pid])")
    || str_contains($addListButton, 'http_build_query(["id" => $pid])'),
    'pid unchanged'
);
assertTrue(
    !preg_match('/\bBootstrap\.Tooltip\b|\btooltip\.js\b|@typo3\/backend\/tooltip/i', $addListButton)
    && !preg_match('/\bBootstrap\.Tooltip\b|\btooltip\.js\b|@typo3\/backend\/tooltip/i', $js)
    && !str_contains($js, 'new Tooltip'),
    'no deprecated tooltip JS'
);
assertTrue(
    !str_contains($js, 'ModuleMenu')
    && !str_contains($js, 'showModule')
    && !str_contains($js, 'web_list')
    && !str_contains($addListButton, 'setAttributes(')
    && !str_contains($addListButton, "buildUriFromRoute('web_list'"),
    'no custom JS / navigation behavior preserved'
);
assertTrue(
    substr_count($addListButton, 'createLinkButton') === 2
    && str_contains($addListButton, 'setTitle($label)')
    && str_contains($addListButton, 'setShowLabelText(true)')
    && str_contains($addListButton, 'setTitle($helpTitle)')
    && str_contains($addListButton, 'setShowLabelText(false)'),
    'short visible label on records button; help title on adjacent affordance'
);

echo $failures === 0
    ? "\nAll RestaurantEditorOpenListButtonContract tests passed.\n"
    : "\n{$failures} RestaurantEditorOpenListButtonContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
