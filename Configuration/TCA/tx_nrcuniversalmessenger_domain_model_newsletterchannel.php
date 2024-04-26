<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

// Do not add "delete => 'deleted'" into the 'ctrl' section to achieve direct removal of records
// upon removeAll call in persistence manager.
return [
    'ctrl' => [
        'title'        => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel',
        'label'        => 'title',
        'tstamp'       => 'tstamp',
        'crdate'       => 'crdate',
        'hideTable'    => false,
        'sortby'       => 'sorting',
        'searchFields' => 'title,sender,reply_to',
        'iconfile'     => 'EXT:nrc_universal_messenger/Resources/Public/Icons/Extension.png',
    ],
    'interface' => [
        'maxSingleDBListItems' => 50,
    ],
    'types' => [
        0 => [
            'showitem' => 'newsletter_channel_id, title, sender, reply_to, skip_used_id, embed_images',
        ],
    ],
    'columns' => [
        'pid' => [
            'label'  => 'pid',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'crdate' => [
            'label'  => 'crdate',
            'config' => [
                'type' => 'datetime',
            ],
        ],
        'tstamp' => [
            'label'  => 'tstamp',
            'config' => [
                'type' => 'datetime',
            ],
        ],
        'starttime' => [
            'exclude' => true,
            'label'   => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.starttime',
            'config'  => [
                'type'      => 'datetime',
                'default'   => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'endtime' => [
            'exclude' => true,
            'label'   => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.endtime',
            'config'  => [
                'type'    => 'datetime',
                'default' => 0,
                'range'   => [
                    'upper' => mktime(
                        0,
                        0,
                        0,
                        1,
                        1,
                        2038
                    ),
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],

        'newsletter_channel_id' => [
            'exclude'     => true,
            'label'       => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.newsletter_channel_id',
            'description' => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.newsletter_channel_id.description',
            'config'      => [
                'type'     => 'input',
                'size'     => 255,
                'eval'     => 'trim',
                'readOnly' => true,
                'required' => true,
            ],
        ],
        'title' => [
            'label'       => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.title',
            'description' => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.title.description',
            'displayCond' => 'VERSION:IS:false',
            'config'      => [
                'type'     => 'input',
                'size'     => 255,
                'eval'     => 'trim',
                'readOnly' => true,
                'required' => true,
            ],
        ],
        'sender' => [
            'exclude'     => true,
            'label'       => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.sender',
            'description' => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.sender.description',
            'config'      => [
                'type'     => 'input',
                'size'     => 255,
                'eval'     => 'trim',
                'required' => true,
            ],
        ],
        'reply_to' => [
            'exclude'     => true,
            'label'       => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.reply_to',
            'description' => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.reply_to.description',
            'config'      => [
                'type'     => 'input',
                'size'     => 255,
                'eval'     => 'trim',
                'required' => true,
            ],
        ],
        'skip_used_id' => [
            'exclude'     => true,
            'label'       => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.skip_used_id',
            'description' => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.skip_used_id.description',
            'config'      => [
                'type'       => 'check',
                'renderType' => 'checkboxToggle',
                'default'    => 0,
            ],
        ],
        'embed_images' => [
            'exclude'     => true,
            'label'       => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.embed_images',
            'description' => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.embed_images.description',
            'config'      => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'minitems'   => 0,
                'maxitems'   => 1,
                'default'    => 'none',
                'items'      => [
                    [
                        'label' => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.embed_images.all',
                        'value' => 'all',
                    ],
                    [
                        'label' => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.embed_images.byPath',
                        'value' => 'byPath',
                    ],
                    [
                        'label' => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:tx_nrcuniversalmessenger_domain_model_newsletterchannel.embed_images.none',
                        'value' => 'none',
                    ],
                ],
            ],
        ],
    ],
];
