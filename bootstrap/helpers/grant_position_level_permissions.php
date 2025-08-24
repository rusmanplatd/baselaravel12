<?php

/**
 * Helper script to grant position level permissions to users
 * Usage: php artisan tinker < bootstrap/helpers/grant_position_level_permissions.php
 */

// Function to grant position level permissions to a user
function grantPositionLevelPermissions($email, $organizationId = null) {
    if (!$organizationId) {
        $organizationId = App\Models\Organization::first()?->id;
    }
    
    if (!$organizationId) {
        echo "Error: No organization found. Please create an organization first.\n";
        return false;
    }
    
    // Set team context
    setPermissionsTeamId($organizationId);
    
    $user = App\Models\User::where('email', $email)->first();
    
    if (!$user) {
        echo "Error: User with email {$email} not found.\n";
        return false;
    }
    
    $permissions = [
        'position.level.view',
        'position.level.create', 
        'position.level.edit',
        'position.level.delete',
        'view organization position levels',
        'create organization position levels',
        'edit organization position levels', 
        'delete organization position levels'
    ];
    
    try {
        $user->givePermissionTo($permissions);
        echo "✅ Position level permissions granted to {$email}\n";
        echo "Permissions granted: " . count($permissions) . "\n";
        return true;
    } catch (Exception $e) {
        echo "Error granting permissions: " . $e->getMessage() . "\n";
        return false;
    }
}

// Function to check if user has position level permissions
function checkPositionLevelPermissions($email, $organizationId = null) {
    if (!$organizationId) {
        $organizationId = App\Models\Organization::first()?->id;
    }
    
    setPermissionsTeamId($organizationId);
    
    $user = App\Models\User::where('email', $email)->first();
    
    if (!$user) {
        echo "Error: User with email {$email} not found.\n";
        return;
    }
    
    echo "Position level permissions for {$email}:\n";
    echo "- Can view position levels: " . ($user->can('position.level.view') ? 'YES' : 'NO') . "\n";
    echo "- Can create position levels: " . ($user->can('create organization position levels') ? 'YES' : 'NO') . "\n";
    echo "- Can edit position levels: " . ($user->can('edit organization position levels') ? 'YES' : 'NO') . "\n";
    echo "- Can delete position levels: " . ($user->can('delete organization position levels') ? 'YES' : 'NO') . "\n";
}

echo "Position Level Permission Helper loaded.\n";
echo "Available functions:\n";
echo "- grantPositionLevelPermissions('user@email.com')\n";
echo "- checkPositionLevelPermissions('user@email.com')\n";
echo "\n";

// Example usage (commented out):
// grantPositionLevelPermissions('admin@example.com');
// checkPositionLevelPermissions('admin@example.com');