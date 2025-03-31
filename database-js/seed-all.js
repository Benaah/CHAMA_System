const db = require('./db');
const bcrypt = require('bcrypt');

async function seedAll() {
  try {
    console.log('Starting database seeding...');
    
    // Create initial emergency fund and welfare fund records
    await db.query('INSERT INTO emergency_fund (total_balance, created_at) VALUES (0, NOW())');
    await db.query('INSERT INTO welfare_fund (total_balance, created_at) VALUES (0, NOW())');
    
    // Insert default settings
    await db.query(`
      INSERT INTO settings (setting_key, setting_value, setting_description) VALUES
      ('min_contribution', '1000', 'Minimum monthly contribution amount'),
      ('loan_interest_rate', '10', 'Loan interest rate (%)'),
      ('max_loan_multiplier', '3', 'Maximum loan amount as a multiplier of savings'),
      ('withdrawal_fee_percentage', '2', 'Withdrawal fee as a percentage of withdrawal amount'),
      ('registration_fee', '500', 'One-time registration fee for new members'),
      ('annual_membership_fee', '1000', 'Annual membership renewal fee'),
      ('mpesa_shortcode', '123456', 'M-Pesa paybill number for payments'),
      ('mpesa_b2c_shortcode', '123456', 'M-Pesa shortcode for disbursements'),
      ('organization_name', 'Agape Youth Group', 'Name of the organization'),
      ('organization_email', 'info@agapeyouthchama.org', 'Official email address'),
      ('organization_phone', '+254712345678', 'Official phone number'),
      ('organization_address', 'Luanda, Vihiga, Kenya', 'Physical address')
    `);
    
    // Seed users
    await seedUsers();
    
    // Seed announcements
    await seedAnnouncements();
    
    // Seed meetings
    await seedMeetings();
    
    // Seed investments
    await seedInvestments();
    
    // Seed projects
    await seedProjects();
    
    // Seed contributions
    await seedContributions();
    
    // Seed loans
    await seedLoans();
    
    // Seed investment contributions
    await seedInvestmentContributions();
    
    // Seed project contributions
    await seedProjectContributions();
    
    // Seed emergency fund contributions
    await seedEmergencyFundContributions();
    
    // Seed welfare contributions
    await seedWelfareContributions();
    
    // Seed events
    await seedEvents();
    
    // Seed contact messages
    await seedContactMessages();
    
    // Seed notifications
    await seedNotifications();
    
    // Seed activity logs
    await seedActivityLogs();
    
    // Seed polls
    await seedPolls();
    
    // Seed transactions
    await seedTransactions();
    
    // Seed meeting attendees
    await seedMeetingAttendees();
    
    // Seed event attendees
    await seedEventAttendees();
    
    // Seed contributions schedule
    await seedContributionsSchedule();
    
    // Seed penalties
    await seedPenalties();
    
    // Update various totals
    await updateTotals();
    
    console.log('Database seeding completed successfully!');
    process.exit(0);
  } catch (error) {
    console.error('Error seeding database:', error);
    process.exit(1);
  }
}

