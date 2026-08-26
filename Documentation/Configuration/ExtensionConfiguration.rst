..  include:: /Includes.rst.txt

..  _configuration-extension-configuration:

=======================
Extension configuration
=======================

Open :guilabel:`Admin Tools > Settings > Extension Configuration` and
switch to the ``universal_messenger`` extension.

..  _configuration-extension-configuration-general:

General
=======

..  figure:: /ExtensionConfiguration1.png
    :alt: Extension configuration tab General, showing the Storage page ID
        and Page type fields
    :class: with-border with-shadow
    :zoom: lightbox

    Extension configuration tab :guilabel:`General`

..  confval:: storagePageId

    :type: int
    :Default: 0
    :Path: General > Storage page ID

    The page ID used to store the Universal Messenger newsletter channel
    records.

..  confval:: newsletterPageDokType

    :type: int
    :Default: 20
    :Path: General > Page type

    The page type (``doktype``) used for Universal Messenger newsletter
    pages. Change this if the default value already selects a different
    page type in your installation.

..  _configuration-extension-configuration-webservice:

Webservice
==========

..  figure:: /ExtensionConfiguration2.png
    :alt: Extension configuration tab Webservice, showing the Enable
        logging checkbox
    :class: with-border with-shadow
    :zoom: lightbox

    Extension configuration tab :guilabel:`Webservice`

..  confval:: enableLogging

    :type: boolean
    :Default: false
    :Path: Webservice > Enable logging

    Log all Universal Messenger API requests and responses to a log file,
    see :ref:`configuration-webservice-logging`.

..  _configuration-extension-configuration-expert:

Expert
======

..  figure:: /ExtensionConfiguration3.png
    :alt: Extension configuration tab Expert, showing the test and live
        newsletter channel suffix fields
    :class: with-border with-shadow
    :zoom: lightbox

    Extension configuration tab :guilabel:`Expert`

..  confval:: newsletter.testChannelSuffix

    :type: string
    :Default: _Test
    :Path: Expert > Test newsletter channel suffix

    The suffix that marks a Universal Messenger channel as a TEST channel,
    see :ref:`configuration-extension-configuration-test-live-channels`.

..  confval:: newsletter.liveChannelSuffix

    :type: string
    :Default: _Live
    :Path: Expert > Live newsletter channel suffix

    The suffix that marks a Universal Messenger channel as a LIVE channel.

..  _configuration-extension-configuration-test-live-channels:

Test and live channels
-----------------------

To test a newsletter before it goes out, set up a separate test channel
in Universal Messenger and give it the configured suffix, for example:

Test operation
    Channel ``Newsletter_TEST``, sent to a defined recipient list used to
    validate the newsletter before the real dispatch.

Live operation
    Channel ``Newsletter_LIVE``, sent to the recipient list with actual
    customer addresses.

:confval:`newsletter.testChannelSuffix` and
:confval:`newsletter.liveChannelSuffix` adapt this convention to whatever
suffix your Universal Messenger setup actually uses. The extension strips
the suffix when it imports channels, see :ref:`usage-basic`.
