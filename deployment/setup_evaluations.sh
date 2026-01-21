#!/bin/bash
# Setup script for Goal-Based Evaluation Platform

echo "========================================="
echo "Goal-Based Evaluation Platform Setup"
echo "========================================="
echo ""

# Create uploads directory
echo "Creating uploads directory..."
mkdir -p uploads/eval_media
chmod 755 uploads/eval_media
echo "✓ Upload directory created: uploads/eval_media"
echo ""

# Check if database credentials are available
if [ -f .env ]; then
    echo "Found .env file"
    
    # Source the .env file to get database credentials
    source <(grep -v '^#' .env | sed 's/\r$//' | sed 's/"/\\"/g' | sed "s/'/\\\'/g" | sed 's/=/="/g' | sed 's/$/"/g')
    
    echo "Database credentials found"
    echo "Database: ${DB_NAME:-crashhockey}"
    
    # Ask user if they want to create the tables
    read -p "Do you want to create the database tables now? (y/n): " -n 1 -r
    echo ""
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "Creating database tables..."
        
        # Use mysql command with credentials from .env
        mysql -h"${DB_HOST:-localhost}" \
              -u"${DB_USER}" \
              -p"${DB_PASS}" \
              "${DB_NAME:-crashhockey}" \
              < deployment/sql/goal_evaluations_schema.sql
        
        if [ $? -eq 0 ]; then
            echo "✓ Database tables created successfully"
        else
            echo "✗ Error creating database tables"
            echo "  Please check your database credentials and run manually:"
            echo "  mysql -u[user] -p [database] < deployment/sql/goal_evaluations_schema.sql"
        fi
    else
        echo "Skipping database table creation"
        echo "To create tables manually, run:"
        echo "  mysql -u[user] -p [database] < deployment/sql/goal_evaluations_schema.sql"
    fi
else
    echo "No .env file found"
    echo "To create tables manually, run:"
    echo "  mysql -u[user] -p [database] < deployment/sql/goal_evaluations_schema.sql"
fi

echo ""
echo "========================================="
echo "Setup Complete!"
echo "========================================="
echo ""
echo "Files created:"
echo "  - views/evaluations_goals.php"
echo "  - process_eval_goals.php"
echo "  - process_eval_goal_approval.php"
echo "  - deployment/sql/goal_evaluations_schema.sql"
echo ""
echo "Next steps:"
echo "  1. Add navigation menu item for evaluations_goals"
echo "  2. Test creating an evaluation"
echo "  3. Test the approval workflow"
echo "  4. Configure notification settings"
echo ""
echo "Documentation: EVALUATION_PLATFORM_README.md"
echo ""
