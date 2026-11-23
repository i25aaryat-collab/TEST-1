-- Add reverse-lookup indexes for feature-table foreign keys.
-- These speed up joins and parent-row deletes as the newer tables grow.
CREATE INDEX IF NOT EXISTS idx_communities_created_by ON communities (created_by);
CREATE INDEX IF NOT EXISTS idx_community_members_user_id ON community_members (user_id);
CREATE INDEX IF NOT EXISTS idx_contact_shares_viewer_id ON contact_shares (viewer_id);
CREATE INDEX IF NOT EXISTS idx_flat_groups_created_by ON flat_groups (created_by);
CREATE INDEX IF NOT EXISTS idx_group_members_user_id ON group_members (user_id);
CREATE INDEX IF NOT EXISTS idx_pacts_accepted_by ON pacts (accepted_by);
CREATE INDEX IF NOT EXISTS idx_pacts_proposed_by ON pacts (proposed_by);
CREATE INDEX IF NOT EXISTS idx_phone_shares_viewer_id ON phone_shares (viewer_id);
CREATE INDEX IF NOT EXISTS idx_poll_votes_listing_id ON poll_votes (listing_id);
CREATE INDEX IF NOT EXISTS idx_poll_votes_user_id ON poll_votes (user_id);
CREATE INDEX IF NOT EXISTS idx_shortlist_added_by ON shortlist (added_by);
CREATE INDEX IF NOT EXISTS idx_shortlist_listing_id ON shortlist (listing_id);
