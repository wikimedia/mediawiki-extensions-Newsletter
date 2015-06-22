-- Database schema for creating nl_newsletter table.

CREATE TABLE /*_*/nl_newsletters(
	--Primary key
	nl_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
	nl_name VARCHAR (50) NOT NULL UNIQUE,
	nl_desc VARCHAR (256),
	nl_main_page_id int NOT NULL,
	nl_frequency VARCHAR (50) NOT NULL,
	nl_publisher_id int NOT NULL
)/*$wgDBTableOptions*/;