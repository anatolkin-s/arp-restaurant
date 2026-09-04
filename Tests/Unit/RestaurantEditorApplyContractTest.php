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

assertTrue(
    str_contains($controller, 'dataHandlerAttempted')
    && preg_match(
        '/if\s*\(\s*!\$execution->dataHandlerAttempted\s*\)\s*\{[^}]*writePreparationBlocked/s',
        $controller
    ) === 1
    && str_contains($controller, 'RedirectResponse'),
    'A. PRG is conditional on dataHandlerAttempted'
);
assertTrue(
    preg_match(
        '/buildContext\([\s\S]*?catch\s*\(\s*\\\\Throwable\s*\)\s*\{[\s\S]*?writePreparationBlocked/s',
        $controller
    ) === 1,
    'B. pre-write sort failure does not PRG'
);
assertTrue(
    str_contains($writer, 'writePreparationFailed')
    && str_contains($writer, 'dataHandlerAttempted: false')
    && str_contains($controller, "'writePreparationBlocked'"),
    'C. pre-write datamap failure does not PRG'
);
assertTrue(
    preg_match(
        '/if\s*\(\s*!\$execution->dataHandlerAttempted\s*\)[\s\S]*?enqueueApplyFlash\(\$execution[\s\S]*?RedirectResponse\(\$redirectUri,\s*303\)/s',
        $controller
    ) === 1,
    'D. attempted DataHandler result does PRG after attempt branch'
);
assertTrue(
    preg_match(
        '/buildContext\([\s\S]*?catch[\s\S]*?writePreparationBlocked[\s\S]*?applyWriter->execute/s',
        $controller
    ) === 1,
    'E. writer cannot be invoked when sort context failed'
);
assertTrue(
    substr_count($controller, 'applyWriter->execute(') === 1,
    'F. no automatic retry (writer invoked once in controller source)'
);
assertTrue(
    !str_contains($writerWithoutComments, 'rollBack')
    && !str_contains($controller, 'rollBack')
    && !str_contains($writerWithoutComments, 'process_cmdmap'),
    'G. no rollback / process_cmdmap'
);

$snapshotHelper = file_get_contents($root . '/Classes/Backend/Editor/Apply/Write/ApplyDataHandlerStateSnapshot.php') ?: '';
assertTrue(
    str_contains($snapshotHelper, 'fromDataHandler')
    && str_contains($writer, 'ApplyDataHandlerStateSnapshot::fromDataHandler')
    && preg_match(
        '/\$dataHandlerAttempted\s*=\s*true;\s*\$dataHandler->process_datamap\(\);[\s\S]*?finally\s*\{[\s\S]*?fromDataHandler/s',
        $writerWithoutComments
    ) === 1,
    'DataHandler state captured in finally after process_datamap attempt'
);
assertTrue(
    str_contains($template, 'bulk.apply.writePreparationBlocked'),
    'writePreparationBlocked UI copy present'
);

$workbenchHead = '';
if (preg_match(
    '/data-arp-editor-grid="1"\s*>(.*?)<f:if condition="\{bulk\.identity\}"/s',
    $template,
    $headMatch
)) {
    $workbenchHead = $headMatch[1];
}
assertTrue($workbenchHead !== '', 'workbench status head before identity card is extractable');
assertTrue(
    str_contains($workbenchHead, "confirmationWarning} == 'confirmationStale'")
    && str_contains($workbenchHead, 'data-arp-confirmation-stale="1"'),
    '1. stale warning is before Identity card'
);
assertTrue(
    preg_match(
        '/<div\b[^>]*\barp-editor-stale-warning\b[^>]*\bdata-arp-confirmation-stale="1"/s',
        $workbenchHead
    ) === 1,
    '2. stale warning has dedicated wrapper'
);
assertTrue(
    preg_match(
        '/<div\b[^>]*\barp-editor-stale-warning\b[^>]*\brole="alert"/s',
        $workbenchHead
    ) === 1,
    '3. wrapper has role="alert"'
);
assertTrue(
    str_contains($workbenchHead, 'bulk.apply.confirmationStale.heading'),
    '4. dedicated heading localization key is used'
);
assertTrue(
    str_contains($workbenchHead, 'bulk.apply.confirmationStale.body'),
    '5. dedicated body localization key is used'
);
assertTrue(
    str_contains($workbenchHead, 'arp-editor-stale-warning')
    && str_contains($workbenchHead, 'message-warning'),
    '6. warning visual class is present'
);

