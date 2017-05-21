-- Add a column nl_subscriber_count to enable better sorting
-- This is always a negative integer, for indexing reasons.
ALTER TABLE /*_*/nl_newsletters
  ADD COLUMN nl_subscriber_count INTEGER NOT NULL DEFAULT 0;
