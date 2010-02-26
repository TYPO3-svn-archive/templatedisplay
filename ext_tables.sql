#
# Table structure for table 'tx_templatedisplay_displays'
#
CREATE TABLE tx_templatedisplay_displays (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	debug_markers tinyint(4) DEFAULT '0' NOT NULL,
	debug_template_structure tinyint(4) DEFAULT '0' NOT NULL,
	debug_data_structure tinyint(4) DEFAULT '0' NOT NULL,
	title varchar(255) DEFAULT '' NOT NULL,
	description text,
	template text,
	mappings mediumtext,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);