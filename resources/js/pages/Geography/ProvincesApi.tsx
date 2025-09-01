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
import { Eye, Edit, Trash2, Search, MapPin, Plus, ArrowUpDown, FileText } from 'lucide-react';
import React, { useState, useCallback } from 'react';
import { useApiData } from '@/hooks/useApiData';
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
    } = useApiData<Province>({
        endpoint: 'provinces',
        initialFilters: {
            code: '',
            name: '',
            country_id: '',
        },
        initialSort: 'name',
    });

    const [searchFilters, setSearchFilters] = useState({
        code: filters.code || '',
        name: filters.name || '',
        country_id: filters.country_id || '',
    });

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

    const debouncedSearch = useCallback(
        debounce((newFilters: typeof searchFilters) => {
            Object.entries(newFilters).forEach(([key, value]) => {
                updateFilter(key, value);
            });
        }, 500),
        [updateFilter]
    );

    const handleFilterChange = (key: keyof typeof searchFilters, value: string) => {
        const newFilters = { ...searchFilters, [key]: value };
        setSearchFilters(newFilters);
        debouncedSearch(newFilters);
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
                        <CardTitle>All Provinces ({provinces?.total || 0})</CardTitle>
                        <CardDescription>
                            View and manage all provinces in the system
                        </CardDescription>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                                <Input
                                    placeholder="Search by code..."
                                    value={searchFilters.code}
                                    onChange={(e) => handleFilterChange('code', e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                                <Input
                                    placeholder="Search by name..."
                                    value={searchFilters.name}
                                    onChange={(e) => handleFilterChange('name', e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <SearchableSelect
                                placeholder="Select a country..."
                                items={countrySelectItems}
                                value={searchFilters.country_id}
                                onValueChange={(value) => handleFilterChange('country_id', value)}
                                emptyLabel="All Countries"
                                searchPlaceholder="Search countries..."
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="text-center py-8">
                                <p className="text-muted-foreground">Loading...</p>
                            </div>
                        ) : !provinces?.data?.length ? (
                            <div className="text-center py-8">
                                <MapPin className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-muted-foreground">No provinces found</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
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
                                        <TableHead className="w-[100px]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {provinces.data.map((province) => (
                                        <TableRow key={province.id}>
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

                    {provinces && (
                        <ApiPagination
                            meta={provinces}
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
