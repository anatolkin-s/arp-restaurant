<?php

declare(strict_types=1);

/**
 * Static contract: DocHeader Open List uses ModuleMenu dispatch, not nested frame href.
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

$addListButton = '';
if (preg_match(
    '/private function addListButton\([\s\S]*?\n    \}/',
    $controller,
    $match
)) {
    $addListButton = $match[0];
}
assertTrue($addListButton !== '', 'addListButton method is extractable');

$xlf = file_get_contents($root . '/Resources/Private/Language/locallang_mod_editor.xlf') ?: '';
assertTrue(
    preg_match(
        '/id="button\.openList">\s*<source>Open records<\/source>/s',
        $xlf
    ) === 1,
    '1. visible/localized copy is "Open records"'
);
assertTrue(
    preg_match(
        '/id="button\.openList\.title">\s*<source>Open this page in TYPO3\'s native records module<\/source>/s',
        $xlf
    ) === 1
    && str_contains($addListButton, 'button.openList.title'),
    '2. tooltip/title explains native records module'
);
assertTrue(
    str_contains($addListButton, "'dispatch-action' => 'TYPO3.ModuleMenu.showModule'")
    || str_contains($addListButton, '"dispatch-action" => "TYPO3.ModuleMenu.showModule"'),
    '3. dispatch-action remains TYPO3.ModuleMenu.showModule'
);
assertTrue(
    str_contains($addListButton, "'web_list,&'")
    || str_contains($addListButton, '"web_list,&"'),
    '4. dispatch target remains web_list'
);
assertTrue(
    str_contains($addListButton, "http_build_query(['id' => \$pid])")
    || str_contains($addListButton, 'http_build_query(["id" => $pid])'),
    '5. pid remains preserved'
);
assertTrue(
    str_contains($addListButton, "setHref('#')")
    || str_contains($addListButton, 'setHref("#")'),
    "6. href remains '#'"
);
assertTrue(
    !str_contains($addListButton, "buildUriFromRoute('web_list'")
    && str_contains($addListButton, 'setDataAttributes(')
    && !str_contains($addListButton, '_blank')
    && !str_contains($addListButton, 'window.open'),
    '7. no navigation behavior change'
);

assertTrue(
    str_contains($addListButton, 'button.openList')
    && str_contains($addListButton, 'setShowLabelText(true)'),
    'short Open records label drives setTitle/visible text'
);

$js = file_get_contents($root . '/Resources/Public/JavaScript/restaurant-editor-table.js') ?: '';
assertTrue(
    !str_contains($js, 'ModuleMenu')
    && !str_contains($js, 'showModule')
    && !str_contains($js, 'web_list')
    && !str_contains($addListButton, 'setAttributes('),
    'no JS / no TYPO3-14-only setAttributes()'
);

echo $failures === 0
    ? "\nAll RestaurantEditorOpenListButtonContract tests passed.\n"
    : "\n{$failures} RestaurantEditorOpenListButtonContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
