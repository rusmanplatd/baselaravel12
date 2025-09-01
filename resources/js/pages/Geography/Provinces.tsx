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
    country_id: string;
    code: string;
    name: string;
    country: Country;
    cities_count?: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    provinces: {
        data: Province[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        'filter[code]'?: string;
        'filter[name]'?: string;
        'filter[country_id]'?: string;
        sort?: string;
    };
    countries: Country[];
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: '', title: 'Provinces' },
];

export default function ProvincesIndex({ provinces, filters, countries }: Props) {
    const [searchFilters, setSearchFilters] = useState({
        code: filters['filter[code]'] || '',
        name: filters['filter[name]'] || '',
        country_id: filters['filter[country_id]'] || '',
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

            router.get(route('geography.provinces'), queryParams, {
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

        router.get(route('geography.provinces'), {
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

    const handleDelete = (province: Province) => {
        if (confirm(`Are you sure you want to delete the province "${province.name}"?`)) {
            router.delete(route('geography.provinces.destroy', province.id), {
                onSuccess: () => {
                    router.reload();
                }
            });
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
                        <Button onClick={() => router.get(route('geography.provinces.create'))}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Province
                        </Button>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Provinces ({provinces.total})</CardTitle>
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
                            <select 
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                value={searchFilters.country_id}
                                onChange={(e) => handleFilterChange('country_id', e.target.value)}
                            >
                                <option value="">All Countries</option>
                                {countries.map((country) => (
                                    <option key={country.id} value={country.id}>
                                        {country.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {provinces.data.length === 0 ? (
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
                                                            onClick={() => router.get(route('api.geo.provinces.show', province.id))}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_province:write">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => router.get(route('geography.provinces.edit', province.id))}
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