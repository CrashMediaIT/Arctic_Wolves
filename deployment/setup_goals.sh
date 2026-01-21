#!/bin/bash
# Goals System Setup Script
# This script sets up the Goals and Progress Tracking System

echo "=========================================="
echo "Goals & Progress Tracking System Setup"
echo "=========================================="
echo ""

# Check if MySQL credentials are available
if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
    echo "⚠️  Database credentials not found in environment"
    echo "Please ensure .env file is configured with:"
    echo "  DB_HOST=your_host"
    echo "  DB_NAME=your_database"
    echo "  DB_USER=your_username"
    echo "  DB_PASSWORD=your_password"
    echo ""
    echo "Manual installation:"
    echo "  mysql -u username -p database_name < deployment/goals_tables.sql"
    exit 1
fi

echo "📊 Creating database tables..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < deployment/goals_tables.sql

if [ $? -eq 0 ]; then
    echo "✅ Database tables created successfully!"
    echo ""
    echo "Tables created:"
    echo "  - goals"
    echo "  - goal_steps"
    echo "  - goal_progress"
    echo "  - goal_history"
    echo ""
    echo "🎯 Goals system is ready to use!"
    echo "   Access at: dashboard.php?page=goals"
    echo ""
    echo "📚 Documentation: GOALS_SYSTEM_README.md"
else
    echo "❌ Error creating database tables"
    echo "Please check your database credentials and try again"
    exit 1
fi