async function seedUsers() {
  console.log('Seeding users...');
  
  // Password hash for all users (password: admin123, treasurer123, etc.)
  const passwordHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
  
  // Create admin user
  await db.query(`
    INSERT INTO users (full_name, username, email, phone, password, role, status, registration_fee_paid, registration_date, membership_status, created_at)
    VALUES ('Admin User', 'admin', 'admin@agapeyouthgroup.com', '+254712345678', $1, 'admin', 'active', TRUE, NOW(), 'active', NOW())
  `, [passwordHash]);
  
  // Create treasurer user
  await db.query(`
    INSERT INTO users (full_name, username, email, phone, password, role, status, registration_fee_paid, registration_date, membership_status, created_at)
    VALUES ('Treasurer User', 'treasurer', 'treasurer@agapeyouthgroup.com', '+254723456789', $1, 'treasurer', 'active', TRUE, NOW(), 'active', NOW())
  `, [passwordHash]);
  
  // Create secretary user
  await db.query(`
    INSERT INTO users (full_name, username, email, phone, password, role, status, registration_fee_paid, registration_date, membership_status, created_at)
    VALUES ('Secretary User', 'secretary', 'secretary@agapeyouthgroup.com', '+254734567890', $1, 'secretary', 'active', TRUE, NOW(), 'active', NOW())
  `, [passwordHash]);
  
  // Create regular members
  await db.query(`
    INSERT INTO users (full_name, username, email, phone, password, role, status, registration_fee_paid, registration_date, membership_status, created_at)
    VALUES 
    ('John Doe', 'johndoe', 'john@example.com', '+254745678901', $1, 'member', 'active', TRUE, NOW(), 'active', NOW()),
    ('Jane Smith', 'janesmith', 'jane@example.com', '+254756789012', $1, 'member', 'active', TRUE, NOW(), 'active', NOW()),
    ('Michael Johnson', 'michael', 'michael@example.com', '+254767890123', $1, 'member', 'active', TRUE, NOW(), 'active', NOW()),
    ('Sarah Williams', 'sarah', 'sarah@example.com', '+254778901234', $1, 'member', 'active', TRUE, NOW(), 'active', NOW()),
    ('David Brown', 'david', 'david@example.com', '+254789012345', $1, 'member', 'active', TRUE, NOW(), 'active', NOW())
  `, [passwordHash]);
  
  // Create user accounts for all users
  await db.query(`
    INSERT INTO user_accounts (user_id, total_savings, total_investments, available_balance, created_at)
    SELECT id, 0, 0, 0, NOW() FROM users
  `);
}

async function seedAnnouncements() {
  console.log('Seeding announcements...');
  
  await db.query(`
    INSERT INTO announcements (title, content, importance, created_by, created_at)
    VALUES 
    ('Welcome to Agape Youth Group', 'We are excited to have you join our community. Please complete your profile and make your first contribution.', 'normal', 1, NOW()),
    ('Upcoming Annual General Meeting', 'Our AGM will be held on June 15th, 2023. All members are required to attend.', 'high', 1, NOW()),
    ('New Loan Products Available', 'We have introduced new loan products with better interest rates. Check them out in the loans section.', 'normal', 1, NOW())
  `);
}

async function seedMeetings() {
  console.log('Seeding meetings...');
  
  await db.query(`
    INSERT INTO meetings (title, description, meeting_date, meeting_time, location, status, created_by, created_at)
    VALUES 
    ('Monthly Members Meeting', 'Regular monthly meeting to discuss group progress and plans.', CURRENT_DATE + INTERVAL '10 days', '10:00:00', 'Luanda Community Hall', 'scheduled', 1, NOW()),
    ('Investment Committee Meeting', 'Meeting to review and approve new investment opportunities.', CURRENT_DATE + INTERVAL '15 days', '14:00:00', 'Virtual Meeting (Zoom)', 'scheduled', 1, NOW()),
    ('Financial Literacy Workshop', 'Workshop on personal financial management and investment strategies.', CURRENT_DATE + INTERVAL '20 days', '09:00:00', 'Luanda Youth Center', 'scheduled', 1, NOW())
  `);
}

async function seedInvestments() {
  console.log('Seeding investments...');
  
  await db.query(`
    INSERT INTO investments (title, description, investment_type, amount_target, total_contributed, start_date, end_date, status, created_by, created_at)
    VALUES 
    ('Land Purchase - Luanda Township', 'Investment in a prime plot in Luanda Township for future development or resale.', 'land', 500000, 150000, CURRENT_DATE - INTERVAL '30 days', CURRENT_DATE + INTERVAL '150 days', 'active', 1, NOW()),
    ('Poultry Farming Project', 'Investment in a group-owned poultry farm with egg and meat production.', 'business', 200000, 75000, CURRENT_DATE - INTERVAL '60 days', CURRENT_DATE + INTERVAL '90 days', 'active', 1, NOW()),
    ('Treasury Bonds Investment', 'Investment in government treasury bonds with fixed returns.', 'securities', 300000, 100000, CURRENT_DATE - INTERVAL '45 days', CURRENT_DATE + INTERVAL '120 days', 'active', 1, NOW())
  `);
}

