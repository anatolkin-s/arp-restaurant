<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Bulk;

use Anatolkin\ArpRestaurant\Backend\Editor\RestaurantTitleNormalizer;

/**
 * Shared Placement-run identity for draft validation and future Placement counts.
 *
 * Logical Category+Item equality uses RestaurantTitleNormalizer::matchKey, not
 * case-preserving display strings. Variant duplicate comparison uses the same
 * match-key contract. Prefixed keys keep numeric-looking labels string-safe.
 */
final class BulkDraftRunGrouping
{
    public function __construct(
        private readonly RestaurantTitleNormalizer $titleNormalizer = new RestaurantTitleNormalizer(),
    ) {}

    public function categoryItemKey(string $categoryDisplay, string $itemDisplay): string
    {
        return 'r:'
            . $this->titleNormalizer->matchKey($categoryDisplay)
            . "\0"
            . $this->titleNormalizer->matchKey($itemDisplay);
    }

    public function sameCategoryItem(
        string $leftCategory,
        string $leftItem,
        string $rightCategory,
        string $rightItem,
    ): bool {
        return $this->categoryItemKey($leftCategory, $leftItem)
            === $this->categoryItemKey($rightCategory, $rightItem);
    }

    /**
     * Prefixed so PHP does not cast integer-looking Variant labels to int keys.
     */
    public function variantDuplicateKey(string $variantDisplay): string
    {
        return 'v:' . $this->titleNormalizer->matchKey($variantDisplay);
    }
}
