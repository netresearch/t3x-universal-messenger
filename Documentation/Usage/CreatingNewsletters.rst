..  include:: /Includes.rst.txt

..  _usage-creating-newsletters:

=====================
Creating a newsletter
=====================

To create a new newsletter, create a new page in the TYPO3 backend. Use
the :guilabel:`Newsletter` shortcut in the page tree's "new page" drag
area, or create a standard page and change its type afterwards.

..  figure:: /Newsletter-Step-1.png
    :alt: Page tree "new page" drag area showing the Newsletter page type
        shortcut
    :class: with-border with-shadow
    :zoom: lightbox

    Create a new newsletter page

For a standard page, open the page properties and select the page type
:guilabel:`Newsletter`.

..  figure:: /Newsletter-Step-2.png
    :alt: Page properties General tab with the Newsletter page type selected
    :class: with-border with-shadow
    :zoom: lightbox

    Select the :guilabel:`Newsletter` page type

The page reloads, and a selection for the Universal Messenger newsletter
channel appears below the page type field. Select the channel this
newsletter should be sent through.

..  figure:: /Newsletter-Step-3.png
    :alt: Page properties showing the newsletter channel selection field
    :class: with-border with-shadow
    :zoom: lightbox

    Select the newsletter channel

Build the newsletter with the usual TYPO3 content elements. Use a
container framework such as `container_elements
<https://extensions.typo3.org/extension/container_elements>`__ if you
need to group elements into columns and rows.

The extension also provides ViewHelpers (see :ref:`usage-view-helpers`)
and content elements (see :ref:`usage-content-elements`) to arrange
content according to the `Foundation for Emails 2
<https://get.foundation/emails.html>`__ framework the shipped rendering
is built on.
