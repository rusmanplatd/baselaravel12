# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Naming Conventions and Code Style

### Naming Rules
- **NO marketing adjectives**: Never use "advanced", "enhanced", "improved", "better", "superior", "features", "premium", or similar terms in code names
- **Be descriptive and direct**: Use specific, functional names that describe what the code does
- **Avoid redundancy**: Don't use "features" suffix (e.g., `useE2EE` not `useE2EEFeatures`)
- **Keep it simple**: Prefer shorter, clearer names over verbose ones
- **Examples**:
  - ✅ `useE2EE`, `MessageScheduler`, `DisappearingMessages`
  - ❌ `useAdvancedE2EE`, `EnhancedMessageScheduler`, `ImprovedFeatures`

### Integration over Creation
- Always integrate new functionality into existing hooks/components when possible
- Only create separate files when functionality is truly distinct
- Prefer extending existing interfaces over creating new ones
- Merge related functionality rather than creating parallel systems

## Architecture Overview

This is a Laravel 12 + React fullstack application using:
- **Backend**: Laravel 12 with Inertia.js for SPA functionality
- **Frontend**: React 19 with TypeScript, Vite for bundling
- **UI Library**: shadcn/ui components with Radix UI primitives and Tailwind CSS v4
- **Testing**: Pest (PHP) for backend, Playwright for E2E tests
- **Database**: PostgreSQL (default), migrations in `database/migrations/`
- **SSR**: Enabled via Inertia.js server-side rendering (port 13714)
- **Authentication**: Laravel Passport for OAuth 2.0/OIDC, Laravel Breeze-style UI flows, WebAuthn/Passkeys support
- **Extensions**: Multiple Spatie packages (permissions, activity logs, event sourcing)
- **Organization Management**: Hierarchical organization structure with units, positions, and memberships
- **Chat System**: End-to-end encrypted chat with multi-device and quantum-resistant cryptography support
- **File Storage**: MinIO S3-compatible storage with encrypted file handling
- **Containerization**: Docker Compose setup for local, dev, staging, and production environments

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

### E2E and Testing
```bash
npm run test:e2e        # Run all Playwright E2E tests
npm run test:e2e:ui     # Run E2E tests with UI mode
npm run test:e2e:headed # Run E2E tests in headed mode
npm run test:e2e:debug  # Debug E2E tests

./scripts/test-e2ee.sh     # Comprehensive E2EE chat testing
./scripts/test-e2ee.sh unit        # Run only unit tests
./scripts/test-e2ee.sh multidevice # Run only multi-device tests
./scripts/test-e2ee.sh quantum     # Run quantum cryptography tests

php artisan test --filter=Chat      # Run specific chat test group
```

### Docker Development
```bash
make help           # Show all available commands
make install        # Install and setup the project
make local          # Start local development environment
make local-fresh    # Start with fresh database
make test           # Run complete test suite
make logs service=app # Show logs for specific service
make shell          # Open shell in app container
make clean          # Clean up Docker resources
```

## Project Structure

### Backend (Laravel)
- **Routes**: 
  - `routes/web.php` - Main web routes
  - `routes/webs/auth.php` - Authentication routes
  - `routes/webs/oauth.php` - OAuth/OIDC routes
  - `routes/webs/settings.php` - Settings routes
  - `routes/api.php` - API endpoints
  - `routes/channels.php` - Broadcasting channels
- **Controllers**: `app/Http/Controllers/` organized by feature:
  - `Api/Chat/` - Chat system controllers (Conversation, Message, Encryption, etc.)
  - `Api/QuantumController.php` - Quantum cryptography API endpoints
  - `Api/` - Organization, User, Permission, Role controllers
  - `Auth/` - Authentication controllers
  - `OAuth/` - OAuth 2.0 server controllers
  - `Settings/` - Profile and password management
- **Models**: `app/Models/` including:
  - Organization hierarchy: `Organization`, `OrganizationUnit`, `OrganizationPosition`, `OrganizationMembership`
  - Chat system: `Chat/Conversation`, `Chat/Message`, `Chat/EncryptionKey`, `Chat/Participant`
  - Authentication: `User`, `UserDevice`, `UserMfaSetting`
  - OAuth: `Client`, `OAuthAuditLog`, `OAuthScope`
- **Services**: `app/Services/` - Business logic:
  - `ChatEncryptionService.php` - Chat encryption with quantum algorithm support
  - `QuantumCryptoService.php` - NIST-approved post-quantum cryptography (ML-KEM)
  - `Crypto/MLKEMProviderInterface.php` - ML-KEM provider abstraction
  - `Crypto/LibOQSMLKEMProvider.php` - Production LibOQS implementation
  - `Crypto/FallbackMLKEMProvider.php` - Development/testing fallback
  - `MultiDeviceEncryptionService.php` - Multi-device quantum support
  - `ChatFileService.php` - File handling and other services
