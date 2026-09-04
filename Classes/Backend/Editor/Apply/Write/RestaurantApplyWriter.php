<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\Apply\ApplyPlan;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sole restaurant-record write boundary for compact-editor Apply.
 *
 * Uses DataHandler::start + process_datamap only. No process_cmdmap,
 * transactions, bypass flags, or QueryBuilder mutation.
 */
final class RestaurantApplyWriter
{
    public function __construct(
        private readonly RestaurantApplyCreatedRecordReader $createdRecordReader,
        private readonly ApplyDataMapBuilder $dataMapBuilder = new ApplyDataMapBuilder(),
        private readonly ApplyWriteVerifier $verifier = new ApplyWriteVerifier(),
    ) {}

    public function execute(
        ApplyPlan $plan,
        ApplySortContext $sortContext,
        int $pid,
        BackendUserAuthentication $backendUser,
    ): ApplyExecutionResult {
        try {
            $dataMap = $this->dataMapBuilder->build($plan, $pid, $sortContext);
        } catch (\Throwable) {
            return new ApplyExecutionResult(
                outcome: 'failed',
                createdCategories: 0,
                createdItems: 0,
                createdPlacements: 0,
                createdPriceOptions: 0,
                dataHandlerAttempted: false,
                diagnostics: ['writePreparationFailed'],
            );
        }

        $dataHandlerAttempted = false;
        $processThrew = false;
        $snapshot = ApplyDataHandlerStateSnapshot::empty();
        /** @var DataHandler|null $dataHandler */
        $dataHandler = null;

        try {
            /** @var DataHandler $dataHandler */
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($dataMap->dataMap, [], $backendUser);
            $dataHandlerAttempted = true;
            $dataHandler->process_datamap();
        } catch (\Throwable) {
            if ($dataHandlerAttempted) {
                $processThrew = true;
            } else {
                return new ApplyExecutionResult(
                    outcome: 'failed',
                    createdCategories: 0,
                    createdItems: 0,
                    createdPlacements: 0,
                    createdPriceOptions: 0,
                    dataHandlerAttempted: false,
                    diagnostics: ['writePreparationFailed'],
                );
            }
        } finally {
            if ($dataHandler !== null) {
                $snapshot = ApplyDataHandlerStateSnapshot::fromDataHandler($dataHandler);
            }
        }

        $errorLog = $snapshot->errorLog;
        if ($processThrew) {
            $errorLog[] = 'applyException';
        }

        return $this->verifyAfterAttempt(
            $dataMap,
            $snapshot->substNEWwithIDs,
            $snapshot->substNEWwithIDsTable,
            $errorLog,
            $pid,
            $backendUser,
            $processThrew,
        );
    }

    /**
     * @param array<string, int|string> $substNEWwithIDs
     * @param array<string, string> $substNEWwithIDsTable
     * @param list<string> $errorLog
     */
    private function verifyAfterAttempt(
        ApplyDataMap $dataMap,
        array $substNEWwithIDs,
        array $substNEWwithIDsTable,
        array $errorLog,
        int $pid,
        BackendUserAuthentication $backendUser,
        bool $processThrew,
    ): ApplyExecutionResult {
        $targets = [];
        foreach ($dataMap->expectedCreates as $expected) {
            $uid = (int)($substNEWwithIDs[$expected->newToken] ?? 0);
            if ($uid > 0) {
                $targets[] = ['table' => $expected->table, 'uid' => $uid];
            }
        }

        $fetched = [];
        try {
            $fetched = $this->createdRecordReader->fetchMany($targets, $backendUser);
        } catch (\Throwable) {
            $errorLog[] = 'verificationReadFailed';
        }

        try {
            $result = $this->verifier->verify(
                $dataMap,
                $substNEWwithIDs,
                $substNEWwithIDsTable,
                $errorLog,
                $fetched,
                $pid,
            );
        } catch (\Throwable) {
            return new ApplyExecutionResult(
                outcome: $fetched !== [] ? 'partialFailure' : 'failed',
                createdCategories: 0,
                createdItems: 0,
                createdPlacements: 0,
                createdPriceOptions: 0,
                dataHandlerAttempted: true,
                diagnostics: array_values(array_unique(array_merge($errorLog, ['verificationFailed']))),
            );
        }

        if ($processThrew && $result->outcome === 'failed' && $fetched !== []) {
            return new ApplyExecutionResult(
                outcome: 'partialFailure',
                createdCategories: $result->createdCategories,
                createdItems: $result->createdItems,
                createdPlacements: $result->createdPlacements,
                createdPriceOptions: $result->createdPriceOptions,
                dataHandlerAttempted: true,
                diagnostics: $result->diagnostics,
                createdUidsByLocalRef: $result->createdUidsByLocalRef,
            );
        }

        return new ApplyExecutionResult(
            outcome: $result->outcome,
            createdCategories: $result->createdCategories,
            createdItems: $result->createdItems,
            createdPlacements: $result->createdPlacements,
            createdPriceOptions: $result->createdPriceOptions,
            dataHandlerAttempted: true,
            diagnostics: $result->diagnostics,
            createdUidsByLocalRef: $result->createdUidsByLocalRef,
        );
    }
}
