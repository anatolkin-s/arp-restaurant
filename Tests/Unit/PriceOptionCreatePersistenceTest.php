<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\ExistingPriceOptionSnapshot;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreateContext;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePlan;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePlanBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write\PriceOptionCreateDataMapBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write\PriceOptionCreateDataHandlerStateSnapshot;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write\PriceOptionCreateVerificationSnapshot;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write\PriceOptionCreateVerifier;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Anatolkin\\ArpRestaurant\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__, 2) . '/Classes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
$failures = 0;
function check(bool $ok, string $message): void {
    global $failures;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $message . "\n";
    $failures += $ok ? 0 : 1;
}
function changed(object $object, array $changes): object {
    $args = [];
    foreach ((new ReflectionClass($object))->getConstructor()->getParameters() as $parameter) {
        $name = $parameter->getName();
        $args[$name] = array_key_exists($name, $changes) ? $changes[$name] : $object->$name;
    }
    return new ($object::class)(...$args);
}
$args = [];
foreach ((new ReflectionClass(PriceOptionCreateContext::class))->getConstructor()->getParameters() as $parameter) {
    $name = $parameter->getName();
    $args[$name] = match ((string)$parameter->getType()) {
        'int' => 10,
        'string' => str_ends_with($name, 'PublicUuid') ? 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' : 'Dinner',
        'array' => [],
        default => null,
    };
}
$existing = new ExistingPriceOptionSnapshot(40, '11111111-bbbb-4ccc-8ddd-eeeeeeeeeeee', 12, 'Small', 500, 256, 0);
$other = changed($existing, ['uid' => 41, 'publicUuid' => '22222222-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'label' => 'Large', 'sorting' => 512]);
$args['existingPriceOptions'] = [$existing, $other];
$context = new PriceOptionCreateContext(...$args);
$builder = new PriceOptionCreatePlanBuilder();
$plan = $builder->prepare($context, 'Family', '7.99')->plan;
check($plan instanceof PriceOptionCreatePlan && preg_match('/^[a-f0-9]{64}$/D', $plan->fingerprint) === 1, 'fingerprint lowercase 64 hex');
check($builder->prepare($context, 'Family', '7.99')->plan->fingerprint === $plan->fingerprint, 'same plan deterministic');
check(changed($plan, ['existingPriceOptions' => [$other, $existing]])->fingerprint === $plan->fingerprint, 'snapshot order is not authority');
foreach (['pid', 'menuUid', 'menuPublicUuid', 'menuTstamp', 'categoryUid', 'categoryPublicUuid', 'categoryTstamp',
    'placementUid', 'placementPublicUuid', 'placementTstamp', 'itemUid', 'itemPublicUuid', 'itemTstamp',
    'label', 'amountMinor', 'plannedSorting'] as $field) {
    $value = is_int($plan->$field) ? $plan->$field + 1 : $plan->$field . 'x';
    check(changed($plan, [$field => $value])->fingerprint !== $plan->fingerprint, 'fingerprint authority: ' . $field);
}
foreach (['menuTitle', 'categoryTitle', 'itemTitle', 'formattedAmount'] as $field) {
    check(changed($plan, [$field => 'Different display'])->fingerprint === $plan->fingerprint, 'display excluded: ' . $field);
}
foreach (['uid', 'publicUuid', 'tstamp', 'label', 'amountMinor', 'sorting', 'hidden'] as $field) {
    $value = is_int($existing->$field) ? $existing->$field + 1 : $existing->$field . 'x';
    check(changed($plan, ['existingPriceOptions' => [changed($existing, [$field => $value]), $other]])->fingerprint !== $plan->fingerprint, 'existing authority: ' . $field);
}
check(changed($plan, ['existingPriceOptions' => [$existing]])->fingerprint !== $plan->fingerprint, 'removed row changes fingerprint');
$third = changed($existing, ['uid' => 42, 'label' => 'Medium', 'sorting' => 768]);
check(changed($plan, ['existingPriceOptions' => [$existing, $other, $third]])->fingerprint !== $plan->fingerprint, 'added row changes fingerprint');
check(changed($plan, ['existingPriceOptions' => [changed($existing, ['label' => "\u{00A0}Small  "]), $other]])->fingerprint === $plan->fingerprint, 'stored display whitespace normalized');
check($builder->prepare($context, 'Family', '8.49')->plan->fingerprint !== $plan->fingerprint, 'live price edited after review becomes stale');
check($builder->prepare($context, 'Party', '7.99')->plan->fingerprint !== $plan->fingerprint, 'live label edited after review becomes stale');
$concurrent = changed($context, ['existingPriceOptions' => [$existing, $other, changed($third, ['label' => 'family'])]]);
check($builder->prepare($concurrent, 'Family', '7.99')->blockers[0]->code === 'duplicateVariant', 'concurrent duplicate blocks before confirmation');
$concurrent = changed($context, ['existingPriceOptions' => [$existing, $other, $third]]);
check($builder->prepare($concurrent, 'Family', '7.99')->plan->plannedSorting === 1024
    && $builder->prepare($concurrent, 'Family', '7.99')->plan->fingerprint !== $plan->fingerprint, 'concurrent addition recomputes sorting and becomes stale');

$mapBuilder = new PriceOptionCreateDataMapBuilder();
$map = $mapBuilder->build($plan);
$table = 'tx_arprestaurant_domain_model_priceoption';
check(array_keys($map->dataMap) === [$table] && array_keys($map->dataMap[$table]) === [$map->newToken], 'exactly one table and one NEW row; zero parent rows');
check(str_starts_with($map->newToken, 'NEW') && !is_numeric($map->newToken) && $mapBuilder->build($plan)->newToken === $map->newToken, 'deterministic temporary NEW token');
check($map->dataMap[$table][$map->newToken] === ['pid' => 10, 'placement' => 10, 'label' => 'Family', 'amount' => 799, 'sorting' => 768, 'sys_language_uid' => 0], 'exact six fields; no UUID, hidden or metadata');
foreach (['pid' => 0, 'placementUid' => 0, 'plannedSorting' => 2147483648, 'amountMinor' => -1, 'label' => str_repeat('a', 256)] as $field => $value) {
    try { $mapBuilder->build(changed($plan, [$field => $value])); $blocked = false; } catch (InvalidArgumentException) { $blocked = true; }
    check($blocked, 'datamap fail closed: ' . $field);
}

$state = new PriceOptionCreateDataHandlerStateSnapshot([], [$map->newToken => 99], [$map->newToken => $table]);
$row = ['uid' => 99, 'pid' => 10, 'placement' => 10, 'label' => 'Family', 'amount' => 799, 'sorting' => 768,
    'hidden' => 0, 'deleted' => 0, 'sys_language_uid' => 0, 'public_uuid' => '99999999-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'tstamp' => 123];
$after = new PriceOptionCreateVerificationSnapshot($row, $context);
$verifier = new PriceOptionCreateVerifier();
$verify = static fn ($snapshot = null, $handler = null, $threw = false) => $verifier->verify($plan, $map->newToken, $handler ?? $state, $snapshot ?? $after, $threw);
check($verify()->outcome === 'created', 'exact row + unchanged parents + clean diagnostics => created');
check($verify(null, new PriceOptionCreateDataHandlerStateSnapshot([], [], []))->outcome === 'failed', 'missing substitution => failed');
check($verify(null, new PriceOptionCreateDataHandlerStateSnapshot([], [$map->newToken => 99], [$map->newToken => 'wrong']))->outcome === 'failed', 'wrong substitution table => failed');
check($verify(new PriceOptionCreateVerificationSnapshot(null, null))->outcome === 'partialFailure', 'resolved uid but missing read-back => partialFailure');
foreach (['uid', 'pid', 'placement', 'label', 'amount', 'sorting', 'sys_language_uid', 'hidden', 'deleted', 'public_uuid'] as $field) {
    $bad = $row;
    $bad[$field] = is_int($row[$field]) ? $row[$field] + 1 : 'invalid';
    check($verify(new PriceOptionCreateVerificationSnapshot($bad, $context))->outcome === 'partialFailure', 'read-back rejects ' . $field);
}
$bad = $row;
$bad['public_uuid'] = strtoupper($existing->publicUuid);
check($verify(new PriceOptionCreateVerificationSnapshot($bad, $context))->outcome === 'partialFailure', 'existing UUID reuse rejected case-insensitively');
$bad['public_uuid'] = '99999999-bbbb-5ccc-8ddd-eeeeeeeeeeee';
check($verify(new PriceOptionCreateVerificationSnapshot($bad, $context))->outcome === 'partialFailure', 'non-v4 UUID rejected');
foreach (['menu', 'category', 'placement', 'item'] as $parent) {
    foreach (['Uid', 'PublicUuid', 'Tstamp'] as $suffix) {
        $field = $parent . $suffix;
        $value = is_int($context->$field) ? $context->$field + 1 : 'changed';
        check($verify(new PriceOptionCreateVerificationSnapshot($row, changed($context, [$field => $value])))->outcome === 'partialFailure', 'parent unchanged required: ' . $field);
    }
}
check($verify(null, new PriceOptionCreateDataHandlerStateSnapshot(['Core error'], $state->substNEWwithIDs, $state->substNEWwithIDsTable))->outcome === 'partialFailure', 'DataHandler error is never clean success');
check($verify(null, null, true)->outcome === 'partialFailure', 'process exception is never clean success');
exit($failures === 0 ? 0 : 1);
