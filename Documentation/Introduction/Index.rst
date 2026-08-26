..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

..  _introduction-what-it-does:

What does it do?
=================

Universal Messenger lets editors compose a newsletter as a normal TYPO3
page, using the site's usual content elements, and send it through the
`Universal Messenger <https://www.universal-messenger.de/>`__ webservice.

*   A dedicated :guilabel:`Newsletter` page type carries the newsletter
    content and a selectable Universal Messenger channel.
*   A backend module renders a per-language preview of the newsletter and
    triggers a TEST or LIVE dispatch to Universal Messenger.
*   A console command imports the newsletter channels that are configured
    in Universal Messenger, so editors always see the current channel list
    in the page properties.
*   Fluid ViewHelpers and dedicated content elements (for example a
    personalization control structure) integrate with Universal Messenger's
    placeholder and templating conventions.
*   Shipped TypoScript renders the newsletter content with the `Foundation
    for Emails 2 <https://get.foundation/emails.html>`__ framework and
    inlines the resulting CSS, so the newsletter HTML matches what most
    email clients render correctly.

..  _introduction-requirements:

Requirements
=============

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: TYPO3

        TYPO3 14.0 or later. The extension does not support earlier TYPO3
        versions, see :ref:`introduction-version-matrix`.

    ..  card:: PHP

        PHP 8.2 to 8.5.

    ..  card:: Universal Messenger server

        Version 7.56.0 or later for API-key basic authentication, see
        :ref:`configuration-webservice`.

    ..  card:: Backend module access

        A backend user with access to the :guilabel:`Netresearch` module
        group and rights on the relevant newsletter channels, see
        :ref:`configuration-backend-user`.

..  _introduction-version-matrix:

Version matrix
===============

==================  ====================
Extension version   TYPO3 version
==================  ====================
3.x                 14.0 - 14.99
2.x                 13.4, 12.4
==================  ====================

The 2.x line also carries the ``umopen``/``cmsbs.open`` authentication
token required by Universal Messenger servers older than 7.56.0. See the
`SDK compatibility notes
<https://github.com/netresearch/sdk-api-universal-messenger>`__ for
details.
