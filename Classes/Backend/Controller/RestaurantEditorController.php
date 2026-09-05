<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Controller;

use Anatolkin\ArpRestaurant\Backend\Editor\Apply\BulkApplyPlanBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\BulkApplyPreparationResult;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write\ApplyExecutionResult;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write\RestaurantApplySortPositionReader;
use Anatolkin\ArpRestaurant\Backend\Editor\Apply\Write\RestaurantApplyWriter;
use Anatolkin\ArpRestaurant\Backend\Editor\BackendAccessGuard;
use Anatolkin\ArpRestaurant\Backend\Editor\BackendRecordEditUrlBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftValidationResult;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkDraftValidator;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkMenuParser;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkPreviewView;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\BulkIdentityResolutionResult;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\BulkIdentityResolver;
use Anatolkin\ArpRestaurant\Backend\Editor\Identity\RestaurantIdentityReader;
use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphReader;
use Anatolkin\ArpRestaurant\Backend\Editor\ModuleLinkButtonFactory;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreateBlocker;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePanelView;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePlanBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreatePreparationResult;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\RestaurantPriceOptionCreateReader;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write\RestaurantPriceOptionCreateWriter;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\Write\PriceOptionCreateExecutionResult;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditBlocker;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditPanelView;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdatePlanBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdatePreparationResult;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\RestaurantPriceOptionEditReader;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\Write\PriceOptionUpdateExecutionResult;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\Write\RestaurantPriceOptionUpdateWriter;
use Anatolkin\ArpRestaurant\Backend\Editor\ViewModel\EditorScreen;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityBlocker;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityPanelView;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityPlanBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityPreparationResult;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\RestaurantPriceOptionVisibilityReader;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\Write\PriceOptionVisibilityExecutionResult;
use Anatolkin\ArpRestaurant\Backend\Editor\Visibility\Write\RestaurantPriceOptionVisibilityWriter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\MathUtility;

#[AsController]
final class RestaurantEditorController
{
    private const LLL = 'LLL:EXT:arp_restaurant/Resources/Private/Language/locallang_mod_editor.xlf:';
    private const BULK_FORM = 'web_arp_restaurant_editor';
    private const BULK_PREVIEW_ACTION = 'bulkPreview';
    private const BULK_REVALIDATE_ACTION = 'bulkDraftRevalidate';
    private const BULK_RESET_ACTION = 'bulkDraftReset';
    private const BULK_RESOLVE_ACTION = 'bulkIdentityResolve';
    private const BULK_PREPARE_ACTION = 'bulkApplyPrepare';
    private const BULK_APPLY_ACTION = 'bulkApply';
    private const PRICE_EDIT_REVIEW_ACTION = 'priceOptionEditReview';
    private const PRICE_EDIT_APPLY_ACTION = 'priceOptionEditApply';
    private const PRICE_VISIBILITY_REVIEW_ACTION = 'priceOptionVisibilityReview';
    private const PRICE_VISIBILITY_APPLY_ACTION = 'priceOptionVisibilityApply';
    private const PRICE_CREATE_REVIEW_ACTION = 'priceOptionCreateReview';
    private const PRICE_CREATE_APPLY_ACTION = 'priceOptionCreateApply';
    private const FINGERPRINT_PATTERN = '/^[0-9a-f]{64}$/';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly IconFactory $iconFactory,
        private readonly BackendAccessGuard $accessGuard,
        private readonly MenuGraphReader $menuGraphReader,
        private readonly ModuleLinkButtonFactory $linkButtonFactory,
        private readonly FormProtectionFactory $formProtectionFactory,
        private readonly BulkMenuParser $bulkMenuParser,
        private readonly BulkDraftValidator $bulkDraftValidator,
        private readonly RestaurantIdentityReader $identityReader,
        private readonly BulkIdentityResolver $identityResolver,
        private readonly BulkApplyPlanBuilder $applyPlanBuilder,
        private readonly RestaurantApplySortPositionReader $applySortPositionReader,
        private readonly RestaurantApplyWriter $applyWriter,
        private readonly FlashMessageService $flashMessageService,
        private readonly RestaurantPriceOptionEditReader $priceOptionEditReader,
        private readonly PriceOptionUpdatePlanBuilder $priceOptionUpdatePlanBuilder,
        private readonly RestaurantPriceOptionUpdateWriter $priceOptionUpdateWriter,
        private readonly RestaurantPriceOptionVisibilityReader $priceOptionVisibilityReader,
        private readonly PriceOptionVisibilityPlanBuilder $priceOptionVisibilityPlanBuilder,
        private readonly RestaurantPriceOptionVisibilityWriter $priceOptionVisibilityWriter,
        private readonly RestaurantPriceOptionCreateReader $priceOptionCreateReader,
        private readonly PriceOptionCreatePlanBuilder $priceOptionCreatePlanBuilder,
        private readonly RestaurantPriceOptionCreateWriter $priceOptionCreateWriter,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->getLanguageService();
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($languageService->sL(self::LLL . 'mlang_tabs_tab'));

        $moduleTemplate->assign('lll', self::LLL);
        $moduleTemplate->assign('bulk', null);
        $moduleTemplate->assign('priceEdit', null);
        $moduleTemplate->assign('priceVisibility', null);
        $moduleTemplate->assign('priceCreate', null);

        $pid = $this->resolvePid($request);
        $backendUser = $this->getBackendUser();

        if ($pid <= 0) {
            $this->addListButton($moduleTemplate, 0, $languageService);
            $moduleTemplate->assign('screen', $this->emptyScreen(0, '', 'selectPage'));
            return $moduleTemplate->renderResponse('RestaurantEditor/Index');
        }

        $page = $this->accessGuard->readPage($pid, $backendUser);
        if ($page === null) {
            $this->addListButton($moduleTemplate, $pid, $languageService);
            $moduleTemplate->assign('screen', $this->emptyScreen($pid, '', 'noPageAccess'));
            return $moduleTemplate->renderResponse('RestaurantEditor/Index');
        }

        $pageTitle = trim((string)($page['title'] ?? ''));
        $this->addListButton($moduleTemplate, $pid, $languageService);

        if (!$this->accessGuard->canSelectRestaurantTables($backendUser)) {
            $moduleTemplate->assign('screen', $this->emptyScreen($pid, $pageTitle, 'noTableAccess'));
            return $moduleTemplate->renderResponse('RestaurantEditor/Index');
        }

        $requestedMenuUid = (int)($request->getQueryParams()['menu'] ?? ($request->getParsedBody()['menu'] ?? 0));

