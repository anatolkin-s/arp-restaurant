<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePlan;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/** Sole persistence boundary for creating one PriceOption under an existing Placement. */
final class RestaurantPriceOptionCreateWriter
{
    public function __construct(
        private readonly RestaurantPriceOptionCreateResultReader $resultReader,
        private readonly PriceOptionCreateDataMapBuilder $dataMapBuilder = new PriceOptionCreateDataMapBuilder(),
        private readonly PriceOptionCreateVerifier $verifier = new PriceOptionCreateVerifier(),
    ) {}

    public function execute(PriceOptionCreatePlan $plan, BackendUserAuthentication $backendUser): PriceOptionCreateExecutionResult
    {
        $dataHandlerAttempted = false;
        $processThrew = false;
        $dataHandler = null;
        $state = PriceOptionCreateDataHandlerStateSnapshot::empty();
        try {
            $map = $this->dataMapBuilder->build($plan);
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($map->dataMap, [], $backendUser);
            $dataHandlerAttempted = true;
            $dataHandler->process_datamap();
        } catch (\Throwable) {
            if (!$dataHandlerAttempted) {
                return new PriceOptionCreateExecutionResult('failed', false, ['writePreparationFailed']);
            }
            $processThrew = true;
        } finally {
            if ($dataHandler !== null) {
                $state = PriceOptionCreateDataHandlerStateSnapshot::fromDataHandler($dataHandler);
            }
        }
        $after = null;
        $uid = $this->verifier->resolveUid($map->newToken, $state);
        if ($uid !== null) {
            try {
                $after = $this->resultReader->load($uid, $plan, $backendUser);
            } catch (\Throwable) {
                // The verifier reports incomplete read-back without retrying.
            }
        }

        return $this->verifier->verify($plan, $map->newToken, $state, $after, $processThrew);
    }
}