async function seedProjects() {
  console.log('Seeding projects...');
  
  await db.query(`
    INSERT INTO projects (title, description, funding_goal, total_raised, start_date, end_date, status, created_by, created_at)
    VALUES 
    ('Youth Center Renovation', 'Renovation of the local youth center to create a better space for community activities.', 150000, 50000, CURRENT_DATE - INTERVAL '20 days', CURRENT_DATE + INTERVAL '100 days', 'active', 1, NOW()),
    ('Community Library', 'Establishing a community library with educational resources for local students.', 200000, 60000, CURRENT_DATE - INTERVAL '40 days', CURRENT_DATE + INTERVAL '80 days', 'active', 1, NOW())
  `);
}

async function seedContributions() {
  console.log('Seeding contributions...');
  
  await db.query(`
    INSERT INTO contributions (user_id, amount, payment_method, contribution_date, status, approved_by, approval_date, created_at)
    VALUES 
    (4, 5000, 'mpesa', CURRENT_DATE - INTERVAL '25 days', 'approved', 1, CURRENT_DATE - INTERVAL '24 days', NOW()),
    (4, 5000, 'mpesa', CURRENT_DATE - INTERVAL '15 days', 'approved', 1, CURRENT_DATE - INTERVAL '14 days', NOW()),
    (5, 3000, 'bank_transfer', CURRENT_DATE - INTERVAL '20 days', 'approved', 1, CURRENT_DATE - INTERVAL '19 days', NOW()),
    (5, 3000, 'bank_transfer', CURRENT_DATE - INTERVAL '10 days', 'approved', 1, CURRENT_DATE - INTERVAL '9 days', NOW()),
    (6, 2000, 'cash', CURRENT_DATE - INTERVAL '18 days', 'approved', 1, CURRENT_DATE - INTERVAL '17 days', NOW()),
    (6, 2000, 'cash', CURRENT_DATE - INTERVAL '8 days', 'approved', 1, CURRENT_DATE - INTERVAL '7 days', NOW()),
    (7, 4000, 'mpesa', CURRENT_DATE - INTERVAL '22 days', 'approved', 1, CURRENT_DATE - INTERVAL '21 days', NOW()),
    (7, 4000, 'mpesa', CURRENT_DATE - INTERVAL '12 days', 'approved', 1, CURRENT_DATE - INTERVAL '11 days', NOW()),
    (8, 3500, 'bank_transfer', CURRENT_DATE - INTERVAL '19 days', 'approved', 1, CURRENT_DATE - INTERVAL '18 days', NOW()),
    (8, 3500, 'bank_transfer', CURRENT_DATE - INTERVAL '9 days', 'approved', 1, CURRENT_DATE - INTERVAL '8 days', NOW())
  `);
  
  // Update user account balances based on contributions
  await db.query(`
    UPDATE user_accounts 
    SET total_savings = (
        SELECT SUM(amount) 
        FROM contributions 
        WHERE user_id = user_accounts.user_id AND status = 'approved'
    ),
    available_balance = (
        SELECT SUM(amount) 
        FROM contributions 
        WHERE user_id = user_accounts.user_id AND status = 'approved'
    ),
    last_contribution_date = (
        SELECT MAX(contribution_date) 
        FROM contributions 
        WHERE user_id = user_accounts.user_id AND status = 'approved'
    )
    WHERE EXISTS (
        SELECT 1 
        FROM contributions 
        WHERE user_id = user_accounts.user_id AND status = 'approved'
    )
  `);
}

