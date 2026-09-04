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
        $dataMap = $this->dataMapBuilder->build($plan, $pid, $sortContext);

        $errorLog = [];
        $substNEWwithIDs = [];
        $substNEWwithIDsTable = [];
        $threw = false;

        try {
            /** @var DataHandler $dataHandler */
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($dataMap->dataMap, [], $backendUser);
            $dataHandler->process_datamap();
            $errorLog = is_array($dataHandler->errorLog) ? array_values($dataHandler->errorLog) : [];
            $substNEWwithIDs = is_array($dataHandler->substNEWwithIDs)
                ? $dataHandler->substNEWwithIDs
                : [];
            $substNEWwithIDsTable = is_array($dataHandler->substNEWwithIDs_table)
                ? $dataHandler->substNEWwithIDs_table
                : [];
        } catch (\Throwable) {
            $threw = true;
            $errorLog[] = 'applyException';
        }

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

        $result = $this->verifier->verify(
            $dataMap,
            $substNEWwithIDs,
            $substNEWwithIDsTable,
            $errorLog,
            $fetched,
            $pid,
        );

        if ($threw && $result->outcome === 'failed' && $fetched !== []) {
            return new ApplyExecutionResult(
                outcome: 'partialFailure',
                createdCategories: $result->createdCategories,
                createdItems: $result->createdItems,
                createdPlacements: $result->createdPlacements,
                createdPriceOptions: $result->createdPriceOptions,
                diagnostics: $result->diagnostics,
                createdUidsByLocalRef: $result->createdUidsByLocalRef,
            );
        }

        return $result;
    }
}
