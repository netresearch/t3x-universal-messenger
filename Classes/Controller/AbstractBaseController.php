<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Controller;

use Exception;
use Netresearch\UniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use Netresearch\UniversalMessenger\Domain\Repository\PageRepository;
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use Netresearch\UniversalMessenger\Service\UniversalMessengerService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\ButtonInterface;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItemInterface;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownRadio;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

use function count;

/**
 * AbstractBaseController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
abstract class AbstractBaseController extends ActionController
{
    /**
     * @var int
     */
    protected const PREVIEW_TYPE_NUMBER = 1_715_682_913;

    /**
     * @var ModuleTemplateFactory
     */
    private readonly ModuleTemplateFactory $moduleTemplateFactory;

    /**
     * @var ExtensionConfiguration
     */
    protected ExtensionConfiguration $extensionConfiguration;

    /**
     * @var ModuleTemplate
     */
    protected ModuleTemplate $moduleTemplate;

    /**
     * @var UniversalMessengerService
     */
    protected UniversalMessengerService $universalMessengerService;

    /**
     * @var PageRepository
     */
    protected PageRepository $pageRepository;

    /**
     * @var NewsletterChannelRepository
     */
    protected NewsletterChannelRepository $newsletterChannelRepository;

    /**
     * @var NewsletterRenderService
     */
    protected NewsletterRenderService $newsletterRenderService;

    /**
     * The selected page ID.
     *
     * @var int
     */
    protected int $pageId = 0;

    /**
     * Backend module data.
     *
     * @var ModuleData|null
     */
    protected ?ModuleData $moduleData = null;

    /**
     * The available site languages.
     *
     * @var SiteLanguage[]
     */
    protected array $availableLanguages = [];

    /**
     * @var array<int, string>
     */
    protected mixed $languages;

    /**
     * @var int
     */
    protected int $currentSelectedLanguage;

    /**
     * AbstractBaseController constructor.
     *
     * @param ModuleTemplateFactory       $moduleTemplateFactory
     * @param ExtensionConfiguration      $extensionConfiguration
     * @param UniversalMessengerService   $universalMessengerService
     * @param PageRepository              $pageRepository,
     * @param NewsletterChannelRepository $newsletterChannelRepository
     * @param NewsletterRenderService     $newsletterRenderService
     */
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        ExtensionConfiguration $extensionConfiguration,
        UniversalMessengerService $universalMessengerService,
        PageRepository $pageRepository,
        NewsletterChannelRepository $newsletterChannelRepository,
        NewsletterRenderService $newsletterRenderService
    ) {
        $this->moduleTemplateFactory       = $moduleTemplateFactory;
        $this->extensionConfiguration      = $extensionConfiguration;
        $this->universalMessengerService   = $universalMessengerService;
        $this->pageRepository              = $pageRepository;
        $this->newsletterChannelRepository = $newsletterChannelRepository;
        $this->newsletterRenderService     = $newsletterRenderService;
    }

    /**
     * Initialize action.
     *
     * @return void
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();

        $this->pageId         = $this->getPageId();
        $this->moduleTemplate = $this->getModuleTemplate();
        $this->moduleData     = $this->request->getAttribute('moduleData');

        $this->availableLanguages = $this->request
            ->getAttribute('site')
            ->getAvailableLanguages(
                $this->getBackendUserAuthentication(),
                false,
                $this->pageId
            );

        $this->currentSelectedLanguage = (int) $this->moduleData->get('language');
    }

    /**
     * The error entry point.
     *
     * @return ResponseInterface
     */
    protected function errorAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger');
    }

    /**
     * Returns the page ID extracted from the given request object.
     *
     * @return int
     */
    private function getPageId(): int
    {
        return (int) ($this->request->getParsedBody()['id'] ?? $this->request->getQueryParams()['id'] ?? -1);
    }

    /**
     * Returns the module template instance.
     *
     * @return ModuleTemplate
     */
    private function getModuleTemplate(): ModuleTemplate
    {
        $pageRecord = BackendUtility::readPageAccess(
            $this->pageId,
            $this->getBackendUserAuthentication()->getPagePermsClause(Permission::PAGE_SHOW)
        );

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setBodyTag('<body class="typo3-module-universal-messenger">');
        $moduleTemplate->setModuleId('typo3-module-universal-messenger');
        $moduleTemplate->setTitle(
            $this->translate(
                'mlang_tabs_tab',
                null,
                'LLL:EXT:universal_messenger/Resources/Private/Language/locallang_mod_um.xlf'
            ),
            $pageRecord['title'] ?? ''
        );

        if ($pageRecord !== false) {
            $moduleTemplate
                ->getDocHeaderComponent()
                ->setMetaInformation($pageRecord);
        }

        return $moduleTemplate;
    }

    /**
     * Creates the language switch button of the button bar.
     *
     * @param ButtonBar $buttonbar
     *
     * @return ButtonInterface|null
     *
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws RouteNotFoundException
     */
    protected function makeLanguageSwitchButton(ButtonBar $buttonbar): ?ButtonInterface
    {
        $backendUserAuthentication = $this->getBackendUserAuthentication();

        $this->languages = [
            0 => isset($this->availableLanguages[0])
                ? $this->availableLanguages[0]->getTitle()
                : $this->getLanguageService()->sL(
                    'LLL:EXT:backend/Resources/Private/Language/locallang_layout.xlf:m_default'
                ),
        ];

        // First, select all localized page records on the current page.
        // Each represents a possibility for a language on the page. Add these to language selector.
        if ($this->pageId > 0) {
            // Compile language data for pid != 0 only. The language drop-down is not shown on pid 0
            // since pid 0 can't be localized.
            $pageTranslations = $this->pageRepository
                ->getExistingPageTranslations(
                    $this->pageId,
                    $backendUserAuthentication
                );

            foreach ($pageTranslations as $pageTranslation) {
                $languageId = $pageTranslation[$GLOBALS['TCA']['pages']['ctrl']['languageField']];

                if (isset($this->availableLanguages[$languageId])) {
                    $this->languages[$languageId] = $this->availableLanguages[$languageId]->getTitle();
                }
            }
        }

        // Early return if less than 2 languages are available
        if (count($this->languages) < 2) {
            return null;
        }

        $languageDropDownButton = $buttonbar->makeDropDownButton()
            ->setLabel(
                $this->getLanguageService()->sL(
                    'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.language'
                )
            )
            ->setShowLabelText(true);

        foreach (array_keys($this->languages) as $key) {
            $siteLanguage = $this->availableLanguages[$key] ?? null;

            // Skip invalid language keys
            if (!($siteLanguage instanceof SiteLanguage)) {
                continue;
            }

            /** @var DropDownItemInterface $languageItem */
            $languageItem = GeneralUtility::makeInstance(DropDownRadio::class)
                ->setActive($this->currentSelectedLanguage === $siteLanguage->getLanguageId())
                ->setIcon($this->getIconFactory()->getIcon($siteLanguage->getFlagIdentifier()))
                ->setHref(
                    (string) $this->getBackendUriBuilder()
                        ->buildUriFromRoute(
                            'netresearch_universal_messenger',
                            [
                                'id'       => $this->pageId,
                                'language' => $siteLanguage->getLanguageId(),
                            ]
                        )
                )
                ->setLabel($siteLanguage->getTitle());

            $languageDropDownButton->addItem($languageItem);
        }

        return $languageDropDownButton;
    }

    /**
     * Adds a flash message to the message queue and forward to the error action to abort further processing.
     *
     * @param string                     $key
     * @param ContextualFeedbackSeverity $severity
     *
     * @return ResponseInterface
     */
    protected function forwardFlashMessage(
        string $key,
        ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::ERROR
    ): ResponseInterface {
        $this->moduleTemplate->addFlashMessage(
            $this->translate($key),
            $this->translate('common.universalMessenger'),
            $severity
        );

        return new ForwardResponse('error');
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Returns an instance of the language service.
     *
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @return IconFactory
     */
    protected function getIconFactory(): IconFactory
    {
        return GeneralUtility::makeInstance(IconFactory::class);
    }

    /**
     * Returns an instance of the backend uri builder.
     *
     * @return UriBuilder
     */
    protected function getBackendUriBuilder(): UriBuilder
    {
        return GeneralUtility::makeInstance(UriBuilder::class);
    }

    /**
     * Returns the translated language label for the given identifier.
     *
     * @param string                       $key
     * @param array<int|float|string>|null $arguments
     * @param string                       $languageFile
     *
     * @return string
     */
    protected function translate(
        string $key,
        ?array $arguments = null,
        string $languageFile = 'LLL:EXT:universal_messenger/Resources/Private/Language/locallang.xlf'
    ): string {
        return LocalizationUtility::translate(
            $languageFile . ':' . $key,
            null,
            $arguments
        ) ?? LocalizationUtility::translate(
            $languageFile . ':error.missingTranslation',
            null,
            $arguments
        ) . ' ' . $key;
    }

    /**
     * Get the extension configuration.
     *
     * @param string $path Path to get the config for
     *
     * @return mixed
     */
    protected function getExtensionConfiguration(string $path): mixed
    {
        try {
            return $this->extensionConfiguration->get('universal_messenger', $path);
        } catch (Exception) {
            return null;
        }
    }
}
