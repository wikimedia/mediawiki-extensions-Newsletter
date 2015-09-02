-- Database schema for creating nl_publishers table.

CREATE TABLE /*_*/nl_publishers(
	nlp_newsletter_id int unsigned NOT NULL,
	nlp_publisher_id int NOT NULL,
	PRIMARY KEY (nlp_publisher_id, nlp_newsletter_id)
)/*$wgDBTableOptions*/;

CREATE INDEX /*i*/nlp_nl_id ON /*_*/nl_publishers(nlp_newsletter_id);
