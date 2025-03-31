-- This SQL script is used to seed the database with initial data for the Agape Youth Chama application.

-- Insert admin user
-- Default password is 'admin123' (hashed with password_hash)
INSERT INTO users (username, password, email, name, phone, user_role, status, registration_fee_paid, membership_status, created_at) 
VALUES ('admin', '$2y$10$8zf0SXXZxFLfSyG0xQUUUOXCTxq5RWzk.82LqSdafJ9XYrWQnA9.G', 'admin@agapeyouthchama.org', 'System Administrator', '+254712345678', 'admin', 'active', TRUE, 'active', CURRENT_TIMESTAMP);

-- Insert manager user
-- Default password is 'manager123' (hashed with password_hash)
INSERT INTO users (username, password, email, name, phone, user_role, status, registration_fee_paid, membership_status, created_at) 
VALUES ('manager', '$2y$10$1Yw1oBvWgI.4MxDLSYEECexxJzwPzWIpEP9DQWwAj.uXXiIGW2qs2', 'manager@agapeyouthchama.org', 'Group Manager', '+254723456789', 'manager', 'active', TRUE, 'active', CURRENT_TIMESTAMP);

-- Insert regular members
-- Default password is 'member123' (hashed with password_hash)
INSERT INTO users (username, password, email, name, phone, user_role, status, registration_fee_paid, membership_status, created_at) 
VALUES 
('john', '$2y$10$Nt1ESVXq.mOkbZiNBF7x3OgCZGcH0LZE3d.9UOt0jXpGd4TBsOLEm', 'john@example.com', 'John Doe', '+254734567890', 'member', 'active', TRUE, 'active', CURRENT_TIMESTAMP),
('jane', '$2y$10$Nt1ESVXq.mOkbZiNBF7x3OgCZGcH0LZE3d.9UOt0jXpGd4TBsOLEm', 'jane@example.com', 'Jane Smith', '+254745678901', 'member', 'active', TRUE, 'active', CURRENT_TIMESTAMP),
('peter', '$2y$10$Nt1ESVXq.mOkbZiNBF7x3OgCZGcH0LZE3d.9UOt0jXpGd4TBsOLEm', 'peter@example.com', 'Peter Kamau', '+254756789012', 'member', 'active', TRUE, 'active', CURRENT_TIMESTAMP),
('mary', '$2y$10$Nt1ESVXq.mOkbZiNBF7x3OgCZGcH0LZE3d.9UOt0jXpGd4TBsOLEm', 'mary@example.com', 'Mary Wanjiku', '+254767890123', 'member', 'active', TRUE, 'active', CURRENT_TIMESTAMP),
('david', '$2y$10$Nt1ESVXq.mOkbZiNBF7x3OgCZGcH0LZE3d.9UOt0jXpGd4TBsOLEm', 'david@example.com', 'David Ochieng', '+254778901234', 'member', 'active', TRUE, 'active', CURRENT_TIMESTAMP);

-- Initialize user accounts
INSERT INTO user_accounts (user_id, total_savings, total_investments, available_balance, created_at)
VALUES 
(1, 0.00, 0.00, 0.00, CURRENT_TIMESTAMP),
(2, 1000.00, 2000.00, 500.00, CURRENT_TIMESTAMP),
(3, 15000.00, 10000.00, 5000.00, CURRENT_TIMESTAMP),
(4, 12000.00, 5000.00, 7000.00, CURRENT_TIMESTAMP),
(5, 18000.00, 7000.00, 11000.00, CURRENT_TIMESTAMP),
(6, 8000.00, 3000.00, 5000.00, CURRENT_TIMESTAMP),
(7, 10000.00, 5000.00, 5000.00, CURRENT_TIMESTAMP);

