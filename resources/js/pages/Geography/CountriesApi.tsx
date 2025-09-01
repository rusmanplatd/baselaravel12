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
import { Eye, Edit, Trash2, Search, Globe, Plus, ArrowUpDown, FileText } from 'lucide-react';
import { useState, useCallback } from 'react';
import { useApiData } from '@/hooks/useApiData';
import { debounce } from 'lodash';
import axios from 'axios';

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

    const [searchFilters, setSearchFilters] = useState({
        code: filters.code || '',
        name: filters.name || '',
        iso_code: filters.iso_code || '',
        phone_code: filters.phone_code || '',
    });

    const [activityLogModal, setActivityLogModal] = useState({
        isOpen: false,
        subjectType: '',
        subjectId: '',
        title: '',
    });

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
        if (filters.sort === field) {
            return <ArrowUpDown className="h-4 w-4 text-primary" />;
        }
        if (filters.sort === `-${field}`) {
            return <ArrowUpDown className="h-4 w-4 text-primary rotate-180" />;
        }
        return <ArrowUpDown className="h-4 w-4 text-muted-foreground opacity-50" />;
    };

    const handleDelete = async (country: Country) => {
        if (confirm(`Are you sure you want to delete the country "${country.name}"?`)) {
            try {
                await axios.delete(`/api/v1/geo/countries/${country.id}`);
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
                        <CardTitle>All Countries ({countries?.total || 0})</CardTitle>
                        <CardDescription>
                            View and manage all countries in the system
                        </CardDescription>

                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                                <Input
                                    placeholder="Search by ISO code..."
                                    value={searchFilters.iso_code}
                                    onChange={(e) => handleFilterChange('iso_code', e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                                <Input
                                    placeholder="Search by phone code..."
                                    value={searchFilters.phone_code}
                                    onChange={(e) => handleFilterChange('phone_code', e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="text-center py-8">
                                <p className="text-muted-foreground">Loading...</p>
                            </div>
                        ) : !countries?.data?.length ? (
                            <div className="text-center py-8">
                                <Globe className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-muted-foreground">No countries found</p>
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
                                        <TableHead className="w-[100px]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {countries.data.map((country) => (
                                        <TableRow key={country.id}>
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

                    {countries && (
                        <ApiPagination
                            meta={countries}
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
