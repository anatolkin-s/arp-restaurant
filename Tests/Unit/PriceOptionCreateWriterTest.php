<?php

declare(strict_types=1);

namespace TYPO3\CMS\Core\Authentication {
    class BackendUserAuthentication {}
}
namespace TYPO3\CMS\Core\DataHandling {
    class DataHandler {
        public static string $mode = '';
        public static int $calls = 0;
        public array $errorLog = [];
        public array $substNEWwithIDs = [];
        public array $substNEWwithIDs_table = [];
        private array $map;
        public function start(array $map, array $commands, $user): void {
            if (self::$mode === 'start') { throw new \RuntimeException(); }
            if ($commands !== []) { throw new \LogicException(); }
            $this->map = $map;
        }
        public function process_datamap(): void {
            ++self::$calls;
            $table = array_key_first($this->map);
            $token = array_key_first($this->map[$table]);
            if (self::$mode === 'process') { throw new \RuntimeException(); }
            $this->substNEWwithIDs[$token] = 99;
            $this->substNEWwithIDs_table[$token] = $table;
            if (self::$mode === 'mappedException') { throw new \RuntimeException(); }
        }
    }
}
namespace TYPO3\CMS\Core\Utility {
    class GeneralUtility {
        public static function makeInstance(string $class): object {
            if (\TYPO3\CMS\Core\DataHandling\DataHandler::$mode === 'construct') { throw new \RuntimeException(); }
            return new $class();
        }
    }
}
namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write {
    class RestaurantPriceOptionCreateResultReader {
        public function load($uid, $plan, $user): PriceOptionCreateVerificationSnapshot {
            throw new \RuntimeException('Read failed');
        }
    }
}
namespace {
    use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePlan;
    use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write\RestaurantPriceOptionCreateResultReader;
    use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write\RestaurantPriceOptionCreateWriter;
    use TYPO3\CMS\Core\DataHandling\DataHandler;
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Anatolkin\\ArpRestaurant\\';
        if (str_starts_with($class, $prefix)) {
            require dirname(__DIR__, 2) . '/Classes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    });
    $args = [];
    foreach ((new ReflectionClass(PriceOptionCreatePlan::class))->getConstructor()->getParameters() as $p) {
        $args[$p->getName()] = match ((string)$p->getType()) { 'int' => 10, 'array' => [], default => 'Family' };
    }
    $args['plannedSorting'] = 256;
    $plan = new PriceOptionCreatePlan(...$args);
    $writer = new RestaurantPriceOptionCreateWriter(new RestaurantPriceOptionCreateResultReader());
    $user = new \TYPO3\CMS\Core\Authentication\BackendUserAuthentication();
    $failures = 0;
    foreach (['construct' => [false, 'failed', 0], 'start' => [false, 'failed', 0], 'process' => [true, 'failed', 1],
        'mappedException' => [true, 'partialFailure', 1], '' => [true, 'partialFailure', 1]] as $mode => $expected) {
        DataHandler::$mode = $mode;
        DataHandler::$calls = 0;
        $result = $writer->execute($plan, $user);
        $ok = [$result->dataHandlerAttempted, $result->outcome, DataHandler::$calls] === $expected;
        echo ($ok ? 'PASS  ' : 'FAIL  ') . 'writer attempt boundary: ' . ($mode ?: 'read failure') . "\n";
        $failures += $ok ? 0 : 1;
    }
    $args['pid'] = 0;
    DataHandler::$calls = 0;
    $result = $writer->execute(new PriceOptionCreatePlan(...$args), $user);
    $ok = !$result->dataHandlerAttempted && DataHandler::$calls === 0;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . "invalid map stops before DataHandler\n";
    exit($failures + ($ok ? 0 : 1) === 0 ? 0 : 1);
}
