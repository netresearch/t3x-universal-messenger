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
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

/**
 * Records the flash messages instead of translating them.
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

    protected function forwardFlashMessage(
        string $key,
        ContextualFeedbackSeverity $contextualFeedbackSeverity = ContextualFeedbackSeverity::ERROR,
    ): ResponseInterface {
        $this->forwardedFlashMessages[] = $key;

        return new ForwardResponse('error');
    }
}