        $applyRenderState = null;
        $priceEditReviewState = null;
        $visibilityReviewState = null;
        $priceCreateReviewState = null;
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['bulkApply'])) {
            $applyHandled = $this->processBulkApplyWrite($request, $pid, $page, $backendUser, $languageService);
            if ($applyHandled instanceof RedirectResponse) {
                return $applyHandled;
            }
            $applyRenderState = $applyHandled;
        } elseif (is_array($body) && isset($body['priceOptionEditApply'])) {
            $priceEditHandled = $this->processPriceOptionEditApply(
                $request,
                $pid,
                $page,
                $backendUser,
                $languageService,
            );
            if ($priceEditHandled instanceof RedirectResponse) {
                return $priceEditHandled;
            }
            $priceEditReviewState = $priceEditHandled;
        } elseif (is_array($body) && isset($body['priceOptionEditReview'])) {
            $priceEditReviewState = $this->processPriceOptionEditReview($request, $pid, $page, $backendUser);
        } elseif (is_array($body) && isset($body['priceOptionVisibilityApply'])) {
            $visibilityHandled = $this->processPriceOptionVisibilityApply(
                $request,
                $pid,
                $page,
                $backendUser,
                $languageService,
            );
            if ($visibilityHandled instanceof RedirectResponse) {
                return $visibilityHandled;
            }
            $visibilityReviewState = $visibilityHandled;
        } elseif (is_array($body) && isset($body['priceOptionVisibilityReview'])) {
            $visibilityReviewState = $this->processPriceOptionVisibilityReview($request, $pid, $page, $backendUser);
        } elseif (is_array($body) && isset($body['priceOptionCreateApply'])) {
            $createHandled = $this->processPriceOptionCreateApply($request, $pid, $page, $backendUser, $languageService);
            if ($createHandled instanceof RedirectResponse) {
                return $createHandled;
            }
            $priceCreateReviewState = $createHandled;
        } elseif (is_array($body) && isset($body['priceOptionCreateReview'])) {
            $priceCreateReviewState = $this->processPriceOptionCreateReview($request, $pid, $page, $backendUser);
        }

        $editUrlBuilder = new BackendRecordEditUrlBuilder($this->uriBuilder, $request);
        $uriBuilder = $this->uriBuilder;
        $screen = $this->menuGraphReader->load(
            $pid,
            $pageTitle,
            $requestedMenuUid,
            time(),
            $backendUser,
            static function (int $menuUid) use ($uriBuilder, $pid): string {
                return (string)$uriBuilder->buildUriFromRoute(
                    'web_arp_restaurant_editor',
                    ['id' => $pid, 'menu' => $menuUid]
                );
            },
            $editUrlBuilder,
            static function (int $optionUid, int $menuUid) use ($uriBuilder, $pid): string {
                return (string)$uriBuilder->buildUriFromRoute(
                    'web_arp_restaurant_editor',
                    ['id' => $pid, 'menu' => $menuUid, 'priceOption' => $optionUid]
                );
            },
            static function (int $optionUid, int $menuUid) use ($uriBuilder, $pid): string {
                return (string)$uriBuilder->buildUriFromRoute(
                    'web_arp_restaurant_editor',
                    ['id' => $pid, 'menu' => $menuUid, 'priceOptionVisibility' => $optionUid]
                );
            },
            static function (int $placementUid, int $menuUid) use ($uriBuilder, $pid): string {
                return (string)$uriBuilder->buildUriFromRoute(
                    'web_arp_restaurant_editor',
                    ['id' => $pid, 'menu' => $menuUid, 'priceOptionCreate' => $placementUid]
                );
            },
        );

        $activeMenuUid = $screen->selectedMenu->uid ?? $requestedMenuUid;
        $moduleTemplate->assign('screen', $screen);
        $moduleTemplate->assign(
            'bulk',
            $this->buildBulkPreview($request, $pid, $activeMenuUid, $page, $backendUser, $applyRenderState)
        );
        $moduleTemplate->assign(
            'priceEdit',
            $this->buildPriceOptionEditPanel(
                $request,
                $pid,
                $activeMenuUid,
                $page,
                $backendUser,
                $editUrlBuilder,
                $priceEditReviewState,
            )
        );
        $moduleTemplate->assign(
            'priceVisibility',
            $this->buildPriceOptionVisibilityPanel(
                $request,
                $pid,
                $activeMenuUid,
                $page,
                $backendUser,
                $editUrlBuilder,
                $visibilityReviewState,
            )
        );
        $moduleTemplate->assign(
            'priceCreate',
            $this->buildPriceOptionCreatePanel(
                $request,
                $pid,
                $activeMenuUid,
                $page,
                $backendUser,
                $editUrlBuilder,
                $priceCreateReviewState,
            )
        );

        return $moduleTemplate->renderResponse('RestaurantEditor/Index');
    }

    /**
     * @param array<string, mixed> $page
     * @return RedirectResponse|array{
     *   rawInput: string,
     *   draft: ?BulkDraftValidationResult,
     *   requestError: string,
     *   identity: ?BulkIdentityResolutionResult,
     *   apply: ?BulkApplyPreparationResult,
     *   confirmationWarning: string
     * }|null
     */
    private function processBulkApplyWrite(
        ServerRequestInterface $request,
        int $pid,
        array $page,
        BackendUserAuthentication $backendUser,
        LanguageService $languageService,
    ): RedirectResponse|array|null {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return null;
        }

        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $submittedToken = (string)($body['applyToken'] ?? '');
        $rawInput = (string)($body['bulkSource'] ?? '');
        if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::BULK_APPLY_ACTION)) {
            return [
                'rawInput' => $rawInput,
                'draft' => null,
                'requestError' => 'invalidCsrf',
                'identity' => null,
                'apply' => null,
                'confirmationWarning' => '',
            ];
        }

        $draft = $this->bulkDraftValidator->validatePosted($body['rows'] ?? null);
        if (!$draft->isDraftValid()) {
            return [
                'rawInput' => $rawInput,
                'draft' => $draft,
                'requestError' => '',
                'identity' => null,
                'apply' => null,
                'confirmationWarning' => '',
            ];
        }

        $claimedMenuUid = (int)($body['menu'] ?? 0);
        $identity = $this->resolveIdentities($draft, $pid, $claimedMenuUid, $page, $backendUser);
        if ($identity->outcome !== 'identityResolved') {
            return [
                'rawInput' => $rawInput,
                'draft' => $draft,
                'requestError' => '',
                'identity' => $identity,
                'apply' => null,
                'confirmationWarning' => '',
            ];
        }

        $preparation = $this->applyPlanBuilder->prepare($identity);
        if ($preparation->outcome !== 'applyReady' || $preparation->plan === null) {
            return [
                'rawInput' => $rawInput,
                'draft' => $draft,
                'requestError' => '',
                'identity' => $identity,
                'apply' => $preparation,
                'confirmationWarning' => '',
            ];
        }

        $confirmedFingerprint = strtolower(trim((string)($body['confirmedFingerprint'] ?? '')));
        if (preg_match(self::FINGERPRINT_PATTERN, $confirmedFingerprint) !== 1) {
            return [
                'rawInput' => $rawInput,
                'draft' => $draft,
                'requestError' => '',
                'identity' => $identity,
                'apply' => $preparation,
                'confirmationWarning' => '',
            ];
        }

        if (!hash_equals($preparation->plan->fingerprint, $confirmedFingerprint)) {
            return [
                'rawInput' => $rawInput,
                'draft' => $draft,
                'requestError' => '',
                'identity' => $identity,
                'apply' => $preparation,
                'confirmationWarning' => 'confirmationStale',
            ];
        }

        try {
            $sortContext = $this->applySortPositionReader->buildContext(
                $preparation->plan,
                $pid,
                $backendUser,
            );
        } catch (\Throwable) {
            return [
                'rawInput' => $rawInput,
                'draft' => $draft,
                'requestError' => '',
                'identity' => $identity,
                'apply' => $preparation,
                'confirmationWarning' => 'writePreparationBlocked',
            ];
        }

        $execution = $this->applyWriter->execute(
            $preparation->plan,
            $sortContext,
            $pid,
            $backendUser,
        );

        if (!$execution->dataHandlerAttempted) {
            return [
                'rawInput' => $rawInput,
                'draft' => $draft,
                'requestError' => '',
                'identity' => $identity,
                'apply' => $preparation,
                'confirmationWarning' => 'writePreparationBlocked',
            ];
        }

        $this->enqueueApplyFlash($execution, $languageService);

        $redirectUri = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            ['id' => $pid, 'menu' => $preparation->plan->targetMenu->uid]
        );

        return new RedirectResponse($redirectUri, 303);
    }

    /**
     * Confirmed existing PriceOption update. PRG only after DataHandler was attempted.
     *
     * @param array<string, mixed> $page
     * @return RedirectResponse|array{
     *   priceOptionUid: int,
     *   menuUid: int,
     *   submittedLabel: string,
     *   submittedPrice: string,
     *   requestError: string,
     *   blockers: list<PriceOptionEditBlocker>,
     *   review: ?PriceOptionUpdatePreparationResult,
     *   context: ?\Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditContext,
     *   confirmationWarning: string
     * }
     */
    private function processPriceOptionEditApply(
        ServerRequestInterface $request,
        int $pid,
        array $page,
        BackendUserAuthentication $backendUser,
        LanguageService $languageService,
    ): RedirectResponse|array {
        $empty = static function (
            int $priceOptionUid,
            int $menuUid,
            string $submittedLabel,
            string $submittedPrice,
            string $requestError = '',
            array $blockers = [],
            ?PriceOptionUpdatePreparationResult $review = null,
            $context = null,
            string $confirmationWarning = '',
        ): array {
            return [
                'priceOptionUid' => $priceOptionUid,
                'menuUid' => $menuUid,
                'submittedLabel' => $submittedLabel,
                'submittedPrice' => $submittedPrice,
                'requestError' => $requestError,
                'blockers' => $blockers,
                'review' => $review,
                'context' => $context,
                'confirmationWarning' => $confirmationWarning,
            ];
        };

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $empty(0, 0, '', '', 'invalidCsrf');
        }

        $priceOptionUid = (int)($body['priceOptionUid'] ?? 0);
        $menuUid = (int)($body['menu'] ?? 0);
        $submittedLabel = (string)($body['label'] ?? '');
        $submittedPrice = (string)($body['price'] ?? '');

        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $submittedToken = (string)($body['priceEditApplyToken'] ?? '');
        if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::PRICE_EDIT_APPLY_ACTION)) {
            return $empty($priceOptionUid, $menuUid, $submittedLabel, $submittedPrice, 'invalidCsrf');
        }

        $permissionBlocker = $this->accessGuard->priceOptionEditPermissionBlocker($page, $backendUser);
        if ($permissionBlocker !== '') {
            $code = $permissionBlocker === 'fieldModifyDenied' ? 'fieldModifyDenied' : 'inaccessiblePriceOption';

            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedLabel,
                $submittedPrice,
                '',
                [new PriceOptionEditBlocker($code)],
            );
        }

        $editUrlBuilder = new BackendRecordEditUrlBuilder($this->uriBuilder, $request);
        $load = $this->priceOptionEditReader->load(
            $pid,
            $menuUid,
            $priceOptionUid,
            $backendUser,
            $editUrlBuilder,
        );
        if ($load->outcome !== 'loaded' || $load->context === null) {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedLabel,
                $submittedPrice,
                '',
                $load->blockers !== []
                    ? $load->blockers
                    : [new PriceOptionEditBlocker('inaccessiblePriceOption')],
            );
        }

        $review = $this->priceOptionUpdatePlanBuilder->prepare(
            $load->context,
            $submittedLabel,
            $submittedPrice,
        );

        if ($review->outcome === 'preparationBlocked') {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedLabel,
                $submittedPrice,
                '',
                $review->blockers,
                $review,
                $load->context,
            );
        }

        if ($review->outcome === 'noChanges') {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedLabel,
                $submittedPrice,
                '',
                [],
                $review,
                $load->context,
                'alreadyMatches',
            );
        }

        if ($review->outcome !== 'updateReady' || $review->plan === null) {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedLabel,
                $submittedPrice,
                '',
                [new PriceOptionEditBlocker('inaccessiblePriceOption')],
                $review,
                $load->context,
            );
        }

        $confirmedFingerprint = strtolower(trim((string)($body['confirmedFingerprint'] ?? '')));
        if (preg_match(self::FINGERPRINT_PATTERN, $confirmedFingerprint) !== 1) {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedLabel,
                $submittedPrice,
                '',
                [],
                $review,
                $load->context,
                'writePreparationBlocked',
            );
        }

        if (!hash_equals($review->plan->fingerprint, $confirmedFingerprint)) {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedLabel,
                $submittedPrice,
                '',
                [],
                $review,
                $load->context,
                'confirmationStale',
            );
        }

        $execution = $this->priceOptionUpdateWriter->execute(
            $review->plan,
            $menuUid,
            $backendUser,
        );

        if (!$execution->dataHandlerAttempted) {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedLabel,
                $submittedPrice,
                '',
                [],
                $review,
                $load->context,
                'writePreparationBlocked',
            );
        }

        $this->enqueuePriceUpdateFlash($execution, $languageService);

        $redirectUri = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            [
                'id' => $pid,
                'menu' => $menuUid,
                'priceOption' => $priceOptionUid,
            ]
        );

        return new RedirectResponse($redirectUri, 303);
    }

    /**
     * Review-only existing PriceOption update preparation. No DataHandler / write / PRG.
     *
     * @param array<string, mixed> $page
     * @return array{
     *   priceOptionUid: int,
     *   menuUid: int,
     *   submittedLabel: string,
     *   submittedPrice: string,
     *   requestError: string,
     *   blockers: list<PriceOptionEditBlocker>,
     *   review: ?PriceOptionUpdatePreparationResult,
     *   context: ?\Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditContext,
     *   confirmationWarning: string
     * }
     */
    private function processPriceOptionEditReview(
        ServerRequestInterface $request,
        int $pid,
        array $page,
        BackendUserAuthentication $backendUser,
    ): array {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return [
                'priceOptionUid' => 0,
                'menuUid' => 0,
                'submittedLabel' => '',
                'submittedPrice' => '',
                'requestError' => 'invalidCsrf',
                'blockers' => [],
                'review' => null,
                'context' => null,
                'confirmationWarning' => '',
            ];
        }

        $priceOptionUid = (int)($body['priceOptionUid'] ?? 0);
        $menuUid = (int)($body['menu'] ?? 0);
        $submittedLabel = (string)($body['label'] ?? '');
        $submittedPrice = (string)($body['price'] ?? '');

        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $submittedToken = (string)($body['priceEditToken'] ?? '');
        if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::PRICE_EDIT_REVIEW_ACTION)) {
            return [
                'priceOptionUid' => $priceOptionUid,
                'menuUid' => $menuUid,
                'submittedLabel' => $submittedLabel,
                'submittedPrice' => $submittedPrice,
                'requestError' => 'invalidCsrf',
                'blockers' => [],
                'review' => null,
                'context' => null,
                'confirmationWarning' => '',
            ];
        }

        $permissionBlocker = $this->accessGuard->priceOptionEditPermissionBlocker($page, $backendUser);
        if ($permissionBlocker !== '') {
            $code = $permissionBlocker === 'fieldModifyDenied' ? 'fieldModifyDenied' : 'inaccessiblePriceOption';

            return [
                'priceOptionUid' => $priceOptionUid,
                'menuUid' => $menuUid,
                'submittedLabel' => $submittedLabel,
                'submittedPrice' => $submittedPrice,
                'requestError' => '',
                'blockers' => [new PriceOptionEditBlocker($code)],
                'review' => null,
                'context' => null,
                'confirmationWarning' => '',
            ];
        }

        $editUrlBuilder = new BackendRecordEditUrlBuilder($this->uriBuilder, $request);
        $load = $this->priceOptionEditReader->load(
            $pid,
            $menuUid,
            $priceOptionUid,
            $backendUser,
            $editUrlBuilder,
        );
        if ($load->outcome !== 'loaded' || $load->context === null) {
            return [
                'priceOptionUid' => $priceOptionUid,
                'menuUid' => $menuUid,
                'submittedLabel' => $submittedLabel,
                'submittedPrice' => $submittedPrice,
                'requestError' => '',
                'blockers' => $load->blockers !== []
                    ? $load->blockers
                    : [new PriceOptionEditBlocker('inaccessiblePriceOption')],
                'review' => null,
                'context' => null,
                'confirmationWarning' => '',
            ];
        }

        $review = $this->priceOptionUpdatePlanBuilder->prepare(
            $load->context,
            $submittedLabel,
            $submittedPrice,
        );

        return [
            'priceOptionUid' => $priceOptionUid,
            'menuUid' => $menuUid,
            'submittedLabel' => $submittedLabel,
            'submittedPrice' => $submittedPrice,
            'requestError' => '',
            'blockers' => $review->outcome === 'preparationBlocked' ? $review->blockers : [],
            'review' => $review,
            'context' => $load->context,
            'confirmationWarning' => '',
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @param array{
     *   priceOptionUid: int,
     *   menuUid: int,
     *   submittedLabel: string,
     *   submittedPrice: string,
     *   requestError: string,
     *   blockers: list<PriceOptionEditBlocker>,
     *   review: ?PriceOptionUpdatePreparationResult,
     *   context: ?\Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditContext,
     *   confirmationWarning: string
     * }|null $reviewState
     */
    private function buildPriceOptionEditPanel(
        ServerRequestInterface $request,
        int $pid,
        int $menuUid,
        array $page,
        BackendUserAuthentication $backendUser,
        BackendRecordEditUrlBuilder $editUrlBuilder,
        ?array $reviewState = null,
    ): PriceOptionEditPanelView {
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $priceEditToken = $formProtection->generateToken(self::BULK_FORM, self::PRICE_EDIT_REVIEW_ACTION);
        $priceEditApplyToken = $formProtection->generateToken(self::BULK_FORM, self::PRICE_EDIT_APPLY_ACTION);
        $formAction = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            ['id' => $pid, 'menu' => $menuUid]
        );
        $cancelUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            ['id' => $pid, 'menu' => $menuUid]
        );

        if ($reviewState !== null) {
            return new PriceOptionEditPanelView(
                formAction: $formAction,
                priceEditToken: $priceEditToken,
                priceEditApplyToken: $priceEditApplyToken,
                pid: $pid,
                menuUid: $menuUid > 0 ? $menuUid : $reviewState['menuUid'],
                priceOptionUid: $reviewState['priceOptionUid'],
                context: $reviewState['context'],
                review: $reviewState['review'],
                submittedLabel: $reviewState['submittedLabel'],
                submittedPrice: $reviewState['submittedPrice'],
                requestError: $reviewState['requestError'],
                blockers: $reviewState['blockers'],
                cancelUrl: $cancelUrl,
                confirmationWarning: $reviewState['confirmationWarning'] ?? '',
            );
        }

        $priceOptionUid = (int)($request->getQueryParams()['priceOption'] ?? 0);
        if ($priceOptionUid <= 0 || $menuUid <= 0) {
            return new PriceOptionEditPanelView(
                formAction: $formAction,
                priceEditToken: $priceEditToken,
                priceEditApplyToken: $priceEditApplyToken,
                pid: $pid,
                menuUid: $menuUid,
                priceOptionUid: 0,
                context: null,
                review: null,
                submittedLabel: '',
                submittedPrice: '',
                requestError: '',
                blockers: [],
                cancelUrl: $cancelUrl,
            );
        }

        $permissionBlocker = $this->accessGuard->priceOptionEditPermissionBlocker($page, $backendUser);
        if ($permissionBlocker !== '') {
            $code = $permissionBlocker === 'fieldModifyDenied' ? 'fieldModifyDenied' : 'inaccessiblePriceOption';

            return new PriceOptionEditPanelView(
                formAction: $formAction,
                priceEditToken: $priceEditToken,
                priceEditApplyToken: $priceEditApplyToken,
                pid: $pid,
                menuUid: $menuUid,
                priceOptionUid: $priceOptionUid,
                context: null,
                review: null,
                submittedLabel: '',
                submittedPrice: '',
                requestError: '',
                blockers: [new PriceOptionEditBlocker($code)],
                cancelUrl: $cancelUrl,
            );
        }

        $load = $this->priceOptionEditReader->load(
            $pid,
            $menuUid,
            $priceOptionUid,
            $backendUser,
            $editUrlBuilder,
        );

        $context = $load->outcome === 'loaded' ? $load->context : null;
        $submittedLabel = $context?->label ?? '';
        $submittedPrice = $context !== null ? $context->formattedAmount : '';

        return new PriceOptionEditPanelView(
            formAction: $formAction,
            priceEditToken: $priceEditToken,
            priceEditApplyToken: $priceEditApplyToken,
            pid: $pid,
            menuUid: $menuUid,
            priceOptionUid: $priceOptionUid,
            context: $context,
            review: null,
            submittedLabel: $submittedLabel,
            submittedPrice: $submittedPrice,
            requestError: '',
            blockers: $load->outcome === 'blocked' ? $load->blockers : [],
            cancelUrl: $cancelUrl,
        );
    }

    /**
     * Confirmed PriceOption.hidden update. PRG only after DataHandler was attempted.
     *
     * @param array<string, mixed> $page
     * @return RedirectResponse|array{
     *   priceOptionUid: int,
     *   menuUid: int,
     *   submittedVisibility: string,
     *   requestError: string,
     *   blockers: list<PriceOptionVisibilityBlocker>,
     *   review: ?PriceOptionVisibilityPreparationResult,
     *   context: ?\Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityContext,
     *   confirmationWarning: string
     * }
     */
    private function processPriceOptionVisibilityApply(
        ServerRequestInterface $request,
        int $pid,
        array $page,
        BackendUserAuthentication $backendUser,
        LanguageService $languageService,
    ): RedirectResponse|array {
        $empty = static function (
            int $priceOptionUid,
            int $menuUid,
            string $submittedVisibility,
            string $requestError = '',
            array $blockers = [],
            ?PriceOptionVisibilityPreparationResult $review = null,
            $context = null,
            string $confirmationWarning = '',
        ): array {
            return [
                'priceOptionUid' => $priceOptionUid,
                'menuUid' => $menuUid,
                'submittedVisibility' => $submittedVisibility,
                'requestError' => $requestError,
                'blockers' => $blockers,
                'review' => $review,
                'context' => $context,
                'confirmationWarning' => $confirmationWarning,
            ];
        };

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $empty(0, 0, '', 'invalidCsrf');
        }

        $priceOptionUid = (int)($body['priceOptionUid'] ?? 0);
        $menuUid = (int)($body['menu'] ?? 0);
        $submittedVisibility = (string)($body['visibility'] ?? '');

        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $submittedToken = (string)($body['priceVisibilityApplyToken'] ?? '');
        if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::PRICE_VISIBILITY_APPLY_ACTION)) {
            return $empty($priceOptionUid, $menuUid, $submittedVisibility, 'invalidCsrf');
        }

        $permissionBlocker = $this->accessGuard->priceOptionVisibilityPermissionBlocker($page, $backendUser);
        if ($permissionBlocker !== '') {
            $code = $permissionBlocker === 'fieldModifyDenied' ? 'fieldModifyDenied' : 'inaccessiblePriceOption';

            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedVisibility,
                '',
                [new PriceOptionVisibilityBlocker($code)],
            );
        }

        $editUrlBuilder = new BackendRecordEditUrlBuilder($this->uriBuilder, $request);
        $load = $this->priceOptionVisibilityReader->load(
            $pid,
            $menuUid,
            $priceOptionUid,
            $backendUser,
            $editUrlBuilder,
        );
        if ($load->outcome !== 'loaded' || $load->context === null) {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedVisibility,
                '',
                $load->blockers !== []
                    ? $load->blockers
                    : [new PriceOptionVisibilityBlocker('inaccessiblePriceOption')],
            );
        }

        $review = $this->priceOptionVisibilityPlanBuilder->prepare(
            $load->context,
            $submittedVisibility,
        );

        if ($review->outcome === 'preparationBlocked') {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedVisibility,
                '',
                $review->blockers,
                $review,
                $load->context,
            );
        }

        if ($review->outcome === 'noChanges') {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedVisibility,
                '',
                [],
                $review,
                $load->context,
                'alreadyMatches',
            );
        }

        if ($review->outcome !== 'visibilityUpdateReady' || $review->plan === null) {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedVisibility,
                '',
                [new PriceOptionVisibilityBlocker('inaccessiblePriceOption')],
                $review,
                $load->context,
            );
        }

        $confirmedFingerprint = strtolower(trim((string)($body['confirmedFingerprint'] ?? '')));
        if (preg_match(self::FINGERPRINT_PATTERN, $confirmedFingerprint) !== 1) {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedVisibility,
                '',
                [],
                $review,
                $load->context,
                'writePreparationBlocked',
            );
        }

        if (!hash_equals($review->plan->fingerprint, $confirmedFingerprint)) {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedVisibility,
                '',
                [],
                $review,
                $load->context,
                'confirmationStale',
            );
        }

        $execution = $this->priceOptionVisibilityWriter->execute(
            $review->plan,
            $menuUid,
            $backendUser,
        );

        if (!$execution->dataHandlerAttempted) {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedVisibility,
                '',
                [],
                $review,
                $load->context,
                'writePreparationBlocked',
            );
        }

        $this->enqueueVisibilityFlash($execution, $languageService);

        $redirectUri = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            [
                'id' => $pid,
                'menu' => $menuUid,
                'priceOptionVisibility' => $priceOptionUid,
            ]
        );

        return new RedirectResponse($redirectUri, 303);
    }

    /**
     * Review-only PriceOption.hidden preparation. No DataHandler / write / PRG.
     *
     * @param array<string, mixed> $page
     * @return array{
     *   priceOptionUid: int,
     *   menuUid: int,
     *   submittedVisibility: string,
     *   requestError: string,
     *   blockers: list<PriceOptionVisibilityBlocker>,
     *   review: ?PriceOptionVisibilityPreparationResult,
     *   context: ?\Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityContext
     * }
     */
    private function processPriceOptionVisibilityReview(
        ServerRequestInterface $request,
        int $pid,
        array $page,
        BackendUserAuthentication $backendUser,
    ): array {
        $empty = static function (
            int $priceOptionUid,
            int $menuUid,
            string $submittedVisibility,
            string $requestError = '',
            array $blockers = [],
            ?PriceOptionVisibilityPreparationResult $review = null,
            $context = null,
        ): array {
            return [
                'priceOptionUid' => $priceOptionUid,
                'menuUid' => $menuUid,
                'submittedVisibility' => $submittedVisibility,
                'requestError' => $requestError,
                'blockers' => $blockers,
                'review' => $review,
                'context' => $context,
            ];
        };

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $empty(0, 0, '', 'invalidCsrf');
        }

        $priceOptionUid = (int)($body['priceOptionUid'] ?? 0);
        $menuUid = (int)($body['menu'] ?? 0);
        $submittedVisibility = (string)($body['visibility'] ?? '');

        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $submittedToken = (string)($body['priceVisibilityToken'] ?? '');
        if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::PRICE_VISIBILITY_REVIEW_ACTION)) {
            return $empty($priceOptionUid, $menuUid, $submittedVisibility, 'invalidCsrf');
        }

        $permissionBlocker = $this->accessGuard->priceOptionVisibilityPermissionBlocker($page, $backendUser);
        if ($permissionBlocker !== '') {
            $code = $permissionBlocker === 'fieldModifyDenied' ? 'fieldModifyDenied' : 'inaccessiblePriceOption';

            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedVisibility,
                '',
                [new PriceOptionVisibilityBlocker($code)],
            );
        }

        $editUrlBuilder = new BackendRecordEditUrlBuilder($this->uriBuilder, $request);
        $load = $this->priceOptionVisibilityReader->load(
            $pid,
            $menuUid,
            $priceOptionUid,
            $backendUser,
            $editUrlBuilder,
        );
        if ($load->outcome !== 'loaded' || $load->context === null) {
            return $empty(
                $priceOptionUid,
                $menuUid,
                $submittedVisibility,
                '',
                $load->blockers !== []
                    ? $load->blockers
                    : [new PriceOptionVisibilityBlocker('inaccessiblePriceOption')],
            );
        }

        $review = $this->priceOptionVisibilityPlanBuilder->prepare(
            $load->context,
            $submittedVisibility,
        );

        return [
            'priceOptionUid' => $priceOptionUid,
            'menuUid' => $menuUid,
            'submittedVisibility' => $submittedVisibility,
            'requestError' => '',
            'blockers' => $review->outcome === 'preparationBlocked' ? $review->blockers : [],
            'review' => $review,
            'context' => $load->context,
            'confirmationWarning' => '',
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @param array{
     *   priceOptionUid: int,
     *   menuUid: int,
     *   submittedVisibility: string,
     *   requestError: string,
     *   blockers: list<PriceOptionVisibilityBlocker>,
     *   review: ?PriceOptionVisibilityPreparationResult,
     *   context: ?\Anatolkin\ArpRestaurant\Backend\Editor\Visibility\PriceOptionVisibilityContext
     * }|null $reviewState
     */
    private function buildPriceOptionVisibilityPanel(
        ServerRequestInterface $request,
        int $pid,
        int $menuUid,
        array $page,
        BackendUserAuthentication $backendUser,
        BackendRecordEditUrlBuilder $editUrlBuilder,
        ?array $reviewState = null,
    ): PriceOptionVisibilityPanelView {
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $priceVisibilityToken = $formProtection->generateToken(self::BULK_FORM, self::PRICE_VISIBILITY_REVIEW_ACTION);
        $priceVisibilityApplyToken = $formProtection->generateToken(self::BULK_FORM, self::PRICE_VISIBILITY_APPLY_ACTION);
        $formAction = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            ['id' => $pid, 'menu' => $menuUid]
        );
        $cancelUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            ['id' => $pid, 'menu' => $menuUid]
        );

        if ($reviewState !== null) {
            return new PriceOptionVisibilityPanelView(
                formAction: $formAction,
                priceVisibilityToken: $priceVisibilityToken,
                priceVisibilityApplyToken: $priceVisibilityApplyToken,
                pid: $pid,
                menuUid: $menuUid > 0 ? $menuUid : $reviewState['menuUid'],
                priceOptionUid: $reviewState['priceOptionUid'],
                context: $reviewState['context'],
                review: $reviewState['review'],
                submittedVisibility: $reviewState['submittedVisibility'],
                requestError: $reviewState['requestError'],
                blockers: $reviewState['blockers'],
                cancelUrl: $cancelUrl,
                confirmationWarning: $reviewState['confirmationWarning'] ?? '',
            );
        }

        $priceOptionUid = (int)($request->getQueryParams()['priceOptionVisibility'] ?? 0);
        if ($priceOptionUid <= 0 || $menuUid <= 0) {
            return new PriceOptionVisibilityPanelView(
                formAction: $formAction,
                priceVisibilityToken: $priceVisibilityToken,
                priceVisibilityApplyToken: $priceVisibilityApplyToken,
                pid: $pid,
                menuUid: $menuUid,
                priceOptionUid: 0,
                context: null,
                review: null,
                submittedVisibility: '',
                requestError: '',
                blockers: [],
                cancelUrl: $cancelUrl,
            );
        }

        $permissionBlocker = $this->accessGuard->priceOptionVisibilityPermissionBlocker($page, $backendUser);
        if ($permissionBlocker !== '') {
            $code = $permissionBlocker === 'fieldModifyDenied' ? 'fieldModifyDenied' : 'inaccessiblePriceOption';

            return new PriceOptionVisibilityPanelView(
                formAction: $formAction,
                priceVisibilityToken: $priceVisibilityToken,
                priceVisibilityApplyToken: $priceVisibilityApplyToken,
                pid: $pid,
                menuUid: $menuUid,
                priceOptionUid: $priceOptionUid,
                context: null,
                review: null,
                submittedVisibility: '',
                requestError: '',
                blockers: [new PriceOptionVisibilityBlocker($code)],
                cancelUrl: $cancelUrl,
            );
        }

        $load = $this->priceOptionVisibilityReader->load(
            $pid,
            $menuUid,
            $priceOptionUid,
            $backendUser,
            $editUrlBuilder,
        );

        $context = $load->outcome === 'loaded' ? $load->context : null;
        $submittedVisibility = $context !== null
            ? ($context->hidden ? 'visible' : 'hidden')
            : '';

        return new PriceOptionVisibilityPanelView(
            formAction: $formAction,
            priceVisibilityToken: $priceVisibilityToken,
            priceVisibilityApplyToken: $priceVisibilityApplyToken,
            pid: $pid,
            menuUid: $menuUid,
            priceOptionUid: $priceOptionUid,
            context: $context,
            review: null,
            submittedVisibility: $submittedVisibility,
            requestError: '',
            blockers: $load->outcome === 'blocked' ? $load->blockers : [],
            cancelUrl: $cancelUrl,
        );
    }

    /**
     * Review-only creation of one PriceOption under an existing Placement.
     * No DataHandler / write / PRG.
     *
     * @param array<string, mixed> $page
     * @return array{
     *   placementUid: int,
     *   menuUid: int,
     *   submittedLabel: string,
     *   submittedPrice: string,
     *   requestError: string,
     *   blockers: list<PriceOptionCreateBlocker>,
     *   review: ?PriceOptionCreatePreparationResult,
     *   context: ?\Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreateContext
     * }
     */
    private function processPriceOptionCreateReview(
        ServerRequestInterface $request,
        int $pid,
        array $page,
        BackendUserAuthentication $backendUser,
    ): array {
        $empty = static function (
            int $placementUid,
            int $menuUid,
            string $submittedLabel,
            string $submittedPrice,
            string $requestError = '',
            array $blockers = [],
            ?PriceOptionCreatePreparationResult $review = null,
            $context = null,
        ): array {
            return [
                'placementUid' => $placementUid,
                'menuUid' => $menuUid,
                'submittedLabel' => $submittedLabel,
                'submittedPrice' => $submittedPrice,
                'requestError' => $requestError,
                'blockers' => $blockers,
                'review' => $review,
                'context' => $context,
            ];
        };

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $empty(0, 0, '', '', 'invalidCsrf');
        }

        $placementUid = (int)($body['placementUid'] ?? 0);
        $menuUid = (int)($body['menu'] ?? 0);
        $submittedLabel = (string)($body['label'] ?? '');
        $submittedPrice = (string)($body['price'] ?? '');

        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $submittedToken = (string)($body['priceCreateToken'] ?? '');
        if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::PRICE_CREATE_REVIEW_ACTION)) {
            return $empty($placementUid, $menuUid, $submittedLabel, $submittedPrice, 'invalidCsrf');
        }

        $permissionBlocker = $this->accessGuard->priceOptionCreatePermissionBlocker($page, $backendUser);
        if ($permissionBlocker !== '') {
            return $empty(
                $placementUid,
                $menuUid,
                $submittedLabel,
                $submittedPrice,
                '',
                [new PriceOptionCreateBlocker($permissionBlocker)],
            );
        }

        $editUrlBuilder = new BackendRecordEditUrlBuilder($this->uriBuilder, $request);
        $load = $this->priceOptionCreateReader->load(
            $pid,
            $menuUid,
            $placementUid,
            $backendUser,
            $editUrlBuilder,
        );
        if ($load->outcome !== 'loaded' || $load->context === null) {
            return $empty(
                $placementUid,
                $menuUid,
                $submittedLabel,
                $submittedPrice,
                '',
                $load->blockers !== []
                    ? $load->blockers
                    : [new PriceOptionCreateBlocker('inaccessiblePlacement')],
            );
        }

        $review = $this->priceOptionCreatePlanBuilder->prepare(
            $load->context,
            $submittedLabel,
            $submittedPrice,
        );

        $preservedLabel = $review->outcome === 'createReady' && $review->plan !== null
            ? $review->plan->label
            : $submittedLabel;
        $preservedPrice = $review->outcome === 'createReady' && $review->plan !== null
            ? $review->plan->formattedAmount
            : $submittedPrice;

        return [
            'placementUid' => $placementUid,
            'menuUid' => $menuUid,
            'submittedLabel' => $preservedLabel,
            'submittedPrice' => $preservedPrice,
            'requestError' => '',
            'blockers' => $review->outcome === 'preparationBlocked' ? $review->blockers : [],
            'review' => $review,
            'context' => $load->context,
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @param array{
     *   placementUid: int,
     *   menuUid: int,
     *   submittedLabel: string,
     *   submittedPrice: string,
     *   requestError: string,
     *   blockers: list<PriceOptionCreateBlocker>,
     *   review: ?PriceOptionCreatePreparationResult,
     *   context: ?\Anatolkin\ArpRestaurant\Backend\Editor\PriceCreate\PriceOptionCreateContext
     * }|null $reviewState
     */
    private function buildPriceOptionCreatePanel(
        ServerRequestInterface $request,
        int $pid,
        int $menuUid,
        array $page,
        BackendUserAuthentication $backendUser,
        BackendRecordEditUrlBuilder $editUrlBuilder,
        ?array $reviewState = null,
    ): PriceOptionCreatePanelView {
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $priceCreateToken = $formProtection->generateToken(self::BULK_FORM, self::PRICE_CREATE_REVIEW_ACTION);
        $priceCreateApplyToken = $formProtection->generateToken(self::BULK_FORM, self::PRICE_CREATE_APPLY_ACTION);
        $formAction = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            ['id' => $pid, 'menu' => $menuUid]
        );
        $cancelUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            ['id' => $pid, 'menu' => $menuUid]
        );

        if ($reviewState !== null) {
            return new PriceOptionCreatePanelView(
                formAction: $formAction,
                priceCreateToken: $priceCreateToken,
                priceCreateApplyToken: $priceCreateApplyToken,
                pid: $pid,
                menuUid: $menuUid > 0 ? $menuUid : $reviewState['menuUid'],
                placementUid: $reviewState['placementUid'],
                context: $reviewState['context'],
                review: $reviewState['review'],
                submittedLabel: $reviewState['submittedLabel'],
                submittedPrice: $reviewState['submittedPrice'],
                requestError: $reviewState['requestError'],
                blockers: $reviewState['blockers'],
                confirmationWarning: $reviewState['confirmationWarning'] ?? '',
                cancelUrl: $cancelUrl,
            );
        }

        $placementUid = (int)($request->getQueryParams()['priceOptionCreate'] ?? 0);
        if ($placementUid <= 0 || $menuUid <= 0) {
            return new PriceOptionCreatePanelView(
                formAction: $formAction,
                priceCreateToken: $priceCreateToken,
                priceCreateApplyToken: $priceCreateApplyToken,
                pid: $pid,
                menuUid: $menuUid,
                placementUid: 0,
                context: null,
                review: null,
                submittedLabel: '',
                submittedPrice: '',
                requestError: '',
                blockers: [],
                cancelUrl: $cancelUrl,
            );
        }

        $permissionBlocker = $this->accessGuard->priceOptionCreatePermissionBlocker($page, $backendUser);
        if ($permissionBlocker !== '') {
            return new PriceOptionCreatePanelView(
                formAction: $formAction,
                priceCreateToken: $priceCreateToken,
                priceCreateApplyToken: $priceCreateApplyToken,
                pid: $pid,
                menuUid: $menuUid,
                placementUid: $placementUid,
                context: null,
                review: null,
                submittedLabel: '',
                submittedPrice: '',
                requestError: '',
                blockers: [new PriceOptionCreateBlocker($permissionBlocker)],
                cancelUrl: $cancelUrl,
            );
        }

        $load = $this->priceOptionCreateReader->load(
            $pid,
            $menuUid,
            $placementUid,
            $backendUser,
            $editUrlBuilder,
        );

        $context = $load->outcome === 'loaded' ? $load->context : null;

        return new PriceOptionCreatePanelView(
            formAction: $formAction,
            priceCreateToken: $priceCreateToken,
            priceCreateApplyToken: $priceCreateApplyToken,
            pid: $pid,
            menuUid: $menuUid,
            placementUid: $placementUid,
            context: $context,
            review: null,
            submittedLabel: '',
            submittedPrice: '',
            requestError: '',
            blockers: $load->outcome === 'blocked' ? $load->blockers : [],
            cancelUrl: $cancelUrl,
        );
    }

    /**
     * @param array<string, mixed> $page
     * @param array{
     *   rawInput: string,
     *   draft: ?BulkDraftValidationResult,
     *   requestError: string,
     *   identity: ?BulkIdentityResolutionResult,
     *   apply: ?BulkApplyPreparationResult,
     *   confirmationWarning: string
     * }|null $applyRenderState
     */
    private function buildBulkPreview(
        ServerRequestInterface $request,
        int $pid,
        int $menuUid,
        array $page,
        BackendUserAuthentication $backendUser,
        ?array $applyRenderState = null,
    ): BulkPreviewView {
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $previewToken = $formProtection->generateToken(self::BULK_FORM, self::BULK_PREVIEW_ACTION);
        $revalidateToken = $formProtection->generateToken(self::BULK_FORM, self::BULK_REVALIDATE_ACTION);
        $resetToken = $formProtection->generateToken(self::BULK_FORM, self::BULK_RESET_ACTION);
        $resolveToken = $formProtection->generateToken(self::BULK_FORM, self::BULK_RESOLVE_ACTION);
        $prepareToken = $formProtection->generateToken(self::BULK_FORM, self::BULK_PREPARE_ACTION);
        $applyToken = $formProtection->generateToken(self::BULK_FORM, self::BULK_APPLY_ACTION);
        $formAction = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            ['id' => $pid, 'menu' => $menuUid]
        ) . '#arp-restaurant-bulk-workbench';

        $rawInput = '';
        $parseGlobalError = '';
        $draft = null;
        $requestError = '';
        $identity = null;
        $apply = null;
        $confirmationWarning = '';

        if ($applyRenderState !== null) {
            return new BulkPreviewView(
                formAction: $formAction,
                previewToken: $previewToken,
                revalidateToken: $revalidateToken,
                resetToken: $resetToken,
                resolveToken: $resolveToken,
                prepareToken: $prepareToken,
                applyToken: $applyToken,
                rawInput: $applyRenderState['rawInput'],
                parseGlobalError: '',
                draft: $applyRenderState['draft'],
                requestError: $applyRenderState['requestError'],
                pid: $pid,
                menuUid: $menuUid,
                maxBytes: BulkMenuParser::DEFAULT_MAX_BYTES,
                maxRows: BulkMenuParser::DEFAULT_MAX_ROWS,
                identity: $applyRenderState['identity'],
                apply: $applyRenderState['apply'],
                confirmationWarning: $applyRenderState['confirmationWarning'],
            );
        }

        $body = $request->getParsedBody();
        $isPreviewPost = is_array($body) && isset($body['bulkPreview']);
        $isResetPost = is_array($body) && isset($body['bulkDraftReset']);
        $isRevalidatePost = is_array($body) && isset($body['bulkDraftRevalidate']);
        $isResolvePost = is_array($body) && isset($body['bulkIdentityResolve']);
        $isPreparePost = is_array($body) && isset($body['bulkApplyPrepare']);

        if ($isPreviewPost) {
            $rawInput = (string)($body['bulkPaste'] ?? '');
            $submittedToken = (string)($body['formToken'] ?? '');
            if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::BULK_PREVIEW_ACTION)) {
                $requestError = 'invalidCsrf';
            } else {
                $parsed = $this->bulkMenuParser->parse($rawInput);
                if ($parsed->hasGlobalError()) {
                    $parseGlobalError = $parsed->globalError;
                } else {
                    $draft = $this->bulkDraftValidator->fromParsedRows($parsed->rows);
                }
            }
        } elseif ($isResetPost) {
            $rawInput = (string)($body['bulkSource'] ?? '');
            $submittedToken = (string)($body['resetToken'] ?? '');
            if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::BULK_RESET_ACTION)) {
                $requestError = 'invalidCsrf';
            } else {
                $parsed = $this->bulkMenuParser->parse($rawInput);
                if ($parsed->hasGlobalError()) {
                    $parseGlobalError = $parsed->globalError;
                } else {
                    $draft = $this->bulkDraftValidator->fromParsedRows($parsed->rows);
                }
            }
        } elseif ($isRevalidatePost) {
            $rawInput = (string)($body['bulkSource'] ?? '');
            $submittedToken = (string)($body['formToken'] ?? '');
            if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::BULK_REVALIDATE_ACTION)) {
                $requestError = 'invalidCsrf';
            } else {
                $draft = $this->bulkDraftValidator->validatePosted($body['rows'] ?? null);
            }
        } elseif ($isResolvePost) {
            $rawInput = (string)($body['bulkSource'] ?? '');
            $submittedToken = (string)($body['resolveToken'] ?? '');
            if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::BULK_RESOLVE_ACTION)) {
                $requestError = 'invalidCsrf';
            } else {
                $draft = $this->bulkDraftValidator->validatePosted($body['rows'] ?? null);
                if ($draft->isDraftValid()) {
                    $claimedMenuUid = (int)($body['menu'] ?? 0);
                    $identity = $this->resolveIdentities(
                        $draft,
                        $pid,
                        $claimedMenuUid,
                        $page,
                        $backendUser,
                    );
                }
            }
        } elseif ($isPreparePost) {
            $rawInput = (string)($body['bulkSource'] ?? '');
            $submittedToken = (string)($body['prepareToken'] ?? '');
            if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::BULK_PREPARE_ACTION)) {
                $requestError = 'invalidCsrf';
            } else {
                $draft = $this->bulkDraftValidator->validatePosted($body['rows'] ?? null);
                if ($draft->isDraftValid()) {
                    $claimedMenuUid = (int)($body['menu'] ?? 0);
                    $identity = $this->resolveIdentities(
                        $draft,
                        $pid,
                        $claimedMenuUid,
                        $page,
                        $backendUser,
                    );
                    if ($identity->outcome === 'identityResolved') {
                        $apply = $this->applyPlanBuilder->prepare($identity);
                    }
                }
            }
        }

        return new BulkPreviewView(
            formAction: $formAction,
            previewToken: $previewToken,
            revalidateToken: $revalidateToken,
            resetToken: $resetToken,
            resolveToken: $resolveToken,
            prepareToken: $prepareToken,
            applyToken: $applyToken,
            rawInput: $rawInput,
            parseGlobalError: $parseGlobalError,
            draft: $draft,
            requestError: $requestError,
            pid: $pid,
            menuUid: $menuUid,
            maxBytes: BulkMenuParser::DEFAULT_MAX_BYTES,
            maxRows: BulkMenuParser::DEFAULT_MAX_ROWS,
            identity: $identity,
            apply: $apply,
            confirmationWarning: $confirmationWarning,
        );
    }

    /**
     * @param array<string, mixed> $page
     */
    private function resolveIdentities(
        BulkDraftValidationResult $draft,
        int $pid,
        int $claimedMenuUid,
        array $page,
        BackendUserAuthentication $backendUser,
    ): BulkIdentityResolutionResult {
        $permissionBlocker = $this->accessGuard->futureApplyPermissionBlocker($page, $backendUser);
        if ($permissionBlocker !== '') {
            return $this->identityResolver->resolve(
                $draft,
                null,
                [],
                [],
                $permissionBlocker,
            );
        }

        $menuLookup = $this->identityReader->lookupTargetMenu($pid, $claimedMenuUid, $backendUser);
        if ($menuLookup->snapshot === null) {
            return $this->identityResolver->resolve(
                $draft,
                null,
                [],
                [],
                '',
                $menuLookup->blocker !== '' ? $menuLookup->blocker : 'missingTargetMenu',
            );
        }

        $itemCandidates = $this->identityReader->findItemCandidates($pid, $backendUser);
        $categoryCandidates = $this->identityReader->findCategoryCandidates(
            $pid,
            $menuLookup->snapshot->uid,
            $backendUser,
        );

        return $this->identityResolver->resolve(
            $draft,
            $menuLookup->snapshot,
            $itemCandidates,
            $categoryCandidates,
        );
    }

    /** Fresh confirmation gate; only the dedicated writer may persist. */
    private function processPriceOptionCreateApply(
        ServerRequestInterface $request,
        int $pid,
        array $page,
        BackendUserAuthentication $backendUser,
        LanguageService $languageService,
    ): RedirectResponse|array {
        $state = [
            'placementUid' => 0, 'menuUid' => 0, 'submittedLabel' => '', 'submittedPrice' => '',
            'requestError' => '', 'blockers' => [], 'review' => null, 'context' => null, 'confirmationWarning' => '',
        ];
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $state['requestError'] = 'invalidCsrf';
            return $state;
        }
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $token = is_string($body['priceCreateApplyToken'] ?? null) ? $body['priceCreateApplyToken'] : '';
        if (!$formProtection->validateToken($token, self::BULK_FORM, self::PRICE_CREATE_APPLY_ACTION)) {
            $state['requestError'] = 'invalidCsrf';
            return $state;
        }
        $placementUid = MathUtility::canBeInterpretedAsInteger($body['placementUid'] ?? null) ? (int)$body['placementUid'] : 0;
        $menuUid = MathUtility::canBeInterpretedAsInteger($body['menu'] ?? null) ? (int)$body['menu'] : 0;
        $label = is_string($body['label'] ?? null) ? $body['label'] : '';
        $price = is_string($body['price'] ?? null) ? $body['price'] : '';
        $state = array_replace($state, [
            'placementUid' => $placementUid, 'menuUid' => $menuUid, 'submittedLabel' => $label, 'submittedPrice' => $price,
        ]);
        $permissionBlocker = $this->accessGuard->priceOptionCreatePermissionBlocker($page, $backendUser);
        if ($permissionBlocker !== '') {
            $state['blockers'] = [new PriceOptionCreateBlocker($permissionBlocker)];
            return $state;
        }
        $load = $this->priceOptionCreateReader->load(
            $pid, $menuUid, $placementUid, $backendUser,
            new BackendRecordEditUrlBuilder($this->uriBuilder, $request),
        );
        if ($load->outcome !== 'loaded' || $load->context === null) {
            $state['blockers'] = $load->blockers !== [] ? $load->blockers : [new PriceOptionCreateBlocker('inaccessiblePlacement')];
            return $state;
        }
        $review = $this->priceOptionCreatePlanBuilder->prepare($load->context, $label, $price);
        $state['context'] = $load->context;
        $state['review'] = $review;
        if ($review->outcome !== 'createReady' || $review->plan === null) {
            $state['blockers'] = $review->blockers !== [] ? $review->blockers : [new PriceOptionCreateBlocker('inaccessiblePlacement')];
            return $state;
        }
        $confirmedFingerprint = is_string($body['confirmedFingerprint'] ?? null) ? $body['confirmedFingerprint'] : '';
        if (strlen($confirmedFingerprint) !== 64 || preg_match(self::FINGERPRINT_PATTERN, $confirmedFingerprint) !== 1) {
            $state['confirmationWarning'] = 'writePreparationBlocked';
            return $state;
        }
        if (!hash_equals($review->plan->fingerprint, $confirmedFingerprint)) {
            $state['confirmationWarning'] = 'confirmationStale';
            return $state;
        }
        $execution = $this->priceOptionCreateWriter->execute($review->plan, $backendUser);
        if (!$execution->dataHandlerAttempted) {
            $state['confirmationWarning'] = 'writePreparationBlocked';
            return $state;
        }
        $this->enqueuePriceCreateFlash($execution, $languageService);
        $redirectUri = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            ['id' => $pid, 'menu' => $menuUid, 'priceOptionCreate' => $placementUid],
        );

        return new RedirectResponse($redirectUri, 303);
    }

    private function enqueuePriceCreateFlash(PriceOptionCreateExecutionResult $execution, LanguageService $languageService): void
    {
        [$key, $severity] = match ($execution->outcome) {
            'created' => ['created', ContextualFeedbackSeverity::OK],
            'partialFailure' => ['partial', ContextualFeedbackSeverity::WARNING],
            default => ['failed', ContextualFeedbackSeverity::ERROR],
        };
        $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
            $languageService->sL(self::LLL . 'priceCreate.flash.' . $key),
            $languageService->sL(self::LLL . 'priceCreate.flash.' . $key . 'Title'),
            $severity,
            true,
        ));
    }

    private function enqueuePriceUpdateFlash(
        PriceOptionUpdateExecutionResult $execution,
        LanguageService $languageService,
    ): void {
        $queue = $this->flashMessageService->getMessageQueueByIdentifier();
        if ($execution->outcome === 'updated') {
            $queue->enqueue(new FlashMessage(
                $languageService->sL(self::LLL . 'priceEdit.flash.updated'),
                $languageService->sL(self::LLL . 'priceEdit.flash.updatedTitle'),
                ContextualFeedbackSeverity::OK,
                true,
            ));
            return;
        }

        if ($execution->outcome === 'partialFailure') {
            $queue->enqueue(new FlashMessage(
                $languageService->sL(self::LLL . 'priceEdit.flash.partial'),
                $languageService->sL(self::LLL . 'priceEdit.flash.partialTitle'),
                ContextualFeedbackSeverity::WARNING,
                true,
            ));
            return;
        }

        $queue->enqueue(new FlashMessage(
            $languageService->sL(self::LLL . 'priceEdit.flash.failed'),
            $languageService->sL(self::LLL . 'priceEdit.flash.failedTitle'),
            ContextualFeedbackSeverity::ERROR,
            true,
        ));
    }

    private function enqueueVisibilityFlash(
        PriceOptionVisibilityExecutionResult $execution,
        LanguageService $languageService,
    ): void {
        $queue = $this->flashMessageService->getMessageQueueByIdentifier();
        if ($execution->outcome === 'updated') {
            $queue->enqueue(new FlashMessage(
                $languageService->sL(self::LLL . 'priceVisibility.flash.updated'),
                $languageService->sL(self::LLL . 'priceVisibility.flash.updatedTitle'),
                ContextualFeedbackSeverity::OK,
                true,
            ));
            return;
        }

        if ($execution->outcome === 'partialFailure') {
            $queue->enqueue(new FlashMessage(
                $languageService->sL(self::LLL . 'priceVisibility.flash.partial'),
                $languageService->sL(self::LLL . 'priceVisibility.flash.partialTitle'),
                ContextualFeedbackSeverity::WARNING,
                true,
            ));
            return;
        }

        $queue->enqueue(new FlashMessage(
            $languageService->sL(self::LLL . 'priceVisibility.flash.failed'),
            $languageService->sL(self::LLL . 'priceVisibility.flash.failedTitle'),
            ContextualFeedbackSeverity::ERROR,
            true,
        ));
    }

    private function enqueueApplyFlash(
        ApplyExecutionResult $execution,
        LanguageService $languageService,
    ): void {
        $queue = $this->flashMessageService->getMessageQueueByIdentifier();
        if ($execution->outcome === 'applied') {
            $body = sprintf(
                $languageService->sL(self::LLL . 'bulk.apply.flash.applied'),
                $execution->createdCategories,
                $execution->createdItems,
                $execution->createdPlacements,
                $execution->createdPriceOptions,
            );
            $queue->enqueue(new FlashMessage(
                $body,
                $languageService->sL(self::LLL . 'bulk.apply.flash.appliedTitle'),
                ContextualFeedbackSeverity::OK,
                true,
            ));
            return;
        }

        if ($execution->outcome === 'partialFailure') {
            $detail = $execution->diagnostics !== []
                ? ' ' . implode(' ', array_slice($execution->diagnostics, 0, 3))
                : '';
            $queue->enqueue(new FlashMessage(
                $languageService->sL(self::LLL . 'bulk.apply.flash.partial') . $detail,
                $languageService->sL(self::LLL . 'bulk.apply.flash.partialTitle'),
                ContextualFeedbackSeverity::WARNING,
                true,
            ));
            return;
        }

        $queue->enqueue(new FlashMessage(
            $languageService->sL(self::LLL . 'bulk.apply.flash.failed'),
            $languageService->sL(self::LLL . 'bulk.apply.flash.failedTitle'),
            ContextualFeedbackSeverity::ERROR,
            true,
        ));
    }

    private function resolvePid(ServerRequestInterface $request): int
    {
        $raw = $request->getQueryParams()['id']
            ?? (is_array($request->getParsedBody()) ? ($request->getParsedBody()['id'] ?? 0) : 0);
        if (!MathUtility::canBeInterpretedAsInteger($raw)) {
            return 0;
        }

        return (int)$raw;
    }

    private function emptyScreen(int $pid, string $pageTitle, string $emptyState): EditorScreen
    {
        return new EditorScreen(
            pid: $pid,
            pageTitle: $pageTitle,
            canRead: false,
            emptyState: $emptyState,
            menus: [],
            selectedMenu: null,
        );
    }

    private function addListButton(
        ModuleTemplate $moduleTemplate,
        int $pid,
        LanguageService $languageService,
    ): void {
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        // LinkButton on TYPO3 13/14 uses setTitle() for both the HTML title attribute
        // and the visible label when setShowLabelText(true). There is no shared 13/14
        // API to override only the title attribute while keeping a short visible label.
        $label = $languageService->sL(self::LLL . 'button.openList');
        $helpTitle = $languageService->sL(self::LLL . 'button.openList.title');
        $button = $this->linkButtonFactory->createLinkButton($buttonBar)
            ->setHref('#')
            ->setDataAttributes([
                'dispatch-action' => 'TYPO3.ModuleMenu.showModule',
                'dispatch-args-list' => 'web_list,&' . http_build_query(['id' => $pid]),
            ])
            ->setTitle($label)
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-list', IconSize::SMALL));
        $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_LEFT, 1);

        // Native icon-only help control: title attribute carries the longer explanation
        // (hover + accessible name). No Bootstrap tooltip JS / custom JS.
        $helpButton = $this->linkButtonFactory->createLinkButton($buttonBar)
            ->setHref('#')
            ->setTitle($helpTitle)
            ->setShowLabelText(false)
            ->setIcon($this->iconFactory->getIcon('actions-info', IconSize::SMALL));
        $buttonBar->addButton($helpButton, ButtonBar::BUTTON_POSITION_LEFT, 2);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
