# Activity Log Permissions Guide

This guide explains how to set up and manage activity log permissions in the Laravel application.

## Overview

The activity log system uses role-based permissions with three levels of access:

- **`activity.view.own`** - Users can only view their own activity logs
- **`activity.view.organization`** - Users can view activity logs within their organizations
- **`activity.view.all`** - Users can view all activity logs across all organizations
- **`activity.delete`** - Users can delete activity logs
- **`activity.export`** - Users can export activity logs  
- **`activity.purge`** - Users can purge old activity logs

## Role Hierarchy

### 1. **Employee** (`employee` role)
- **Permissions**: `activity.view.own`
- **Access**: Can only view their own activities
- **Use case**: Regular employees who should only see their own actions

### 2. **Manager** (`manager` role)
- **Permissions**: `activity.view.organization`
- **Access**: Can view activities within their organization
- **Use case**: Department managers, team leaders

### 3. **Board Member** (`board-member` role)
- **Permissions**: `activity.view.organization`
- **Access**: Can view activities within their organization
- **Use case**: Board members with oversight responsibilities

### 4. **Organization Admin** (`organization-admin` role)
- **Permissions**: `activity.view.organization`, `activity.view.all`, `activity.delete`, `activity.export`, `activity.purge`
- **Access**: Can view, delete, and manage organization and all activities
- **Use case**: Organization administrators with full management rights

### 5. **Auditor** (`auditor` role)
- **Permissions**: `activity.view.all`, `activity.export`
- **Access**: Can view and export all activities (read-only)
- **Use case**: Internal or external auditors who need comprehensive read access

### 6. **Security Admin** (`security-admin` role)
- **Permissions**: `activity.view.all`, `activity.delete`, `activity.export`, `activity.purge`
- **Access**: Can manage all activity logs with full administrative control
- **Use case**: Security officers, IT administrators

### 7. **Super Admin** (`super-admin` role)
- **Permissions**: All activity permissions
- **Access**: Complete system access
- **Use case**: System administrators

### 8. **Consultant** (`consultant` role)
- **Permissions**: `activity.view.own`
- **Access**: Can only view their own activities
- **Use case**: External consultants with limited access

## Setup Instructions

### 1. Seed Permissions and Roles

Run the permission seeder to create all roles and permissions:

```bash
php artisan db:seed --class=PermissionSeeder
```

This will create all the necessary permissions and roles with proper assignments.

### 2. Assign Roles to Users

#### Using Artisan Command (Recommended)

The system includes a helpful artisan command:

```bash
# List all roles and their permissions
php artisan activity-log:assign-permissions --list

# Show current users with activity log permissions
php artisan activity-log:assign-permissions --show-users

# Assign a role to a user
php artisan activity-log:assign-permissions --user=user@example.com --role=manager

# Interactive mode (will prompt for user and role)
php artisan activity-log:assign-permissions
```

#### Using Code/Tinker

**Important**: This system uses team-based permissions. For global roles, you need to set the team context:

```php
// Set global context (outside any organization)
setPermissionsTeamId(null);

$user = User::where('email', 'user@example.com')->first();
$user->assignRole('manager');
```

For organization-specific roles:

```php
// Set organization context
$organization = Organization::first();
setPermissionsTeamId($organization->id);

$user = User::where('email', 'user@example.com')->first();
$user->assignRole('organization-admin');

// Reset context
setPermissionsTeamId(null);
```

### 3. Access the Activity Log

Once users have the appropriate permissions, they can access the activity log at:

```
https://yourapp.com/activity-log
```

The page will automatically filter activities based on their permission level.

## Filtering and Search

The activity log page includes several filtering options:

### Automatic Filtering (Based on Permissions)
- **Super Admin**: Sees all activities
- **Organization Admin**: Sees activities within their organizations + all activities
- **Manager/Board Member**: Sees activities within their organizations only
- **Employee/Consultant**: Sees only their own activities

### Manual Filters
- **Resource/Menu Filter**: Filter by activity type (auth, organization, oauth, system, etc.)
- **Organization Filter**: Filter by specific organization (if permitted)
- **User Filter**: Filter by specific user (if permitted)
- **Date Range**: Filter by from/to dates
- **Search**: Full-text search through activity descriptions

## Navigation Access

The Activity Log menu item appears in the sidebar only for users with any of these permissions:
- `activity.view.own`
- `activity.view.organization` 
- `activity.view.all`

## Security Considerations

### 1. **Principle of Least Privilege**
- Start with minimal permissions (`activity.view.own`)
- Gradually increase access as needed
- Regular audit of user permissions

### 2. **Organization Isolation**
- Organization-level permissions respect organization boundaries
- Users can only see activities within their assigned organizations
- Cross-organization access requires `activity.view.all` permission

### 3. **Audit Trail**
- All permission changes are logged
- User role assignments create activity logs
- Regular monitoring recommended

## Troubleshooting

### Permission Issues

**Problem**: User cannot see activity log menu
**Solution**: Ensure user has at least one activity permission:
```bash
php artisan activity-log:assign-permissions --user=user@example.com --role=employee
```

**Problem**: User sees "Access Denied" error
**Solution**: Check user's role assignments and organization memberships

**Problem**: Empty activity log page
**Solution**: Check if activities exist and user has correct organization assignments

### Database Issues

**Problem**: `team_id` constraint errors when assigning roles
**Solution**: Always set permission context before role assignment:
```php
setPermissionsTeamId(null); // for global roles
// or
setPermissionsTeamId($organizationId); // for organization-specific roles
```

## API Usage

The activity log can also be accessed via API endpoints for programmatic access:

```
GET /activity-log - List activities (with filtering)
GET /activity-log/{id} - View specific activity details
```

All API access respects the same permission structure.

## Best Practices

1. **Regular Permission Audits**: Review user permissions quarterly
2. **Role-Based Assignment**: Use predefined roles rather than individual permissions
3. **Organization Mapping**: Ensure users are properly assigned to organizations
4. **Monitoring**: Set up alerts for permission changes
5. **Documentation**: Keep user permission documentation updated

## Examples

### Example 1: Department Manager Setup
```bash
# Assign manager role to department head
php artisan activity-log:assign-permissions --user=dept.head@company.com --role=manager
```

### Example 2: External Auditor Setup
```bash
# Assign auditor role for comprehensive read access
php artisan activity-log:assign-permissions --user=auditor@external.com --role=auditor
```

### Example 3: Security Team Setup
```bash
# Assign security admin role for full management access
php artisan activity-log:assign-permissions --user=security@company.com --role=security-admin
```

This completes the activity log permissions setup. Users will now have appropriate access based on their roles and can effectively monitor system activities within their permission scope.