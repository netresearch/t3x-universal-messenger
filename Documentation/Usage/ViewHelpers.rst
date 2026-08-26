..  include:: /Includes.rst.txt

..  _usage-view-helpers:

============
ViewHelpers
============

..  _usage-view-helpers-initialization:

Initialization
================

To use the extension's ViewHelpers in a Fluid template, declare their
namespace:

..  code-block:: html

    xmlns:um="http://typo3.org/ns/Netresearch/UniversalMessenger/ViewHelpers"

..  _usage-view-helpers-format-placeholder:

um:format.placeholder
========================

Marks a value as a Universal Messenger placeholder using curly brackets,
so it can be passed as a literal placeholder inside a URL, for example the
unsubscribe link of a newsletter.

..  code-block:: html
    :caption: Mark the "identifier" value as a Universal Messenger placeholder

    <f:link.external uri="https://newsletter.example.org/unsubscribe?identifier={um:format.placeholder(value: 'identifier')}">
        Unsubscribe
    </f:link.external>

This renders a URL of the form:

..  code-block:: text

    https://newsletter.example.org/unsubscribe?identifier={identifier}

Universal Messenger replaces ``{identifier}`` with the actual value when
the newsletter is sent.
