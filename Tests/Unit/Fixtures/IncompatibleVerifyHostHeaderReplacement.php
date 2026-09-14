<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Fixtures;

/**
 * IncompatibleVerifyHostHeaderReplacement.
 *
 * A GH-174 test fixture: a stand-in with a constructor signature incompatible with
 * TYPO3\CMS\Core\Middleware\VerifyHostHeader's, registered as its XCLASS override to prove
 * that a future TYPO3 core change or a third-party XCLASS of that @internal class makes
 * GeneralUtility::makeInstance() itself throw, not just the delegated method call.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final readonly class IncompatibleVerifyHostHeaderReplacement
{
    /**
     * @var array<int, string>
     */
    private array $trustedHostsPatterns;

    /**
     * Constructor.
     *
     * Deliberately requires an array where VerifyHostHeader requires a string (the trusted
     * hosts pattern), so instantiating it with that same one argument raises a TypeError.
     *
     * @param array<int, string> $trustedHostsPatterns
     */
    public function __construct(array $trustedHostsPatterns)
    {
        $this->trustedHostsPatterns = $trustedHostsPatterns;
    }

    /**
     * @return array<int, string>
     */
    public function getTrustedHostsPatterns(): array
    {
        return $this->trustedHostsPatterns;
    }
}
