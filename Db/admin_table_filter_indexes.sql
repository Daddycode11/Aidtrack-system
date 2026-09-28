-- Apply once on existing installations after backing up the database.
-- If an index with the same name already exists, omit that statement.

ALTER TABLE users
    ADD INDEX idx_users_role_created (role, created_at),
    ADD INDEX idx_users_created_at (created_at),
    ADD INDEX idx_users_barangay_created (barangay, created_at),
    ADD INDEX idx_users_verified_role (email_verified, role);

ALTER TABLE applications
    ADD INDEX idx_applications_status_created (status, created_at),
    ADD INDEX idx_applications_created_at (created_at),
    ADD INDEX idx_applications_type_created (type, created_at),
    ADD INDEX idx_applications_request_date (date_of_request),
    ADD INDEX idx_applications_amount_requested (amount_requested),
    ADD INDEX idx_applications_amount_released (amount_released),
    ADD INDEX idx_applications_user_created (user_id, created_at);

ALTER TABLE admin_actions
    ADD INDEX idx_admin_actions_app_action_date (application_id, action, created_at),
    ADD INDEX idx_admin_actions_admin_date (admin_id, created_at);

ALTER TABLE audit_logs
    ADD INDEX idx_audit_logs_created (created_at),
    ADD INDEX idx_audit_logs_user_created (user_id, created_at),
    ADD INDEX idx_audit_logs_action_created (action, created_at),
    ADD INDEX idx_audit_logs_ip (ip_address);

ALTER TABLE approval_requests
    ADD INDEX idx_approval_status_created (status, created_at),
    ADD INDEX idx_approval_created_at (created_at),
    ADD INDEX idx_approval_requester_status_created (requested_by, status, created_at),
    ADD INDEX idx_approval_type_created (request_type, created_at),
    ADD INDEX idx_approval_reference (reference_id);

ALTER TABLE budgets
    ADD INDEX idx_budgets_updated_at (updated_at);