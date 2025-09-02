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
import { Eye, Edit, Trash2, Search, MapPin, Plus, ArrowUpDown, FileText, X } from 'lucide-react';
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
    country_id: string;
    code: string;
    name: string;
    country: Country;
    cities_count?: number;
    created_at: string;
    updated_at: string;
    updated_by?: { name: string } | null;
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: '', title: 'Provinces' },
];

export default function ProvincesApi() {
    const {
        data: provinces,
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
    } = usePublicApiData<Province>({
        endpoint: '/api/v1/geo/provinces',
        initialFilters: {
            code: '',
            name: '',
            country_id: '',
        },
        initialSort: 'name',
    });

    // Local input state for immediate UI feedback
    const [inputValues, setInputValues] = useState({
        code: filters.code || '',
        name: filters.name || '',
        country_id: filters.country_id || '',
    });

    // Sync input values with filters when filters change (from URL or other sources)
    useEffect(() => {
        setInputValues({
            code: filters.code || '',
            name: filters.name || '',
            country_id: filters.country_id || '',
        });
    }, [filters.code, filters.name, filters.country_id]);

    const [countries, setCountries] = useState<Country[]>([]);

    const [activityLogModal, setActivityLogModal] = useState({
        isOpen: false,
        subjectType: '',
        subjectId: '',
        title: '',
    });

    // Load countries for filter dropdown
    React.useEffect(() => {
        apiService.get<Country[]>('/api/v1/geo/countries/list')
            .then(data => setCountries(data))
            .catch(console.error);
    }, []);

    // Convert countries to SearchableSelectItem format
    const countrySelectItems: SearchableSelectItem[] = countries.map((country) => ({
        value: country.id,
        label: country.name,
        searchText: `${country.name} ${country.code}`,
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

    const handleDelete = async (province: Province) => {
        if (confirm(`Are you sure you want to delete the province "${province.name}"?`)) {
            try {
                await apiService.delete(`/api/v1/geo/provinces/${province.id}`);
                refresh();
            } catch (error) {
                console.error('Error deleting province:', error);
            }
        }
    };

    const showActivityLog = (province: Province) => {
        setActivityLogModal({
            isOpen: true,
            subjectType: 'App\\Models\\Master\\Geo\\Province',
            subjectId: province.id,
            title: `${province.name} (${province.code})`,
        });
    };

    if (error) {
        return (
            <AppLayout breadcrumbs={breadcrumbItems}>
                <Head title="Provinces" />
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
            <Head title="Provinces" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Provinces</h1>
                        <p className="text-muted-foreground">
                            Manage provinces and their geographic information
                        </p>
                    </div>
                    <PermissionGuard permission="geo_province:write">
                        <Button onClick={() => window.location.href = route('geography.provinces.create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Province
                        </Button>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Provinces ({total})</CardTitle>
                        <CardDescription>
                            View and manage all provinces in the system
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
                                    placeholder="Select a country..."
                                    items={countrySelectItems}
                                    value={inputValues.country_id}
                                    onValueChange={(value) => handleFilterChange('country_id', value)}
                                    emptyLabel="All Countries"
                                    searchPlaceholder="Search countries..."
                                />
                            </div>
                            
                            {(inputValues.code || inputValues.name || inputValues.country_id) && (
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
                                    {inputValues.country_id && (
                                        <Badge variant="secondary" className="gap-1">
                                            Country: {countries.find(c => c.id === inputValues.country_id)?.name || inputValues.country_id}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleFilterChange('country_id', '')}
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
                                            const clearedFilters = { code: '', name: '', country_id: '' };
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
                        ) : !provinces?.length ? (
                            <div className="text-center py-8">
                                <MapPin className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-muted-foreground">No provinces found</p>
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
                                        <TableHead>Country</TableHead>
                                        <TableHead>Cities</TableHead>
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
                                    {provinces.map((province, index) => (
                                        <TableRow key={province.id}>
                                            <TableCell className="text-center text-muted-foreground">
                                                {(currentPage - 1) * perPage + index + 1}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                <Badge variant="outline">{province.code}</Badge>
                                            </TableCell>
                                            <TableCell>{province.name}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="secondary">{province.country.code}</Badge>
                                                    <span>{province.country.name}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {province.cities_count || 0} cities
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {new Date(province.created_at).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                {new Date(province.updated_at).toLocaleString()}
                                            </TableCell>
                                            <TableCell>
                                                {province.updated_by ? province.updated_by.name : '-'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <PermissionGuard permission="geo_province:read">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => router.get(route('geography.provinces.show', province.id))}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_province:write">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => window.location.href = route('geography.provinces.edit', province.id)}
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_province:delete">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(province)}
                                                            className="text-destructive hover:text-destructive"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="audit_log:read">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => showActivityLog(province)}
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
