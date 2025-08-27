# GitHub-Style Permissions System

This application uses a GitHub-inspired permission system powered by Spatie Laravel Permission. Permissions follow the GitHub naming convention of `resource:action` format for clear, intuitive access control.

## Permission Naming Convention

All permissions follow the GitHub-style format: `resource:action`

- **resource**: The entity or feature being accessed (e.g., `user`, `org`, `repo`)
- **action**: The operation being performed (`read`, `write`, `delete`, `admin`)

### Common Actions
- **`read`**: View and list resources
- **`write`**: Create and update resources (includes `read`)
- **`delete`**: Remove resources (usually requires `write`)
- **`admin`**: Full administrative access (includes all other actions)

## Core Permission Categories

### User Management
Control access to user accounts and profiles.

#### `user:read`
**View and list users**
- Browse user directories and listings
- View user profiles and basic information
- Access user search and filtering features
- Export user lists and basic reports

#### `user:write`
**Create and update users**
- Create new user accounts
- Update user profile information
- Modify user settings and preferences
- **Includes all `user:read` permissions**

#### `user:delete`
**Delete user accounts**
- Remove user accounts and associated data
- Permanently delete user profiles
- Manage user account deactivation
- **Requires `user:write` permissions**

#### `user:impersonate`
**Impersonate users for troubleshooting**
- Log in as another user for support purposes
- Access user accounts for debugging
- Troubleshoot user-specific issues
- **High-privilege permission requiring careful control**

### Organization Management
GitHub-style organization permissions for hierarchical management.

#### `org:read`
**View organization information**
- Browse organization details and structure
- Access organization public information
- View organization hierarchy and relationships
- Read organization settings and configurations

#### `org:write`
**Create and update organizations**
- Create new organizations and sub-organizations
- Update organization information and settings
- Modify organization structure and hierarchy
- **Includes all `org:read` permissions**

#### `org:delete`
**Delete organizations**
- Remove organizations and their data
- Permanently delete organization structures
- Clean up organization relationships
- **Requires `org:write` permissions**

#### `org:admin`
**Full administrative access to organization**
- Complete control over organization settings
- Manage organization-wide policies
- Access sensitive organization data
- **Includes all organization permissions**

### Organization Membership
Manage organization membership like GitHub teams.

#### `org_member:read`
**View organization memberships**
- List organization members and their roles
- View membership status and history
- Access membership reports and analytics
- Browse member profiles within organization

#### `org_member:write`
**Create and update organization memberships**
- Invite users to join organization
- Update member roles and permissions
- Manage membership status changes
- **Includes all `org_member:read` permissions**

#### `org_member:delete`
**Remove organization memberships**
- Remove members from organization
- Revoke organization access
- Handle membership terminations
- **Requires `org_member:write` permissions**

#### `org_member:admin`
**Full administrative access to organization memberships**
- Complete membership management control
- Override membership restrictions
- Manage owner and admin roles
- **Includes all membership permissions**

### Organizational Units
Repository-style permissions for organizational units.

#### `org_unit:read`
**View organization units**
- Browse departments, divisions, and teams
- View unit structure and hierarchy
- Access unit information and members
- Read unit reports and analytics

#### `org_unit:write`
**Create and update organization units**
- Create new organizational units
- Update unit information and structure
- Modify unit hierarchy and relationships
- **Includes all `org_unit:read` permissions**

#### `org_unit:delete`
**Delete organization units**
- Remove organizational units
- Clean up unit relationships
- Handle unit restructuring
- **Requires `org_unit:write` permissions**

#### `org_unit:admin`
**Full administrative access to organization units**
- Complete unit management control
- Override unit restrictions and policies
- Manage cross-unit relationships
- **Includes all unit permissions**

### Position Management
GitHub-style position and role management.

#### `org_position:read`
**View organization positions**
- Browse available positions and roles
- View position descriptions and requirements
- Access position hierarchy and reporting lines
- Read position-related reports

#### `org_position:write`
**Create and update organization positions**
- Create new positions and job roles
- Update position descriptions and requirements
- Modify position hierarchy and relationships
- **Includes all `org_position:read` permissions**

