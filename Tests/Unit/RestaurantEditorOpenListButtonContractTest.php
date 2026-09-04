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

assertTrue(
    !str_contains($addListButton, "buildUriFromRoute('web_list'")
    && !str_contains($addListButton, 'buildUriFromRoute("web_list"'),
    '1. addListButton no longer uses a direct raw web_list href'
);
assertTrue(
    str_contains($addListButton, "setHref('#')")
    || str_contains($addListButton, 'setHref("#")'),
    "2. href is '#'"
);
assertTrue(
    str_contains($addListButton, "'dispatch-action' => 'TYPO3.ModuleMenu.showModule'")
    || str_contains($addListButton, '"dispatch-action" => "TYPO3.ModuleMenu.showModule"'),
    '3. dispatch-action is TYPO3.ModuleMenu.showModule'
);
assertTrue(
    str_contains($addListButton, "'web_list,&'")
    || str_contains($addListButton, '"web_list,&"'),
    '4. dispatch args target web_list'
);
assertTrue(
    str_contains($addListButton, "http_build_query(['id' => \$pid])")
    || str_contains($addListButton, 'http_build_query(["id" => $pid])'),
    '5. pid is included in query args'
);
assertTrue(
    str_contains($addListButton, 'setDataAttributes('),
    '6. implementation uses setDataAttributes()'
);
assertTrue(
    !str_contains($addListButton, '_blank')
    && !str_contains($addListButton, 'window.open')
    && !str_contains($addListButton, 'setTarget'),
    '7. no target=_blank / window.open'
);

$js = file_get_contents($root . '/Resources/Public/JavaScript/restaurant-editor-table.js') ?: '';
assertTrue(
    !str_contains($js, 'ModuleMenu')
    && !str_contains($js, 'showModule')
    && !str_contains($js, 'web_list'),
    '8. no JS added for list navigation'
);
assertTrue(
    !str_contains($addListButton, 'setAttributes('),
    'does not use TYPO3-14-only setAttributes()'
);

echo $failures === 0
    ? "\nAll RestaurantEditorOpenListButtonContract tests passed.\n"
    : "\n{$failures} RestaurantEditorOpenListButtonContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
