import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePublicApiData } from '@/hooks/usePublicApiData';
import { PermissionGuard } from '@/components/permission-guard';
import ActivityLogModal from '@/components/ActivityLogModal';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { apiService } from '@/services/ApiService';
import {
    Eye,
    Edit,
    Trash2,
    Plus,
    Globe,
    Search,
    ArrowUpDown,
    X,
    SortAsc,
    SortDesc,
    FileText
} from 'lucide-react';
import { useState, useCallback, useEffect } from 'react';
import { debounce } from 'lodash';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Countries', href: '/geography/countries' },
];

export default function CountriesApi() {
    const [inputValues, setInputValues] = useState({
        code: '',
        name: '',
        iso_code: '',
        phone_code: '',
    });

    const [activityLogModal, setActivityLogModal] = useState({
        isOpen: false,
        subjectType: '',
        subjectId: '',
        title: '',
    });

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

    // Sync input values with filters when filters change (from URL or other sources)
    useEffect(() => {
        setInputValues({
            code: filters.code || '',
            name: filters.name || '',
            iso_code: filters.iso_code || '',
            phone_code: filters.phone_code || '',
        });
    }, [filters.code, filters.name, filters.iso_code, filters.phone_code]);

    // Debounced search input
    const debouncedUpdateFilter = useCallback(
        debounce((key: string, value: string) => {
            updateFilter(key, value);
        }, 500),
        [updateFilter]
    );

    const handleFilterChange = (key: string, value: string) => {
        setInputValues(prev => ({ ...prev, [key]: value }));
        debouncedUpdateFilter(key, value);
    };

    const handleSort = (field: string) => {
        updateSort(field);
    };

    const getSortIcon = (field: string) => {
        if (sort === field) {
            return <SortAsc className="h-4 w-4" />;
        }
        if (sort === `-${field}`) {
            return <SortDesc className="h-4 w-4" />;
        }
        return <ArrowUpDown className="h-4 w-4 opacity-50" />;
    };

    const handleClearFilters = () => {
        setInputValues({
            code: '',
            name: '',
            iso_code: '',
            phone_code: '',
        });
        Object.keys(inputValues).forEach(key => {
            updateFilter(key, '');
        });
    };

    const handleDelete = async (country: Country) => {
        if (!confirm(`Are you sure you want to delete the country "${country.name}"?`)) {
            return;
        }

        try {
            await apiService.delete(`/api/v1/geo/countries/${country.id}`);
            refresh();
        } catch (error) {
            console.error('Error deleting country:', error);
            alert('Failed to delete country');
        }
    };

    const hasActiveFilters = Object.values(inputValues).some(value => value !== '');

    const getActiveFilters = () => {
        const activeFilters: Array<{key: string, value: string, label: string, displayValue: string}> = [];

        Object.entries(inputValues).forEach(([key, value]) => {
            if (value !== '') {
                let label = '';
                const displayValue = value;

                switch (key) {
                    case 'code':
                        label = 'Code';
                        break;
                    case 'name':
                        label = 'Name';
                        break;
                    case 'iso_code':
                        label = 'ISO Code';
                        break;
                    case 'phone_code':
                        label = 'Phone Code';
                        break;
                }

                activeFilters.push({ key, value, label, displayValue });
            }
        });

        return activeFilters;
    };

    const removeFilter = (filterKey: string) => {
        handleFilterChange(filterKey, '');
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
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Countries" />
                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center text-destructive">
                                Error loading countries: {error}
                                <Button onClick={refresh} className="ml-4">
                                    Try Again
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
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
                        <Link href="/geography/countries/create">
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Country
                            </Button>
                        </Link>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Countries ({total || 0})</CardTitle>
                        <CardDescription>
                            View and manage countries and their geographic information
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
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

                            {hasActiveFilters && (
                                <div className="flex items-center gap-2 flex-wrap pt-2 border-t">
                                    <span className="text-sm text-muted-foreground">Active filters:</span>
                                    {getActiveFilters().map((filter) => (
                                        <Badge key={filter.key} variant="secondary" className="gap-1">
                                            {filter.label}: {filter.displayValue}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => removeFilter(filter.key)}
                                                className="h-4 w-4 p-0 hover:bg-transparent"
                                            >
                                                <X className="h-3 w-3" />
                                            </Button>
                                        </Badge>
                                    ))}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleClearFilters}
                                        className="text-xs text-muted-foreground hover:text-destructive px-2 h-6"
                                    >
                                        Clear all
                                    </Button>
                                </div>
                            )}
                        </div>

                        <Separator className="mb-6" />

                        {/* Results Header */}
                        <div className="flex items-center justify-between mb-4">
                            <div className="flex items-center gap-4">
                                <div className="text-sm text-muted-foreground">
                                    {loading ? 'Loading...' : `Showing ${from} to ${to} of ${total} results`}
                                </div>
                                {hasActiveFilters && (
                                    <Button onClick={handleClearFilters} variant="ghost" size="sm" className="text-muted-foreground hover:text-destructive">
                                        <X className="mr-1 h-3 w-3" />
                                        Clear all
                                    </Button>
                                )}
                            </div>
                            <div className="flex items-center gap-2">
                                <Label htmlFor="per-page">Show:</Label>
                                <Select value={perPage.toString()} onValueChange={(value) => updatePerPage(parseInt(value))}>
                                    <SelectTrigger className="w-20">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="5">5</SelectItem>
                                        <SelectItem value="10">10</SelectItem>
                                        <SelectItem value="15">15</SelectItem>
                                        <SelectItem value="25">25</SelectItem>
                                        <SelectItem value="50">50</SelectItem>
                                        <SelectItem value="100">100</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Data Table */}
                        <div className="rounded-md border overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[60px] sticky left-0 bg-background border-r">#</TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('code')}
                                            >
                                                Code
                                                {getSortIcon('code')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('name')}
                                            >
                                                Name
                                                {getSortIcon('name')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('iso_code')}
                                            >
                                                ISO Code
                                                {getSortIcon('iso_code')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
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
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('created_at')}
                                            >
                                                Created
                                                {getSortIcon('created_at')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('updated_at')}
                                            >
                                                Updated At
                                                {getSortIcon('updated_at')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>Updated By</TableHead>
                                        <TableHead className="w-[120px] sticky right-0 bg-background border-l">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {loading && countries.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={10} className="text-center py-12">
                                                Loading countries...
                                            </TableCell>
                                        </TableRow>
                                    ) : countries.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={10} className="text-center py-12">
                                                <div className="flex flex-col items-center">
                                                    <Globe className="h-12 w-12 text-muted-foreground mb-4" />
                                                    <h3 className="text-lg font-semibold mb-2">No countries found</h3>
                                                    <p className="text-muted-foreground mb-4">
                                                        {hasActiveFilters
                                                            ? 'No countries match your current filters.'
                                                            : 'Get started by creating your first country.'}
                                                    </p>
                                                    {!hasActiveFilters && (
                                                        <PermissionGuard permission="geo_country:write">
                                                            <Link href="/geography/countries/create">
                                                                <Button>
                                                                    <Plus className="mr-2 h-4 w-4" />
                                                                    Add Country
                                                                </Button>
                                                            </Link>
                                                        </PermissionGuard>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        countries.map((country, index) => (
                                            <TableRow key={country.id}>
                                                <TableCell className="text-center text-muted-foreground sticky left-0 bg-background border-r">
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
                                                <TableCell className="sticky right-0 bg-background border-l">
                                                    <div className="flex items-center gap-1">
                                                        <PermissionGuard permission="geo_country:read">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                title="View Details"
                                                                onClick={() => router.get(`/geography/countries/${country.id}`)}
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </PermissionGuard>
                                                        <PermissionGuard permission="geo_country:write">
                                                            <Link href={`/geography/countries/${country.id}/edit`}>
                                                                <Button variant="ghost" size="sm" title="Edit">
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            </Link>
                                                        </PermissionGuard>
                                                        <PermissionGuard permission="geo_country:delete">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                title="Delete"
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
                                                                title="Activity Log"
                                                                onClick={() => showActivityLog(country)}
                                                            >
                                                                <FileText className="h-4 w-4" />
                                                            </Button>
                                                        </PermissionGuard>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="flex items-center justify-between pt-4">
                                <div className="text-sm text-muted-foreground">
                                    Page {currentPage} of {totalPages} ({total} total results)
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => goToPage(currentPage - 1)}
                                        disabled={currentPage === 1 || loading}
                                    >
                                        Previous
                                    </Button>

                                    {/* Page numbers */}
                                    {(() => {
                                        const pages = [];
                                        const showEllipsis = totalPages > 7;

                                        if (!showEllipsis) {
                                            // Show all pages if 7 or fewer
                                            for (let i = 1; i <= totalPages; i++) {
                                                pages.push(
                                                    <Button
                                                        key={i}
                                                        variant={currentPage === i ? "default" : "outline"}
                                                        size="sm"
                                                        onClick={() => goToPage(i)}
                                                        disabled={loading}
                                                        className="min-w-[36px]"
                                                    >
                                                        {i}
                                                    </Button>
                                                );
                                            }
                                        } else {
                                            // Always show first page
                                            pages.push(
                                                <Button
                                                    key={1}
                                                    variant={currentPage === 1 ? "default" : "outline"}
                                                    size="sm"
                                                    onClick={() => goToPage(1)}
                                                    disabled={loading}
                                                    className="min-w-[36px]"
                                                >
                                                    1
                                                </Button>
                                            );

                                            // Add ellipsis if current page is far from start
                                            if (currentPage > 4) {
                                                pages.push(
                                                    <span key="ellipsis-start" className="px-2 text-muted-foreground">
                                                        ...
                                                    </span>
                                                );
                                            }

                                            // Show pages around current page
                                            const start = Math.max(2, currentPage - 1);
                                            const end = Math.min(totalPages - 1, currentPage + 1);

                                            for (let i = start; i <= end; i++) {
                                                pages.push(
                                                    <Button
                                                        key={i}
                                                        variant={currentPage === i ? "default" : "outline"}
                                                        size="sm"
                                                        onClick={() => goToPage(i)}
                                                        disabled={loading}
                                                        className="min-w-[36px]"
                                                    >
                                                        {i}
                                                    </Button>
                                                );
                                            }

                                            // Add ellipsis if current page is far from end
                                            if (currentPage < totalPages - 3) {
                                                pages.push(
                                                    <span key="ellipsis-end" className="px-2 text-muted-foreground">
                                                        ...
                                                    </span>
                                                );
                                            }

                                            // Always show last page
                                            if (totalPages > 1) {
                                                pages.push(
                                                    <Button
                                                        key={totalPages}
                                                        variant={currentPage === totalPages ? "default" : "outline"}
                                                        size="sm"
                                                        onClick={() => goToPage(totalPages)}
                                                        disabled={loading}
                                                        className="min-w-[36px]"
                                                    >
                                                        {totalPages}
                                                    </Button>
                                                );
                                            }
                                        }

                                        return pages;
                                    })()}

                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => goToPage(currentPage + 1)}
                                        disabled={currentPage === totalPages || loading}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
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