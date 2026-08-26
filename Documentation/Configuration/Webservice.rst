..  include:: /Includes.rst.txt

..  _configuration-webservice:

==========
Webservice
==========

..  _configuration-webservice-api-endpoint:

API endpoint
============

To access the Universal Messenger API, store the connection configuration
in :file:`additional.php`, under ``TYPO3_CONF_VARS`` >
``EXTENSIONS`` > ``universal_messenger`` (note the spelling, it uses the
extension key, not the Composer package name).

..  code-block:: php
    :caption: config/system/additional.php

    // The Universal Messenger API endpoint
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['universal_messenger'] = array_merge(
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['universal_messenger'] ?? [],
        [
            'apiUrl'    => 'YOUR-API-URL',
            'apiKey'    => 'YOUR-API-KEY',
            'apiSecret' => 'YOUR-API-SECRET',
        ]
    );

..  confval:: apiUrl

    :type: string
    :Required: true

    Your Universal Messenger API URL, the basis of all requests, for
    example ``https://your-domain.td.universal-messenger.de/p``.

..  confval:: apiKey

    :type: string
    :Required: true

    The public key of your Universal Messenger API key, used as the HTTP
    basic authentication username.

..  confval:: apiSecret

    :type: string
    :Required: true

    The secret key of your Universal Messenger API key, used as the HTTP
    basic authentication password.

..  important::
    :Server compatibility:

    The Universal Messenger REST API is not versioned on the client side,
    the authentication scheme depends on the Universal Messenger server
    version. API-key basic authentication requires a Universal Messenger
    server **7.56.0 or later**. The ``umopen``/``cmsbs.open`` token is
    deprecated since UM 7.41 and works only transitionally, older servers
    require the 2.x line of this extension and of the underlying `SDK
    <https://github.com/netresearch/sdk-api-universal-messenger>`__.

Create the API key in the Universal Messenger backend, under the API key
management for your account.

..  _configuration-webservice-logging:

API logging
============

To log Universal Messenger API requests and responses, enable
:confval:`enableLogging` (see :ref:`configuration-extension-configuration`)
and add a log writer configuration to :file:`ext_localconf.php`:

..  code-block:: php
    :caption: ext_localconf.php

    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Netresearch']['UniversalMessenger']['writerConfiguration'] = [
        \Psr\Log\LogLevel::DEBUG => [
            \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                'logFileInfix' => 'universal_messenger',
            ],
        ],
    ];
