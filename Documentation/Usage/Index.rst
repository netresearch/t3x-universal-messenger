..  include:: /Includes.rst.txt

..  _usage:

=====
Usage
=====

..  _usage-basic:

Basic concepts
===============

Universal Messenger newsletter channels are imported into TYPO3 as
generic channels, the configured test or live suffix (see
:ref:`configuration-extension-configuration-test-live-channels`) is cut
off during import, regardless of upper or lower case.

A newsletter page is therefore always assigned to a single generic
channel name. The split between a TEST and a LIVE dispatch happens only
when the newsletter is actually sent, in the backend module described in
:ref:`usage-backend-module`.

Each imported newsletter channel can carry additional, per-channel
settings. A re-import does not overwrite them:

================================  ===============  ==================================================================================
Field                              Default value    Description
================================  ===============  ==================================================================================
Sender email address                                Overrides the sender ID preset in the webservice configuration, if set.
Reply-to email address                              Overrides the reply-to ID preset in the webservice configuration, if set.
Skip used ID                       false            Cancel sending if a newsletter with the same event ID already exists in the archive.
Embed images                       none              Behavior for embedding images in the dispatched newsletter.
================================  ===============  ==================================================================================

These settings are sent to the Universal Messenger API together with the
newsletter content.

..  note::
    If "Embed images" is set to ``all``, Universal Messenger downloads
    the images from your TYPO3 instance's public URLs to embed them into
    the newsletter. This only works if those URLs are covered by the
    ``cmsbs.contentDownloader.urlAllowList`` configured on the Universal
    Messenger server side. Ask your Universal Messenger contact to add
    your domain to the allow list before using ``all``, otherwise the
    newsletter dispatch fails with an ``EventException: Ressource(n)
    fehlen in "html body"`` error. ``byPath`` only rewrites the image
    URLs to relative links and is not affected by this restriction.

..  _usage-importing-channels:

Importing newsletter channels
===============================

The console command ``universal-messenger:newsletter-channels:import``
imports the newsletter channels currently configured in Universal
Messenger.

..  code-block:: bash
    :caption: Import newsletter channels

    vendor/bin/typo3 universal-messenger:newsletter-channels:import

Configure it as a :guilabel:`Scheduler` task (:guilabel:`Execute console
commands`) to keep the channel list in TYPO3 in sync with Universal
Messenger automatically, for example once a day.

..  toctree::
    :maxdepth: 2
    :titlesonly:

    CreatingNewsletters
    ContentElements
    ViewHelpers
    BackendModule
