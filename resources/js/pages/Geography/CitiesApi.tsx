import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { SearchableSelect, type SearchableSelectItem } from '@/components/ui/searchable-select';
import { PermissionGuard } from '@/components/permission-guard';
import ActivityLogModal from '@/components/ActivityLogModal';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    Eye,
    Edit,
    Trash2,
    Plus,
    Search,
    ArrowUpDown,
    RotateCcw,
    X,
    SortAsc,
    SortDesc,
    FileText,
    Building
} from 'lucide-react';
import React, { useState, useCallback, useEffect } from 'react';
import { usePublicApiData } from '@/hooks/usePublicApiData';
import { debounce } from 'lodash';
import { apiService } from '@/services/ApiService';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

interface Country {
    id: string;
    name: string;
    code: string;
}

interface Province {
    id: string;
    name: string;
    code: string;
    country: Country;
}

interface City {
    id: string;
    province_id: string;
    code: string;
    name: string;
    province: Province;
    district_count?: number;
    created_at: string;
    updated_at: string;
    updated_by?: { name: string } | null;
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: '', title: 'Cities' },
];

export default function CitiesApi() {
    const {
        data: cities,
        loading,
        error,
        sort,
        perPage,
        currentPage,
        totalPages,
        total,
        from,
        to,
        updateFilter,
        updateSort,
        updatePerPage,
        goToPage,
        refresh,
        clearFilters,
    } = usePublicApiData<City>({
        endpoint: '/api/v1/geo/cities',
        initialFilters: {
            code: '',
            name: '',
            province_id: '',
        },
        initialSort: 'name',
        initialPerPage: 15,
    });

    const [inputValues, setInputValues] = useState({
        code: '',
        name: '',
        province_id: '',
    });

    const [provinces, setProvinces] = useState<Province[]>([]);
    const [provincesLoading, setProvincesLoading] = useState(false);

    const [activityLogModal, setActivityLogModal] = useState({
        isOpen: false,
        subjectType: '',
        subjectId: '',
        title: '',
    });

    // Load provinces for filter dropdown
    useEffect(() => {
        setProvincesLoading(true);
        apiService.get<Province[]>('/api/v1/geo/provinces/list')
            .then(data => setProvinces(data))
            .catch(console.error)
            .finally(() => setProvincesLoading(false));
    }, []);

    // Refetch provinces based on search query
    const handleProvincesRefetch = useCallback((searchQuery: string) => {
        setProvincesLoading(true);
        apiService.get<Province[]>(`/api/v1/geo/provinces/list?filter[name]=${encodeURIComponent(searchQuery)}`)
            .then(data => setProvinces(data))
            .catch(console.error)
            .finally(() => setProvincesLoading(false));
    }, []);

    // Clear provinces filter and reload all provinces
    const handleProvincesClear = useCallback(() => {
        setProvincesLoading(true);
        apiService.get<Province[]>('/api/v1/geo/provinces/list')
            .then(data => setProvinces(data))
            .catch(console.error)
            .finally(() => setProvincesLoading(false));
    }, []);

    // Convert provinces to SearchableSelectItem format
    const provinceSelectItems: SearchableSelectItem[] = provinces.map((province) => ({
        value: province.id,
        label: `${province.name} (${province.country.name})`,
        searchText: `${province.name} ${province.code} ${province.country.name}`,
    }));

    const debouncedUpdateFilter = useCallback(
        debounce((key: string, value: string) => {
            updateFilter(key, value);
        }, 500),
        [updateFilter]
    );

    const handleFilterChange = (key: string, value: string) => {
        // Update input immediately for UI responsiveness
        setInputValues(prev => ({ ...prev, [key]: value }));
        // Debounce the actual API call
        debouncedUpdateFilter(key, value);
    };

    const handleSort = (field: string) => {
        updateSort(field);
    };

    const getSortIcon = (field: string) => {
        if (sort === field) {
            return <SortAsc className="h-4 w-4" />;
        }
        if (sort === `-${field}`) {
            return <SortDesc className="h-4 w-4" />;
        }
        return <ArrowUpDown className="h-4 w-4 opacity-50" />;
    };

    const handleClearFilters = () => {
        setInputValues({
            code: '',
            name: '',
            province_id: '',
        });
        clearFilters();
    };

    const hasActiveFilters = Object.values(inputValues).some(value => value !== '');

    const getActiveFilters = () => {
        const activeFilters: Array<{key: string, value: string, label: string, displayValue: string}> = [];

        Object.entries(inputValues).forEach(([key, value]) => {
            if (value !== '') {
                let label = '';
                let displayValue = value;

                switch (key) {
                    case 'code':
                        label = 'Code';
                        break;
                    case 'name':
                        label = 'Name';
                        break;
                    case 'province_id': {
                        label = 'Province';
                        const province = provinces.find(p => p.id === value);
                        displayValue = province?.name || value;
                        break;
                    }
                }

                activeFilters.push({ key, value, label, displayValue });
            }
        });

        return activeFilters;
    };

    const removeFilter = (filterKey: string) => {
        handleFilterChange(filterKey, '');
    };

    const handleDelete = async (city: City) => {
        if (confirm(`Are you sure you want to delete the city "${city.name}"?`)) {
            try {
                await apiService.delete(`/api/v1/geo/cities/${city.id}`);
                refresh();
            } catch (error) {
                console.error('Error deleting city:', error);
            }
        }
    };

    const showActivityLog = (city: City) => {
        setActivityLogModal({
            isOpen: true,
            subjectType: 'App\\Models\\Master\\Geo\\City',
            subjectId: city.id,
            title: `${city.name} (${city.code})`,
        });
    };

    if (error) {
        return (
            <AppLayout breadcrumbs={breadcrumbItems}>
                <Head title="Cities" />
                <div className="text-center py-8">
                    <p className="text-red-500">Error: {error}</p>
                    <Button onClick={refresh} className="mt-4">
                        Try Again
                    </Button>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbItems}>
            <Head title="Cities" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Cities</h1>
                        <p className="text-muted-foreground">
                            Manage cities and their geographic information
                        </p>
                    </div>
                    <PermissionGuard permission="geo_city:write">
                        <Button onClick={() => window.location.href = route('geography.cities.create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add City
                        </Button>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Cities ({total})</CardTitle>
                        <CardDescription>
                            View and manage all cities in the system
                        </CardDescription>

                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4 z-10" />
                                    <Input
                                        placeholder="Search by code..."
                                        value={inputValues.code}
                                        onChange={(e) => handleFilterChange('code', e.target.value)}
                                        className="pl-10 pr-10"
                                    />
                                    {inputValues.code && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleFilterChange('code', '')}
                                            className="absolute right-1 top-1/2 transform -translate-y-1/2 h-8 w-8 p-0 hover:bg-muted"
                                        >
                                            <X className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4 z-10" />
                                    <Input
                                        placeholder="Search by name..."
                                        value={inputValues.name}
                                        onChange={(e) => handleFilterChange('name', e.target.value)}
                                        className="pl-10 pr-10"
                                    />
                                    {inputValues.name && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleFilterChange('name', '')}
                                            className="absolute right-1 top-1/2 transform -translate-y-1/2 h-8 w-8 p-0 hover:bg-muted"
                                        >
                                            <X className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                                <SearchableSelect
                                    placeholder="Select a province..."
                                    items={provinceSelectItems}
                                    value={inputValues.province_id}
                                    onValueChange={(value) => handleFilterChange('province_id', value)}
                                    emptyLabel="All Provinces"
                                    searchPlaceholder="Search provinces..."
                                    onRefetch={handleProvincesRefetch}
                                    onClear={handleProvincesClear}
                                    refetchDelay={500}
                                    disabled={provincesLoading}
                                />
                            </div>
                            

                            {hasActiveFilters && (
                                <div className="flex items-center gap-2 flex-wrap pt-2 border-t">
                                    <span className="text-sm text-muted-foreground">Active filters:</span>
                                    {getActiveFilters().map((filter) => (
                                        <Badge key={filter.key} variant="secondary" className="gap-1">
                                            {filter.label}: {filter.displayValue}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => removeFilter(filter.key)}
                                                className="h-4 w-4 p-0 hover:bg-transparent"
                                            >
                                                <X className="h-3 w-3" />
                                            </Button>
                                        </Badge>
                                    ))}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleClearFilters}
                                        className="text-xs text-muted-foreground hover:text-destructive px-2 h-6"
                                    >
                                        Clear all
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Separator className="mb-6" />

                        {/* Results Header */}
                        <div className="flex items-center justify-between mb-4">
                            <div className="flex items-center gap-4">
                                <div className="text-sm text-muted-foreground">
                                    {loading ? 'Loading...' : `Showing ${from} to ${to} of ${total} results`}
                                </div>
                                {hasActiveFilters && (
                                    <Button onClick={handleClearFilters} variant="ghost" size="sm" className="text-muted-foreground hover:text-destructive">
                                        <X className="mr-1 h-3 w-3" />
                                        Clear all
                                    </Button>
                                )}
                            </div>
                            <div className="flex items-center gap-2">
                                <Label htmlFor="per-page">Show:</Label>
                                <Select value={perPage.toString()} onValueChange={(value) => updatePerPage(parseInt(value))}>
                                    <SelectTrigger className="w-20">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="5">5</SelectItem>
                                        <SelectItem value="10">10</SelectItem>
                                        <SelectItem value="15">15</SelectItem>
                                        <SelectItem value="25">25</SelectItem>
                                        <SelectItem value="50">50</SelectItem>
                                        <SelectItem value="100">100</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Data Table */}
                        <div className="rounded-md border overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[60px] sticky left-0 bg-background border-r">#</TableHead>
                                        <TableHead className="sticky left-[60px] bg-background border-r">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('code')}
                                            >
                                                Code
                                                {getSortIcon('code')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('name')}
                                            >
                                                Name
                                                {getSortIcon('name')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('province')}
                                            >
                                                Province
                                                {getSortIcon('province')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>Country</TableHead>
                                        <TableHead>Districts</TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('created_at')}
                                            >
                                                Created
                                                {getSortIcon('created_at')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('updated_at')}
                                            >
                                                Updated At
                                                {getSortIcon('updated_at')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>Updated By</TableHead>
                                        <TableHead className="w-[120px] sticky right-0 bg-background border-l">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {loading && cities.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={10} className="text-center py-12">
                                                <RotateCcw className="h-8 w-8 animate-spin mx-auto mb-2" />
                                                Loading cities...
                                            </TableCell>
                                        </TableRow>
                                    ) : cities.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={10} className="text-center py-12">
                                                <div className="flex flex-col items-center">
                                                    <Building className="h-12 w-12 text-muted-foreground mb-4" />
                                                    <h3 className="text-lg font-semibold mb-2">No cities found</h3>
                                                    <p className="text-muted-foreground mb-4">
                                                        {hasActiveFilters
                                                            ? 'No cities match your current filters.'
                                                            : 'Get started by creating your first city.'
                                                        }
                                                    </p>
                                                    {!hasActiveFilters && (
                                                        <PermissionGuard permission="geo_city:write">
                                                            <Button onClick={() => window.location.href = route('geography.cities.create')}>
                                                                <Plus className="mr-2 h-4 w-4" />
                                                                Create City
                                                            </Button>
                                                        </PermissionGuard>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        cities.map((city, index) => (
                                            <TableRow key={city.id}>
                                                <TableCell className="text-center text-muted-foreground sticky left-0 bg-background border-r">
                                                    {(currentPage - 1) * perPage + index + 1}
                                                </TableCell>
                                                <TableCell className="font-medium sticky left-[60px] bg-background border-r">
                                                    <Badge variant="outline">{city.code}</Badge>
                                                </TableCell>
                                                <TableCell>{city.name}</TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="secondary">{city.province.code}</Badge>
                                                        <span>{city.province.name}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="secondary">{city.province.country.code}</Badge>
                                                        <span className="text-sm text-muted-foreground">
                                                            {city.province.country.name}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {city.district_count || 0} districts
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {new Date(city.created_at).toLocaleDateString()}
                                                </TableCell>
                                                <TableCell>
                                                    {new Date(city.updated_at).toLocaleString()}
                                                </TableCell>
                                                <TableCell>
                                                    {city.updated_by ? city.updated_by.name : '-'}
                                                </TableCell>
                                                <TableCell className="sticky right-0 bg-background border-l">
                                                    <div className="flex items-center gap-1">
                                                        <PermissionGuard permission="geo_city:read">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                title="View Details"
                                                                onClick={() => router.get(route('geography.cities.show', city.id))}
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </PermissionGuard>
                                                        <PermissionGuard permission="geo_city:write">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                title="Edit"
                                                                onClick={() => window.location.href = route('geography.cities.edit', city.id)}
                                                            >
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </PermissionGuard>
                                                        <PermissionGuard permission="geo_city:delete">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                title="Delete"
                                                                onClick={() => handleDelete(city)}
                                                                className="text-destructive hover:text-destructive"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </PermissionGuard>
                                                        <PermissionGuard permission="audit_log:read">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                title="Activity Log"
                                                                onClick={() => showActivityLog(city)}
                                                            >
                                                                <FileText className="h-4 w-4" />
                                                            </Button>
                                                        </PermissionGuard>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="flex items-center justify-between pt-4">
                                <div className="text-sm text-muted-foreground">
                                    Page {currentPage} of {totalPages} ({total} total results)
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => goToPage(currentPage - 1)}
                                        disabled={currentPage === 1 || loading}
                                    >
                                        Previous
                                    </Button>

                                    {/* Page numbers */}
                                    {(() => {
                                        const pages = [];
                                        const showEllipsis = totalPages > 7;

                                        if (!showEllipsis) {
                                            // Show all pages if 7 or fewer
                                            for (let i = 1; i <= totalPages; i++) {
                                                pages.push(
                                                    <Button
                                                        key={i}
                                                        variant={currentPage === i ? "default" : "outline"}
                                                        size="sm"
                                                        onClick={() => goToPage(i)}
                                                        disabled={loading}
                                                        className="min-w-[36px]"
                                                    >
                                                        {i}
                                                    </Button>
                                                );
                                            }
                                        } else {
                                            // Always show first page
                                            pages.push(
                                                <Button
                                                    key={1}
                                                    variant={currentPage === 1 ? "default" : "outline"}
                                                    size="sm"
                                                    onClick={() => goToPage(1)}
                                                    disabled={loading}
                                                    className="min-w-[36px]"
                                                >
                                                    1
                                                </Button>
                                            );

                                            // Add ellipsis if current page is far from start
                                            if (currentPage > 4) {
                                                pages.push(
                                                    <span key="ellipsis-start" className="px-2 text-muted-foreground">
                                                        ...
                                                    </span>
                                                );
                                            }

                                            // Show pages around current page
                                            const start = Math.max(2, currentPage - 1);
                                            const end = Math.min(totalPages - 1, currentPage + 1);

                                            for (let i = start; i <= end; i++) {
                                                pages.push(
                                                    <Button
                                                        key={i}
                                                        variant={currentPage === i ? "default" : "outline"}
                                                        size="sm"
                                                        onClick={() => goToPage(i)}
                                                        disabled={loading}
                                                        className="min-w-[36px]"
                                                    >
                                                        {i}
                                                    </Button>
                                                );
                                            }

                                            // Add ellipsis if current page is far from end
                                            if (currentPage < totalPages - 3) {
                                                pages.push(
                                                    <span key="ellipsis-end" className="px-2 text-muted-foreground">
                                                        ...
                                                    </span>
                                                );
                                            }

                                            // Always show last page
                                            if (totalPages > 1) {
                                                pages.push(
                                                    <Button
                                                        key={totalPages}
                                                        variant={currentPage === totalPages ? "default" : "outline"}
                                                        size="sm"
                                                        onClick={() => goToPage(totalPages)}
                                                        disabled={loading}
                                                        className="min-w-[36px]"
                                                    >
                                                        {totalPages}
                                                    </Button>
                                                );
                                            }
                                        }

                                        return pages;
                                    })()}

                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => goToPage(currentPage + 1)}
                                        disabled={currentPage === totalPages || loading}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                </Card>
            </div>

            <ActivityLogModal
                isOpen={activityLogModal.isOpen}
                onClose={() => setActivityLogModal(prev => ({ ...prev, isOpen: false }))}
                subjectType={activityLogModal.subjectType}
                subjectId={activityLogModal.subjectId}
                title={activityLogModal.title}
            />
        </AppLayout>
    );
}
