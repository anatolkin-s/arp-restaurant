<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdatePlan;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\RestaurantPriceOptionEditReader;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sole restaurant-record write boundary for existing PriceOption update.
 *
 * Uses DataHandler::start + process_datamap only. No process_cmdmap,
 * transactions, bypass flags, or QueryBuilder mutation.
 */
final class RestaurantPriceOptionUpdateWriter
{
    public function __construct(
        private readonly RestaurantPriceOptionEditReader $priceOptionEditReader,
        private readonly PriceOptionUpdateDataMapBuilder $dataMapBuilder = new PriceOptionUpdateDataMapBuilder(),
        private readonly PriceOptionUpdateVerifier $verifier = new PriceOptionUpdateVerifier(),
    ) {}

    public function execute(
        PriceOptionUpdatePlan $plan,
        int $selectedMenuUid,
        BackendUserAuthentication $backendUser,
    ): PriceOptionUpdateExecutionResult {
        try {
            $dataMap = $this->dataMapBuilder->build($plan);
        } catch (\Throwable) {
            return new PriceOptionUpdateExecutionResult(
                outcome: 'failed',
                dataHandlerAttempted: false,
                diagnostics: ['writePreparationFailed'],
            );
        }

        $dataHandlerAttempted = false;
        $processThrew = false;
        $snapshot = PriceOptionUpdateDataHandlerStateSnapshot::empty();
        /** @var DataHandler|null $dataHandler */
        $dataHandler = null;

        try {
            /** @var DataHandler $dataHandler */
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($dataMap, [], $backendUser);
            $dataHandlerAttempted = true;
            $dataHandler->process_datamap();
        } catch (\Throwable) {
            if ($dataHandlerAttempted) {
                $processThrew = true;
            } else {
                return new PriceOptionUpdateExecutionResult(
                    outcome: 'failed',
                    dataHandlerAttempted: false,
                    diagnostics: ['writePreparationFailed'],
                );
            }
        } finally {
            if ($dataHandler !== null) {
                $snapshot = PriceOptionUpdateDataHandlerStateSnapshot::fromDataHandler($dataHandler);
            }
        }

        $afterContext = null;
        try {
            $load = $this->priceOptionEditReader->load(
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