-- Insert announcements
INSERT INTO announcements (title, content, importance, created_by, created_at)
VALUES
    ('Welcome to Agape Youth Group!', 'This is a welcome message.', 'high', 1, CURRENT_TIMESTAMP),
    ('Upcoming Event', 'There is an upcoming event.', 'medium', 1, CURRENT_TIMESTAMP),
    ('Reminder', 'This is a reminder.', 'low', 1, CURRENT_TIMESTAMP),
    ('New Announcement', 'This is a new announcement.', 'high', 1, CURRENT_TIMESTAMP),
    ('Another Announcement', 'This is another announcement.', 'medium', 1, CURRENT_TIMESTAMP),
    ('New Event', 'This is a new event.', 'high', 1, CURRENT_TIMESTAMP),
    ('New Project', 'This is a new project.', 'medium', 1, CURRENT_TIMESTAMP),
    ('New Investment', 'This is a new investment.', 'low', 1, CURRENT_TIMESTAMP);

-- Insert meetings
INSERT INTO meetings (title, description, date, time, created_at)
VALUES
    ('Meeting 1', 'This is a meeting.', '2023-03-01', '10:00:00', CURRENT_TIMESTAMP),
    ('Meeting 2', 'This is another meeting.', '2023-03-15', '14:00:00', CURRENT_TIMESTAMP);

-- Insert projects
INSERT INTO projects (title, description, created_at)
VALUES
    ('Project 1', 'This is project 1.', CURRENT_TIMESTAMP),
    ('Project 2', 'This is project 2.', CURRENT_TIMESTAMP),
    ('Project 3', 'This is project 3.', CURRENT_TIMESTAMP);

-- Insert contributions
INSERT INTO contributions (user_id, amount, contribution_date, created_at)
VALUES
    (3, 100.00, '2023-02-01', CURRENT_TIMESTAMP),
    (4, 200.00, '2023-02-15', CURRENT_TIMESTAMP);

-- Insert loans
INSERT INTO loans (user_id, amount, loan_date, status, created_at)
VALUES
    (3, 500.00, '2023-01-01', 'approved', CURRENT_TIMESTAMP),
    (4, 1000.00, '2023-01-15', 'approved', CURRENT_TIMESTAMP);

-- Insert project contributions
INSERT INTO project_contributions (project_id, user_id, amount, contribution_date, created_at)
VALUES
    (1, 3, 100.00, '2023-02-01', CURRENT_TIMESTAMP),
    (1, 4, 200.00, '2023-02-15', CURRENT_TIMESTAMP),
    (2, 3, 50.00, '2023-03-01', CURRENT_TIMESTAMP),
    (2, 4, 100.00, '2023-03-15', CURRENT_TIMESTAMP),
    (3, 3, 75.00, '2023-04-01', CURRENT_TIMESTAMP),
    (3, 4, 150.00, '2023-04-15', CURRENT_TIMESTAMP);

-- Insert project shares
INSERT INTO project_shares (project_id, user_id, shares, created_at)
VALUES
    (1, 3, 10, CURRENT_TIMESTAMP),
    (1, 4, 20, CURRENT_TIMESTAMP),
    (2, 3, 5, CURRENT_TIMESTAMP),
    (2, 4, 10, CURRENT_TIMESTAMP);

-- Insert investments
INSERT INTO investments (user_id, amount, investment_date, created_at)
VALUES
    (3, 1000.00, '2023-01-01', CURRENT_TIMESTAMP),
    (4, 2000.00, '2023-01-15', CURRENT_TIMESTAMP),
    (3, 500.00, '2023-02-01', CURRENT_TIMESTAMP),
    (4, 1000.00, '2023-02-15', CURRENT_TIMESTAMP),
    (3, 750.00, '2023-03-01', CURRENT_TIMESTAMP),
    (4, 1500.00, '2023-03-15', CURRENT_TIMESTAMP);

