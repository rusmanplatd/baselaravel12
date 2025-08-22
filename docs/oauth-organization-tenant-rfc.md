# RFC: OAuth 2.0 with Organization and Tenant-Aware Authorization

## Status
- **Status**: Draft
- **Version**: 1.0
- **Date**: 2025-08-22
- **Author**: Claude Code Assistant

## Abstract

This RFC describes a comprehensive OAuth 2.0 authorization framework that incorporates organizational hierarchy and multi-tenancy as core concepts. The framework provides fine-grained access control based on organizational membership, positions, and tenant isolation. All OAuth clients must be associated with organizations, ensuring proper access control and audit trails.

## 1. Introduction

### 1.1 Background

Modern enterprise applications require authorization mechanisms that integrate organizational hierarchy and multi-tenancy as fundamental requirements, not extensions. Organizations have complex hierarchical structures, and multi-tenant systems need to enforce data isolation while allowing controlled cross-tenant access.

This RFC defines a comprehensive OAuth 2.0 framework that:
- Makes organizational membership and hierarchy core to the authorization process
- Requires all OAuth clients to be associated with organizations
- Supports multi-tenant environments with tenant-aware scoping
- Provides comprehensive audit trails with organizational and tenant context
- Removes legacy compatibility layers for streamlined implementation

### 1.2 Terminology

- **Organization**: A hierarchical entity with members, positions, and units
- **Tenant**: An isolated environment associated with one or more organizations
- **Organization-Aware Client**: An OAuth client associated with a specific organization
- **Tenant-Aware Scope**: An OAuth scope that provides access within a tenant context
- **Hierarchical Scope**: A scope whose access is determined by organizational hierarchy

## 2. Organization-Aware OAuth Clients

### 2.1 Client Registration Requirements

All OAuth clients must be associated with organizations during registration:

```json
{
  "client_name": "Acme Corp Portal",
  "redirect_uris": ["https://portal.acme.com/auth/callback"],
  "organization_id": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
  "client_type": "confidential",
  "allowed_scopes": [
    "openid", "profile", "email",
    "organization:read", "organization:members",
    "tenant:read"
  ]
}
```

### 2.2 Client Properties

All OAuth clients have these organization-specific properties:

- `organization_id`: ULID of the associated organization (required)
- `allowed_scopes`: JSON array of scopes this client can request
- `client_type`: "public" or "confidential" (required, defaults to "confidential")
- `last_used_at`: Timestamp of last token issuance

### 2.3 Client Access Control

Users can only manage OAuth clients for organizations where they have management-level positions:
- C-level executives
- Vice Presidents
- Directors
- Senior Managers
- Managers

## 3. Extended Scope Definitions

### 3.1 Organization Scopes

#### `organization:read`
- **Description**: Read access to organization information
- **Required Membership**: Any active membership in the organization
- **Data Access**: Organization details, structure, basic member list

#### `organization:write`
- **Description**: Write access to organization data
- **Required Membership**: Management-level position
- **Data Access**: Modify organization settings, structure

#### `organization:members`
- **Description**: Access to detailed membership information
- **Required Membership**: Any active membership in the organization
- **Data Access**: Member details, positions, units, membership history

#### `organization:admin`
- **Description**: Administrative access to organization
- **Required Membership**: Management-level position
- **Data Access**: Full administrative capabilities

#### `organization:hierarchy`
- **Description**: Access to full organizational hierarchy
- **Required Membership**: Root organization membership (level 0)
- **Data Access**: All child organizations, cross-organizational relationships

### 3.2 Tenant Scopes

#### `tenant:read`
- **Description**: Read access to tenant-specific data
- **Required Access**: Active membership in tenant's organization
- **Data Access**: Tenant configuration, tenant-scoped resources

#### `tenant:admin`
- **Description**: Administrative access within tenant
- **Required Access**: Management-level position in tenant's organization
- **Data Access**: Tenant administration, cross-tenant operations

## 4. Authorization Flow Extensions

### 4.1 Authorization Request

