<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Visibility;

/**
 * Fluid-facing PriceOption visibility review panel state.
 *
 * @param list<PriceOptionVisibilityBlocker> $blockers
 */
final readonly class PriceOptionVisibilityPanelView
{
    public bool $hasPanel;

    public function __construct(
        public string $formAction,
        public string $priceVisibilityToken,
        public int $pid,
        public int $menuUid,
        public int $priceOptionUid,
        public ?PriceOptionVisibilityContext $context,
        public ?PriceOptionVisibilityPreparationResult $review,
        public string $submittedVisibility,
        public string $requestError,
        public array $blockers,
        public string $cancelUrl,
    ) {
        $this->hasPanel = $this->priceOptionUid > 0
            || $this->context !== null
            || $this->review !== null
            || $this->requestError !== ''
            || $this->blockers !== [];
    }
}
