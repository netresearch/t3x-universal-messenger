#
# Table structure for table 'be_groups'
#
CREATE TABLE be_groups
(
    universal_messenger_channels text
);

#
# Table structure for table 'be_users'
#
CREATE TABLE be_users
(
    universal_messenger_channels text
);

#
# Table structure for table 'pages'
#
CREATE TABLE pages
(
    universal_messenger_channel int(10) UNSIGNED NOT NULL DEFAULT 0
);

#
# Table structure for table 'tx_universalmessenger_domain_model_newsletterchannel'
#
CREATE TABLE tx_universalmessenger_domain_model_newsletterchannel
(
    uid          int(10) UNSIGNED     NOT NULL AUTO_INCREMENT,
    pid          int(10) UNSIGNED     NOT NULL DEFAULT 0,
    tstamp       int(10) UNSIGNED     NOT NULL DEFAULT 0,
    crdate       int(10) UNSIGNED     NOT NULL DEFAULT 0,
    deleted      smallint(5) UNSIGNED NOT NULL DEFAULT 0,
    hidden       smallint(5) UNSIGNED NOT NULL DEFAULT 0,
    starttime    int(10) UNSIGNED     NOT NULL DEFAULT 0,
    endtime      int(10) UNSIGNED     NOT NULL DEFAULT 0,
    sorting      int(10) UNSIGNED     NOT NULL DEFAULT 0,

    channel_id   varchar(255)         NOT NULL,
    title        varchar(255)         NOT NULL DEFAULT '',
    description  text                 NOT NULL DEFAULT '',
    sender       varchar(255)         NOT NULL DEFAULT '',
    reply_to     varchar(255)         NOT NULL DEFAULT '',
    skip_used_id smallint(5) UNSIGNED NOT NULL DEFAULT 0,
    embed_images varchar(255)         NOT NULL DEFAULT 'none',

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY channelId (channel_id)
);
