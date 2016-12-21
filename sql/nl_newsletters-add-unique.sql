-- Creates combined UNIQUE index in table nl_newsletters (nl_main_page_id, nl_active)
CREATE UNIQUE INDEX /*i*/nl_main_page_active ON /*_*/nl_newsletters (nl_main_page_id, nl_active);
