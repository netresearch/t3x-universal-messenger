<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Service;

use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;

/**
 * NewsletterContentResponseTrait.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
trait NewsletterContentResponseTrait
{
    /**
     * Builds a successful HTTP response carrying fixed newsletter content, for a stubbed
     * RequestFactory::request() to return. Uses a real Stream rather than a literal string
     * body, since TYPO3\CMS\Core\Http\Response's string constructor argument is a fopen()
     * stream identifier, not literal content.
     *
     * @return Response
     */
    private function createNewsletterContentResponse(): Response
    {
        $body = new Stream(
            'php://temp',
            'rw',
        );
        $body->write('newsletter content');
        $body->rewind();

        return new Response(
            $body,
            200,
        );
    }
}
