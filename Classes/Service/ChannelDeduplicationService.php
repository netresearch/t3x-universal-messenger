<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Service;

use Netresearch\Sdk\UniversalMessenger\Model\NewsletterChannel;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Groups Universal Messenger newsletter channels that share the same channel ID once
 * the configured test/live suffix is stripped, and deterministically picks which of
 * them supplies the title/description for the imported TYPO3 record.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
class ChannelDeduplicationService implements SingletonInterface
{
    /**
     * Removes the given suffixes from a channel ID, but only if a suffix matches the
     * very end of the ID. A plain str_ireplace() would also strip a suffix occurring
     * in the middle of a channel ID (e.g. "newsletter_Test_archive"), incorrectly
     * colliding it with an unrelated channel (e.g. "newsletter_archive").
     *
     * @param string   $channelId
     * @param string[] $suffixes
     *
     * @return string
     */
    public function stripSuffixes(string $channelId, array $suffixes): string
    {
        foreach ($suffixes as $suffix) {
            if ($this->endsWithCaseInsensitive($channelId, $suffix)) {
                return trim(substr($channelId, 0, -strlen($suffix)));
            }
        }

        return trim($channelId);
    }

    /**
     * Returns TRUE if $haystack ends with $needle, ignoring case. Always FALSE for an
     * empty $needle, so an empty configured suffix never matches (and thus never
     * strips) every channel ID.
     *
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    private function endsWithCaseInsensitive(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        return strtolower(substr($haystack, -strlen($needle))) === strtolower($needle);
    }

    /**
     * Groups channels by their suffix-stripped channel ID.
     *
     * @param NewsletterChannel[] $channels
     * @param string[]            $suffixes
     *
     * @return array<string, NewsletterChannel[]>
     */
    public function groupByStrippedId(array $channels, array $suffixes): array
    {
        $grouped = [];

        foreach ($channels as $channel) {
            $grouped[$this->stripSuffixes($channel->id, $suffixes)][] = $channel;
        }

        return $grouped;
    }

    /**
     * Picks the channel to use as the source of title/description for a group of
     * channels that share the same suffix-stripped channel ID.
     *
     * The channel whose raw ID exactly matches the stripped ID wins, it is the most
     * likely candidate for an intentionally curated base channel. Otherwise, the
     * alphabetically first raw ID is used, so the result stays deterministic instead
     * of depending on the order the Universal Messenger API happens to return.
     *
     * @param string              $channelId
     * @param NewsletterChannel[] $candidates
     *
     * @return NewsletterChannel
     */
    public function selectCanonicalChannel(string $channelId, array $candidates): NewsletterChannel
    {
        foreach ($candidates as $candidate) {
            if ($candidate->id === $channelId) {
                return $candidate;
            }
        }

        $sortedCandidates = $candidates;

        usort(
            $sortedCandidates,
            static fn (NewsletterChannel $left, NewsletterChannel $right): int => strcmp($left->id, $right->id),
        );

        return $sortedCandidates[0];
    }

    /**
     * Returns TRUE if a group of channels sharing the same suffix-stripped channel ID
     * actually consists of more than one distinct Universal Messenger channel.
     *
     * @param NewsletterChannel[] $candidates
     *
     * @return bool
     */
    public function isAmbiguousGroup(array $candidates): bool
    {
        return count($candidates) > 1;
    }
}
