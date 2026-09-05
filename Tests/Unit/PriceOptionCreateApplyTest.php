<?php

declare(strict_types=1);

// Exercise the real private Apply method with boundary doubles, without a TYPO3 database.
namespace Psr\Http\Message {
    interface ServerRequestInterface { public function getParsedBody(); }
}
namespace TYPO3\CMS\Core\Authentication { class BackendUserAuthentication {} }
namespace TYPO3\CMS\Core\FormProtection {
    class FormProtectionFactory {
        public function createFromRequest($request): object { return $this; }
        public function validateToken($token, $form, $action): bool {
            return $token === 'valid' && $action === 'priceOptionCreateApply';
        }
    }
}
namespace TYPO3\CMS\Core\Utility {
    class MathUtility {
        public static function canBeInterpretedAsInteger($value): bool { return filter_var($value, FILTER_VALIDATE_INT) !== false; }
    }
}
namespace TYPO3\CMS\Core\Http {
    class RedirectResponse { public function __construct(public string $uri, public int $status) {} }
}
namespace TYPO3\CMS\Backend\Routing {
    class UriBuilder { public function buildUriFromRoute($route, $params): string { return '/editor?' . http_build_query($params); } }
}
namespace TYPO3\CMS\Core\Localization {
    class LanguageService { public function sL($key): string { return $key; } }
}
namespace TYPO3\CMS\Core\Type {
    enum ContextualFeedbackSeverity { case OK; case WARNING; case ERROR; }
}
namespace TYPO3\CMS\Core\Messaging {
    class FlashMessage {
        public function __construct(public $body, public $title, public $severity, public $session) {}
    }
    class FlashMessageService {
        public array $messages = [];
        public function getMessageQueueByIdentifier(): self { return $this; }
        public function enqueue($message): void { $this->messages[] = $message; }
    }
}
namespace Anatolkin\ArpRestaurant\Backend\Editor {
    class BackendAccessGuard {
        public string $blocker = '';
        public int $calls = 0;
        public function priceOptionCreatePermissionBlocker($page, $user): string { ++$this->calls; return $this->blocker; }
    }
    class BackendRecordEditUrlBuilder { public function __construct($uri, $request) {} }
}
namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate {
    class RestaurantPriceOptionCreateReader {
        public ?PriceOptionCreateContext $context = null;
        public int $calls = 0;
        public function load($pid, $menu, $placement, $user, $edit = null): PriceOptionCreateLoadResult {
            ++$this->calls;
            return new PriceOptionCreateLoadResult($this->context === null ? 'blocked' : 'loaded', $this->context,
                $this->context === null ? [new PriceOptionCreateBlocker('wrongMenu')] : []);
        }
    }
}
namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write {
    class RestaurantPriceOptionCreateWriter {
        public int $calls = 0;
        public bool $attempted = true;
        public string $outcome = 'created';
        public function execute($plan, $user): PriceOptionCreateExecutionResult {
            ++$this->calls;
            return new PriceOptionCreateExecutionResult($this->outcome, $this->attempted);
        }
    }
}
namespace {
    use Anatolkin\ArpRestaurant\Backend\Controller\RestaurantEditorController;
    use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreateContext;
    use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePlanBuilder;
    use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\RestaurantPriceOptionCreateReader;
    use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\ExistingPriceOptionSnapshot;
    use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write\RestaurantPriceOptionCreateWriter;
    use TYPO3\CMS\Core\Http\RedirectResponse;
    use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Anatolkin\\ArpRestaurant\\';
        if (str_starts_with($class, $prefix)) {
            require dirname(__DIR__, 2) . '/Classes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    });
    $args = [];
    foreach ((new ReflectionClass(PriceOptionCreateContext::class))->getConstructor()->getParameters() as $p) {
        $args[$p->getName()] = match ((string)$p->getType()) { 'int' => 10, 'string' => 'Dinner', 'array' => [], default => null };
    }
    $context = new PriceOptionCreateContext(...$args);
    $builder = new PriceOptionCreatePlanBuilder();
    $plan = $builder->prepare($context, 'Family', '7.99')->plan;
    $reader = new RestaurantPriceOptionCreateReader();
    $reader->context = $context;
    $writer = new RestaurantPriceOptionCreateWriter();
    $guard = new \Anatolkin\ArpRestaurant\Backend\Editor\BackendAccessGuard();
    $flashes = new \TYPO3\CMS\Core\Messaging\FlashMessageService();
    $controllerClass = new ReflectionClass(RestaurantEditorController::class);
    $controller = $controllerClass->newInstanceWithoutConstructor();
    foreach (['priceOptionCreateReader' => $reader, 'priceOptionCreatePlanBuilder' => $builder, 'priceOptionCreateWriter' => $writer,
        'accessGuard' => $guard, 'flashMessageService' => $flashes, 'uriBuilder' => new \TYPO3\CMS\Backend\Routing\UriBuilder(),
        'formProtectionFactory' => new \TYPO3\CMS\Core\FormProtection\FormProtectionFactory()] as $property => $value) {
        $controllerClass->getProperty($property)->setValue($controller, $value);
    }
    $apply = $controllerClass->getMethod('processPriceOptionCreateApply');
    $base = ['priceCreateApplyToken' => 'valid', 'menu' => 10, 'placementUid' => 10, 'label' => 'Family', 'price' => '7.99', 'confirmedFingerprint' => $plan->fingerprint];
    $run = static function ($body) use ($apply, $controller, $writer, $reader, $guard, $flashes) {
        $writer->calls = $reader->calls = $guard->calls = 0;
        $flashes->messages = [];
        $request = new class($body) implements \Psr\Http\Message\ServerRequestInterface {
            public function __construct(private $body) {}
            public function getParsedBody() { return $this->body; }
        };
        return $apply->invoke($controller, $request, 10, [], new \TYPO3\CMS\Core\Authentication\BackendUserAuthentication(), new \TYPO3\CMS\Core\Localization\LanguageService());
    };
    $failures = 0;
    $check = static function ($ok, $message) use (&$failures) {
        echo ($ok ? 'PASS  ' : 'FAIL  ') . $message . "\n";
        $failures += $ok ? 0 : 1;
    };
    foreach ([null, array_replace($base, ['priceCreateApplyToken' => 'reviewToken'])] as $body) {
        $result = $run($body);
        $check(is_array($result) && $result['requestError'] === 'invalidCsrf' && $writer->calls === 0 && $reader->calls === 0 && $guard->calls === 0, 'invalid CSRF/body stops before permission, graph and writer');
    }
    $guard->blocker = 'fieldModifyDenied';
    $result = $run($base);
    $check($result['blockers'][0]->code === 'fieldModifyDenied' && $reader->calls === 0 && $writer->calls === 0, 'fresh permission denial stops writer');
    $guard->blocker = '';
    $reader->context = null;
    $result = $run($base);
    $check($result['blockers'][0]->code === 'wrongMenu' && $writer->calls === 0, 'fresh graph denial stops writer');
    $reader->context = $context;
    foreach (['', strtoupper($plan->fingerprint), $plan->fingerprint . "\n"] as $fingerprint) {
        $result = $run(array_replace($base, ['confirmedFingerprint' => $fingerprint]));
        $check(is_array($result) && $result['confirmationWarning'] === 'writePreparationBlocked' && $writer->calls === 0, 'malformed fingerprint stops writer and PRG');
    }
    foreach (['label' => 'Party', 'price' => '8.49', 'confirmedFingerprint' => str_repeat('0', 64)] as $field => $value) {
        $result = $run(array_replace($base, [$field => $value]));
        $check(is_array($result) && $result['confirmationWarning'] === 'confirmationStale' && $writer->calls === 0 && $result['review']->outcome === 'createReady', 'live confirmation stale: ' . $field);
    }
    $args['existingPriceOptions'] = [new ExistingPriceOptionSnapshot(40, '11111111-bbbb-4ccc-8ddd-eeeeeeeeeeee', 10, 'family', 799, 256, 0)];
    $reader->context = new PriceOptionCreateContext(...$args);
    $result = $run($base);
    $check($result['blockers'][0]->code === 'duplicateVariant' && $writer->calls === 0, 'fresh duplicate blocks without writer/PRG');
    $args['existingPriceOptions'] = [new ExistingPriceOptionSnapshot(40, '11111111-bbbb-4ccc-8ddd-eeeeeeeeeeee', 10, 'Small', 799, 256, 0)];
    $reader->context = new PriceOptionCreateContext(...$args);
    $result = $run($base);
    $check($result['confirmationWarning'] === 'confirmationStale' && $result['review']->plan->plannedSorting === 512 && $writer->calls === 0, 'fresh sorting and snapshot change stops writer');
    $reader->context = $context;
    $writer->attempted = false;
    $result = $run($base);
    $check(is_array($result) && $writer->calls === 1 && $result['confirmationWarning'] === 'writePreparationBlocked' && $flashes->messages === [], 'pre-attempt failure stays rendered without flash/PRG');
    $writer->attempted = true;
    foreach (['created' => ContextualFeedbackSeverity::OK, 'partialFailure' => ContextualFeedbackSeverity::WARNING, 'failed' => ContextualFeedbackSeverity::ERROR] as $outcome => $severity) {
        $writer->outcome = $outcome;
        $result = $run(array_replace($base, ['plannedSorting' => 999, 'public_uuid' => 'forged', 'tstamp' => 999, 'category' => 999, 'item' => 999]));
        $check($writer->calls === 1 && $guard->calls === 1 && $reader->calls === 1
            && $result instanceof RedirectResponse && $result->status === 303
            && $result->uri === '/editor?id=10&menu=10&priceOptionCreate=10'
            && count($flashes->messages) === 1 && $flashes->messages[0]->severity === $severity,
            'exact confirmation ignores posted authority; attempted ' . $outcome . ' gets correct flash and PRG');
    }
    exit($failures === 0 ? 0 : 1);
}