async function seedLoans() {
  console.log('Seeding loans...');
  
  await db.query(`
    INSERT INTO loans (user_id, amount, interest_rate, loan_term, purpose, balance, status, approved_by, approval_date, disbursed, disbursement_date, due_date, application_date, created_at)
    VALUES 
    (4, 10000, 10.0, 6, 'Business expansion', 10000, 'approved', 2, CURRENT_DATE - INTERVAL '20 days', TRUE, CURRENT_DATE - INTERVAL '19 days', CURRENT_DATE + INTERVAL '160 days', CURRENT_DATE - INTERVAL '22 days', NOW()),
    (5, 8000, 10.0, 4, 'Education fees', 8000, 'approved', 2, CURRENT_DATE - INTERVAL '15 days', TRUE, CURRENT_DATE - INTERVAL '14 days', CURRENT_DATE + INTERVAL '105 days', CURRENT_DATE - INTERVAL '17 days', NOW()),
    (7, 5000, 10.0, 3, 'Medical expenses', 5000, 'approved', 2, CURRENT_DATE - INTERVAL '10 days', TRUE, CURRENT_DATE - INTERVAL '9 days', CURRENT_DATE + INTERVAL '80 days', CURRENT_DATE - INTERVAL '12 days', NOW())
  `);
  
  // Create sample loan repayments
  await db.query(`
    INSERT INTO loan_repayments (loan_id, user_id, amount, payment_method, repayment_date, created_at)
    VALUES 
    (1, 4, 2000, 'mpesa', CURRENT_DATE - INTERVAL '10 days', NOW()),
    (2, 5, 2500, 'bank_transfer', CURRENT_DATE - INTERVAL '5 days', NOW())
  `);
  
  // Update loan balances based on repayments
  await db.query(`
    UPDATE loans 
    SET balance = amount - (
        SELECT COALESCE(SUM(amount), 0) 
        FROM loan_repayments 
        WHERE loan_id = loans.id
    ),
    last_payment_date = (
        SELECT MAX(repayment_date) 
        FROM loan_repayments 
        WHERE loan_id = loans.id
    )
    WHERE EXISTS (
        SELECT 1 
        FROM loan_repayments 
        WHERE loan_id = loans.id
    )
  `);
}

async function seedInvestmentContributions() {
  console.log('Seeding investment contributions...');
  
  await db.query(`
    INSERT INTO investment_contributions (user_id, investment_id, amount, payment_method, contribution_date, created_at)
    VALUES 
    (4, 1, 50000, 'bank_transfer', CURRENT_DATE - INTERVAL '25 days', NOW()),
    (5, 1, 30000, 'mpesa', CURRENT_DATE - INTERVAL '20 days', NOW()),
    (6, 1, 20000, 'bank_transfer', CURRENT_DATE - INTERVAL '15 days', NOW()),
    (7, 2, 25000, 'mpesa', CURRENT_DATE - INTERVAL '22 days', NOW()),
    (8, 2, 20000, 'bank_transfer', CURRENT_DATE - INTERVAL '18 days', NOW()),
    (4, 3, 30000, 'mpesa', CURRENT_DATE - INTERVAL '30 days', NOW()),
    (5, 3, 25000, 'bank_transfer', CURRENT_DATE - INTERVAL '28 days', NOW()),
    (8, 3, 20000, 'mpesa', CURRENT_DATE - INTERVAL '25 days', NOW())
  `);
  
  // Create investment shares
  await db.query(`
    INSERT INTO investment_shares (investment_id, user_id, amount, contribution_date, created_at)
    VALUES 
    (1, 4, 50000, CURRENT_DATE - INTERVAL '25 days', NOW()),
    (1, 5, 30000, CURRENT_DATE - INTERVAL '20 days', NOW()),
    (1, 6, 20000, CURRENT_DATE - INTERVAL '15 days', NOW()),
    (2, 7, 25000, CURRENT_DATE - INTERVAL '22 days', NOW()),
    (2, 8, 20000, CURRENT_DATE - INTERVAL '18 days', NOW()),
    (3, 4, 30000, CURRENT_DATE - INTERVAL '30 days', NOW()),
    (3, 5, 25000, CURRENT_DATE - INTERVAL '28 days', NOW()),
    (3, 8, 20000, CURRENT_DATE - INTERVAL '25 days', NOW())
  `);
}

