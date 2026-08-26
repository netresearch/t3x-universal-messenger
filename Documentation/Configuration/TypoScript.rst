..  include:: /Includes.rst.txt

..  _configuration-typoscript:

==========
TypoScript
==========

The extension's default TypoScript (the newsletter preview page type, the
plugin and the content element configuration) is loaded automatically, see
:ref:`installation-site-configuration`. Two further static templates
render the actual newsletter markup and are **required** for newsletters
to work, they are not loaded automatically.

..  _configuration-typoscript-fluid-content-elements:

Fluid content elements
========================

Provides the Fluid rendering used for the newsletter view and the
newsletter container template. Without it, the backend preview and the
dispatch to Universal Messenger have no template to render.

On classic (``sys_template`` based) sites, go to the :guilabel:`Web >
Template` module, select :guilabel:`Edit TypoScript Record`, then
:guilabel:`Edit the whole TypoScript record`. On the :guilabel:`Advanced
Options` tab, add ``Universal Messenger: Fluid Content Elements`` to the
list of included static templates.

On Site Set based sites, the same TypoScript is already imported by the
``netresearch/universal-messenger`` Set, no extra step is needed.

..  confval:: inlineCssFiles

    :type: TypoScript array
    :Default: ``EXT:universal_messenger/Resources/Public/Css/ZurbFoundation.css``
    :Path: plugin.tx_universalmessenger.settings.inlineCssFiles

    Additional CSS files that are inlined as ``style`` attributes into the
    respective HTML elements when a newsletter is rendered, in addition to
    the `Foundation for Emails 2
    <https://get.foundation/emails.html>`__ base CSS the extension already
    includes by default.

    ..  code-block:: typoscript
        :caption: Add a project-specific CSS file

        plugin.tx_universalmessenger {
            settings {
                inlineCssFiles {
                    10 = EXT:universal_messenger/Resources/Public/Css/ZurbFoundation.css
                    20 = EXT:your_sitepackage/Resources/Private/Css/Newsletter.css
                }
            }
        }

        module.tx_universalmessenger < plugin.tx_universalmessenger

    The backend module preview uses ``module.tx_universalmessenger``, keep
    it in sync with the frontend plugin setup as shown above.

..  _configuration-typoscript-example-newsletter:

Example newsletter template
=============================

Provides an example newsletter backend layout and page template as a
starting point for a project-specific newsletter design.

On classic sites, include the static template ``Universal Messenger:
Example Newsletter Template`` the same way as above. On Site Set based
sites it is imported together with the Fluid content elements template.

Open the page where the layout should apply, switch to the page
properties :guilabel:`Resources` tab, and add ``Universal Messenger:
Backend Layout`` to the :guilabel:`Page TSconfig` selection. The layout is
then selectable on the page's :guilabel:`Layout` tab.