- **Middleware**: `app/Http/Middleware/` - Rate limiting, permissions, chat security, tenant context

### Frontend (React)
- **Entry Points**: 
  - `resources/js/app.tsx` - client-side entry
  - `resources/js/ssr.tsx` - server-side rendering entry
- **Pages**: `resources/js/pages/` - Inertia.js pages:
  - `chat.tsx` - Main chat interface
  - `dashboard.tsx` - Dashboard
  - `welcome.tsx` - Landing page
- **Components**: `resources/js/components/` - reusable components and shadcn/ui components
- **Layouts**: `resources/js/layouts/` - app layouts (app-layout, auth-layout)
- **Hooks**: `resources/js/hooks/` - custom React hooks:
  - `useChat.ts` - Chat functionality
  - `useE2EE.ts` - End-to-end encryption with quantum algorithm support
  - `useQuantumE2EE.ts` - Quantum-resistant cryptography management
  - `useChatPagination.ts` - Chat message pagination
  - `usePermissions.tsx` - Permission checking
- **Services**: `resources/js/services/` - Frontend services:
  - `MultiDeviceE2EEService.ts` - Multi-device encryption
  - `OptimizedE2EEService.ts` - Performance optimized encryption with quantum support
  - `QuantumE2EEService.ts` - Client-side quantum cryptography
  - `SecurityMonitoringService.ts` - Security monitoring
- **Types**: `resources/js/types/` - TypeScript definitions (chat.ts, index.d.ts)
- **Utils**: `resources/js/utils/` - Utility functions and encryption helpers:
  - `QuantumMigrationUtils.ts` - Migration utilities for quantum transition
  - Standard encryption and chat utilities

### Configuration
- **TypeScript**: Path aliases configured (`@/*` maps to `resources/js/*`), strict mode enabled
- **shadcn/ui**: Configured in `components.json` with Tailwind CSS integration, Lucide icons
- **Vite**: Configured for Laravel with React, Tailwind CSS v4, and SSR support
- **Docker**: Multi-environment setup (local, dev, staging, prod) with Docker Compose
- **Storage**: MinIO S3-compatible storage configuration for encrypted file handling

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
- shadcn/ui components in `resources/js/components/ui/`:
  - `quantum-status-badge.tsx` - Algorithm status indicators with tooltips
  - `quantum-health-indicator.tsx` - System health and device readiness display
  - `quantum-device-manager.tsx` - Device management interface with migration
  - `quantum-admin-panel.tsx` - Complete admin dashboard for quantum systems
  - Standard UI components (button, card, alert, etc.)
- Appearance/theme system with light/dark mode support
- Sidebar navigation with user management

## Testing

### Backend Tests (Pest)
```bash
php artisan test                    # Run all Pest tests
php artisan test --filter=Auth      # Run specific test group
php artisan test tests/Feature/     # Run feature tests only
php artisan test --stop-on-failure  # Stop on first failure
composer test                       # Run tests via composer (includes setup)
```

Tests are located in:
- `tests/Feature/` - Feature tests including auth flows, chat, E2EE, organization management
- `tests/Unit/` - Unit tests for services and utilities
- `tests/e2e/` - End-to-end tests using Playwright

### E2E Tests (Playwright)
```bash
npm run test:e2e        # Run all E2E tests
npm run test:e2e:ui     # Run with Playwright UI
npm run test:e2e:headed # Run in headed browser mode
npm run test:e2e:debug  # Debug mode
```

E2E test categories:
- Chat functionality and messaging
- End-to-end encryption workflows
- Multi-device encryption scenarios  
- Authentication and authorization flows
- Organization management features

### Specialized Testing Scripts
```bash
./scripts/test-e2ee.sh          # Comprehensive E2EE testing suite
./scripts/test-e2ee.sh unit     # Unit tests only
./scripts/test-e2ee.sh security # Security validation tests
./scripts/test-minio.sh         # MinIO integration testing
```

### Database
- Uses PostgreSQL by default (configurable via Docker Compose)
- Extensive migration set including:
  - OAuth 2.0/OIDC tables (Passport) with organization-scoped clients
  - Organization hierarchy tables (organizations, units, positions, memberships)  
  - Chat system tables (conversations, messages, encryption keys, participants)
  - Permission tables (Spatie), activity logs, event sourcing, multitenancy
  - Passkeys/WebAuthn support tables
  - User device management and trusted device tracking
