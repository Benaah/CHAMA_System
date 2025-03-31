#!/bin/bash

# Database credentials
DB_NAME="agape_youth_group"

echo "Creating database..."
sudo -u postgres psql << EOF
DROP DATABASE IF EXISTS $DB_NAME;
CREATE DATABASE $DB_NAME;
EOF

echo "Importing schema..."
sudo -u postgres psql -d $DB_NAME -f schema.sql

echo "Importing seed data..."
sudo -u postgres psql -d $DB_NAME -f seed.sql

echo "Database initialization complete!"