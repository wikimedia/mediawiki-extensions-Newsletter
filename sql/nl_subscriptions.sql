-- Database schema for creating nl_subscriptions table.

CREATE TABLE /*_*/nl_subscriptions(
	nls_newsletter_id int NOT NULL,
	nls_subscriber_id int NOT NULL,
	-- Composite primary key
	PRIMARY KEY (nls_newsletter_id, nls_subscriber_id)
)/*$wgDBTableOptions*/;

CREATE INDEX /*i*/nls_subscriber_id ON /*_*/nl_subscriptions(nls_subscriber_id);
