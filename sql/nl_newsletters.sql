-- Database schema for creating nl_newsletter table.

CREATE TABLE /*_*/nl_newsletters(
	-- Primary key
	nl_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
	nl_name varchar(64) NOT NULL,
	nl_desc blob,
	nl_main_page_id int unsigned NOT NULL,
	nl_active TINYINT(1) NOT NULL DEFAULT 1,
	nl_subscriber_count INTEGER NOT NULL DEFAULT 0
)/*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/nl_name ON /*_*/nl_newsletters (nl_name);
CREATE UNIQUE INDEX /*i*/nl_main_page_active ON /*_*/nl_newsletters (nl_main_page_id, nl_active);
CREATE INDEX /*i*/nl_active ON /*_*/nl_newsletters (nl_active);
CREATE INDEX /*i*/nl_active_subscriber_name ON /*_*/nl_newsletters (nl_active, nl_subscriber_count, nl_name);