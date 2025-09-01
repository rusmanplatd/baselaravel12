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
import { Eye, Edit, Trash2, Search, TreePine, Plus, ArrowUpDown, FileText } from 'lucide-react';
import React, { useState, useCallback, useEffect } from 'react';
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
    name: string;
    code: string;
    country: Country;
}

interface City {
    id: string;
    name: string;
    code: string;
    province: Province;
}

interface District {
    id: string;
    name: string;
    code: string;
    city: City;
}

interface Village {
    id: string;
    district_id: string;
    code: string;
    name: string;
    district: District;
    created_at: string;
    updated_at: string;
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: '', title: 'Villages' },
];

export default function VillagesApi() {
    const {
        data: villages,
        loading,
        error,
        filters,
        updateFilter,
        updateSort,
        updatePerPage,
        goToPage,
        refresh,
    } = useApiData<Village>({
        endpoint: 'villages',
        initialFilters: {
            code: '',
            name: '',
            district_id: '',
        },
        initialSort: 'name',
    });

    const [searchFilters, setSearchFilters] = useState({
        code: filters.code || '',
        name: filters.name || '',
        district_id: filters.district_id || '',
    });

    const [districts, setDistricts] = useState<District[]>([]);

    const [activityLogModal, setActivityLogModal] = useState({
        isOpen: false,
        subjectType: '',
        subjectId: '',
        title: '',
    });

    // Load districts for filter dropdown
    useEffect(() => {
        apiService.get<District[]>('/api/v1/geo/districts/list')
            .then(data => setDistricts(data))
            .catch(console.error);
    }, []);

    // Convert districts to SearchableSelectItem format
    const districtSelectItems: SearchableSelectItem[] = districts.map((district) => ({
        value: district.id,
        label: `${district.name} (${district.city.name}, ${district.city.province.name}, ${district.city.province.country.name})`,
        searchText: `${district.name} ${district.code} ${district.city.name} ${district.city.province.name} ${district.city.province.country.name}`,
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
        if (filters.sort === field) {
            return <ArrowUpDown className="h-4 w-4 text-primary" />;
        }
        if (filters.sort === `-${field}`) {
            return <ArrowUpDown className="h-4 w-4 text-primary rotate-180" />;
        }
        return <ArrowUpDown className="h-4 w-4 text-muted-foreground opacity-50" />;
    };

    const handleDelete = async (village: Village) => {
        if (confirm(`Are you sure you want to delete the village "${village.name}"?`)) {
            try {
                await apiService.delete(`/api/v1/geo/villages/${village.id}`);
                refresh();
            } catch (error) {
                console.error('Error deleting village:', error);
            }
        }
    };

    const showActivityLog = (village: Village) => {
        setActivityLogModal({
            isOpen: true,
            subjectType: 'App\\Models\\Master\\Geo\\Village',
            subjectId: village.id,
            title: `${village.name} (${village.code})`,
        });
    };

    if (error) {
        return (
            <AppLayout breadcrumbs={breadcrumbItems}>
                <Head title="Villages" />
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
            <Head title="Villages" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Villages</h1>
                        <p className="text-muted-foreground">
                            Manage villages and their geographic information
                        </p>
                    </div>
                    <PermissionGuard permission="geo_village:write">
                        <Button onClick={() => window.location.href = route('geography.villages.create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Village
                        </Button>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Villages ({villages?.total || 0})</CardTitle>
                        <CardDescription>
                            View and manage all villages in the system
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
                                placeholder="Select a district..."
                                items={districtSelectItems}
                                value={searchFilters.district_id}
                                onValueChange={(value) => handleFilterChange('district_id', value)}
                                emptyLabel="All Districts"
                                searchPlaceholder="Search districts..."
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="text-center py-8">
                                <p className="text-muted-foreground">Loading...</p>
                            </div>
                        ) : !villages?.data?.length ? (
                            <div className="text-center py-8">
                                <TreePine className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-muted-foreground">No villages found</p>
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
                                        <TableHead>District</TableHead>
                                        <TableHead>City</TableHead>
                                        <TableHead>Province</TableHead>
                                        <TableHead>Country</TableHead>
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
                                    {villages.data.map((village) => (
                                        <TableRow key={village.id}>
                                            <TableCell className="font-medium">
                                                <Badge variant="outline">{village.code}</Badge>
                                            </TableCell>
                                            <TableCell>{village.name}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="secondary">{village.district.code}</Badge>
                                                    <span>{village.district.name}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="secondary">{village.district.city.code}</Badge>
                                                    <span className="text-sm text-muted-foreground">
                                                        {village.district.city.name}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="secondary">{village.district.city.province.code}</Badge>
                                                    <span className="text-sm text-muted-foreground">
                                                        {village.district.city.province.name}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="secondary">{village.district.city.province.country.code}</Badge>
                                                    <span className="text-sm text-muted-foreground">
                                                        {village.district.city.province.country.name}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {new Date(village.created_at).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <PermissionGuard permission="geo_village:read">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => router.get(route('geography.villages.show', village.id))}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_village:write">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => window.location.href = route('geography.villages.edit', village.id)}
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_village:delete">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(village)}
                                                            className="text-destructive hover:text-destructive"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="audit_log:read">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => showActivityLog(village)}
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

                    {villages && (
                        <ApiPagination
                            meta={villages}
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
