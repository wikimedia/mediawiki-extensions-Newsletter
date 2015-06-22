-- Database schema for creating nl_issues table.

CREATE TABLE /*_*/nl_issues(
	issue_id int unsigned NOT NULL,
	issue_page_id int NOT NULL,
	-- Foreign key referenced from nl_newsletters
	issue_newsletter_id int REFERENCES nl_newsletters(nl_id),
	issue_publisher_id int NOT NULL,
	-- Composite primary key
	PRIMARY KEY (issue_id, issue_newsletter_id)
)/*$wgDBTableOptions*/;