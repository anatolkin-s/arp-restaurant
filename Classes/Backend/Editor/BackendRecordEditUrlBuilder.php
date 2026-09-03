<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;

/**
 * Builds FormEngine record_edit URLs.
 *
 * hideTable only hides records from the List module. The documented
 * record_edit route still opens Category, Placement, and PriceOption.
 */
final class BackendRecordEditUrlBuilder implements RecordEditUrlBuilder
{
    public function __construct(
        private readonly UriBuilder $uriBuilder,
        private readonly ServerRequestInterface $request,
    ) {}

    public function build(string $table, int $uid): ?string
    {
        if ($uid <= 0) {
            return null;
        }

        $normalizedParams = $this->request->getAttribute('normalizedParams');
        $returnUrl = is_object($normalizedParams) && method_exists($normalizedParams, 'getRequestUri')
            ? (string)$normalizedParams->getRequestUri()
            : '';

        return (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [
                $table => [
                    $uid => 'edit',
                ],
            ],
            'returnUrl' => $returnUrl,
        ]);
    }
}