#### `org_position:delete`
**Delete organization positions**
- Remove positions and job roles
- Clean up position relationships
- Handle position restructuring
- **Requires `org_position:write` permissions**

#### `org_position:admin`
**Full administrative access to organization positions**
- Complete position management control
- Override position restrictions
- Manage cross-department positions
- **Includes all position permissions**

### OAuth Applications
GitHub-style OAuth app management.

#### `oauth_app:read`
**View OAuth applications**
- List organization's OAuth applications
- View application details and configurations
- Access application statistics and usage
- Read application audit logs

#### `oauth_app:write`
**Create and update OAuth applications**
- Register new OAuth applications
- Update application settings and configurations
- Modify application scopes and permissions
- **Includes all `oauth_app:read` permissions**

#### `oauth_app:delete`
**Delete OAuth applications**
- Remove OAuth applications
- Revoke application access
- Clean up application data
- **Requires `oauth_app:write` permissions**

#### `oauth_app:admin`
**Full administrative access to OAuth applications**
- Complete OAuth application management
- Override application restrictions
- Manage organization-wide OAuth policies
- **Includes all OAuth app permissions**

### OAuth Token Management

#### `oauth_token:read`
**View OAuth tokens and analytics**
- View active tokens and their usage
- Access token analytics and statistics
- Monitor token usage patterns
- Read token audit logs

#### `oauth_token:write`
**Manage OAuth tokens**
- Create and manage token configurations
- Update token settings and scopes
- Configure token policies
- **Includes all `oauth_token:read` permissions**

#### `oauth_token:delete`
**Revoke OAuth tokens**
- Revoke active tokens
- Force token expiration
- Clean up expired tokens
- **Requires `oauth_token:write` permissions**

### Audit Logs (Activity Logs)
GitHub-style audit log permissions.

#### `audit_log:read`
**View audit logs**
- Access audit logs scoped to user's permissions
- View organization activity logs
- Read security events and changes
- Export audit log data

#### `audit_log:write`
**Create audit log entries**
- Generate custom audit log entries
- Log manual actions and events
- Create audit trails for custom processes
- **Includes all `audit_log:read` permissions**

#### `audit_log:delete`
**Delete audit log entries**
- Remove audit log entries
- Clean up old or irrelevant logs
- Manage audit log retention
- **Requires `audit_log:write` permissions**

#### `audit_log:admin`
**Full administrative access to audit logs**
- Complete audit log management including export and purge
- Access all audit logs across organizations
- Manage audit log policies and retention
- **Includes all audit log permissions**

### Role and Permission Management

#### `role:read`
**View roles and their permissions**
- List available roles and their descriptions
- View role assignments and hierarchy
- Access role-based reports and analytics
- Browse permission assignments

#### `role:write`
**Create and update roles**
- Create new roles and permission sets
- Update role descriptions and permissions
- Modify role hierarchy and relationships
- **Includes all `role:read` permissions**

#### `role:delete`
**Delete roles**
- Remove roles and their assignments
- Clean up role relationships
- Handle role consolidation
- **Requires `role:write` permissions**

#### `role:admin`
**Full administrative access to roles**
- Complete role management control
- Override role restrictions
- Manage system-wide role policies
- **Includes all role permissions**

#### `permission:read`
**View permissions**
- List available permissions
- View permission descriptions and categories
- Access permission-related reports
- Browse permission assignments

#### `permission:write`
**Create and update permissions**
- Create custom permissions
- Update permission descriptions and settings
- Modify permission categories and groups
- **Includes all `permission:read` permissions**

#### `permission:delete`
**Delete permissions**
- Remove custom permissions
- Clean up unused permissions
- Handle permission consolidation
- **Requires `permission:write` permissions**

#### `permission:admin`
**Full administrative access to permissions**
- Complete permission system control
- Override permission restrictions
- Manage system-wide permission policies
- **Includes all permission permissions**

### System Administration
GitHub Enterprise-style system administration.

#### `admin:org`
**Organization administration**
- Manage organization-level settings and policies
- Access organization administrative features
- Override organization restrictions
- Similar to GitHub organization owner permissions

