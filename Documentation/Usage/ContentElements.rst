..  include:: /Includes.rst.txt

..  _usage-content-elements:

================
Content elements
================

The extension provides content elements for use with the Universal
Messenger API.

..  _usage-content-elements-control-structure:

Control structure
===================

In the content element's settings, enter the corresponding `control
structure
<https://get.foundation/emails.html>`__ (for example a personalized
salutation) that Universal Messenger substitutes when the newsletter is
sent. Also specify the alternative text shown when the newsletter is
opened in a web view, where personalization is not available.

..  figure:: /CE-ControlStructure.png
    :alt: Content element "Control structure" showing the Universal
        Messenger placeholder and its web-view alternative
    :class: with-border with-shadow
    :zoom: lightbox

    Content element :guilabel:`Control structure`

Both the control structure and the alternative text can be formatted
using the RTE editor and adapted to the newsletter's layout.

..  note::
    ``tt_content.control_structure`` is registered as a standalone
    ``FLUIDTEMPLATE`` object, so it bypasses whatever wrapping a site
    theme applies to other content types. If you need to wrap its
    website-rendered output (but not the mail rendering) in your own
    site-level TypoScript, scope the override using the classic
    ``stdWrap.if`` / ``data = GP:...`` pattern, **not** a TypoScript
    condition with ExpressionLanguage array access on
    ``request.getQueryParams()``:

    ..  code-block:: typoscript
        :caption: Safe: "GP:type" returns an empty string, never a PHP
            warning, when the "type" GET parameter is absent

        tt_content.control_structure.stdWrap.wrap = <div class="component"><div class="component__content">|</div></div>
        tt_content.control_structure.stdWrap.wrap.if.value.data = GP:type
        tt_content.control_structure.stdWrap.wrap.if.equals = 1715682913
        tt_content.control_structure.stdWrap.wrap.if.negate = 1

    A condition such as ``[request.getQueryParams()["type"] !=
    1715682913]`` crashes with an HTTP 500 whenever the ``type`` GET
    parameter is absent, i.e. on every plain page view without a
    newsletter render type: Symfony ExpressionLanguage's array-access
    node has no null-coalescing equivalent to PHP's ``??``, so the
    resulting ``E_WARNING: Undefined array key`` is escalated by TYPO3's
    default ``SYS.exceptionalErrors`` configuration into a fatal,
    uncaught exception.
