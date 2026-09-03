<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor\Identity;

/**
 * Identity-level blocker distinct from draft errors and draft warnings.
 * code examples: ambiguousItem, ambiguousCategory, missingTargetMenu,
 * wrongPidTargetMenu, translatedTargetMenu, missingPublicUuid,
 * nonLiveWorkspace, pageContentEditDenied, tablesModifyDenied,
 * draftNotValid
 */
final readonly class IdentityBlocker
{
    public function __construct(
        public string $code,
        public string $entityKind = '',
        public string $normalizedTitle = '',
        public int $matchCount = 0,
    ) {}
}
