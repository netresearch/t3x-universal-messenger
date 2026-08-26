..  include:: /Includes.rst.txt

..  _start:

===================
Universal Messenger
===================

:Extension key:
    universal_messenger

:Package name:
    netresearch/universal-messenger

:Version:
    |release|

:Language:
    en

:Author:
    Rico Sonntag

:License:
    This document is published under the
    `CC BY-SA 4.0 <https://creativecommons.org/licenses/by-sa/4.0/>`__
    license. The extension itself is proprietary software, see the
    :file:`LICENSE` file in the repository root.

:Rendered:
    |today|

----

A TYPO3 extension that provides a backend module to compose TYPO3 pages as
newsletters and send them through the `Universal Messenger
<https://www.universal-messenger.de/>`__ API.

Editors build a newsletter with the usual TYPO3 content elements on a
dedicated :guilabel:`Newsletter` page type, preview it per language in a
backend module, and send it as a test or live dispatch to a channel
imported from Universal Messenger.

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: 🚀 Introduction

        What the extension does, requirements and compatibility.

        ..  card-footer:: :ref:`Read more <introduction>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: 💻 Installation

        Installing the extension via Composer and setting up the database.

        ..  card-footer:: :ref:`Read more <installation>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: ⚙️ Configuration

        Webservice credentials, extension settings, backend user rights and
        TypoScript.

        ..  card-footer:: :ref:`Read more <configuration>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: 📧 Usage

        Creating newsletters, content elements, ViewHelpers and the
        dispatch module.

        ..  card-footer:: :ref:`Read more <usage>`
            :button-style: btn btn-secondary stretched-link

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Usage/Index