async function seedProjectContributions() {
  console.log('Seeding project contributions...');
  
  await db.query(`
    INSERT INTO project_contributions (project_id, user_id, amount, payment_method, contribution_date, created_at)
    VALUES 
    (1, 4, 15000, 'mpesa', CURRENT_DATE - INTERVAL '18 days', NOW()),
    (1, 5, 10000, 'bank_transfer', CURRENT_DATE - INTERVAL '15 days', NOW()),
    (1, 6, 8000, 'mpesa', CURRENT_DATE - INTERVAL '12 days', NOW()),
    (2, 7, 12000, 'bank_transfer', CURRENT_DATE - INTERVAL '20 days', NOW()),
    (2, 8, 15000, 'mpesa', CURRENT_DATE - INTERVAL '16 days', NOW())
  `);
  
  // Create project shares
  await db.query(`
    INSERT INTO project_shares (project_id, user_id, amount, contribution_date, created_at)
    VALUES 
    (1, 4, 15000, CURRENT_DATE - INTERVAL '18 days', NOW()),
    (1, 5, 10000, CURRENT_DATE - INTERVAL '15 days', NOW()),
    (1, 6, 8000, CURRENT_DATE - INTERVAL '12 days', NOW()),
    (2, 7, 12000, CURRENT_DATE - INTERVAL '20 days', NOW()),
    (2, 8, 15000, CURRENT_DATE - INTERVAL '16 days', NOW())
  `);
}

async function seedEmergencyFundContributions() {
  console.log('Seeding emergency fund contributions...');
  
  await db.query(`
    INSERT INTO emergency_fund_contributions (user_id, amount, payment_method, contribution_date, created_at)
    VALUES 
    (4, 2000, 'mpesa', CURRENT_DATE - INTERVAL '30 days', NOW()),
    (5, 1500, 'bank_transfer', CURRENT_DATE - INTERVAL '28 days', NOW()),
    (6, 1000, 'cash', CURRENT_DATE - INTERVAL '25 days', NOW()),
    (7, 2000, 'mpesa', CURRENT_DATE - INTERVAL '20 days', NOW()),
    (8, 1500, 'bank_transfer', CURRENT_DATE - INTERVAL '15 days', NOW())
  `);
  
  // Update emergency fund balance
  await db.query(`
    UPDATE emergency_fund 
    SET total_balance = (
        SELECT SUM(amount) 
        FROM emergency_fund_contributions
    ),
    last_contribution_date = (
        SELECT MAX(contribution_date) 
        FROM emergency_fund_contributions
    )
  `);
}

async function seedWelfareContributions() {
  console.log('Seeding welfare contributions...');
  
  await db.query(`
    INSERT INTO welfare_contributions (user_id, amount, payment_method, contribution_date, created_at)
    VALUES 
    (4, 1000, 'mpesa', CURRENT_DATE - INTERVAL '30 days', NOW()),
    (5, 1000, 'bank_transfer', CURRENT_DATE - INTERVAL '28 days', NOW()),
    (6, 1000, 'cash', CURRENT_DATE - INTERVAL '25 days', NOW()),
    (7, 1000, 'mpesa', CURRENT_DATE - INTERVAL '20 days', NOW()),
    (8, 1000, 'bank_transfer', CURRENT_DATE - INTERVAL '15 days', NOW())
  `);
  
  // Update welfare fund balance
  await db.query(`
    UPDATE welfare_fund 
    SET total_balance = (
        SELECT SUM(amount) 
        FROM welfare_contributions
    ),
    last_contribution_date = (
        SELECT MAX(contribution_date) 
        FROM welfare_contributions
    )
  `);
}

async function seedEvents() {
  console.log('Seeding events...');
  
  await db.query(`
    INSERT INTO events (title, description, event_date, event_time, location, fee, registration_deadline, max_attendees, status, created_by, created_at)
    VALUES 
    ('Financial Literacy Workshop', 'Learn essential financial management skills from industry experts.', CURRENT_DATE + INTERVAL '30 days', '09:00:00', 'Luanda Community Hall', 200, CURRENT_DATE + INTERVAL '25 days', 50, 'upcoming', 1, NOW()),
    ('Annual General Meeting', 'Annual meeting to review our progress and plan for the future.', CURRENT_DATE + INTERVAL '45 days', '10:00:00', 'Luanda Youth Center', 0, CURRENT_DATE + INTERVAL '40 days', 100, 'upcoming', 1, NOW()),
    ('Entrepreneurship Seminar', 'Learn how to start and grow your own business.', CURRENT_DATE + INTERVAL '60 days', '14:00:00', 'Vihiga County Hall', 500, CURRENT_DATE + INTERVAL '55 days', 40, 'upcoming', 1, NOW())
  `);
}

