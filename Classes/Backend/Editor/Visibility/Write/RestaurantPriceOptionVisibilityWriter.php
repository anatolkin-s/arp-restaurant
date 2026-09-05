<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityPlan;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\RestaurantPriceOptionVisibilityReader;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sole restaurant-record write boundary for PriceOption.hidden.
 *
 * Uses DataHandler::start + process_datamap only. No process_cmdmap,
 * transactions, bypass flags, or QueryBuilder mutation.
 */
final class RestaurantPriceOptionVisibilityWriter
{
    public function __construct(
        private readonly RestaurantPriceOptionVisibilityReader $visibilityReader,
        private readonly PriceOptionVisibilityVerifier $verifier = new PriceOptionVisibilityVerifier(),
    ) {}

    public function execute(
        PriceOptionVisibilityPlan $plan,
        int $selectedMenuUid,
        BackendUserAuthentication $backendUser,
    ): PriceOptionVisibilityExecutionResult {
        try {
            $dataMap = PriceOptionVisibilityDataMap::fromPlan($plan);
        } catch (\Throwable) {
            return new PriceOptionVisibilityExecutionResult(
                outcome: 'failed',
                dataHandlerAttempted: false,
                diagnostics: ['writePreparationFailed'],
            );
        }

        $dataHandlerAttempted = false;
        $processThrew = false;
        $snapshot = PriceOptionVisibilityDataHandlerStateSnapshot::empty();
        /** @var DataHandler|null $dataHandler */
        $dataHandler = null;

        try {
            /** @var DataHandler $dataHandler */
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($dataMap->payload, [], $backendUser);
            $dataHandlerAttempted = true;
            $dataHandler->process_datamap();
        } catch (\Throwable) {
            if ($dataHandlerAttempted) {
                $processThrew = true;
            } else {
                return new PriceOptionVisibilityExecutionResult(
                    outcome: 'failed',
                    dataHandlerAttempted: false,
                    diagnostics: ['writePreparationFailed'],
                );
            }
        } finally {
            if ($dataHandler !== null) {
                $snapshot = PriceOptionVisibilityDataHandlerStateSnapshot::fromDataHandler($dataHandler);
            }
        }

        $afterContext = null;
        try {
            $load = $this->visibilityReader->load(
                $plan->pid,
                $selectedMenuUid,
                $plan->uid,
                $backendUser,
            );
            if ($load->outcome === 'loaded') {
                $afterContext = $load->context;
            }
        } catch (\Throwable) {
            // verificationReadFailed handled by verifier when context is null
        }

        return $this->verifier->verify(
            $plan,
            $afterContext,
            $snapshot->errorLog,
            $processThrew,
        );
    }
}
