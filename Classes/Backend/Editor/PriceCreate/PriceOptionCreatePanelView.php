<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate;

/**
 * Fluid-facing add-PriceOption review and confirmation panel.
 *
 * @param list<PriceOptionCreateBlocker> $blockers
 */
final readonly class PriceOptionCreatePanelView
{
    public bool $hasPanel;

    public function __construct(
        public string $formAction,
        public string $priceCreateToken,
        public int $pid,
        public int $menuUid,
        public int $placementUid,
        public ?PriceOptionCreateContext $context,
        public ?PriceOptionCreatePreparationResult $review,
        public string $submittedLabel,
        public string $submittedPrice,
        public string $requestError,
        public array $blockers,
        public string $cancelUrl,
        public string $priceCreateApplyToken = '',
        public string $confirmationWarning = '',
    ) {
        $this->hasPanel = $this->placementUid > 0
            || $this->context !== null
            || $this->review !== null
            || $this->requestError !== ''
            || $this->blockers !== [];
    }
}
