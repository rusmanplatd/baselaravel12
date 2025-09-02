import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { PermissionGuard } from '@/components/permission-guard';
import ActivityLogModal from '@/components/ActivityLogModal';
import ApiPagination from '@/components/api-pagination';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Eye, Edit, Trash2, Search, Globe, Plus, ArrowUpDown, FileText, X } from 'lucide-react';
import { useState, useCallback, useEffect } from 'react';
import { usePublicApiData } from '@/hooks/usePublicApiData';
import { debounce } from 'lodash';
import { apiService } from '@/services/ApiService';

interface Country {
    id: string;
    code: string;
    name: string;
    iso_code: string | null;
    phone_code: string | null;
    provinces_count?: number;
    created_at: string;
    updated_at: string;
    updated_by?: { name: string } | null;
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: '', title: 'Countries' },
];

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
        perPage,
        currentPage,
        totalPages,
        total,
        from,
        to,
    } = usePublicApiData<Country>({
        endpoint: '/api/v1/geo/countries',
        initialFilters: {
            code: '',
            name: '',
            iso_code: '',
            phone_code: '',
        },
        initialSort: 'name',
    });

    // Local input state for immediate UI feedback
    const [inputValues, setInputValues] = useState({
        code: filters.code || '',
        name: filters.name || '',
        iso_code: filters.iso_code || '',
        phone_code: filters.phone_code || '',
    });

    // Sync input values with filters when filters change (from URL or other sources)
    useEffect(() => {
        setInputValues({
            code: filters.code || '',
            name: filters.name || '',
            iso_code: filters.iso_code || '',
            phone_code: filters.phone_code || '',
        });
    }, [filters.code, filters.name, filters.iso_code, filters.phone_code]);

    const [activityLogModal, setActivityLogModal] = useState({
        isOpen: false,
        subjectType: '',
        subjectId: '',
        title: '',
    });

    // Sync input values with filters when filters change (from URL or other sources)
    useEffect(() => {
        setInputValues({
            code: filters.code || '',
            name: filters.name || '',
            iso_code: filters.iso_code || '',
            phone_code: filters.phone_code || '',
        });
    }, [filters.code, filters.name, filters.iso_code, filters.phone_code]);

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

    const handleDelete = async (country: Country) => {
        if (confirm(`Are you sure you want to delete the country "${country.name}"?`)) {
            try {
                await apiService.delete(`/api/v1/geo/countries/${country.id}`);
                refresh();
            } catch (error) {
                console.error('Error deleting country:', error);
            }
        }
    };

    const showActivityLog = (country: Country) => {
        setActivityLogModal({
            isOpen: true,
            subjectType: 'App\\Models\\Master\\Geo\\Country',
            subjectId: country.id,
            title: `${country.name} (${country.code})`,
        });
    };

    if (error) {
        return (
            <AppLayout breadcrumbs={breadcrumbItems}>
                <Head title="Countries" />
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
            <Head title="Countries" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Countries</h1>
                        <p className="text-muted-foreground">
                            Manage countries and their geographic information
                        </p>
                    </div>
                    <PermissionGuard permission="geo_country:write">
                        <Button onClick={() => window.location.href = route('geography.countries.create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Country
                        </Button>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Countries ({total})</CardTitle>
                        <CardDescription>
                            View and manage all countries in the system
                        </CardDescription>

                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4 z-10" />
                                    <Input
                                        placeholder="Search by ISO code..."
                                        value={inputValues.iso_code}
                                        onChange={(e) => handleFilterChange('iso_code', e.target.value)}
                                        className="pl-10 pr-10"
                                    />
                                    {inputValues.iso_code && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleFilterChange('iso_code', '')}
                                            className="absolute right-1 top-1/2 transform -translate-y-1/2 h-8 w-8 p-0 hover:bg-muted"
                                        >
                                            <X className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4 z-10" />
                                    <Input
                                        placeholder="Search by phone code..."
                                        value={inputValues.phone_code}
                                        onChange={(e) => handleFilterChange('phone_code', e.target.value)}
                                        className="pl-10 pr-10"
                                    />
                                    {inputValues.phone_code && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleFilterChange('phone_code', '')}
                                            className="absolute right-1 top-1/2 transform -translate-y-1/2 h-8 w-8 p-0 hover:bg-muted"
                                        >
                                            <X className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                            
                            {(inputValues.code || inputValues.name || inputValues.iso_code || inputValues.phone_code) && (
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
                                    {inputValues.iso_code && (
                                        <Badge variant="secondary" className="gap-1">
                                            ISO: {inputValues.iso_code}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleFilterChange('iso_code', '')}
                                                className="h-4 w-4 p-0 hover:bg-transparent"
                                            >
                                                <X className="h-3 w-3" />
                                            </Button>
                                        </Badge>
                                    )}
                                    {inputValues.phone_code && (
                                        <Badge variant="secondary" className="gap-1">
                                            Phone: {inputValues.phone_code}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleFilterChange('phone_code', '')}
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
                                            const clearedFilters = { code: '', name: '', iso_code: '', phone_code: '' };
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
                        ) : !countries?.length ? (
                            <div className="text-center py-8">
                                <Globe className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-muted-foreground">No countries found</p>
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
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                className="h-auto p-0 font-semibold"
                                                onClick={() => handleSort('iso_code')}
                                            >
                                                ISO Code
                                                {getSortIcon('iso_code')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                className="h-auto p-0 font-semibold"
                                                onClick={() => handleSort('phone_code')}
                                            >
                                                Phone Code
                                                {getSortIcon('phone_code')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>Provinces</TableHead>
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
                                    {countries.map((country, index) => (
                                        <TableRow key={country.id}>
                                            <TableCell className="text-center text-muted-foreground">
                                                {(currentPage - 1) * perPage + index + 1}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                <Badge variant="outline">{country.code}</Badge>
                                            </TableCell>
                                            <TableCell>{country.name}</TableCell>
                                            <TableCell>
                                                {country.iso_code ? (
                                                    <Badge variant="secondary">{country.iso_code}</Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {country.phone_code ? (
                                                    <Badge variant="secondary">+{country.phone_code}</Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {country.provinces_count || 0} provinces
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {new Date(country.created_at).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                {new Date(country.updated_at).toLocaleString()}
                                            </TableCell>
                                            <TableCell>
                                                {country.updated_by ? country.updated_by.name : '-'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <PermissionGuard permission="geo_country:read">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => router.get(route('geography.countries.show', country.id))}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_country:write">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => window.location.href = route('geography.countries.edit', country.id)}
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_country:delete">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(country)}
                                                            className="text-destructive hover:text-destructive"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="audit_log:read">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => showActivityLog(country)}
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
