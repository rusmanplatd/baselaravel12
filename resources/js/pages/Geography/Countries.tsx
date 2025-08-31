import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { PermissionGuard } from '@/components/permission-guard';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Eye, Edit, Trash2, Search, Globe, Plus, ArrowUpDown } from 'lucide-react';
import { useState, useCallback } from 'react';
import { debounce } from 'lodash';

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

interface Props {
    countries: {
        data: Country[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        'filter[code]'?: string;
        'filter[name]'?: string;
        'filter[iso_code]'?: string;
        'filter[phone_code]'?: string;
        sort?: string;
    };
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: '', title: 'Countries' },
];

export default function CountriesIndex({ countries, filters }: Props) {
    const [searchFilters, setSearchFilters] = useState({
        code: filters['filter[code]'] || '',
        name: filters['filter[name]'] || '',
        iso_code: filters['filter[iso_code]'] || '',
        phone_code: filters['filter[phone_code]'] || '',
    });

    const debouncedSearch = useCallback(
        debounce((newFilters: typeof searchFilters) => {
            const queryParams: any = { ...filters };
            
            // Update filter parameters
            Object.entries(newFilters).forEach(([key, value]) => {
                if (value) {
                    queryParams[`filter[${key}]`] = value;
                } else {
                    delete queryParams[`filter[${key}]`];
                }
            });

            router.get(route('geography.countries'), queryParams, {
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

        router.get(route('geography.countries'), {
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

    const handleDelete = (country: Country) => {
        if (confirm(`Are you sure you want to delete the country "${country.name}"?`)) {
            router.delete(route('api.geo.countries.destroy', country.id), {
                onSuccess: () => {
                    router.reload();
                }
            });
        }
    };

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
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Country
                        </Button>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Countries ({countries.total})</CardTitle>
                        <CardDescription>
                            View and manage all countries in the system
                        </CardDescription>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                        {countries.data.length === 0 ? (
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
                                                    <Badge variant="secondary">{country.phone_code}</Badge>
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
                                                            onClick={() => router.get(route('api.geo.countries.show', country.id))}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="geo_country:write">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            disabled
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
        </AppLayout>
    );
}