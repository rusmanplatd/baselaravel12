# Master Data Menu Implementation Guide

This document provides a comprehensive guide for implementing master data modules in the Laravel 12 + React application. The implementation follows the geography module pattern and can be used as a reference for creating new master data menus.

## Table of Contents

1. [Overview](#overview)
2. [Backend Implementation](#backend-implementation)
3. [Frontend Implementation](#frontend-implementation)
4. [File Structure](#file-structure)
5. [Implementation Checklist](#implementation-checklist)
6. [Best Practices](#best-practices)

## Overview

The master data implementation follows a hierarchical CRUD pattern with:
- **Permission-based access control** using Laravel permissions
- **API-driven data management** with both web routes and API endpoints
- **Advanced filtering and sorting** with query builders
- **Activity logging** for audit trails
- **Responsive UI** with shadcn/ui components
- **Hierarchical relationships** between entities

### Key Features
- Full CRUD operations (Create, Read, Update, Delete)
- Advanced search and filtering
- Sortable columns
- Pagination
- Permission guards
- Activity logging
- Responsive design
- Real-time data updates

## Backend Implementation

### 1. Database Structure

#### Migration Pattern
```php
// database/migrations/xxxx_create_ref_geo_country_table.php
Schema::create('ref_geo_country', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('code', 10)->unique();
    $table->string('name');
    $table->string('iso_code', 3)->nullable();
    $table->string('phone_code', 10)->nullable();
    $table->ulid('created_by')->nullable();
    $table->ulid('updated_by')->nullable();
    $table->timestamps();
    
    $table->index(['code', 'name']);
});
```

**Key Points:**
- Use ULID as primary keys
- Include `created_by` and `updated_by` for tracking
- Add appropriate indexes for performance
- Use nullable fields for optional data

### 2. Model Implementation

#### Base Model Pattern
```php
// app/Models/Master/Geo/Country.php
<?php

namespace App\Models\Master\Geo;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Country extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $table = 'ref_geo_country';

    protected $fillable = [
        'code',
        'name',
        'iso_code',
        'phone_code',
        'created_by',
        'updated_by',
    ];

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'iso_code', 'phone_code'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Country {$eventName}")
            ->useLogName('geography');
    }

    // Relationships
    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class, 'country_id');
    }

    // Business Logic
    public function canDeleted(): bool
    {
        return !$this->provinces()->exists();
    }
}
```

**Key Components:**
- **HasUlids**: For ULID primary keys
- **LogsActivity**: For audit trails
- **Scopes**: For reusable query filters
- **Relationships**: Define hierarchical connections
- **Business Logic**: Custom validation methods

### 3. Controllers

#### Web Controller Pattern
```php
// app/Http/Controllers/Geography/CountryController.php
<?php

namespace App\Http\Controllers\Geography;

use App\Http\Controllers\Controller;
use App\Models\Master\Geo\Country;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CountryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:geo_country:read')->only(['index', 'show']);
        $this->middleware('permission:geo_country:write')->only(['create', 'store', 'edit', 'update']);
        $this->middleware('permission:geo_country:delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        return Inertia::render('Geography/CountriesApi');
    }

    public function create()
    {
        return Inertia::render('Geography/CountryCreate');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:ref_geo_country,code',
            'name' => 'required|string|max:255',
            'iso_code' => 'nullable|string|max:3',
            'phone_code' => 'nullable|string|max:10',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $country = Country::create($validated);

        activity()
            ->performedOn($country)
            ->causedBy(auth()->user())
            ->log('Created country '.$country->name);

        return redirect()
            ->route('geography.countries')
            ->with('success', 'Country created successfully.');
    }

    // ... other CRUD methods
}
```

#### API Controller Pattern
```php
// app/Http/Controllers/Api/Geo/CountryController.php
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class CountryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $perPage = in_array($perPage, [5, 10, 15, 25, 50, 100]) ? $perPage : 15;

        $countries = QueryBuilder::for(Country::class)
            ->allowedFilters([
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('iso_code'),
                AllowedFilter::partial('phone_code'),
            ])
            ->allowedSorts([
                'code', 'name', 'iso_code', 'phone_code', 'created_at',
            ])
            ->defaultSort('name')
            ->with(['provinces'])
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($countries);
    }

    // List endpoint for dropdowns
    public function list(Request $request): JsonResponse
    {
        $countries = QueryBuilder::for(Country::class)
            ->allowedFilters([AllowedFilter::partial('name')])
            ->allowedSorts(['name'])
            ->defaultSort('name')
            ->select(['id', 'code', 'name'])
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json($countries);
    }
}
```

### 4. Routes Configuration

#### Web Routes
```php
// routes/web.php
Route::prefix('geography')->name('geography.')->group(function () {
    Route::get('countries', [CountryController::class, 'index'])->name('countries');
    Route::get('countries/create', [CountryController::class, 'create'])->name('countries.create');
    Route::post('countries', [CountryController::class, 'store'])->name('countries.store');
    Route::get('countries/{country}', [CountryController::class, 'show'])->name('countries.show');
    Route::get('countries/{country}/edit', [CountryController::class, 'edit'])->name('countries.edit');
    Route::put('countries/{country}', [CountryController::class, 'update'])->name('countries.update');
    Route::delete('countries/{country}', [CountryController::class, 'destroy'])->name('countries.destroy');
});
```

#### API Routes
```php
// routes/api.php
Route::middleware('auth:api')->prefix('v1')->group(function () {
    Route::prefix('geo')->name('api.geo.')->group(function () {
        // List endpoint (must come before resource routes)
        Route::get('countries/list', [CountryController::class, 'list'])->name('countries.list');
        
        // Nested routes
        Route::get('countries/{country}/provinces', [ProvinceController::class, 'byCountry'])->name('provinces.by-country');
        
        // Resource routes
        Route::apiResource('countries', CountryController::class);
    });
});
```

### 5. Permissions System

#### Permission Structure
```
geo_country:read    - View countries
geo_country:write   - Create/Edit countries  
geo_country:delete  - Delete countries
```

#### Seeder Pattern
```php
// database/seeders/GeographyPermissionSeeder.php
$permissions = [
    'geo_country:read',
    'geo_country:write', 
    'geo_country:delete',
    'geo_province:read',
    'geo_province:write',
    'geo_province:delete',
];

foreach ($permissions as $permission) {
    Permission::firstOrCreate(['name' => $permission]);
}
```

## Frontend Implementation

### 1. Page Structure

#### List Page Pattern (CountriesApi.tsx)
```typescript
// resources/js/pages/Geography/CountriesApi.tsx
import { useApiData } from '@/hooks/useApiData';
import { PermissionGuard } from '@/components/permission-guard';

interface Country {
    id: string;
    code: string;
    name: string;
    iso_code: string | null;
    phone_code: string | null;
    provinces_count?: number;
    created_at: string;
    updated_at: string;
}

export default function CountriesApi() {
    const {
        data: countries,
        loading,
        error,
        filters,
        sort,
        updateFilter,
        updateSort,
        updatePerPage,
        goToPage,
        refresh,
    } = useApiData<Country>({
        endpoint: 'countries',
        initialFilters: {
            code: '',
            name: '',
            iso_code: '',
            phone_code: '',
        },
        initialSort: 'name',
    });

    // Component implementation...
}
```

#### Key Features Implementation:

**1. Advanced Search and Filtering:**
```typescript
// Debounced search input
const debouncedUpdateFilter = useCallback(
    debounce((key: string, value: string) => {
        updateFilter(key, value);
    }, 500),
    [updateFilter]
);

const handleFilterChange = (key: string, value: string) => {
    setInputValues(prev => ({ ...prev, [key]: value }));
    debouncedUpdateFilter(key, value);
};
```

**2. Sortable Table Headers:**
```typescript
const handleSort = (field: string) => {
    updateSort(field);
};

const getSortIcon = (field: string) => {
    if (sort === field) {
        return <ArrowUpDown className="h-4 w-4 text-primary" />;
    }
    if (sort === `-${field}`) {
        return <ArrowUpDown className="h-4 w-4 text-primary rotate-180" />;
    }
    return <ArrowUpDown className="h-4 w-4 text-muted-foreground opacity-50" />;
};
```

**3. Permission-Based Actions:**
```typescript
<PermissionGuard permission="geo_country:write">
    <Button onClick={() => router.get(route('geography.countries.edit', country.id))}>
        <Edit className="h-4 w-4" />
    </Button>
</PermissionGuard>

<PermissionGuard permission="geo_country:delete">
    <Button onClick={() => handleDelete(country)}>
        <Trash2 className="h-4 w-4" />
    </Button>
</PermissionGuard>
```

### 2. Form Pages

#### Create/Edit Pattern
```typescript
// resources/js/pages/Geography/CountryCreate.tsx
export default function CountryCreate() {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        iso_code: '',
        phone_code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('geography.countries.store'));
    };

    return (
        <form onSubmit={submit}>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                    <Label htmlFor="code">Country Code *</Label>
                    <Input
                        id="code"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        required
                    />
                    {errors.code && (
                        <p className="text-sm text-destructive">{errors.code}</p>
                    )}
                </div>
                // ... other fields
            </div>
        </form>
    );
}
```

### 3. Custom Hooks

#### useApiData Hook Pattern
```typescript
// resources/js/hooks/useApiData.ts
interface UseApiDataOptions<T> {
    endpoint: string;
    initialFilters?: Record<string, any>;
    initialSort?: string;
    baseUrl?: string;
}

export function useApiData<T>({
    endpoint,
    initialFilters = {},
    initialSort = '',
    baseUrl = '/api/v1/geo'
}: UseApiDataOptions<T>) {
    // Hook implementation with state management
    // Returns: data, loading, error, filters, sort, pagination controls
}
```

## File Structure

```
├── Backend
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Geography/          # Web controllers
│   │   │   │   │   ├── CountryController.php
│   │   │   │   │   ├── ProvinceController.php
│   │   │   │   │   └── ...
│   │   │   │   └── Api/
│   │   │   │       └── Geo/            # API controllers
│   │   │   │           ├── CountryController.php
│   │   │   │           └── ...
│   │   │   └── Requests/
│   │   │       └── Geo/
│   │   │           └── CountryRequest.php
│   │   ├── Models/
│   │   │   └── Master/
│   │   │       └── Geo/
│   │   │           ├── Country.php
│   │   │           ├── Province.php
│   │   │           └── ...
│   │   └── Policies/
│   │       └── Geography/
│   │           └── CountryPolicy.php
│   ├── database/
│   │   ├── migrations/
│   │   │   ├── xxxx_create_ref_geo_country_table.php
│   │   │   └── ...
│   │   └── seeders/
│   │       ├── GeographySeeder.php
│   │       └── GeographyPermissionSeeder.php
│   └── routes/
│       ├── web.php                     # Web routes
│       └── api.php                     # API routes
├── Frontend
│   └── resources/js/
│       ├── pages/
│       │   └── Geography/
│       │       ├── CountriesApi.tsx    # List page
│       │       ├── CountryCreate.tsx   # Create page
│       │       ├── CountryEdit.tsx     # Edit page
│       │       ├── CountryShow.tsx     # Detail page
│       │       └── ...
│       ├── components/
│       │   ├── ui/                     # shadcn/ui components
│       │   ├── permission-guard.tsx    # Permission wrapper
│       │   ├── api-pagination.tsx      # Pagination component
│       │   └── ActivityLogModal.tsx    # Activity log modal
│       ├── hooks/
│       │   └── useApiData.ts           # API data management hook
│       ├── services/
│       │   └── ApiService.ts           # API service layer
│       └── types/
│           └── index.d.ts              # TypeScript definitions
```

## Implementation Checklist

### Backend Setup
- [ ] Create migration with ULID primary key and proper indexes
- [ ] Implement Model with HasUlids, LogsActivity, and relationships
- [ ] Create Web Controller with permission middleware and CRUD operations
- [ ] Create API Controller with QueryBuilder for filtering and sorting
- [ ] Add validation rules in FormRequest classes
- [ ] Set up web routes with proper naming
- [ ] Set up API routes with proper grouping and middleware
- [ ] Create permissions and assign to roles
- [ ] Add model factory for testing
- [ ] Create seeders for initial data

### Frontend Setup
- [ ] Create TypeScript interface for the entity
- [ ] Implement List page with useApiData hook
- [ ] Add advanced search and filtering
- [ ] Implement sortable table headers
- [ ] Add pagination component
- [ ] Create Create page with form validation
- [ ] Create Edit page with pre-filled data
- [ ] Create Show/Detail page
- [ ] Add PermissionGuard components
- [ ] Implement delete confirmation
- [ ] Add activity log modal
- [ ] Add breadcrumb navigation
- [ ] Add loading and error states
- [ ] Test responsive design

### Integration Testing
- [ ] Test CRUD operations
- [ ] Verify permission enforcement
- [ ] Test search and filtering
- [ ] Test sorting functionality
- [ ] Verify pagination
- [ ] Test activity logging
- [ ] Validate error handling
- [ ] Test responsive design

## Best Practices

### Backend Best Practices

1. **Use Consistent Naming:**
   - Models: `Country`, `Province` (singular, PascalCase)
   - Controllers: `CountryController` (singular + Controller)
   - Tables: `ref_geo_country` (descriptive prefix + snake_case)

2. **Implement Permission Checks:**
   - Always use middleware for route protection
   - Follow consistent permission naming: `module_entity:action`
   - Use PermissionGuard in frontend components

3. **Activity Logging:**
   - Configure LogOptions for audit trails
   - Log meaningful events with descriptive messages
   - Use appropriate log names for grouping

4. **Query Optimization:**
   - Use eager loading for relationships
   - Add database indexes for searchable fields
   - Implement query scopes for reusable filters

5. **Validation:**
   - Use FormRequest classes for complex validation
   - Implement unique constraints with proper exclusions
   - Add business logic validation in models

### Frontend Best Practices

1. **Component Organization:**
   - Use consistent file naming (PascalCase for components)
   - Group related pages in feature folders
   - Extract reusable logic into custom hooks

2. **State Management:**
   - Use useApiData hook for consistent data fetching
   - Implement local state for immediate UI feedback
   - Use debouncing for search inputs

3. **User Experience:**
   - Add loading states for all async operations
   - Implement proper error handling and display
   - Use optimistic updates where appropriate
   - Add confirmation dialogs for destructive actions

4. **Accessibility:**
   - Use semantic HTML elements
   - Add proper ARIA labels
   - Ensure keyboard navigation works
   - Maintain proper color contrast

5. **Performance:**
   - Implement debounced search
   - Use pagination for large datasets
   - Optimize re-renders with proper dependencies
   - Use React.memo for expensive components

### Security Best Practices

1. **Input Validation:**
   - Validate on both frontend and backend
   - Sanitize all user inputs
   - Use proper data types and constraints

2. **Authorization:**
   - Implement permission checks at multiple levels
   - Use middleware for route protection
   - Validate permissions in API responses

3. **Data Protection:**
   - Use HTTPS for all communications
   - Implement proper error handling without exposing sensitive data
   - Log security-relevant events

This guide provides a comprehensive foundation for implementing master data modules. Each new module should follow these patterns while adapting to specific business requirements.
