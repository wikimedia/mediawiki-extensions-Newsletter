-- Add a column for nl_newsletters.nl_active, used as a flag to indicate
-- whether a particular newsletter is active or not.
ALTER TABLE /*_*/nl_newsletters
  ADD COLUMN nl_active TINYINT(1) NOT NULL DEFAULT 1;
CREATE INDEX /*i*/nl_active ON /*_*/nl_newsletters (nl_active);
