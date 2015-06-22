-- Database schema for creating nl_subscriptions table.

CREATE TABLE /*_*/nl_subscriptions(
	-- Foreign key referenced from nl_newsletters
	newsletter_id int REFERENCES nl_newsletters(nl_id),
	subscriber_id int NOT NULL,
	-- Composite primary key
	PRIMARY KEY (newsletter_id, subscriber_id)
)/*$wgDBTableOptions*/;