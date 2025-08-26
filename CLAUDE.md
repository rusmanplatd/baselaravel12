# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architecture Overview

This is a Laravel 12 + React fullstack application using:
- **Backend**: Laravel 12 with Inertia.js for SPA functionality
- **Frontend**: React 19 with TypeScript, Vite for bundling
- **UI Library**: shadcn/ui components with Radix UI primitives and Tailwind CSS v4
- **Testing**: Pest (PHP) for backend, no frontend testing setup currently
- **Database**: Postgresql (default), migrations in `database/migrations/`
- **SSR**: Enabled via Inertia.js server-side rendering
- **Authentication**: Laravel Passport for OAuth 2.0/OIDC, Laravel Breeze-style UI flows, WebAuthn/Passkeys support
- **Extensions**: Multiple Spatie packages (permissions, activity logs, event sourcing)
- **Organization Management**: Hierarchical organization structure with units, positions, and memberships

## Development Commands

### Start Development Server
```bash
# Runs Laravel server, queue worker, logs, and Vite in parallel
composer dev

# Development with SSR (builds SSR first, then runs with SSR server)
composer dev:ssr
```

### Frontend Only
```bash
npm run dev          # Start Vite dev server
npm run build        # Build for production
npm run build:ssr    # Build with SSR
```

### Backend
```bash
php artisan serve    # Start Laravel server
php artisan test     # Run Pest tests
composer test        # Same as artisan test
```

### Code Quality
```bash
npm run lint         # ESLint with auto-fix
npm run types        # TypeScript type checking
npm run format       # Prettier formatting
npm run format:check # Check formatting

./vendor/bin/pint    # Laravel Pint (PHP formatting)
```

## Project Structure

### Backend (Laravel)
- **Routes**: `routes/web.php` (main), `routes/auth.php` (authentication), `routes/oauth.php` (OAuth/OIDC), `routes/api.php` (API endpoints)
- **Controllers**: `app/Http/Controllers/` - organized by feature (Auth/, OAuth/, Api/Organization*)
- **Models**: `app/Models/` - includes User, Organization hierarchy (Organization, OrganizationUnit, OrganizationPosition, OrganizationMembership)
- **Middleware**: `app/Http/Middleware/` - includes HandleInertiaRequests for Inertia.js, HandleAppearance, OAuthRateLimiter
- **Config**: Multiple configurations for Spatie packages, Passport OAuth, and Inertia.js (SSR on port 13714)

### Frontend (React)
- **Entry Points**: 
  - `resources/js/app.tsx` - client-side entry
  - `resources/js/ssr.tsx` - server-side rendering entry
- **Pages**: `resources/js/pages/` - Inertia.js pages (auth/, settings/, dashboard.tsx, welcome.tsx)
- **Components**: `resources/js/components/` - reusable components and ui/ directory (shadcn/ui)
- **Layouts**: `resources/js/layouts/` - app layouts (auth/, app/, settings/)
- **Hooks**: `resources/js/hooks/` - custom React hooks
- **Types**: `resources/js/types/` - TypeScript definitions

### Configuration
- **TypeScript**: Path aliases configured (`@/*` maps to `resources/js/*`)
- **shadcn/ui**: Configured in `components.json` with Tailwind CSS integration
- **Vite**: Configured for Laravel with React and Tailwind CSS support

## Key Integrations

### Inertia.js
- Server-side rendering enabled
- Pages are React components in `resources/js/pages/`
- Laravel routes return `Inertia::render()` calls
- Ziggy package provides named route helpers in frontend

### Authentication
- Laravel Breeze-style authentication with Inertia.js
- Complete auth flow: register, login, password reset, email verification
- Auth pages in `resources/js/pages/auth/`

### UI Components
- shadcn/ui components in `resources/js/components/ui/`
- Appearance/theme system with light/dark mode support
- Sidebar navigation with user management

## Testing

### Backend Tests
```bash
php artisan test                    # Run all Pest tests
php artisan test --filter=Auth      # Run specific test group
php artisan test tests/Feature/     # Run feature tests only
```

Tests are located in:
- `tests/Feature/` - feature tests including auth flows
- `tests/Unit/` - unit tests

### Database
- Uses pgsql by default
- Extensive migration set including:
  - OAuth 2.0/OIDC tables (Passport) with organization-scoped clients
  - Organization hierarchy tables (organizations, units, positions, memberships)
  - Permission tables (Spatie), activity logs, event sourcing, multitenancy
  - Passkeys/WebAuthn support tables
- Seeders available in `database/seeders/` including organizational structure seeders

## Key Features

### Organization Management
- **Hierarchical Structure**: Organizations can have parent-child relationships with automatic path/level management
- **Organization Types**: holding_company, subsidiary, division, branch, department, unit
- **Organization Units**: Various unit types (boards, committees, divisions, departments, etc.)
- **Position Management**: Hierarchical position levels with salary ranges and qualifications
- **Membership System**: Users can have memberships across multiple organizations with different roles

### OAuth 2.0 & OIDC Provider
- **Full OAuth 2.0 Server**: Authorization code, client credentials, refresh token flows
- **OpenID Connect**: Complete OIDC implementation with discovery endpoints
- **Organization-Scoped Clients**: All OAuth clients must be associated with an organization
- **Dynamic Scopes**: Organization-specific scopes (organization:read, organization:admin, etc.)
- **Rate Limiting**: Separate rate limits for different OAuth endpoints
- **Audit Logging**: Complete audit trail for OAuth operations with tenant context

### Security Features
- **WebAuthn/Passkeys**: Modern passwordless authentication
- **Two-Factor Authentication**: TOTP/Google Authenticator support
- **Rate Limiting**: Comprehensive rate limiting across OAuth and auth endpoints
- **Activity Logging**: Full activity logging with Spatie activity log package

## Development Notes

- Concurrency script in `composer.json` runs all services together
- SSR requires Node.js server on port 13714
- Tailwind CSS v4 configuration with CSS variables
- ESLint + Prettier configured for code formatting
- TypeScript strict mode enabled