Standard OAuth 2.0 authorization requests are extended to validate organizational access:

```http
GET /oauth/authorize?
  client_id=client123&
  redirect_uri=https://app.example.com/callback&
  response_type=code&
  scope=openid profile organization:read tenant:read&
  state=xyz
```

### 4.2 Scope Validation Process

1. **Client Validation**: Verify client exists and redirect_uri is valid
2. **Organization Context**: Determine client's associated organization
3. **User Membership**: Check user's active memberships
4. **Scope Filtering**: Filter requested scopes based on:
   - User's organizational memberships
   - User's positions within organizations
   - Tenant access rights
   - Client's allowed scopes

### 4.3 Consent Screen

The consent screen displays:
- Organization context for the client
- Filtered scopes with organizational context
- Tenant information when applicable

## 5. Token Response Extensions

### 5.1 UserInfo Endpoint Extensions

The `/oauth/userinfo` endpoint includes additional claims when appropriate scopes are granted:

```json
{
  "sub": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
  "name": "John Doe",
  "email": "john.doe@example.com",
  "organizations": [
    {
      "id": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
      "name": "Acme Corporation",
      "code": "ACME",
      "type": "corporate",
      "level": 0,
      "membership_type": "employee",
      "position": "Senior Developer",
      "unit": "Engineering",
      "start_date": "2023-01-15T00:00:00Z"
    }
  ],
  "tenants": [
    {
      "id": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
      "name": "acme",
      "domain": "acme.example.com",
      "organization_id": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX"
    }
  ]
}
```

### 5.2 Introspection Response Extensions

Token introspection responses include organizational context:

```json
{
  "active": true,
  "client_id": "client123",
  "username": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
  "scope": "openid profile organization:read",
  "exp": 1640995200,
  "iat": 1640908800,
  "organization_id": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
  "tenant_id": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX"
}
```

## 6. Audit and Compliance

### 6.1 Enhanced Audit Logging

All OAuth events are logged with organizational and tenant context:

```json
{
  "event_type": "authorize",
  "client_id": "client123",
  "user_id": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
  "organization_id": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
  "tenant_id": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
  "tenant_domain": "acme.example.com",
  "scopes": ["openid", "profile", "organization:read"],
  "success": true,
  "organization_context": {
    "name": "Acme Corporation",
    "code": "ACME",
    "type": "corporate",
    "level": 0,
    "path": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX"
  },
  "timestamp": "2025-08-22T12:00:00Z"
}
```

### 6.2 Compliance Features

- **Data Isolation**: Tenant-scoped audit logs ensure data isolation
- **Access Tracking**: Organization membership changes are audited
- **Scope Usage**: Track which organizational scopes are being used
- **Cross-Tenant Access**: Log any cross-tenant access attempts

## 7. Security Considerations

### 7.1 Organizational Data Protection

- Users can only access organization data if they have active membership
- Management-level access is required for administrative scopes
- Hierarchical access is limited to root organization members

### 7.2 Tenant Isolation

- Tenant-scoped data is isolated by organization membership
- Cross-tenant access requires explicit organizational relationships
- Tenant context is always included in audit logs

### 7.3 Token Security

- Organization and tenant context is embedded in tokens
- Token introspection includes organizational validation
- Revoked organizational memberships invalidate related tokens

## 8. Implementation Guidelines

### 8.1 Database Schema

#### Organization-OAuth Integration
```sql
-- Add organization support to oauth_clients
ALTER TABLE oauth_clients ADD COLUMN organization_id CHAR(26) NULL;
ALTER TABLE oauth_clients ADD COLUMN allowed_scopes JSON NULL;
ALTER TABLE oauth_clients ADD COLUMN client_type VARCHAR(50) DEFAULT 'public';

-- Enhanced audit logging
ALTER TABLE oauth_audit_logs ADD COLUMN organization_id CHAR(26) NULL;
ALTER TABLE oauth_audit_logs ADD COLUMN tenant_id CHAR(26) NULL;
ALTER TABLE oauth_audit_logs ADD COLUMN organization_context JSON NULL;
```

