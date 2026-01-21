-- Add ban management columns to users table
ALTER TABLE users 
ADD COLUMN ban_reason TEXT NULL AFTER status,
ADD COLUMN banned_at DATETIME NULL AFTER ban_reason,
ADD COLUMN ban_until DATETIME NULL AFTER banned_at;

-- Update existing users to have NULL values for these columns
UPDATE users SET ban_reason = NULL, banned_at = NULL, ban_until = NULL WHERE ban_reason IS NULL;