async function seedContactMessages() {
  console.log('Seeding contact messages...');
  
  await db.query(`
    INSERT INTO contact_messages (name, email, phone, subject, message, created_at)
    VALUES 
    ('James Mwangi', 'james@example.com', '+254712345678', 'Membership Inquiry', 'I would like to know more about how to join your group and the benefits of membership.', NOW()),
    ('Mary Wanjiku', 'mary@example.com', '+254723456789', 'Investment Opportunities', 'I am interested in learning more about the investment opportunities available through your group.', NOW()),
    ('Peter Omondi', 'peter@example.com', '+254734567890', 'Loan Application Process', 'Could you please provide more information about the loan application process and requirements?', NOW())
  `);
}

async function seedNotifications() {
  console.log('Seeding notifications...');
  
  await db.query(`
    INSERT INTO notifications (user_id, title, message, type, created_at)
    VALUES 
    (4, 'Welcome to Agape Youth Group', 'Thank you for joining our community. Complete your profile to get started.', 'general', NOW()),
    (4, 'Contribution Received', 'Your contribution of KES 5,000 has been received and processed.', 'payment', NOW() - INTERVAL '1 day'),
    (5, 'Loan Approved', 'Your loan application for KES 8,000 has been approved.', 'loan', NOW() - INTERVAL '2 days'),
    (6, 'Upcoming Meeting', 'Don\'t forget about our monthly meeting this Saturday at 10:00 AM.', 'meeting', NOW() - INTERVAL '3 days'),
    (7, 'Investment Update', 'The Poultry Farming Project has reached 50% of its funding goal.', 'investment', NOW() - INTERVAL '4 days'),
    (8, 'Contribution Reminder', 'Your monthly contribution is due in 5 days.', 'reminder', NOW() - INTERVAL '5 days')
  `);
}

async function seedActivityLogs() {
  console.log('Seeding activity logs...');
  
  await db.query(`
    INSERT INTO activity_logs (user_id, action, description, created_at)
    VALUES 
    (1, 'user_login', 'Admin user logged in', NOW() - INTERVAL '1 hour'),
    (2, 'loan_approval', 'Approved loan #2 for user #5', NOW() - INTERVAL '2 hours'),
    (3, 'meeting_created', 'Created new meeting: Monthly Members Meeting', NOW() - INTERVAL '3 hours'),
    (4, 'contribution_made', 'Made a contribution of KES 5,000', NOW() - INTERVAL '4 hours'),
    (5, 'loan_application', 'Applied for a loan of KES 8,000', NOW() - INTERVAL '5 hours')
  `);
}

async function seedPolls() {
  console.log('Seeding polls...');
  
  await db.query(`
    INSERT INTO polls (title, description, start_date, end_date, status, created_by, created_at)
    VALUES 
    ('Investment Direction', 'Help us decide which investment opportunity to pursue next.', CURRENT_DATE, CURRENT_DATE + INTERVAL '14 days', 'active', 1, NOW()),
    ('Meeting Schedule', 'What is the most convenient day for our monthly meetings?', CURRENT_DATE - INTERVAL '10 days', CURRENT_DATE + INTERVAL '4 days', 'active', 1, NOW())
  `);
  
  // Create poll options
  await db.query(`
    INSERT INTO poll_options (poll_id, option_text, created_at)
    VALUES 
    (1, 'Real Estate Investment', NOW()),
    (1, 'Agricultural Project', NOW()),
    (1, 'Small Business Funding', NOW()),
    (1, 'Government Securities', NOW()),
    (2, 'Saturday Morning', NOW()),
    (2, 'Saturday Afternoon', NOW()),
    (2, 'Sunday Afternoon', NOW()),
    (2, 'Friday Evening', NOW())
  `);
  
  // Create sample poll votes
  await db.query(`
    INSERT INTO poll_votes (poll_id, user_id, option_id, vote_date, created_at)
    VALUES 
    (1, 4, 1, NOW() - INTERVAL '2 days', NOW()),
    (1, 5, 3, NOW() - INTERVAL '3 days', NOW()),
    (1, 6, 2, NOW() - INTERVAL '1 day', NOW()),
    (2, 4, 5, NOW() - INTERVAL '8 days', NOW()),
    (2, 5, 7, NOW() - INTERVAL '7 days', NOW()),
    (2, 6, 5, NOW() - INTERVAL '9 days', NOW()),
    (2, 7, 6, NOW() - INTERVAL '6 days', NOW())
  `);
}

