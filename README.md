AGAPE CHAMA - Financial Management System
Project Overview
AGAPE CHAMA is a comprehensive web-based financial management system designed for Chama groups (informal cooperative societies common in Kenya). The platform facilitates efficient management of member contributions, loans, savings, investments, and group activities.

Key Features

User Management
Registration & Authentication: Secure user registration and login system
Profile Management: Members can update personal information and preferences
Role-based Access Control: Different permission levels for members and administrators

Financial Management
Contributions: Track and manage regular member contributions
Loans: Complete loan application, approval, and repayment system
Savings: Personal savings tracking and management
Withdrawals: Structured process for fund withdrawals
Budget Planner: Personal financial planning tools
Group Activities
Meetings: Schedule and track group meetings with attendance records
Projects: Manage group investment projects and member shares
Welfare: Support system for member emergencies and needs
Dividends: Calculate and distribute earnings to members
Administrative Tools
Member Management: Comprehensive tools for administrators to manage members
Financial Reports: Detailed reports and analytics on group finances
Calendar System: Event scheduling and reminder system
Member Exit Process: Structured workflow for members leaving the group

Technical Stack
Backend: PHP with PDO for database operations
Database: PostgreSQL
Frontend: HTML5, CSS3, JavaScript, Bootstrap 4
Libraries: jQuery, Chart.js, DataTables
Security: Password hashing, input sanitization, prepared statementS

Installation
Clone the repository: git clone https://github.com/Benaah/AGAPE_CHAMA.git
Configure your web server (Apache/Nginx) to point to the project directory

Create a PostgreSQL database and import the schema:
psql -U your_username -d your_database_name -

Update the database configuration in config.php:
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

Ensure the following directories have write permissions:
uploads/
logs/

Usage

Member Functions
Register and login to access the system
Make contributions and apply for loans
Track personal savings and financial status
Participate in group meetings and projects
Use financial planning tools

Administrator Functions
Manage members and their permissions
Process loan applications and withdrawals
Schedule meetings and events
Generate financial reports
Oversee project investments

Project Structure
AGAPE_CHAMA/
├── admin/                  # Administrator interface
├── assets/                 # Static assets (CSS, JS, images)
├── includes/               # Reusable PHP components
├── setup/                  # Database setup scripts
├── tools/                  # Financial tools (budget planner, etc.)
├── uploads/                # User uploaded files
├── config.php              # Configuration settings
├── dashboard.php           # Main user dashboard
├── index.php               # Landing page
├── login.php               # User authentication
├── register.php            # New user registration
└── [feature].php           # Feature-specific

Security Considerations

All user inputs are sanitized to prevent SQL injection
Passwords are hashed using PHP's password_hash() function
Session management for authenticated users
CSRF protection for form submissions
Input validation on both client and server sides

Future Enhancements
Mobile application integration
SMS notifications for important events
Integration with payment gateways (M-Pesa, etc.)
Enhanced reporting and analytics
Document management system
Multi-language support

Contributors:
Eng. ONYANGO BENARD - Lead Developer

License
This project is licensed under the MIT License - see the LICENSE file for details.
