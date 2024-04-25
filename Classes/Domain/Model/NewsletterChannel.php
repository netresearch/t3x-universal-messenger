<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrcUniversalMessenger\Domain\Model;

use DateTime;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Job publication.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class NewsletterChannel extends AbstractEntity
{
    /**
     * @var string
     */
    protected string $newsletterChannelId = '';

    /**
     * @var string
     */
    protected string $title = '';

    /**
     * @var bool
     */
    protected bool $isPublic = false;

    /**
     * @var bool
     */
    protected bool $isVirtual = false;

    /**
     * @var string
     */
    protected string $oid = '';

    /**
     * @var int
     */
    protected int $estimatedCount = 0;

    /**
     * @var DateTime
     */
    protected DateTime $crdate;

    /**
     * @var DateTime
     */
    protected DateTime $tstamp;

    /**
     * @var bool
     */
    protected bool $deleted = false;

    /**
     * @var string
     */
    protected string $sender = '';

    /**
     * @var string
     */
    protected string $replyTo = '';

    /**
     * @var bool
     */
    protected bool $skipUsedId = false;

    /**
     * @var string
     */
    protected string $embedImages = 'none';

    /**
     * @return string
     */
    public function getNewsletterChannelId(): string
    {
        return $this->newsletterChannelId;
    }

    /**
     * @param string $newsletterChannelId
     *
     * @return NewsletterChannel
     */
    public function setNewsletterChannelId(string $newsletterChannelId): NewsletterChannel
    {
        $this->newsletterChannelId = $newsletterChannelId;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return NewsletterChannel
     */
    public function setTitle(string $title): NewsletterChannel
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return bool
     */
    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    /**
     * @param bool $isPublic
     *
     * @return NewsletterChannel
     */
    public function setIsPublic(bool $isPublic): NewsletterChannel
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    /**
     * @return bool
     */
    public function isVirtual(): bool
    {
        return $this->isVirtual;
    }

    /**
     * @param bool $isVirtual
     *
     * @return NewsletterChannel
     */
    public function setIsVirtual(bool $isVirtual): NewsletterChannel
    {
        $this->isVirtual = $isVirtual;

        return $this;
    }

    /**
     * @return string
     */
    public function getOid(): string
    {
        return $this->oid;
    }

    /**
     * @param string $oid
     *
     * @return NewsletterChannel
     */
    public function setOid(string $oid): NewsletterChannel
    {
        $this->oid = $oid;

        return $this;
    }

    /**
     * @return int
     */
    public function getEstimatedCount(): int
    {
        return $this->estimatedCount;
    }

    /**
     * @param int $estimatedCount
     *
     * @return NewsletterChannel
     */
    public function setEstimatedCount(int $estimatedCount): NewsletterChannel
    {
        $this->estimatedCount = $estimatedCount;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getCrdate(): DateTime
    {
        return $this->crdate;
    }

    /**
     * @param DateTime $crdate
     *
     * @return NewsletterChannel
     */
    public function setCrdate(DateTime $crdate): NewsletterChannel
    {
        $this->crdate = $crdate;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getTstamp(): DateTime
    {
        return $this->tstamp;
    }

    /**
     * @param DateTime $tstamp
     *
     * @return NewsletterChannel
     */
    public function setTstamp(DateTime $tstamp): NewsletterChannel
    {
        $this->tstamp = $tstamp;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @param bool $deleted
     *
     * @return NewsletterChannel
     */
    public function setDeleted(bool $deleted): NewsletterChannel
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * @return string
     */
    public function getSender(): string
    {
        return $this->sender;
    }

    /**
     * @param string $sender
     *
     * @return NewsletterChannel
     */
    public function setSender(string $sender): NewsletterChannel
    {
        $this->sender = $sender;

        return $this;
    }

    /**
     * @return string
     */
    public function getReplyTo(): string
    {
        return $this->replyTo;
    }

    /**
     * @param string $replyTo
     *
     * @return NewsletterChannel
     */
    public function setReplyTo(string $replyTo): NewsletterChannel
    {
        $this->replyTo = $replyTo;

        return $this;
    }

    /**
     * @return bool
     */
    public function isSkipUsedId(): bool
    {
        return $this->skipUsedId;
    }

    /**
     * @param bool $skipUsedId
     *
     * @return NewsletterChannel
     */
    public function setSkipUsedId(bool $skipUsedId): NewsletterChannel
    {
        $this->skipUsedId = $skipUsedId;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmbedImages(): string
    {
        return $this->embedImages;
    }

    /**
     * @param string $embedImages
     *
     * @return NewsletterChannel
     */
    public function setEmbedImages(string $embedImages): NewsletterChannel
    {
        $this->embedImages = $embedImages;

        return $this;
    }
}
