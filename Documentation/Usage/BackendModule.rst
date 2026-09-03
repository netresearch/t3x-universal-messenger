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