-- Insert investment contributions
INSERT INTO investment_contributions (investment_id, user_id, amount, contribution_date, created_at)
VALUES
    (1, 3, 100.00, '2023-02-01', CURRENT_TIMESTAMP),
    (1, 4, 200.00, '2023-02-15', CURRENT_TIMESTAMP),
    (2, 3, 50.00, '2023-03-01', CURRENT_TIMESTAMP),
    (2, 4, 100.00, '2023-03-15', CURRENT_TIMESTAMP);

-- Insert investment shares
INSERT INTO investment_shares (investment_id, user_id, shares, created_at)
VALUES
    (1, 3, 10, CURRENT_TIMESTAMP),
    (1, 4, 20, CURRENT_TIMESTAMP),
    (2, 3, 5, CURRENT_TIMESTAMP),
    (2, 4, 10, CURRENT_TIMESTAMP);

-- Insert dividends
INSERT INTO dividends (investment_id, amount, dividend_date, created_at)
VALUES
    (1, 50.00, '2023-02-01', CURRENT_TIMESTAMP),
    (2, 100.00, '2023-02-15', CURRENT_TIMESTAMP),
    (3, 25.00, '2023-03-01', CURRENT_TIMESTAMP),
    (4, 50.00, '2023-03-15', CURRENT_TIMESTAMP),
    (5, 37.50, '2023-04-01', CURRENT_TIMESTAMP),
    (6, 75.00, '2023-04-15', CURRENT_TIMESTAMP);

-- Insert welfare cases
INSERT INTO welfare_cases (user_id, case_description, created_at)
VALUES
    (3, 'This is a welfare case.', CURRENT_TIMESTAMP),
    (4, 'This is another welfare case.', CURRENT_TIMESTAMP),
    (3, 'This is another welfare case for user 3.', CURRENT_TIMESTAMP),
    (4, 'This is another welfare case for user 4.', CURRENT_TIMESTAMP);

-- Insert loan repayments
INSERT INTO loan_repayments (loan_id, amount, repayment_date, created_at)
VALUES
    (1, 100.00, '2023-02-01', CURRENT_TIMESTAMP),
    (1, 200.00, '2023-02-15', CURRENT_TIMESTAMP),
    (2, 50.00, '2023-03-01', CURRENT_TIMESTAMP),
    (2, 100.00, '2023-03-15', CURRENT_TIMESTAMP),
    (1, 75.00, '2023-04-01', CURRENT_TIMESTAMP),
    (2, 150.00, '2023-04-15', CURRENT_TIMESTAMP);

-- Insert meeting attendees
INSERT INTO meeting_attendees (meeting_id, user_id, attended, created_at)
VALUES
    (1, 3, TRUE, CURRENT_TIMESTAMP),
    (1, 4, TRUE, CURRENT_TIMESTAMP),
    (2, 3, TRUE, CURRENT_TIMESTAMP),
    (2, 4, TRUE, CURRENT_TIMESTAMP);

-- Seed data for additional tables

-- Insert settings
INSERT INTO settings (setting_key, setting_value, description, created_at)
VALUES
    ('registration_fee', '1000', 'Registration fee for new members', CURRENT_TIMESTAMP),
    ('loan_interest_rate', '10', 'Interest rate for loans (percentage)', CURRENT_TIMESTAMP),
    ('monthly_contribution', '500', 'Required monthly contribution amount', CURRENT_TIMESTAMP),
    ('meeting_frequency', 'monthly', 'Frequency of regular meetings', CURRENT_TIMESTAMP),
    ('welfare_contribution', '200', 'Standard welfare contribution amount', CURRENT_TIMESTAMP);

