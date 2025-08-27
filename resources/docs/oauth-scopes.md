# OAuth 2.0 Scopes

This API uses OAuth 2.0 with Google-style scope names to control access to resources. Scopes define what permissions an access token has and what resources and operations it can access.

## Core OpenID Connect Scopes

These are standard OpenID Connect scopes that provide basic user information:

### `openid`
**Required for OpenID Connect authentication**
- Enables OpenID Connect ID token generation
- Always included by default for OIDC flows
- Provides basic user identity verification

### `profile`
**Access basic profile information**  
- Provides access to user's basic profile information
- Includes: name, family name, given name, middle name, nickname, preferred username, picture, website, gender, birthdate, zoneinfo, locale, and updated_at claims
- Default scope for most applications

### `email`
**Access email address**
- Provides access to user's primary email address
- Includes: email and email_verified claims
- Default scope for most applications

## Google-Style Resource Scopes

### Organization Management

#### `https://api.yourcompany.com/auth/organization.readonly`
**Read-only access to organization data**
- View organization information, structure, and hierarchy
- Read organization settings and metadata
- Access organization-level statistics and reports

#### `https://api.yourcompany.com/auth/organization`
**Full organization management access**
- Create, update, and delete organizations
- Modify organization settings and structure
- Manage organization hierarchy and relationships
- **Includes all permissions from organization.readonly**

#### `https://api.yourcompany.com/auth/organization.members`
**Organization membership management**
- View, add, remove, and manage organization members
- Access membership roles and permissions
- Manage member invitations and approvals
- View membership history and audit logs

#### `https://api.yourcompany.com/auth/organization.admin`
**Administrative access to organization**
- Full administrative control over organization settings
- Access to sensitive organization data and configurations
- Manage organization-wide policies and permissions
- **Includes all organization-related permissions**

### User Management

#### `https://api.yourcompany.com/auth/userinfo.profile`
**Access user profile information**
- Read user profile data including name, avatar, bio
- Access user preferences and settings
- View user activity and engagement metrics
- Similar to OIDC profile scope but for API access

#### `https://api.yourcompany.com/auth/userinfo.email`
**Access user email information**
- Read user's email addresses (primary and secondary)
- View email verification status
- Access email preferences and settings
- Similar to OIDC email scope but for API access

#### `https://api.yourcompany.com/auth/user.modify`
**Modify user profiles and settings**
- Update user profile information
- Change user preferences and settings
- Upload and manage user avatars
- Modify user account settings

### Analytics and Reporting

#### `https://api.yourcompany.com/auth/analytics.readonly`
**Read access to analytics data**
- View organization analytics and metrics
- Access usage statistics and reports
- Read performance dashboards
- Export analytics data in various formats

#### `https://api.yourcompany.com/auth/reports`
**Generate and access business reports**
- Create custom reports and dashboards
- Schedule automated report generation
- Access historical reporting data
- Export reports in multiple formats (PDF, Excel, CSV)

### Integration and Platform

#### `https://api.yourcompany.com/auth/webhooks`
**Webhook management**
- Create, update, and delete webhook subscriptions
- Manage webhook endpoints and configurations
- View webhook delivery logs and statistics
- Test webhook endpoints and payloads

#### `https://api.yourcompany.com/auth/integrations`
**Third-party system integrations**
- Access integration management features
- Configure external service connections
- Manage API keys and connection settings
- Monitor integration health and status

### Financial and Security

#### `https://api.yourcompany.com/auth/finance.readonly`
**Read-only access to financial data**
- View billing information and invoices
- Access payment history and receipts
- Read subscription and usage data
- Export financial reports

#### `https://api.yourcompany.com/auth/audit.readonly`
**Security audit and compliance access**
- View security logs and audit trails
- Access compliance reports and certificates
- Read security policy configurations
- Monitor security events and alerts

### Platform Administration

#### `https://api.yourcompany.com/auth/platform.full`
**Complete platform access for trusted applications**
- Full access to all platform features and data
- Administrative control over system settings
- Access to sensitive system information
- **Should only be granted to highly trusted applications**

#### `https://api.yourcompany.com/auth/mobile`
**Mobile application specialized access**
- Optimized permissions for mobile applications
- Access to mobile-specific features and APIs
- Push notification management
- Offline data synchronization capabilities

### Standard OAuth Scopes

#### `offline_access`
**Refresh token generation**
- Enables generation of refresh tokens
- Allows applications to maintain access when user is offline
- Required for long-running applications and services
- Standard OAuth 2.0 scope

## Scope Usage Guidelines

### Requesting Scopes
When initiating an OAuth authorization flow, request only the minimum scopes necessary for your application:

```bash
GET /oauth/authorize?
  client_id=your_client_id&
  response_type=code&
  scope=openid profile email https://api.yourcompany.com/auth/organization.readonly&
  redirect_uri=https://yourapp.com/callback
```

### Scope Inheritance
Some scopes include permissions from other scopes:
- `https://api.yourcompany.com/auth/organization` includes `organization.readonly`
- `https://api.yourcompany.com/auth/organization.admin` includes all organization scopes
- `https://api.yourcompany.com/auth/platform.full` includes most other scopes

### Security Considerations
- **Principle of Least Privilege**: Always request the minimum required scopes
- **Sensitive Scopes**: Be extra careful with admin and platform scopes
- **User Consent**: Users will see a consent screen listing all requested scopes
- **Scope Validation**: The API validates scopes on every request

### Dynamic Scopes
Some scopes may be dynamically generated based on organization context:
- Organization-specific scopes may include organization identifiers
- Multi-tenant applications may have tenant-specific scope variations

## Examples

### Basic Web Application
```
openid profile email https://api.yourcompany.com/auth/userinfo.profile
```

### Organization Management Dashboard
```
openid profile email 
https://api.yourcompany.com/auth/organization.readonly 
https://api.yourcompany.com/auth/organization.members
https://api.yourcompany.com/auth/analytics.readonly
```

### Administrative Application
```
openid profile email
https://api.yourcompany.com/auth/organization.admin
https://api.yourcompany.com/auth/user.modify
https://api.yourcompany.com/auth/audit.readonly
offline_access
```

### Mobile Application
```
openid profile email
https://api.yourcompany.com/auth/mobile
https://api.yourcompany.com/auth/userinfo.profile
offline_access
```