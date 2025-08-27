#!/bin/bash

echo "🚀 Starting migration to new naming conventions..."

# Run the migration to update existing data
echo "📊 Running migration to update existing OAuth scopes and permissions..."
php artisan migrate --force

# Fresh seed with new naming conventions
echo "🌱 Re-seeding with new naming conventions..."
php artisan db:seed --class=OAuthScopesSeeder --force
php artisan db:seed --class=PermissionSeeder --force

# Clear caches
echo "🧹 Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "✅ Migration to new naming conventions completed successfully!"
echo ""
echo "📋 Summary of changes:"
echo "• OAuth scopes now use Google-style URLs (https://api.yourcompany.com/auth/...)"
echo "• Permissions now use GitHub-style format (resource:action)"
echo "• All legacy names have been removed"
echo "• Database has been migrated to new conventions"
echo ""
echo "🎉 Your application now uses modern industry-standard naming conventions!"