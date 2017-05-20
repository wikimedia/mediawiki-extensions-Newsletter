-- CREATE (nl_active, nl_name) INDEX to help in sorting on Special:Newsletters
CREATE INDEX /*i*/nl_active_name ON /*_*/nl_newsletters (nl_active, nl_name);
