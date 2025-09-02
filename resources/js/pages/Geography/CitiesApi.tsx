import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { SearchableSelect, type SearchableSelectItem } from '@/components/ui/searchable-select';
import { PermissionGuard } from '@/components/permission-guard';
import ActivityLogModal from '@/components/ActivityLogModal';
import ApiPagination from '@/components/api-pagination';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Eye, Edit, Trash2, Search, Building, Plus, ArrowUpDown, FileText, X } from 'lucide-react';
import React, { useState, useCallback, useEffect } from 'react';
import { usePublicApiData } from '@/hooks/usePublicApiData';
import { debounce } from 'lodash';
import { apiService } from '@/services/ApiService';

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
        filters,
        sort,
        updateFilter,
        updateSort,
        updatePerPage,
        goToPage,
        refresh,
        perPage,
        currentPage,
        totalPages,
        total,
        from,
        to,
    } = usePublicApiData<City>({
        endpoint: '/api/v1/geo/cities',
        initialFilters: {
            code: '',
            name: '',
            province_id: '',
        },
        initialSort: 'name',
    });

    // Local input state for immediate UI feedback
    const [inputValues, setInputValues] = useState({
        code: filters.code || '',
        name: filters.name || '',
        province_id: filters.province_id || '',
    });

    // Sync input values with filters when filters change (from URL or other sources)
    useEffect(() => {
        setInputValues({
            code: filters.code || '',
            name: filters.name || '',
            province_id: filters.province_id || '',
        });
    }, [filters.code, filters.name, filters.province_id]);

    const [provinces, setProvinces] = useState<Province[]>([]);

    const [activityLogModal, setActivityLogModal] = useState({
        isOpen: false,
        subjectType: '',
        subjectId: '',
        title: '',
    });

    // Load provinces for filter dropdown
    useEffect(() => {
        apiService.get<Province[]>('/api/v1/geo/provinces/list')
            .then(data => setProvinces(data))
            .catch(console.error);
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
            return <ArrowUpDown className="h-4 w-4 text-primary" />;
        }
        if (sort === `-${field}`) {
            return <ArrowUpDown className="h-4 w-4 text-primary rotate-180" />;
        }
        return <ArrowUpDown className="h-4 w-4 text-muted-foreground opacity-50" />;
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
                                />
                            </div>
                            
                            {(inputValues.code || inputValues.name || inputValues.province_id) && (
                                <div className="flex items-center gap-2 flex-wrap pt-2 border-t">
                                    <span className="text-sm text-muted-foreground">Active filters:</span>
                                    {inputValues.code && (
                                        <Badge variant="secondary" className="gap-1">
                                            Code: {inputValues.code}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleFilterChange('code', '')}
                                                className="h-4 w-4 p-0 hover:bg-transparent"
                                            >
                                                <X className="h-3 w-3" />
                                            </Button>
                                        </Badge>
                                    )}
                                    {inputValues.name && (
                                        <Badge variant="secondary" className="gap-1">
                                            Name: {inputValues.name}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleFilterChange('name', '')}
                                                className="h-4 w-4 p-0 hover:bg-transparent"
                                            >
                                                <X className="h-3 w-3" />
                                            </Button>
                                        </Badge>
                                    )}
                                    {inputValues.province_id && (
                                        <Badge variant="secondary" className="gap-1">
                                            Province: {provinces.find(p => p.id === inputValues.province_id)?.name || inputValues.province_id}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleFilterChange('province_id', '')}
                                                className="h-4 w-4 p-0 hover:bg-transparent"
                                            >
                                                <X className="h-3 w-3" />
                                            </Button>
                                        </Badge>
                                    )}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            const clearedFilters = { code: '', name: '', province_id: '' };
                                            setInputValues(clearedFilters);
                                            Object.entries(clearedFilters).forEach(([key, value]) => {
                                                updateFilter(key, value);
                                            });
                                        }}
                                        className="gap-1 text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="h-3 w-3" />
                                        Clear all
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="text-center py-8">
                                <p className="text-muted-foreground">Loading...</p>
                            </div>
                        ) : !cities?.length ? (
                            <div className="text-center py-8">
                                <Building className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-muted-foreground">No cities found</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[60px]">#</TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                className="h-auto p-0 font-semibold"
                                                onClick={() => handleSort('code')}
                                            >
                                                Code
                                                {getSortIcon('code')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                className="h-auto p-0 font-semibold"
                                                onClick={() => handleSort('name')}
                                            >
                                                Name
                                                {getSortIcon('name')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>Province</TableHead>
                                        <TableHead>Country</TableHead>
                                        <TableHead>Districts</TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                className="h-auto p-0 font-semibold"
                                                onClick={() => handleSort('created_at')}
                                            >
                                                Created
                                                {getSortIcon('created_at')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                className="h-auto p-0 font-semibold"
                                                onClick={() => handleSort('updated_at')}
                                            >
                                                Updated At
                                                {getSortIcon('updated_at')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>Updated By</TableHead>
                                        <TableHead className="w-[100px]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {cities.map((city, index) => (
                                        <TableRow key={city.id}>
                                            <TableCell className="text-center text-muted-foreground">
                                                {(currentPage - 1) * perPage + index + 1}
                                            </TableCell>
                                            <TableCell className="font-medium">
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
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <PermissionGuard permission="geo_city:read">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => router.get(route('geography.cities.show', city.id))}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_city:write">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => window.location.href = route('geography.cities.edit', city.id)}
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_city:delete">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
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
                                                            onClick={() => showActivityLog(city)}
                                                        >
                                                            <FileText className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>

                    {total > 0 && (
                        <ApiPagination
                            meta={{
                                current_page: currentPage,
                                last_page: totalPages,
                                per_page: perPage,
                                total: total,
                                from: from,
                                to: to,
                                links: [] // Not used in current implementation
                            }}
                            onPageChange={goToPage}
                            onPerPageChange={updatePerPage}
                        />
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