#### `admin:enterprise`
**Enterprise administration**
- Manage enterprise-wide settings and policies
- Access cross-organization administrative features
- Control enterprise billing and licensing
- Equivalent to GitHub Enterprise admin

#### `site_admin`
**Site administration access**
- Full site administration capabilities
- Access to system-wide settings and controls
- Manage global policies and configurations
- **Highest level administrative permission**

#### `system:read`
**View system settings and logs**
- Access system configuration and status
- View system logs and diagnostics
- Read system performance metrics
- Monitor system health

#### `system:write`
**Modify system settings**
- Update system configurations
- Modify system policies and rules
- Change system-wide settings
- **Includes all `system:read` permissions**

#### `system:admin`
**Full system administrative access**
- Complete system administration control
- Override all system restrictions
- Manage critical system operations
- **Includes all system permissions**

### Profile and Security Management

#### `profile:read`
**View own profile information**
- Access own user profile and settings
- View account information and preferences
- Read personal activity and history
- Basic user self-service permission

#### `profile:write`
**Update own profile information**
- Modify own user profile and settings
- Update personal information and preferences
- Change account settings and configurations
- **Includes all `profile:read` permissions**

#### `security:read`
**View security settings**
- Access security configuration and status
- View active sessions and login history
- Read security policies and requirements
- Monitor security events for own account

#### `security:write`
**Modify security settings**
- Update security configurations including MFA and sessions
- Change password and authentication settings
- Manage active sessions and devices
- **Includes all `security:read` permissions**

#### `security:admin`
**Full administrative access to security features**
- Complete security administration control
- Override security restrictions and policies
- Manage organization-wide security settings
- **Includes all security permissions**

## Role-Based Permission Sets

### Default Roles
The system includes several pre-configured roles with GitHub-style permission sets:

#### `super-admin`
**Complete system access**
- All permissions across the entire system
- Equivalent to GitHub Enterprise site admin
- Should be limited to system administrators

#### `organization-admin`
**Organization administrator**
- Full administrative control within organization scope
- Similar to GitHub organization owner
- Recommended for organization leaders and managers

#### `manager`
**Department/unit manager**
- Management permissions within specific organizational units
- Similar to GitHub team maintainer
- Appropriate for departmental managers and team leads

#### `employee`
**Regular employee**
- Basic permissions for standard organizational participation
- Similar to GitHub organization member
- Default role for most users

#### `auditor`
**System auditor**
- Read-only access to audit logs and security information
- Specialized role for compliance and security review
- Independent oversight capabilities

#### `security-admin`
**Security administrator**
- Full access to security features and audit logs
- Specialized role for security management
- Can manage security across organizations

## Legacy Permissions
For backward compatibility, some legacy permission names are maintained:

- `view roles`, `create roles`, `edit roles`, `delete roles`
- `view permissions`, `create permissions`, `edit permissions`, `delete permissions`
- `manage roles`, `manage permissions`
- `assign permissions`, `revoke permissions`
- `view organization position levels`, `create organization position levels`, etc.

## Permission Inheritance

### Hierarchical Access
- `write` permissions typically include `read` permissions
- `delete` permissions typically require `write` permissions
- `admin` permissions include all other permissions for that resource

### Organizational Context
- Permissions are evaluated within organizational context
- Users may have different permissions in different organizations
- Organization hierarchy affects permission inheritance

## Best Practices

### Assignment Guidelines
1. **Principle of Least Privilege**: Assign minimum required permissions
2. **Role-Based Assignment**: Use predefined roles when possible
3. **Regular Review**: Periodically audit permission assignments
4. **Separation of Duties**: Separate conflicting permissions when possible

### Security Considerations
- **Admin Permissions**: Carefully control admin-level permissions
- **Cross-Organization Access**: Monitor permissions across organization boundaries
- **Audit Logging**: All permission-related actions are logged
- **Multi-Factor Authentication**: Require MFA for sensitive permissions

### Development Guidelines
- Use permission guards in controllers and middleware
- Implement permission checks at the API level
- Provide clear error messages for permission denials
- Test permission enforcement thoroughly