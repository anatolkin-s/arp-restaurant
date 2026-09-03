<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Controller;

use Anatolkin\ArpRestaurant\Backend\Editor\BackendAccessGuard;
use Anatolkin\ArpRestaurant\Backend\Editor\BackendRecordEditUrlBuilder;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkMenuParser;
use Anatolkin\ArpRestaurant\Backend\Editor\Bulk\BulkPreviewView;
use Anatolkin\ArpRestaurant\Backend\Editor\MenuGraphReader;
use Anatolkin\ArpRestaurant\Backend\Editor\ModuleLinkButtonFactory;
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
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\MathUtility;

#[AsController]
final class RestaurantEditorController
{
    private const LLL = 'LLL:EXT:arp_restaurant/Resources/Private/Language/locallang_mod_editor.xlf:';
    private const BULK_FORM = 'web_arp_restaurant_editor';
    private const BULK_ACTION = 'bulkPreview';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly IconFactory $iconFactory,
        private readonly BackendAccessGuard $accessGuard,
        private readonly MenuGraphReader $menuGraphReader,
        private readonly ModuleLinkButtonFactory $linkButtonFactory,
        private readonly FormProtectionFactory $formProtectionFactory,
        private readonly BulkMenuParser $bulkMenuParser,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->getLanguageService();
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($languageService->sL(self::LLL . 'mlang_tabs_tab'));

        $moduleTemplate->assign('lll', self::LLL);
        $moduleTemplate->assign('bulk', null);

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
        );

        $activeMenuUid = $screen->selectedMenu->uid ?? $requestedMenuUid;
        $moduleTemplate->assign('screen', $screen);
        $moduleTemplate->assign('bulk', $this->buildBulkPreview($request, $pid, $activeMenuUid));

        return $moduleTemplate->renderResponse('RestaurantEditor/Index');
    }

    private function buildBulkPreview(
        ServerRequestInterface $request,
        int $pid,
        int $menuUid,
    ): BulkPreviewView {
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $formToken = $formProtection->generateToken(self::BULK_FORM, self::BULK_ACTION);
        $formAction = (string)$this->uriBuilder->buildUriFromRoute(
            'web_arp_restaurant_editor',
            ['id' => $pid, 'menu' => $menuUid]
        );

        $rawInput = '';
        $result = null;
        $requestError = '';

        $body = $request->getParsedBody();
        $isPreviewPost = is_array($body) && isset($body['bulkPreview']);
        if ($isPreviewPost) {
            $rawInput = (string)($body['bulkPaste'] ?? '');
            $submittedToken = (string)($body['formToken'] ?? '');
            if (!$formProtection->validateToken($submittedToken, self::BULK_FORM, self::BULK_ACTION)) {
                $requestError = 'invalidCsrf';
            } else {
                $result = $this->bulkMenuParser->parse($rawInput);
            }
        }

        return new BulkPreviewView(
            formAction: $formAction,
            formToken: $formToken,
            rawInput: $rawInput,
            result: $result,
            requestError: $requestError,
            pid: $pid,
            menuUid: $menuUid,
            maxBytes: BulkMenuParser::DEFAULT_MAX_BYTES,
            maxRows: BulkMenuParser::DEFAULT_MAX_ROWS,
        );
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
