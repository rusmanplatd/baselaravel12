# Scoped Permissions System

This document describes the enhanced Spatie Laravel Permissions system that supports global and scoped roles/permissions for organizations, chat, projects, and other resources.

## Overview

The scoped permissions system extends the standard Spatie Laravel Permissions package to provide:

- **Global Permissions**: System-wide permissions that apply everywhere
- **Scoped Permissions**: Permissions tied to specific resources (organizations, projects, chats, etc.)
- **Permission Inheritance**: Child scopes can inherit permissions from parent scopes
- **Flexible Scope Hierarchy**: Support for nested resource structures

## Architecture

### Core Components

1. **Enhanced Models**:
   - `App\Models\Auth\Permission` - Extended with scope support
   - `App\Models\Auth\Role` - Extended with scope support  
   - `App\Models\Auth\PermissionScope` - Manages scope relationships

2. **Traits**:
   - `App\Traits\HasScopedPermissions` - For User model
   - `App\Traits\HasScopedResources` - For resource models

3. **Services**:
   - `App\Services\ScopedPermissionService` - Core permission management
   - `App\Facades\ScopedPermission` - Facade for easy access

4. **Middleware**:
   - `App\Http\Middleware\ScopedPermissionMiddleware`
   - `App\Http\Middleware\ScopedRoleMiddleware`

## Database Schema

The system adds the following columns to existing Spatie tables:

### sys_permissions
- `scope_type` - Type of resource (organization, project, chat, etc.)
- `scope_id` - ID of the scoped resource
- `is_global` - Whether this is a global permission
- `scope_path` - JSON array of the scope hierarchy

### sys_roles
- `scope_type` - Type of resource this role is scoped to
- `scope_id` - ID of the scoped resource  
- `is_global` - Whether this is a global role
- `scope_path` - JSON array of the scope hierarchy

### sys_model_has_permissions & sys_model_has_roles
- `scope_type` - Type of resource for this assignment
- `scope_id` - ID of the scoped resource
- `scope_path` - JSON array of the scope hierarchy

### sys_permission_scopes
New table tracking scope relationships and inheritance rules.

## Usage Examples

### Basic Permission Management

```php
use App\Facades\ScopedPermission;

// Grant permission to user in specific scope
ScopedPermission::grantPermissionToUser(
    $user,
    'edit_project',
    'project',
    $project->id
);

// Check permission in scope
if ($user->hasPermissionInScope('edit_project', 'project', $project->id)) {
    // User has permission
}

// Assign role in scope
ScopedPermission::assignRoleToUser(
    $user,
    'project_admin',
    'project', 
    $project->id
);
```

### Middleware Usage

```php
// In routes/web.php
Route::middleware('permission.scoped:edit_project:project:project')
    ->put('/projects/{project}', [ProjectController::class, 'update']);

// In controller constructor
$this->middleware('permission.scoped:manage_chat:chat:conversation')
    ->only(['addMember', 'removeMember']);
```

### Controller Integration

```php
class ProjectController extends Controller 
{
    public function show(string $project): JsonResponse
    {
        $user = Auth::user();
        
        // Manual permission check
        if (!$user->hasPermissionInScope('view_project', 'project', $project)) {
            abort(403);
        }
        
        $projectModel = Project::findOrFail($project);
        return response()->json($projectModel);
    }
    
    public function getMyPermissions(string $project): JsonResponse
    {
        $user = Auth::user();
        
        return response()->json([
            'permissions' => $user->getPermissionsForScope('project', $project)->pluck('name'),
            'roles' => $user->getRolesForScope('project', $project)->pluck('name'),
        ]);
    }
}
```

### Resource Setup

```php
// Set up permission scope for a new project
ScopedPermission::setupScopeHierarchy(
    $project,
    'organization', // parent scope type
    $organization->id, // parent scope id
    true, // inherits permissions
    ['created_by' => Auth::id()] // metadata
);
```

### Bulk Operations

```php
// Bulk assign permissions
ScopedPermission::bulkAssignPermissions(
    $users, 
    ['view_project', 'edit_tasks'], 
    'project', 
    $project->id
);

// Clone permissions from one scope to another
ScopedPermission::clonePermissions(
    'project', $sourceProject->id,
    'project', $targetProject->id
);
```

## Permission Inheritance

The system supports hierarchical permission inheritance:

```
Organization (parent)
├── Project A (inherits from Organization)
│   ├── Chat A1 (inherits from Project A)
│   └── Chat A2 (inherits from Project A)
└── Project B (inherits from Organization)
```

Users with permissions at higher levels automatically have them at lower levels (if inheritance is enabled).

## Scope Types

The system supports any scope type you define:

- `organization` - Organization-level permissions
- `project` - Project-level permissions  
- `chat` - Chat/conversation-level permissions
- `calendar` - Calendar-level permissions
- Custom scope types as needed

## Role Templates

Common role patterns:

### Organization Roles
- `organization_admin` - Full organization access
- `organization_manager` - Limited admin access
- `organization_member` - Basic member access

### Project Roles
- `project_admin` - Full project control
- `project_manager` - Project management
- `project_member` - Basic project access
- `project_viewer` - Read-only access

### Chat Roles
- `chat_admin` - Full chat control
- `chat_moderator` - Moderation powers
- `chat_member` - Standard participation

## Testing

Run the scoped permissions tests:

```bash
# Feature tests
php artisan test tests/Feature/ScopedPermissionsTest.php

# Unit tests  
php artisan test tests/Unit/ScopedPermissionTraitTest.php
```

## Migration

To add scoped permissions to your existing system:

1. Run the migration:
```bash
php artisan migrate
```

2. Add traits to your User model:
```php
use App\Traits\HasScopedPermissions;

class User extends Authenticatable
{
    use HasScopedPermissions;
    // ...
}
```

3. Add traits to resource models:
```php
use App\Traits\HasScopedResources;

class Project extends Model
{
    use HasScopedResources;
    // ...
}
```

4. Seed with sample permissions:
```bash
php artisan db:seed --class=ScopedPermissionsSeeder
```

## Performance Considerations

- The system includes caching for permission queries
- Use bulk operations when assigning permissions to multiple users
- Index the scope columns for better query performance
- Consider using eager loading when checking multiple permissions

## Troubleshooting

### Permission not working
1. Check if permission exists in the correct scope
2. Verify inheritance settings on the scope
3. Clear permission cache: `php artisan permission:cache-reset`

### Performance issues
1. Ensure database indexes are in place
2. Use `inheritable()` query scope for complex inheritance queries
3. Consider caching frequently-used permission checks

## Security Notes

- Always validate scope IDs exist and user has access
- Use middleware for route protection rather than just controller checks
- Regular audit of permission assignments and scope inheritance
- Be careful with bulk operations - validate all inputs

## Examples Directory

See the `app/Http/Controllers/Examples/` directory for complete implementation examples:

- `ScopedProjectController.php` - Project management with scoped permissions
- `ScopedChatController.php` - Chat system with fine-grained permissions

These examples show real-world usage patterns and best practices for implementing scoped permissions in your controllers.