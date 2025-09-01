import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { PermissionGuard } from '@/components/permission-guard';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Eye, Edit, Trash2, Search, Building, Plus, ArrowUpDown, FileText } from 'lucide-react';
import { useState, useCallback } from 'react';
import ActivityLogModal from '@/components/ActivityLogModal';
import { debounce } from 'lodash';

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
    districts_count?: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    cities: {
        data: City[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        'filter[code]'?: string;
        'filter[name]'?: string;
        'filter[province_id]'?: string;
        sort?: string;
    };
    provinces: Province[];
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: '', title: 'Cities' },
];

export default function CitiesIndex({ cities, filters, provinces }: Props) {
    const [searchFilters, setSearchFilters] = useState({
        code: filters['filter[code]'] || '',
        name: filters['filter[name]'] || '',
        province_id: filters['filter[province_id]'] || '',
    });

    const [activityLogModal, setActivityLogModal] = useState({
        isOpen: false,
        subjectType: '',
        subjectId: '',
        title: '',
    });

    const debouncedSearch = useCallback(
        debounce((newFilters: typeof searchFilters) => {
            const queryParams: Record<string, string> = { ...filters };
            
            // Update filter parameters
            Object.entries(newFilters).forEach(([key, value]) => {
                if (value) {
                    queryParams[`filter[${key}]`] = value;
                } else {
                    delete queryParams[`filter[${key}]`];
                }
            });

            router.get(route('geography.cities'), queryParams, {
                preserveState: true,
                preserveScroll: true,
            });
        }, 500),
        [filters]
    );

    const handleFilterChange = (key: keyof typeof searchFilters, value: string) => {
        const newFilters = { ...searchFilters, [key]: value };
        setSearchFilters(newFilters);
        debouncedSearch(newFilters);
    };

    const handleSort = (field: string) => {
        const currentSort = filters.sort;
        let newSort = field;
        
        if (currentSort === field) {
            newSort = `-${field}`;
        } else if (currentSort === `-${field}`) {
            newSort = '';
        }

        router.get(route('geography.cities'), {
            ...filters,
            sort: newSort || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
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

    const handleDelete = (city: City) => {
        if (confirm(`Are you sure you want to delete the city "${city.name}"?`)) {
            router.delete(route('geography.cities.destroy', city.id), {
                onSuccess: () => {
                    router.reload();
                }
            });
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
                        <Button onClick={() => router.get(route('geography.cities.create'))}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add City
                        </Button>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Cities ({cities.total})</CardTitle>
                        <CardDescription>
                            View and manage all cities in the system
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
                            <select 
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                value={searchFilters.province_id}
                                onChange={(e) => handleFilterChange('province_id', e.target.value)}
                            >
                                <option value="">All Provinces</option>
                                {provinces.map((province) => (
                                    <option key={province.id} value={province.id}>
                                        {province.name} ({province.country.name})
                                    </option>
                                ))}
                            </select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {cities.data.length === 0 ? (
                            <div className="text-center py-8">
                                <Building className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-muted-foreground">No cities found</p>
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
                                        <TableHead className="w-[100px]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {cities.data.map((city) => (
                                        <TableRow key={city.id}>
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
                                                    {city.districts_count || 0} districts
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {new Date(city.created_at).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <PermissionGuard permission="geo_city:read">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => router.get(route('api.geo.cities.show', city.id))}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_city:write">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => router.get(route('geography.cities.edit', city.id))}
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