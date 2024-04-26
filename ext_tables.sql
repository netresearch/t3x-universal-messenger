#
# Table structure for table 'tx_nrcuniversalmessenger_domain_model_newsletterchannel'
#
CREATE TABLE tx_nrcuniversalmessenger_domain_model_newsletterchannel
(
    uid                   int(11) unsigned                    NOT NULL auto_increment,
    pid                   int(11) unsigned     DEFAULT '0'    NOT NULL,
    tstamp                int(11) unsigned     DEFAULT '0'    NOT NULL,
    crdate                int(11) unsigned     DEFAULT '0'    NOT NULL,
    deleted               smallint(5) unsigned DEFAULT '0'    NOT NULL,
    hidden                smallint(5) unsigned DEFAULT '0'    NOT NULL,
    starttime             int(11) unsigned     DEFAULT '0'    NOT NULL,
    endtime               int(11) unsigned     DEFAULT '0'    NOT NULL,
    sorting               int(11) unsigned     DEFAULT '0'    NOT NULL,

    newsletter_channel_id varchar(255)         DEFAULT ''     NOT NULL,
    title                 varchar(255)         DEFAULT ''     NOT NULL,
    sender                varchar(255)         DEFAULT ''     NOT NULL,
    reply_to              varchar(255)         DEFAULT ''     NOT NULL,
    skip_used_id          smallint(5) unsigned DEFAULT '0'    NOT NULL,
    embed_images          varchar(255)         DEFAULT 'none' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY newsletterChannelId (newsletter_channel_id)
);