### 8.2 Scope Validation Logic

```php
private function validateScopesForUser($requestedScopes, $availableScopes, $organization, $userOrganizations)
{
    $validScopes = array_intersect($requestedScopes, array_keys($availableScopes));
    
    if ($organization) {
        $hasOrganizationAccess = $userOrganizations->contains('id', $organization->id);
        
        if (!$hasOrganizationAccess) {
            // Remove organization-specific scopes
            $organizationScopes = ['organization:read', 'organization:write', 'organization:members', 'organization:admin', 'organization:hierarchy'];
            $validScopes = array_diff($validScopes, $organizationScopes);
        } else {
            // Check for management-level access
            $userMembership = Auth::user()->memberships()
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->first();
            
            if (!$userMembership || !$userMembership->isManagementMembership()) {
                $adminScopes = ['organization:admin', 'organization:hierarchy'];
                $validScopes = array_diff($validScopes, $adminScopes);
            }
        }
    }
    
    return $validScopes;
}
```

## 9. Implementation Requirements

### 9.1 Organization-First Architecture

- All OAuth clients must be associated with organizations
- No legacy non-organization clients are supported
- Standard OAuth scopes work within organizational context
- Organization membership is validated for all authorization flows

### 9.2 Database Schema Requirements

1. **Required Fields**: `organization_id` is required on all OAuth clients
2. **Data Integrity**: Foreign key constraints ensure valid organization associations
3. **Legacy Cleanup**: All orphaned clients and tokens are removed
4. **Audit Context**: All OAuth events include organizational and tenant context

## 10. Standards Compliance

### 10.1 OAuth 2.0 Compliance

This implementation enhances but remains compliant with:
- RFC 6749: The OAuth 2.0 Authorization Framework
- RFC 6750: The OAuth 2.0 Bearer Token Usage
- OpenID Connect Core 1.0
- RFC 7662: OAuth 2.0 Token Introspection

### 10.2 Security Standards

- PKCE (RFC 7636) is supported for all authorization flows
- JWT tokens follow RFC 7519 standards
- All sensitive operations require HTTPS
- Comprehensive audit logging for compliance requirements

## 11. Conclusion

This RFC defines a comprehensive OAuth 2.0 framework that makes organizational hierarchy and multi-tenancy core requirements rather than extensions. The implementation provides:

- **Organizational Security**: All access is organization-scoped with proper membership validation
- **Tenant Isolation**: Complete data isolation with controlled cross-tenant access
- **Audit Compliance**: Full organizational and tenant context in all audit logs
- **Enterprise Ready**: Designed for complex organizational structures and governance requirements

By removing legacy compatibility layers and making organization association mandatory, this framework provides a more secure, auditable, and manageable OAuth implementation for enterprise applications.

## Appendix A: Example Implementations

### A.1 Client Registration
```bash
curl -X POST /oauth/clients \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <user_token>" \
  -d '{
    "name": "Acme Portal",
    "redirect_uris": ["https://portal.acme.com/callback"],
    "organization_id": "01HXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
    "allowed_scopes": ["openid", "profile", "organization:read"],
    "client_type": "confidential"
  }'

# Note: User must have management-level access to the specified organization
```

### A.2 Authorization Flow
```bash
# 1. Authorization Request
GET /oauth/authorize?client_id=123&redirect_uri=https://app.com/callback&response_type=code&scope=openid+organization:read&state=xyz

# 2. User grants consent (organization context validated)

# 3. Authorization Response
GET https://app.com/callback?code=abc123&state=xyz

# 4. Token Request
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&code=abc123&client_id=123&client_secret=secret&redirect_uri=https://app.com/callback

# 5. Token Response with organization context
{
  "access_token": "eyJ...",
  "token_type": "bearer",
  "expires_in": 3600,
  "refresh_token": "def456",
  "scope": "openid organization:read"
}
```

---

*This RFC is a living document and will be updated as the implementation evolves and new requirements are identified.*