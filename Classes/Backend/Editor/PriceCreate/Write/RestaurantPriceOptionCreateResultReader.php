<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write;

use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphAssembler;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePlan;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\RestaurantPriceOptionCreateReader;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/** SELECT-only read-back by the exact DataHandler substitution uid. */
final class RestaurantPriceOptionCreateResultReader
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly RestaurantPriceOptionCreateReader $graphReader,
    ) {}

    public function load(int $uid, PriceOptionCreatePlan $plan, BackendUserAuthentication $backendUser): PriceOptionCreateVerificationSnapshot
    {
        $query = $this->connectionPool->getQueryBuilderForTable(MenuGraphAssembler::TABLE_PRICEOPTION);
        // Include deleted/hidden rows so verification rejects them explicitly.
        $query->getRestrictions()->removeAll();
        $row = $query->select('uid', 'pid', 'placement', 'label', 'amount', 'sorting', 'hidden', 'public_uuid', 'tstamp', 'sys_language_uid', 'deleted')
            ->from(MenuGraphAssembler::TABLE_PRICEOPTION)
            ->where($query->expr()->eq('uid', $query->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()->fetchAssociative();
        $graph = $this->graphReader->load($plan->pid, $plan->menuUid, $plan->placementUid, $backendUser);

        return new PriceOptionCreateVerificationSnapshot(is_array($row) ? $row : null, $graph->context);
    }
}
