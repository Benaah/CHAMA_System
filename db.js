const { Pool } = require('pg');
require('dotenv').config();

const pool = new Pool({
  host: process.env.DB_HOST || 'localhost',
  port: process.env.DB_PORT || 5432,
  database: process.env.DB_NAME || 'agape_youth_group',
  user: process.env.DB_USER || 'postgres',
  password: process.env.DB_PASSWORD || '', // Make sure this is a string
});

// Log connection parameters for debugging (remove sensitive info in production)
console.log('Database connection parameters:');
console.log(`Host: ${process.env.DB_HOST || 'localhost'}`);
console.log(`Database: ${process.env.DB_NAME || 'agape_youth_group'}`);
console.log(`User: ${process.env.DB_USER || 'postgres'}`);
console.log(`Password is ${process.env.DB_PASSWORD ? 'set' : 'not set'}`);

module.exports = {
  query: (text, params) => pool.query(text, params),
  pool
};