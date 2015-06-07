-- Database schema for the Newsletter extension.

CREATE TABLE /*_*/nl_newsletters(
	--Primary key
	nl_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
	nl_name VARCHAR (50) NOT NULL,
	nl_desc VARCHAR (256),
	nl_main_page_id int NOT NULL,
	nl_frequency VARCHAR (10) NOT NULL,
	nl_publisher_id int NOT NULL
)/*$wgDBTableOptions*/;

CREATE TABLE /*_*/nl_issues(
	issue_id int unsigned NOT NULL,
	issue_page_id int NOT NULL,
	-- Foreign key referenced from nl_newsletters
	issue_newsletter_id int REFERENCES nl_newsletters(nl_id),
	issue_publisher_id int NOT NULL,
	-- Composite primary key
	PRIMARY KEY (issue_id, issue_newsletter_id)
)/*$wgDBTableOptions*/;