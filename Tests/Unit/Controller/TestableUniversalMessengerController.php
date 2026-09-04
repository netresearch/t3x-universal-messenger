<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Controller;

use Netresearch\UniversalMessenger\Controller\UniversalMessengerController;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

/**
 * Records the flash messages instead of translating them, and lets tests double
 * the page record and the backend user without a database or a bootstrapped
 * TYPO3 backend session.
 *
 * Translation goes through a static call that needs a bootstrapped TYPO3, which
 * a unit test does not provide. Capturing the message key keeps the test focused
 * on the controller decision.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final class TestableUniversalMessengerController extends UniversalMessengerController
{
    /**
     * @var string[]
     */
    public array $forwardedFlashMessages = [];

    /**
     * @var array<string, int|string|null>|null
     */
    public ?array $pageRecordOverride = null;

    public ?BackendUserAuthentication $backendUserAuthenticationOverride = null;

    protected function forwardFlashMessage(
        string $key,
        ContextualFeedbackSeverity $contextualFeedbackSeverity = ContextualFeedbackSeverity::ERROR,
    ): ResponseInterface {
        $this->forwardedFlashMessages[] = $key;

        return new ForwardResponse('error');
    }

    /**
     * Returns the doubled page record instead of hitting the database.
     */
    protected function getPageRecord(): ?array
    {
        return $this->pageRecordOverride;
    }

    /**
     * Returns the doubled backend user, or the real one when no override was set.
     */
    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $this->backendUserAuthenticationOverride ?? parent::getBackendUserAuthentication();
    }

    /**
     * Widens visibility so the authorization guard can be exercised directly,
     * without going through createAction()'s POST/argument scaffolding.
     */
    public function getChannelAuthorizationFailure(?array $pageRecord, int $channelUid): ?string
    {
        return parent::getChannelAuthorizationFailure($pageRecord, $channelUid);
    }

    /**
     * Widens visibility so the severity mapping can be exercised directly.
     */
    public function getAuthorizationFailureSeverity(string $authorizationFailure): ContextualFeedbackSeverity
    {
        return parent::getAuthorizationFailureSeverity($authorizationFailure);
    }
}