-- Insert sample transactions
INSERT INTO transactions (user_id, transaction_type, amount, description, reference_id, reference_type, transaction_date, created_at)
VALUES
    (3, 'contribution', 100.00, 'Monthly contribution', 1, 'contributions', '2023-02-01', CURRENT_TIMESTAMP),
    (4, 'contribution', 200.00, 'Monthly contribution', 2, 'contributions', '2023-02-15', CURRENT_TIMESTAMP),
    (3, 'loan', 500.00, 'Loan disbursement', 1, 'loans', '2023-01-01', CURRENT_TIMESTAMP),
    (4, 'loan', 1000.00, 'Loan disbursement', 2, 'loans', '2023-01-15', CURRENT_TIMESTAMP),
    (3, 'loan_repayment', 100.00, 'Loan repayment', 1, 'loan_repayments', '2023-02-01', CURRENT_TIMESTAMP),
    (3, 'investment', 1000.00, 'Investment contribution', 1, 'investments', '2023-01-01', CURRENT_TIMESTAMP);

-- Insert sample notifications
INSERT INTO notifications (user_id, title, message, notification_type, created_at)
VALUES
    (3, 'Welcome to Agape Youth Chama', 'Thank you for joining our community!', 'welcome', CURRENT_TIMESTAMP),
    (3, 'Contribution Reminder', 'Your monthly contribution is due in 3 days', 'reminder', CURRENT_TIMESTAMP),
    (4, 'Welcome to Agape Youth Chama', 'Thank you for joining our community!', 'welcome', CURRENT_TIMESTAMP),
    (4, 'Loan Approved', 'Your loan request has been approved', 'loan', CURRENT_TIMESTAMP);

-- Insert sample welfare contributions
INSERT INTO welfare_contributions (welfare_case_id, user_id, amount, contribution_date, notes, created_at)
VALUES
    (1, 3, 200.00, '2023-02-05', 'Contribution for welfare case', CURRENT_TIMESTAMP),
    (1, 4, 300.00, '2023-02-06', 'Contribution for welfare case', CURRENT_TIMESTAMP),
    (2, 3, 150.00, '2023-03-10', 'Contribution for welfare case', CURRENT_TIMESTAMP),
    (2, 4, 250.00, '2023-03-11', 'Contribution for welfare case', CURRENT_TIMESTAMP);

-- Insert sample messages
INSERT INTO messages (sender_id, recipient_id, subject, message, created_at)
VALUES
    (1, 3, 'Welcome Message', 'Welcome to Agape Youth Chama. We are glad to have you as a member.', CURRENT_TIMESTAMP),
    (1, 4, 'Welcome Message', 'Welcome to Agape Youth Chama. We are glad to have you as a member.', CURRENT_TIMESTAMP),
    (3, 4, 'Meeting Question', 'What time is the next meeting?', CURRENT_TIMESTAMP),
    (4, 3, 'RE: Meeting Question', 'The next meeting is at 10 AM on Saturday.', CURRENT_TIMESTAMP);

-- Insert sample documents
INSERT INTO documents (title, description, file_path, file_type, file_size, uploaded_by, created_at)
VALUES
    ('Constitution', 'Group constitution document', '/documents/constitution.pdf', 'pdf', 1024000, 1, CURRENT_TIMESTAMP),
    ('Meeting Minutes', 'Minutes for February meeting', '/documents/minutes_feb.pdf', 'pdf', 512000, 1, CURRENT_TIMESTAMP),
    ('Loan Agreement', 'Standard loan agreement template', '/documents/loan_agreement.docx', 'docx', 256000, 1, CURRENT_TIMESTAMP);

-- Insert sample audit logs
INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip_address, created_at)
VALUES
    (1, 'create', 'user', 3, '{"name": "John Doe", "email": "john@example.com"}', '192.168.1.1', CURRENT_TIMESTAMP),
    (1, 'create', 'user', 4, '{"name": "Jane Smith", "email": "jane@example.com"}', '192.168.1.1', CURRENT_TIMESTAMP),
    (1, 'create', 'loan', 1, '{"user_id": 3, "amount": 500.00}', '192.168.1.1', CURRENT_TIMESTAMP),
    (1, 'update', 'loan', 1, '{"status": "approved"}', '192.168.1.1', CURRENT_TIMESTAMP);