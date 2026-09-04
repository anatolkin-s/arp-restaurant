<?php

declare(strict_types=1);

/**
 * Static contract checks for EDITOR-2B4 confirmed DataHandler Apply.
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
$writer = file_get_contents($root . '/Classes/Backend/Editor/Apply/Write/RestaurantApplyWriter.php') ?: '';
$template = file_get_contents($root . '/Resources/Private/Templates/RestaurantEditor/Index.html') ?: '';
$view = file_get_contents($root . '/Classes/Backend/Editor/Bulk/BulkPreviewView.php') ?: '';
$js = file_get_contents($root . '/Resources/Public/JavaScript/restaurant-editor-table.js') ?: '';

assertTrue(
    str_contains($controller, "BULK_APPLY_ACTION = 'bulkApply'")
    && str_contains($controller, 'applyToken')
    && str_contains($controller, "\$body['applyToken']"),
    '1. dedicated bulkApply action/token'
);
assertTrue(
    str_contains($controller, 'processBulkApplyWrite')
    && substr_count($controller, 'validatePosted($body[\'rows\'] ?? null)') >= 3,
    '2. bulkApply revalidates posted rows'
);
assertTrue(
    str_contains($controller, 'resolveIdentities(')
    && str_contains($controller, 'applyPlanBuilder->prepare($identity)'),
    '3/4. Apply re-resolves identity and rebuilds plan'
);

$applyBlock = '';
if (preg_match('/function processBulkApplyWrite\(.*?return new RedirectResponse/s', $controller, $m)) {
    $applyBlock = $m[0];
}
assertTrue($applyBlock !== '', 'Apply write handler extractable');
assertTrue(
    str_contains($applyBlock, "\$identity->outcome !== 'identityResolved'")
    && str_contains($applyBlock, "outcome !== 'applyReady'"),
    '5/6. identityResolved + applyReady gates retained'
);
assertTrue(
    str_contains($applyBlock, 'FINGERPRINT_PATTERN')
    && str_contains($controller, "'/^[0-9a-f]{64}$/'")
    && str_contains($applyBlock, 'hash_equals'),
    '7/8. confirmedFingerprint 64 hex + hash_equals'
);
assertTrue(
    preg_match(
        '/hash_equals\(\$preparation->plan->fingerprint,\s*\$confirmedFingerprint\)\s*\)\s*\{.*?applyWriter->execute/s',
        $applyBlock
    ) === 1
    || (
        str_contains($applyBlock, 'hash_equals($preparation->plan->fingerprint, $confirmedFingerprint)')
        && strpos($applyBlock, 'hash_equals') < strpos($applyBlock, 'applyWriter->execute')
    ),
    '9. writer only after fingerprint match'
);
assertTrue(
    !str_contains($controller, 'postedIdentity')
    && !str_contains($controller, "body['identity"),
    '10. no posted identity snapshot authority'
);
assertTrue(
    str_contains($applyBlock, "'confirmationStale'")
    && !str_contains(explode("'confirmationStale'", $applyBlock)[0] ?? '', 'applyWriter->execute'),
    '11. stale fingerprint => no writer before confirmationStale return'
);
assertTrue(
    preg_match(
        '/bulk\.apply\.outcome\} == \'applyReady\' && \{bulk\.apply\.plan\}.*?name="bulkApply"/s',
        $template
    ) === 1,
    '12. Apply button only with applyReady plan'
);
assertTrue(
    str_contains($template, 'name="confirmedFingerprint"')
    && str_contains($template, 'bulk.apply.plan.fingerprint'),
    '13. confirmed fingerprint from server ApplyPlan'
);
assertTrue(
    str_contains($template, 'data-arp-server-status')
    && str_contains($js, 'data-arp-server-status')
    && str_contains($template, 'name="bulkApply"'),
    '14. Apply control uses data-arp-server-status dirty hide'
);
$writerWithoutComments = preg_replace('!/\*.*?\*/!s', '', $writer) ?? $writer;
$writerWithoutComments = preg_replace('!^\s*//.*$!m', '', $writerWithoutComments) ?? $writerWithoutComments;
assertTrue(!str_contains($writerWithoutComments, 'process_cmdmap'), '15. no process_cmdmap');
assertTrue(
    !preg_match('/->(insert|update|delete)\s*\(/', $writerWithoutComments)
    && !str_contains($writerWithoutComments, 'executeStatement'),
    '16. no direct SQL mutation in writer'
);
assertTrue(
    !str_contains($writerWithoutComments, 'transactional')
    && !str_contains($writerWithoutComments, 'beginTransaction')
    && !str_contains($writerWithoutComments, 'rollBack'),
    '17. no transaction wrapper'
);
assertTrue(
    !str_contains($writerWithoutComments, 'bypassAccessCheckForRecords')
    && !str_contains($writerWithoutComments, 'isImporting'),
    '18. no DataHandler bypass flags'
);
assertTrue(
    !file_exists($root . '/ext_tables.sql')
    || !str_contains((string)file_get_contents($root . '/ext_tables.sql'), 'sku'),
    '19. no schema sku additions in focus'
);
assertTrue(
    str_contains($controller, 'RedirectResponse')
    && str_contains($controller, ', 303')
    && str_contains($controller, 'enqueueApplyFlash'),
    '20. PRG required after DataHandler attempt'
);

$writerCode = '';
if (preg_match('/public function execute\(.*?^\}/ms', $writer, $wm)) {
    $writerCode = $wm[0];
}
assertTrue(
    str_contains($writer, 'DataHandler')
    && str_contains($writer, '->start(')
    && str_contains($writer, '->process_datamap()'),
    'DataHandler start/process_datamap present'
);
assertTrue(
    !str_contains($writerWithoutComments, 'process_cmdmap')
    && !str_contains($writerWithoutComments, 'bypassAccessCheckForRecords')
    && !str_contains($writerWithoutComments, 'isImporting')
    && !str_contains($writerWithoutComments, 'beginTransaction')
    && !str_contains($writerWithoutComments, 'rollBack')
    && !str_contains($writerWithoutComments, 'transactional')
    && !preg_match('/\b(insert|update|delete)\s*\(/i', $writerWithoutComments),
    'DataHandler static contract clean'
);

assertTrue(
    str_contains($view, 'applyToken')
    && str_contains($view, 'confirmationWarning'),
    'BulkPreviewView exposes applyToken + confirmationWarning'
);

echo $failures === 0 ? "\nAll RestaurantEditorApplyContract tests passed.\n" : "\n{$failures} RestaurantEditorApplyContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
