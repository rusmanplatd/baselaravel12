import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { PermissionGuard } from '@/components/permission-guard';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Eye, Edit, Trash2, Search, MapPin, Plus, ArrowUpDown, FileText } from 'lucide-react';
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
    name: string;
    code: string;
    province: Province;
}

interface District {
    id: string;
    city_id: string;
    code: string;
    name: string;
    city: City;
    villages_count?: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    districts: {
        data: District[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        'filter[code]'?: string;
        'filter[name]'?: string;
        'filter[city_id]'?: string;
        sort?: string;
    };
    cities: City[];
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: '', title: 'Districts' },
];

export default function DistrictsIndex({ districts, filters, cities }: Props) {
    const [searchFilters, setSearchFilters] = useState({
        code: filters['filter[code]'] || '',
        name: filters['filter[name]'] || '',
        city_id: filters['filter[city_id]'] || '',
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

            router.get(route('geography.districts'), queryParams, {
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

        router.get(route('geography.districts'), {
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

    const handleDelete = (district: District) => {
        if (confirm(`Are you sure you want to delete the district "${district.name}"?`)) {
            router.delete(route('geography.districts.destroy', district.id), {
                onSuccess: () => {
                    router.reload();
                }
            });
        }
    };

    const showActivityLog = (district: District) => {
        setActivityLogModal({
            isOpen: true,
            subjectType: 'App\\Models\\Master\\Geo\\District',
            subjectId: district.id,
            title: `${district.name} (${district.code})`,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems}>
            <Head title="Districts" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Districts</h1>
                        <p className="text-muted-foreground">
                            Manage districts and their geographic information
                        </p>
                    </div>
                    <PermissionGuard permission="geo_district:write">
                        <Button onClick={() => router.get(route('geography.districts.create'))}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add District
                        </Button>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Districts ({districts.total})</CardTitle>
                        <CardDescription>
                            View and manage all districts in the system
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
                                value={searchFilters.city_id}
                                onChange={(e) => handleFilterChange('city_id', e.target.value)}
                            >
                                <option value="">All Cities</option>
                                {cities.map((city) => (
                                    <option key={city.id} value={city.id}>
                                        {city.name} ({city.province.name}, {city.province.country.name})
                                    </option>
                                ))}
                            </select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {districts.data.length === 0 ? (
                            <div className="text-center py-8">
                                <MapPin className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-muted-foreground">No districts found</p>
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
                                        <TableHead>City</TableHead>
                                        <TableHead>Province</TableHead>
                                        <TableHead>Country</TableHead>
                                        <TableHead>Villages</TableHead>
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
                                    {districts.data.map((district) => (
                                        <TableRow key={district.id}>
                                            <TableCell className="font-medium">
                                                <Badge variant="outline">{district.code}</Badge>
                                            </TableCell>
                                            <TableCell>{district.name}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="secondary">{district.city.code}</Badge>
                                                    <span>{district.city.name}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="secondary">{district.city.province.code}</Badge>
                                                    <span className="text-sm text-muted-foreground">
                                                        {district.city.province.name}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="secondary">{district.city.province.country.code}</Badge>
                                                    <span className="text-sm text-muted-foreground">
                                                        {district.city.province.country.name}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {district.villages_count || 0} villages
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {new Date(district.created_at).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <PermissionGuard permission="geo_district:read">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => router.get(route('api.geo.districts.show', district.id))}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_district:write">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => router.get(route('geography.districts.edit', district.id))}
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_district:delete">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(district)}
                                                            className="text-destructive hover:text-destructive"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="audit_log:read">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => showActivityLog(district)}
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