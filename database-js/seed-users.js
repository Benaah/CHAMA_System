const db = require('../db');
const bcrypt = require('bcrypt');

async function seedUsers() {
  try {
    console.log('Seeding users...');
    
    // Password hash for all users (password: admin123, treasurer123, etc.)
    const passwordHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    
    // Create admin user
    await db.query(`
      INSERT INTO users (full_name, username, email, phone, password, role, status, registration_fee_paid, registration_date, membership_status, created_at)
      VALUES ('Admin User', 'admin', 'admin@agapeyouthchama.org', '+254712345678', $1, 'admin', 'active', TRUE, NOW(), 'active', NOW())
    `, [passwordHash]);
    
    // Create treasurer user
    await db.query(`
      INSERT INTO users (full_name, username, email, phone, password, role, status, registration_fee_paid, registration_date, membership_status, created_at)
      VALUES ('Treasurer User', 'treasurer', 'treasurer@agapeyouthchama.org', '+254723456789', $1, 'treasurer', 'active', TRUE, NOW(), 'active', NOW())
    `, [passwordHash]);
    
    // Create secretary user
    await db.query(`
      INSERT INTO users (full_name, username, email, phone, password, role, status, registration_fee_paid, registration_date, membership_status, created_at)
      VALUES ('Secretary User', 'secretary', 'secretary@agapeyouthchama.org', '+254734567890', $1, 'secretary', 'active', TRUE, NOW(), 'active', NOW())
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
    
    console.log('Users seeded successfully!');
    process.exit(0);
  } catch (error) {
    console.error('Error seeding users:', error);
    process.exit(1);
  }
}

seedUsers();