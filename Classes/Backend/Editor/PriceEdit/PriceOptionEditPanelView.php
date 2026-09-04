<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit;

/**
 * Fluid-facing existing PriceOption edit / review / confirm panel state.
 *
 * @param list<PriceOptionEditBlocker> $blockers
 */
final readonly class PriceOptionEditPanelView
{
    public bool $hasPanel;

    public function __construct(
        public string $formAction,
        public string $priceEditToken,
        public string $priceEditApplyToken,
        public int $pid,
        public int $menuUid,
        public int $priceOptionUid,
        public ?PriceOptionEditContext $context,
        public ?PriceOptionUpdatePreparationResult $review,
        public string $submittedLabel,
        public string $submittedPrice,
        public string $requestError,
        public array $blockers,
        public string $cancelUrl,
        public string $confirmationWarning = '',
    ) {
        $this->hasPanel = $this->priceOptionUid > 0
            || $this->context !== null
            || $this->review !== null
            || $this->requestError !== ''
            || $this->blockers !== []
            || $this->confirmationWarning !== '';
    }
}