async function seedTransactions() {
  console.log('Seeding transactions...');
  
  await db.query(`
    INSERT INTO transactions (user_id, amount, phone, transaction_type, description, status, mpesa_receipt, transaction_date, created_at)
    VALUES 
    (4, 5000, '+254745678901', 'contribution', 'Monthly contribution', 'completed', 'PXC123456789', CURRENT_DATE - INTERVAL '25 days', NOW()),
    (4, 5000, '+254745678901', 'contribution', 'Monthly contribution', 'completed', 'PXC234567890', CURRENT_DATE - INTERVAL '15 days', NOW()),
    (5, 3000, '+254756789012', 'contribution', 'Monthly contribution', 'completed', 'PXC345678901', CURRENT_DATE - INTERVAL '20 days', NOW()),
    (5, 3000, '+254756789012', 'contribution', 'Monthly contribution', 'completed', 'PXC456789012', CURRENT_DATE - INTERVAL '10 days', NOW()),
    (6, 2000, '+254767890123', 'contribution', 'Monthly contribution', 'completed', 'PXC567890123', CURRENT_DATE - INTERVAL '18 days', NOW()),
    (6, 2000, '+254767890123', 'contribution', 'Monthly contribution', 'completed', 'PXC678901234', CURRENT_DATE - INTERVAL '8 days', NOW()),
    (7, 4000, '+254778901234', 'contribution', 'Monthly contribution', 'completed', 'PXC789012345', CURRENT_DATE - INTERVAL '22 days', NOW()),
    (7, 4000, '+254778901234', 'contribution', 'Monthly contribution', 'completed', 'PXC890123456', CURRENT_DATE - INTERVAL '12 days', NOW()),
    (8, 3500, '+254789012345', 'contribution', 'Monthly contribution', 'completed', 'PXC901234567', CURRENT_DATE - INTERVAL '19 days', NOW()),
    (8, 3500, '+254789012345', 'contribution', 'Monthly contribution', 'completed', 'PXC012345678', CURRENT_DATE - INTERVAL '9 days', NOW()),
    (4, 2000, '+254745678901', 'loan_repayment', 'Loan repayment', 'completed', 'PXC123456780', CURRENT_DATE - INTERVAL '10 days', NOW()),
    (5, 2500, '+254756789012', 'loan_repayment', 'Loan repayment', 'completed', 'PXC234567801', CURRENT_DATE - INTERVAL '5 days', NOW())
  `);
}

async function seedMeetingAttendees() {
  console.log('Seeding meeting attendees...');
  
  await db.query(`
    INSERT INTO meeting_attendees (meeting_id, user_id, rsvp_status, created_at)
    VALUES 
    (1, 4, 'attending', NOW()),
    (1, 5, 'attending', NOW()),
    (1, 6, 'attending', NOW()),
    (1, 7, 'pending', NOW()),
    (1, 8, 'not_attending', NOW()),
    (2, 4, 'attending', NOW()),
    (2, 5, 'pending', NOW()),
    (2, 6, 'not_attending', NOW()),
    (2, 7, 'attending', NOW()),
    (2, 8, 'attending', NOW()),
    (3, 4, 'attending', NOW()),
    (3, 5, 'attending', NOW()),
    (3, 6, 'attending', NOW()),
    (3, 7, 'attending', NOW()),
    (3, 8, 'attending', NOW())
  `);
}

