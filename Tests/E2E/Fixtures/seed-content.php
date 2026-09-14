<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

// Connect to MariaDB. Same container/database as the shared provisioning
// script (mariadb-e2e / e2e_test), so this simply continues where it left
// off: the root page (uid 1) and the admin backend user already exist.
$pdo = new PDO(
    'mysql:host=mariadb-e2e;port=3306;dbname=e2e_test',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$now = time();

// Two newsletter channels. One is the newsletter page's own configured
// channel; the other is a channel the admin user is separately permitted
// for, but that has nothing to do with this page. Submitting the latter for
// this page is the exact GH-139 IDOR: the guard must reject it even though
// the user genuinely has access to that channel elsewhere.
const CHANNEL_OWN_UID   = 101;
const CHANNEL_OTHER_UID = 102;

$insertChannel = $pdo->prepare(
    'INSERT INTO tx_universalmessenger_domain_model_newsletterchannel
        (uid, pid, tstamp, crdate, channel_id, title, sender, reply_to)
     VALUES (?, 1, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE channel_id = VALUES(channel_id), title = VALUES(title)',
);

$insertChannel->execute([CHANNEL_OWN_UID, $now, $now, 'e2e_own', 'E2E Own Channel', 'sender@example.invalid', 'reply@example.invalid']);
$insertChannel->execute([CHANNEL_OTHER_UID, $now, $now, 'e2e_other', 'E2E Other Channel', 'sender@example.invalid', 'reply@example.invalid']);
echo "Newsletter channel records created\n";

/** Inserts a doktype-20 newsletter page as a child of the root page, visible, not hidden or deleted. */
function insertNewsletterPage(PDO $pdo, int $uid, string $title, string $slug, int $channelUid, int $now): void
{
    $pdo
        ->prepare(
            'INSERT IGNORE INTO pages (uid, pid, title, slug, doktype, universal_messenger_channel, hidden, deleted, tstamp, crdate)
             VALUES (?, 1, ?, ?, 20, ?, 0, 0, ?, ?)',
        )
        ->execute([$uid, $title, $slug, $channelUid, $now, $now]);
}

// Newsletter page (doktype 20), child of the root page, configured for the
// "own" channel. Visible, not hidden or deleted.
insertNewsletterPage(
    $pdo,
    10,
    'Newsletter',
    '/newsletter',
    CHANNEL_OWN_UID,
    $now,
);
echo "Newsletter page (uid=10) created\n";

// Grant the admin backend user permission for BOTH channels: the page's own
// one, and the unrelated one. This is the fixture, not the vulnerability.
// The point is that "permitted for channel X in general" must not be enough
// to dispatch through a page configured for a different channel.
$pdo
    ->prepare("UPDATE be_users SET universal_messenger_channels = ? WHERE username = 'admin'")
    ->execute([CHANNEL_OWN_UID . ',' . CHANNEL_OTHER_UID]);
echo "Admin user granted both channel permissions\n";

// A second newsletter page (uid 11), also configured for the "own"
// channel, but without the "Example Newsletter Template" static template a
// classic (non-Site-Set) site needs for the container template. A
// page-scoped sys_template override unsets both the frontend plugin's own
// templatePathAndFilename AND the backend module's independent copy of it
// (module.tx_universalmessenger < plugin.tx_universalmessenger is a
// TypoScript copy processed once, at the site's own base TypoScript, before
// this override applies, so only unsetting the plugin branch would leave
// the module's already-copied value intact and not reproduce a real "static
// template never included" site).
insertNewsletterPage(
    $pdo,
    11,
    'Newsletter Without Template',
    '/newsletter-without-template',
    CHANNEL_OWN_UID,
    $now,
);
echo "Newsletter page without a container template (uid=11) created\n";

$pdo
    ->prepare(
        'INSERT INTO sys_template (pid, tstamp, crdate, title, root, clear, config)
         VALUES (11, ?, ?, \'GH-141 E2E: container template not configured\', 0, 0, ?)',
    )
    ->execute([
        $now,
        $now,
        "plugin.tx_universalmessenger.view.templatePathAndFilename >\n"
        . 'module.tx_universalmessenger.view.templatePathAndFilename >',
    ]);
echo "Page 11's container-template TypoScript unset\n";
