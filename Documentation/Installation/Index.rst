..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-composer:

Composer
========

Install the extension via Composer:

..  code-block:: bash
    :caption: Install the extension

    composer require netresearch/universal-messenger

Activate the extension in the :guilabel:`Admin Tools > Extensions` backend
module if your installation does not activate required extensions
automatically.

..  _installation-database:

Update the database structure
==============================

The extension adds the newsletter page type and its channel-selection
field to the ``pages`` table. Open :guilabel:`Admin Tools > Maintenance`
and run :guilabel:`Analyze Database Structure` to add the missing fields.

..  _installation-site-configuration:

Site configuration
====================

The extension ships its default TypoScript two ways, so no manual
"include static template" step is required for either kind of site:

*   **Classic sites** (``sys_template`` based) receive the default
    TypoScript automatically through a global TypoScript registration.
*   **Site Set based sites** load the same TypoScript by adding
    ``netresearch/universal-messenger`` to the site's Set dependencies.

..  code-block:: yaml
    :caption: config/sites/<site-identifier>/config.yaml

    dependencies:
      - netresearch/universal-messenger

Two additional static templates provide the Fluid rendering of the
newsletter content and an example newsletter page template. Include them
as described in :ref:`configuration-typoscript`.

After installation, continue with :ref:`configuration` to set up the
Universal Messenger webservice credentials.
