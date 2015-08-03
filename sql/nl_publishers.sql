-- Database schema for creating nl_publishers table.

CREATE TABLE /*_*/nl_publishers(
	--Primary key
	newsletter_id int REFERENCES nl_newsletter(nl_id),
	publisher_id int NOT NULL,
	PRIMARY KEY (newsletter_id, publisher_id)
)/*$wgDBTableOptions*/;
