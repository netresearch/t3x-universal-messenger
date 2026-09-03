..  include:: /Includes.rst.txt

..  _usage-backend-module:

==============
Backend module
==============

Open the backend module by clicking :guilabel:`Universal Messenger` under
the :guilabel:`Netresearch` group in the left-hand navigation.

..  figure:: /Module-Step1.png
    :alt: Backend module navigation entry "Universal Messenger" under the
        Netresearch group
    :class: with-border with-shadow
    :zoom: lightbox

    Open the :guilabel:`Universal Messenger` backend module

Select the newsletter page you want to send. If you have the appropriate
access rights (see :ref:`configuration-backend-user`), a preview of the
newsletter is shown exactly as it would be transferred to Universal
Messenger and sent. A language switcher appears above the preview when
the newsletter exists in multiple languages.

..  note::
    The language switcher only appears if the newsletter **page itself** has
    been localized (for example via the page tree context menu or the
    language column in the :guilabel:`Page` module), translating individual
    content elements alone is not sufficient. This mirrors TYPO3 core's own
    page-translation detection and applies regardless of how many languages
    are configured for the site.

..  figure:: /Module-Step2.png
    :alt: Backend module showing a newsletter preview with a language
        switcher above it
    :class: with-border with-shadow
    :zoom: lightbox

    Newsletter preview

Below the preview, two buttons trigger a TEST dispatch and the final LIVE
dispatch, see :ref:`usage-basic` for how the target channel is derived.
The LIVE dispatch must be confirmed again in a dialog before it is sent.

..  note::
    Once a newsletter page has been sent LIVE, the module keeps showing a
    status banner ("The newsletter has been sent out: ...") every time it is
    opened for that page and language, not just once right after sending.
    This is not a stale flash message, the module derives a deterministic
    event ID from the site, page and language, and queries Universal
    Messenger for that exact ID's status on every request. Once that ID has
    been sent, its status stays "finished" in Universal Messenger, so the
    banner keeps reappearing. This is the visible counterpart of the "Skip
    used ID" channel setting, which cancels a resend of an already-used
    event ID, the banner is a reminder that this newsletter/language
    combination has already gone out, not a report of a send that just
    happened.
