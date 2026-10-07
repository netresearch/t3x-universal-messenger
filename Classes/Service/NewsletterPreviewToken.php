<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 * SPDX-License-Identifier: LicenseRef-Netresearch-Restricted-Use
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Service;

use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * The token that authorises one newsletter preview request.
 *
 * The backend module builds every preview URL (the iframe in the module and the URL
 * createAction() fetches to build the mail body) with a token for the page it shows:
 * an HMAC of the page UID, keyed with the installation's encryption key. The
 * frontend preview plugin renders a page only with the token for that page, so only
 * URLs the backend issued make the server fetch and render a newsletter page.
 *
 * A token names one page and has no expiry: it grants the preview of that page, which
 * whoever holds the URL has already been shown.
 */
class NewsletterPreviewToken implements SingletonInterface
{
    /**
     * Scope of the HMAC, so the token cannot be reused for another purpose of the same key.
     *
     * @var string
     */
    private const HMAC_SCOPE = 'universal_messenger_newsletter_preview';

    /**
     * One algorithm for issuing and for checking a token.
     *
     * @var HashAlgo
     */
    private const HMAC_ALGORITHM = HashAlgo::SHA3_256;

    /**
     * @var HashService
     */
    private readonly HashService $hashService;

    /**
     * Constructor.
     *
     * @param HashService $hashService
     */
    public function __construct(HashService $hashService)
    {
        $this->hashService = $hashService;
    }

    /**
     * Returns the token for the preview of the given page.
     *
     * @param int $pageId
     *
     * @return string
     */
    public function create(int $pageId): string
    {
        return $this->hashService->hmac((string) $pageId, self::HMAC_SCOPE, self::HMAC_ALGORITHM);
    }

    /**
     * Whether the token is the one create() issues for the given page.
     *
     * @param int    $pageId
     * @param string $token
     *
     * @return bool
     */
    public function isValid(int $pageId, string $token): bool
    {
        return ($pageId > 0)
            && ($token !== '')
            && $this->hashService->validateHmac((string) $pageId, self::HMAC_SCOPE, $token, self::HMAC_ALGORITHM);
    }
}
