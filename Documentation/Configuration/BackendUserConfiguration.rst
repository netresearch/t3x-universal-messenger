..  include:: /Includes.rst.txt

..  _configuration-backend-user:

========================
Backend user permissions
========================

Use the :guilabel:`Universal Messenger` tab on the backend user (or
backend user group) record to grant individual editors rights to send
newsletters through specific newsletter channels.

..  figure:: /BackendUserConfiguration.png
    :alt: Backend user edit form showing the Universal Messenger tab with
        a list of selectable newsletter channels
    :class: with-border with-shadow
    :zoom: lightbox

    Backend user configuration, :guilabel:`Universal Messenger` tab

..  note::
    :TYPO3 v14:

    The newsletter page type appears in the page tree's "new page" drag
    area automatically. Administrators always see it, non-admin editors
    see it only when the :guilabel:`Newsletter` page type is enabled in
    their backend group under :guilabel:`Access Rights > Page types`.

    Since TYPO3 v14.2 the drag area is derived from these group
    permissions, the former
    ``options.pageTree.doktypesToShowInNewPageDragArea`` user TSconfig
    option is deprecated and is removed in v15.0. If your installation
    still sets that option explicitly, either add the newsletter page
    type to the list or, preferably, drop the deprecated option and grant
    the page type through the group permission instead.
