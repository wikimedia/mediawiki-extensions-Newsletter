-- Database schema for creating nl_issues table.

CREATE TABLE /*_*/nl_issues(
	nli_issue_id int unsigned NOT NULL,
	nli_page_id int NOT NULL,
	-- Foreign key referenced from nl_newsletters
	nli_newsletter_id int,
	nli_publisher_id int NOT NULL,
	-- Composite primary key
	PRIMARY KEY (nli_newsletter_id, nli_issue_id)
)/*$wgDBTableOptions*/;