async function seedEventAttendees() {
  console.log('Seeding event attendees...');
  
  await db.query(`
    INSERT INTO event_attendees (event_id, user_id, registration_date, payment_status, created_at)
    VALUES 
    (1, 4, NOW() - INTERVAL '5 days', 'paid', NOW()),
    (1, 5, NOW() - INTERVAL '4 days', 'paid', NOW()),
    (1, 6, NOW() - INTERVAL '3 days', 'pending', NOW()),
    (2, 4, NOW() - INTERVAL '2 days', 'paid', NOW()),
    (2, 5, NOW() - INTERVAL '2 days', 'paid', NOW()),
    (2, 6, NOW() - INTERVAL '1 day', 'paid', NOW()),
    (2, 7, NOW() - INTERVAL '1 day', 'paid', NOW()),
    (2, 8, NOW(), 'pending', NOW())
  `);
  
  // Create sample event payments
  await db.query(`
    INSERT INTO event_payments (event_id, user_id, amount, payment_method, payment_date, created_at)
    VALUES 
    (1, 4, 200, 'mpesa', NOW() - INTERVAL '5 days', NOW()),
    (1, 5, 200, 'mpesa', NOW() - INTERVAL '4 days', NOW()),
    (2, 4, 0, 'waived', NOW() - INTERVAL '2 days', NOW()),
    (2, 5, 0, 'waived', NOW() - INTERVAL '2 days', NOW()),
    (2, 6, 0, 'waived', NOW() - INTERVAL '1 day', NOW()),
    (2, 7, 0, 'waived', NOW() - INTERVAL '1 day', NOW())
  `);
}

async function seedContributionsSchedule() {
  console.log('Seeding contributions schedule...');
  
  await db.query(`
    INSERT INTO contributions_schedule (user_id, deadline, amount, penalty, paid_status, created_at)
    VALUES 
    (4, CURRENT_DATE + INTERVAL '5 days', 5000, 500, 'pending', NOW()),
    (5, CURRENT_DATE + INTERVAL '5 days', 3000, 300, 'pending', NOW()),
    (6, CURRENT_DATE + INTERVAL '5 days', 2000, 200, 'pending', NOW()),
    (7, CURRENT_DATE + INTERVAL '5 days', 4000, 400, 'pending', NOW()),
    (8, CURRENT_DATE + INTERVAL '5 days', 3500, 350, 'pending', NOW())
  `);
}

async function seedPenalties() {
  console.log('Seeding penalties...');
  
  await db.query(`
    INSERT INTO penalties (user_id, amount, reason, status, due_date, created_by, created_at)
    VALUES 
    (6, 200, 'Late contribution payment for previous month', 'pending', CURRENT_DATE + INTERVAL '15 days', 1, NOW()),
    (8, 350, 'Late contribution payment for previous month', 'pending', CURRENT_DATE + INTERVAL '15 days', 1, NOW())
  `);
}

async function updateTotals() {
  console.log('Updating various totals...');
  
  // Update investment totals
  await db.query(`
    UPDATE investments 
    SET total_contributed = (
        SELECT SUM(amount) 
        FROM investment_contributions 
        WHERE investment_id = investments.id
    ),
    last_contribution_date = (
        SELECT MAX(contribution_date) 
        FROM investment_contributions 
        WHERE investment_id = investments.id
    )
    WHERE EXISTS (
        SELECT 1 
        FROM investment_contributions 
        WHERE investment_id = investments.id
    )
  `);
  
  // Update project totals
  await db.query(`
    UPDATE projects 
    SET total_raised = (
        SELECT SUM(amount) 
        FROM project_contributions 
        WHERE project_id = projects.id
    ),
    last_contribution_date = (
        SELECT MAX(contribution_date) 
        FROM project_contributions 
        WHERE project_id = projects.id
    )
    WHERE EXISTS (
        SELECT 1 
        FROM project_contributions 
        WHERE project_id = projects.id
    )
  `);
  
  // Update user investment totals
  await db.query(`
    UPDATE user_accounts 
    SET total_investments = (
        SELECT COALESCE(SUM(amount), 0) 
        FROM investment_shares 
        WHERE user_id = user_accounts.user_id
    )
    WHERE EXISTS (
        SELECT 1 
        FROM investment_shares 
        WHERE user_id = user_accounts.user_id
    )
  `);
}

// Run the seeding process
seedAll();