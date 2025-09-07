import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { SearchableSelect, type SearchableSelectItem } from '@/components/ui/searchable-select';
import { useApiData } from '@/hooks/useApiData';
import { PermissionGuard } from '@/components/permission-guard';
import ActivityLogModal from '@/components/ActivityLogModal';
import AppLayout from '@/layouts/app-layout';
import { apiService } from '@/services/ApiService';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Plus,
    Eye,
    Edit,
    Trash2,
    Search,
    ArrowUpDown,
    RotateCcw,
    X,
    SortAsc,
    SortDesc,
    FileText,
    Shield
} from 'lucide-react';
import { useState, useCallback } from 'react';
import { debounce } from 'lodash';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Permission {
    id: string;
    name: string;
    guard_name: string;
}

interface Organization {
    id: string;
    name: string;
    organization_type: string;
}

interface Role {
    id: string;
    name: string;
    guard_name: string;
    team_id: string | null;
    users_count: number;
    permissions: Permission[];
    organization?: Organization;
    created_at: string;
    updated_at: string;
    updated_by?: { name: string } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles', href: '/roles' },
];

const guardNames: SearchableSelectItem[] = [
    { value: 'web', label: 'Web', searchText: 'web application' },
    { value: 'api', label: 'API', searchText: 'api application' },
];


export default function RolesApi() {
    const [inputValues, setInputValues] = useState({
        name: '',
        guard_name: '',
        team_id: '',
    });

    const [activityLogModal, setActivityLogModal] = useState({
        isOpen: false,
        subjectType: '',
        subjectId: '',
        title: '',
    });

    const {
        data: roles,
        loading,
        error,
        sort,
        perPage,
        currentPage,
        totalPages,
        total,
        from,
        to,
        updateFilter,
        updateSort,
        updatePerPage,
        goToPage,
        refresh,
        clearFilters,
    } = useApiData<Role>({
        endpoint: '/api/v1/roles',
        initialFilters: {
            name: '',
            guard_name: '',
            team_id: '',
        },
        initialSort: 'name',
        initialPerPage: 15,
    });

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
            name: '',
            guard_name: '',
            team_id: '',
        });
        clearFilters();
    };

    const handleDelete = async (role: Role) => {
        if (!confirm(`Are you sure you want to delete the role "${role.name}"?`)) {
            return;
        }

        try {
            await apiService.delete(`/api/v1/roles/${role.id}`);
            refresh();
        } catch (error) {
            console.error('Error deleting role:', error);
            alert(error instanceof Error ? error.message : 'Failed to delete role');
        }
    };

    const hasActiveFilters = Object.values(inputValues).some(value => value !== '');

    const getActiveFilters = () => {
        const activeFilters: Array<{key: string, value: string, label: string, displayValue: string}> = [];

        Object.entries(inputValues).forEach(([key, value]) => {
            if (value !== '') {
                let label = '';
                let displayValue = value;

                switch (key) {
                    case 'name':
                        label = 'Name';
                        break;
                    case 'guard_name': {
                        label = 'Guard';
                        const guard = guardNames.find(g => g.value === value);
                        displayValue = guard?.label || value;
                        break;
                    }
                    case 'team_id':
                        label = 'Organization';
                        // You'd need to map this to organization names if needed
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

    const showActivityLog = (role: Role) => {
        setActivityLogModal({
            isOpen: true,
            subjectType: 'Spatie\\Permission\\Models\\Role',
            subjectId: role.id,
            title: `${role.name}`,
        });
    };

    if (error) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Roles" />
                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center text-destructive">
                                Error loading roles: {error}
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
            <Head title="Roles" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Roles</h1>
                        <p className="text-muted-foreground">
                            Manage system roles and their permissions
                        </p>
                    </div>
                    <PermissionGuard permission="role:write">
                        <Link href="/roles/create">
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Create Role
                            </Button>
                        </Link>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Roles ({total || 0})</CardTitle>
                        <CardDescription>
                            View and manage system roles and their permissions
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
                                    placeholder="Select guard..."
                                    items={guardNames}
                                    value={inputValues.guard_name}
                                    onValueChange={(value) => handleFilterChange('guard_name', value)}
                                    emptyLabel="All Guards"
                                    searchPlaceholder="Search guards..."
                                />
                                <div className="relative">
                                    <Input
                                        placeholder="Organization ID..."
                                        value={inputValues.team_id}
                                        onChange={(e) => handleFilterChange('team_id', e.target.value)}
                                        className="pr-10"
                                    />
                                    {inputValues.team_id && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleFilterChange('team_id', '')}
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
                                                onClick={() => handleSort('guard_name')}
                                            >
                                                Guard
                                                {getSortIcon('guard_name')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>Organization</TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('users_count')}
                                            >
                                                Users
                                                {getSortIcon('users_count')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>Permissions</TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('created_at')}
                                            >
                                                Created At
                                                {getSortIcon('created_at')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>Updated By</TableHead>
                                        <TableHead className="w-[120px] sticky right-0 bg-background border-l">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {loading && roles.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={9} className="text-center py-12">
                                                <RotateCcw className="h-8 w-8 animate-spin mx-auto mb-2" />
                                                Loading roles...
                                            </TableCell>
                                        </TableRow>
                                    ) : roles.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={9} className="text-center py-12">
                                                <div className="flex flex-col items-center">
                                                    <Shield className="h-12 w-12 text-muted-foreground mb-4" />
                                                    <h3 className="text-lg font-semibold mb-2">No roles found</h3>
                                                    <p className="text-muted-foreground mb-4">
                                                        {hasActiveFilters
                                                            ? 'No roles match your current filters.'
                                                            : 'Get started by creating your first role.'}
                                                    </p>
                                                    {!hasActiveFilters && (
                                                        <PermissionGuard permission="role:write">
                                                            <Link href="/roles/create">
                                                                <Button>
                                                                    <Plus className="mr-2 h-4 w-4" />
                                                                    Create Role
                                                                </Button>
                                                            </Link>
                                                        </PermissionGuard>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        roles.map((role, index) => (
                                            <TableRow key={role.id}>
                                                <TableCell className="text-center text-muted-foreground sticky left-0 bg-background border-r">
                                                    {(currentPage - 1) * perPage + index + 1}
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {role.name}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline" className="text-xs">
                                                        {role.guard_name}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {role.organization ? role.organization.name : role.team_id ? `Org ${role.team_id}` : 'Global'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="secondary">
                                                        {role.users_count} users
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {role.permissions.length} permissions
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {new Date(role.created_at).toLocaleString()}
                                                </TableCell>
                                                <TableCell>
                                                    {role.updated_by ? role.updated_by.name : '-'}
                                                </TableCell>
                                                <TableCell className="sticky right-0 bg-background border-l">
                                                    <div className="flex items-center gap-1">
                                                        <PermissionGuard permission="role:read">
                                                            <Link href={`/roles/${role.id}`}>
                                                                <Button variant="ghost" size="sm" title="View Details">
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                            </Link>
                                                        </PermissionGuard>
                                                        <PermissionGuard permission="role:write">
                                                            <Link href={`/roles/${role.id}/edit`}>
                                                                <Button variant="ghost" size="sm" title="Edit">
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            </Link>
                                                        </PermissionGuard>
                                                        <PermissionGuard permission="role:delete">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                title="Delete"
                                                                onClick={() => handleDelete(role)}
                                                                disabled={role.users_count > 0}
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
                                                                onClick={() => showActivityLog(role)}
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
