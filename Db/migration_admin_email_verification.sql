-- Upgrade for this repository's current schema, which already has email VARCHAR(255).
-- Resolve any duplicate non-NULL email values before adding the unique index.
ALTER TABLE users
    MODIFY COLUMN email VARCHAR(255) NULL,
    ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN verification_token_hash CHAR(64) NULL,
    ADD COLUMN verification_expires DATETIME NULL,
    ADD UNIQUE KEY uq_users_email (email);

-- Clients do not require email verification; login enforcement applies only to
-- admin and super_admin roles. Their email may remain NULL.
UPDATE users
SET email_verified = 0,
    verification_token_hash = NULL,
    verification_expires = NULL
WHERE role = 'client';

-- Before turning on the new login flow, assign each existing admin and
-- super_admin a unique email address they control and mark it verified once
-- you have confirmed it. Example (replace phone/email values per account):
-- UPDATE users SET email = 'admin@gmail.com', email_verified = 1
-- WHERE phone = '09171234567' AND role = 'admin';
-- UPDATE users SET email = 'superadmin@gmail.com', email_verified = 1
-- WHERE phone = '09170000001' AND role = 'super_admin';