$applyCardBlock = '';
if (preg_match(
    '/data-arp-apply-plan="1"(.*?)data-arp-draft-dirty/s',
    $template,
    $applyCardMatch
)) {
    $applyCardBlock = $applyCardMatch[1];
}
assertTrue($applyCardBlock !== '', 'ApplyPlan card block is extractable');
assertTrue(
    !str_contains($applyCardBlock, 'confirmationStale')
    && !str_contains($applyCardBlock, 'data-arp-confirmation-stale')
    && !str_contains($applyCardBlock, 'arp-editor-stale-warning'),
    '7. warning is not duplicated later in ApplyPlan'
);

$actionsBlock = '';
if (preg_match('/class="arp-editor-draft-actions"(.*?)<\/p>/s', $template, $actionsMatch)) {
    $actionsBlock = $actionsMatch[1];
}
assertTrue($actionsBlock !== '', 'draft actions block extractable');
assertTrue(
    str_contains($actionsBlock, "confirmationWarning} == 'confirmationStale'")
    && str_contains($actionsBlock, 'bulk.applyRefreshedPlan')
    && substr_count($actionsBlock, 'name="bulkApply"') === 1,
    '8. Apply refreshed plan behavior is unchanged'
);
assertTrue(
    str_contains($actionsBlock, 'bulk.applyToMenu'),
    '9. normal Apply to menu behavior is unchanged'
);
assertTrue(
    str_contains($template, 'name="confirmedFingerprint"')
    && str_contains($template, 'bulk.apply.plan.fingerprint'),
    'both button states still use current server plan fingerprint'
);

$prgBlock = '';
if (preg_match(
    '/if\s*\(\s*!\$execution->dataHandlerAttempted\s*\)\s*\{.*?return new RedirectResponse\(\$redirectUri,\s*303\);/s',
    $controller,
    $prgMatch
)) {
    $prgBlock = $prgMatch[0];
}
assertTrue($prgBlock !== '', 'attempted-write PRG block extractable');
assertTrue(
    str_contains($prgBlock, 'RedirectResponse($redirectUri, 303)'),
    'successful/attempted-write RedirectResponse remains HTTP 303'
);
assertTrue(
    !str_contains($prgBlock, '#arp-restaurant-bulk-workbench'),
    'redirect URI does NOT contain workbench fragment'
);
assertTrue(
    str_contains($prgBlock, "'id' => \$pid")
    && str_contains($prgBlock, "'menu' => \$preparation->plan->targetMenu->uid"),
    'redirect still contains same pid/id and menu'
);
assertTrue(
    preg_match(
        '/!hash_equals\(\$preparation->plan->fingerprint,\s*\$confirmedFingerprint\)\)\s*\{\s*return \[[\s\S]*?\'confirmationWarning\'\s*=>\s*\'confirmationStale\',[\s\S]*?\];\s*\}/s',
        $applyBlock
    ) === 1,
    'confirmationStale remains non-redirect'
);
assertTrue(
    preg_match_all(
        '/return \[[\s\S]*?\'confirmationWarning\'\s*=>\s*\'writePreparationBlocked\',\s*\];/s',
        $applyBlock,
        $prepBlockedMatches
    ) === 2,
    'writePreparationBlocked remains non-redirect'
);
assertTrue(
    str_contains($writer, 'process_datamap')
    && !str_contains($writerWithoutComments, 'process_cmdmap')
    && str_contains($applyBlock, 'hash_equals($preparation->plan->fingerprint, $confirmedFingerprint)')
    && str_contains($applyBlock, 'applyWriter->execute'),
    '10. no controller/write pipeline behavior redesign'
);

echo $failures === 0 ? "\nAll RestaurantEditorApplyContract tests passed.\n" : "\n{$failures} RestaurantEditorApplyContract test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
