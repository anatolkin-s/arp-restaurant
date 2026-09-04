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
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditBlocker;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditPanelView;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdatePlanBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionUpdatePreparationResult;
use Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\RestaurantPriceOptionEditReader;
use Anatolkin\ArpRestaurant\Backend\Editor\ViewModel\EditorScreen;
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
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->getLanguageService();
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($languageService->sL(self::LLL . 'mlang_tabs_tab'));

        $moduleTemplate->assign('lll', self::LLL);
        $moduleTemplate->assign('bulk', null);
        $moduleTemplate->assign('priceEdit', null);

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
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['bulkApply'])) {
            $applyHandled = $this->processBulkApplyWrite($request, $pid, $page, $backendUser, $languageService);
            if ($applyHandled instanceof RedirectResponse) {
                return $applyHandled;
            }
            $applyRenderState = $applyHandled;
        } elseif (is_array($body) && isset($body['priceOptionEditReview'])) {
            $priceEditReviewState = $this->processPriceOptionEditReview($request, $pid, $page, $backendUser);
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
     *   context: ?\Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditContext
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
     *   context: ?\Anatolkin\ArpRestaurant\Backend\Editor\PriceEdit\PriceOptionEditContext
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
            );
        }

        $priceOptionUid = (int)($request->getQueryParams()['priceOption'] ?? 0);
        if ($priceOptionUid <= 0 || $menuUid <= 0) {
            return new PriceOptionEditPanelView(
                formAction: $formAction,
                priceEditToken: $priceEditToken,
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
        $button = $this->linkButtonFactory->createLinkButton($buttonBar)
            ->setHref((string)$this->uriBuilder->buildUriFromRoute('web_list', ['id' => $pid]))
            ->setTitle($languageService->sL(self::LLL . 'button.openList'))
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-list', IconSize::SMALL));
        $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_LEFT, 1);
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