- Comprehensive seeders in `database/seeders/`:
  - Organizational structure and position hierarchies
  - OAuth clients and scopes
  - Permission system setup
  - Industry-specific permissions

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
- **Google-Style Scopes**: Modern URL-based OAuth scopes (https://api.yourcompany.com/auth/organization.readonly, etc.)
- **Rate Limiting**: Separate rate limits for different OAuth endpoints
- **Audit Logging**: Complete audit trail for OAuth operations with tenant context

### End-to-End Encrypted Chat System
- **Multi-Device Support**: Seamless encryption across multiple user devices
- **Quantum-Resistant Cryptography**: NIST-approved post-quantum algorithms (ML-KEM-512/768/1024)
- **Hybrid Encryption**: Smooth transition from RSA to quantum-resistant algorithms
- **Key Management**: Sophisticated encryption key generation, rotation, and recovery
- **File Encryption**: Encrypted file uploads and downloads with thumbnail support
- **Performance Optimized**: Bulk encryption/decryption operations for better performance
- **Security Monitoring**: Real-time security anomaly detection and forensic audit trails
- **Cross-Platform Compatibility**: Support for different device types and encryption implementations
- **Algorithm Negotiation**: Automatic selection of best compatible encryption algorithms
- **Migration Tools**: Comprehensive utilities for upgrading to quantum-resistant encryption

### Quantum Cryptography Features
- **NIST-Approved Algorithms**: ML-KEM (CRYSTALS-KYBER) key encapsulation mechanism
- **Security Levels**: Support for ML-KEM-512, ML-KEM-768, and ML-KEM-1024
- **Hybrid Cryptography**: RSA+ML-KEM combinations for transition periods
- **Provider Architecture**: Pluggable ML-KEM implementations (LibOQS production, fallback testing)
- **Device Readiness Assessment**: Automatic evaluation of quantum migration readiness
- **Migration Strategies**: Immediate, gradual, and hybrid migration approaches
- **Real-time Health Monitoring**: Quantum system status and device capability tracking
- **Admin Dashboard**: Comprehensive quantum security management interface
- **Backward Compatibility**: Seamless interoperability with existing RSA-encrypted conversations
- **Performance Optimization**: Caching, batching, and background processing for quantum operations

### Security Features
- **WebAuthn/Passkeys**: Modern passwordless authentication
- **Two-Factor Authentication**: TOTP/Google Authenticator support
- **Rate Limiting**: Comprehensive rate limiting across OAuth, auth, and chat endpoints  
- **Activity Logging**: Full activity logging with Spatie activity log package
- **Trusted Device Management**: Device registration and trust verification
- **Session Management**: Advanced session tracking and termination capabilities
- **Security Audit Trails**: Comprehensive logging for OAuth operations and chat encryption events

## Development Notes

### Key Development Patterns
- **Service Layer**: Business logic separated into dedicated service classes (`app/Services/`)
- **Repository Pattern**: Models use factories for testing with comprehensive relationship support
- **Event-Driven Architecture**: Laravel events for chat notifications, presence updates, and security alerts
- **Multi-Tenant Architecture**: Organization-scoped permissions and OAuth clients with tenant context
- **API Rate Limiting**: Different rate limits for various endpoint categories (auth, chat, OAuth)

### Environment Setup
- **Concurrency Scripts**: `composer dev` and `composer dev:ssr` run multiple services in parallel
- **Docker Integration**: Full containerization with separate configs for local/dev/staging/prod
- **SSR Configuration**: Inertia.js server-side rendering on port 13714
- **File Storage**: MinIO S3-compatible storage with encryption support
- **Database Testing**: Separate test database with comprehensive seeding

### Code Quality Tools
- **Backend**: Laravel Pint for PHP formatting, Pest for testing
- **Frontend**: ESLint + Prettier with TypeScript strict mode
- **E2E Testing**: Playwright with comprehensive chat and encryption test suites
- **Specialized Testing**: Custom scripts for E2EE validation and performance benchmarking

### Data Manipulation Rules
- **NEVER use `php artisan tinker`**: Tinker should never be used for data manipulation, seeding, or any database operations
- **Use proper seeders**: For database seeding, always use `php artisan db:seed` with proper seeder classes in `database/seeders/`
- **Use migrations**: For schema changes, create and run migrations with `php artisan make:migration` and `php artisan migrate`
- **Use factories**: For test data generation, use model factories with `php artisan make:factory`
- **Use Artisan commands**: Create custom Artisan commands for one-time data operations with `php artisan make:command`
- **Production safety**: Tinker poses significant risks in production environments and should be avoided entirely

### Key Configuration Files
- `composer.json` - Development scripts and dependency management
- `docker-compose.yml` - Local development environment
- `vite.config.ts` - Frontend build configuration with SSR support
- `components.json` - shadcn/ui configuration
- `tsconfig.json` - TypeScript configuration with path aliases

## Quantum Cryptography API

### Backend API Endpoints
All quantum endpoints are available under `/api/v1/quantum/` with authentication required:

```php
// Key generation
POST /api/v1/quantum/generate-keypair
{
  "algorithm": "ML-KEM-768",  // ML-KEM-512, ML-KEM-768, ML-KEM-1024
  "security_level": 768
}

// Key encapsulation  
POST /api/v1/quantum/encapsulate
{
  "public_key": "base64_encoded_key",
  "algorithm": "ML-KEM-768"
}

// Key decapsulation
POST /api/v1/quantum/decapsulate  
{
  "ciphertext": "base64_encoded_ciphertext", 
  "private_key": "base64_encoded_key",
  "algorithm": "ML-KEM-768"
}

// Device registration
POST /api/v1/quantum/register-device
{
  "device_name": "My Phone",
  "device_type": "mobile",
  "capabilities": ["ml-kem-768", "ml-kem-1024"]
}

// Algorithm negotiation
GET /api/v1/quantum/conversations/{id}/negotiate-algorithm

// Health check
GET /api/v1/quantum/health
```

### Frontend Usage Examples

#### Basic Quantum Encryption
```typescript
import { useQuantumE2EE } from '@/hooks/useQuantumE2EE';

const { encryptMessage, quantumStatus } = useQuantumE2EE();

// Encrypt with quantum algorithm
const encrypted = await encryptMessage(
  "Hello, quantum world!", 
  conversationId,
  "ML-KEM-768"
);
```

#### Device Management
```tsx
import { QuantumDeviceManager } from '@/components/ui/quantum-device-manager';

<QuantumDeviceManager 
  showAddDevice={true}
  className="max-w-4xl"
/>
```

#### Health Monitoring
```tsx
import { QuantumHealthIndicator } from '@/components/ui/quantum-health-indicator';

<QuantumHealthIndicator 
  showDetails={true}
  autoRefresh={true}
  refreshInterval={30000}
/>
```

#### Migration Management
```typescript
import { quantumMigrationUtils } from '@/utils/QuantumMigrationUtils';

// Assess migration readiness
const assessment = await quantumMigrationUtils.assessMigrationReadiness();

// Start migration
const migrationId = await quantumMigrationUtils.startMigration('gradual');

// Monitor progress
const status = quantumMigrationUtils.getMigrationStatus();
```

#### Admin Dashboard
```tsx
import { QuantumAdminPanel } from '@/components/ui/quantum-admin-panel';

<QuantumAdminPanel className="container mx-auto" />
```

### Quantum Algorithm Support

#### Supported Algorithms
- **ML-KEM-512**: 128-bit security level, smaller keys (fastest)
- **ML-KEM-768**: 192-bit security level, balanced performance (recommended)
- **ML-KEM-1024**: 256-bit security level, largest keys (highest security)
- **HYBRID-RSA4096-MLKEM768**: Transition mode combining RSA and ML-KEM

#### Algorithm Selection Priority
1. **ML-KEM-768**: Default for new conversations
2. **HYBRID-RSA4096-MLKEM768**: For mixed classical/quantum device compatibility
3. **RSA-4096-OAEP**: Fallback for legacy devices

#### Encryption Version History
- **v1**: Legacy (deprecated)
- **v2**: RSA-based encryption (current classical)
- **v3**: Quantum-resistant encryption (ML-KEM based)

### Testing Quantum Features

#### Backend Testing
```bash
# Run quantum-specific tests
php artisan test --filter=Quantum

# Test ML-KEM provider implementations
php artisan test tests/Feature/QuantumCryptoServiceTest.php

# Test API endpoints
php artisan test tests/Feature/Api/QuantumControllerTest.php
```

#### Frontend Testing  
```bash
# Run quantum E2EE tests
./scripts/test-e2ee.sh quantum

# Test device migration
npm run test:e2e -- --grep "quantum.*migration"

# Test algorithm negotiation
npm run test:e2e -- --grep "quantum.*negotiation"
```

#### Development Setup
```bash
# Install LibOQS (production ML-KEM provider)
# Ubuntu/Debian:
sudo apt-get install liboqs-dev

# macOS:
brew install liboqs

# For development/testing, fallback provider is used automatically
# when LibOQS is not available
```
