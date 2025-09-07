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
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { apiService } from '@/services/ApiService';
import {
    Briefcase,
    Eye,
    Edit,
    Trash2,
    Plus,
    Users,
    DollarSign,
    Search,
    ArrowUpDown,
    RotateCcw,
    X,
    SortAsc,
    SortDesc,
    FileText
} from 'lucide-react';
import { useState, useCallback } from 'react';
import { debounce } from 'lodash';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Organization {
    id: number;
    name: string;
    organization_type: string;
}

interface OrganizationUnit {
    id: string;
    name: string;
    organization: Organization;
}

interface OrganizationPositionLevel {
    id: string;
    name: string;
    code: string;
    hierarchy_level: number;
}

interface User {
    id: string;
    name: string;
    email: string;
    avatar?: string;
}

interface OrganizationMembership {
    id: string;
    user: User;
    status: string;
}

interface OrganizationPosition {
    id: string;
    organization_id: number;
    organization_unit_id: string;
    position_code: string;
    title: string;
    job_description: string | null;
    qualifications: string[] | null;
    responsibilities: string[] | null;
    min_salary: number | null;
    max_salary: number | null;
    is_active: boolean;
    max_incumbents: number;
    organization_unit: OrganizationUnit;
    organization_position_level: OrganizationPositionLevel;
    active_memberships: OrganizationMembership[];
    active_memberships_count: number;
    salary_range: string;
    full_title: string;
    created_at: string;
    updated_at: string;
    updated_by?: { name: string } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization Positions', href: '/organization-positions' },
];

const positionLevelColors: Record<string, string> = {
    board_member: 'bg-purple-100 text-purple-800 border-purple-200',
    c_level: 'bg-red-100 text-red-800 border-red-200',
    vice_president: 'bg-indigo-100 text-indigo-800 border-indigo-200',
    director: 'bg-blue-100 text-blue-800 border-blue-200',
    senior_manager: 'bg-cyan-100 text-cyan-800 border-cyan-200',
    manager: 'bg-green-100 text-green-800 border-green-200',
    assistant_manager: 'bg-teal-100 text-teal-800 border-teal-200',
    supervisor: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    senior_staff: 'bg-orange-100 text-orange-800 border-orange-200',
    staff: 'bg-amber-100 text-amber-800 border-amber-200',
    junior_staff: 'bg-gray-100 text-gray-800 border-gray-200',
};

const positionLevels: SearchableSelectItem[] = [
    { value: '1', label: 'Level 1 (Board)', searchText: 'board member' },
    { value: '2', label: 'Level 2 (C-Level)', searchText: 'executive ceo cfo cto' },
    { value: '3', label: 'Level 3 (VP)', searchText: 'vice president' },
    { value: '4', label: 'Level 4 (Director)', searchText: 'director' },
    { value: '5', label: 'Level 5 (Sr. Manager)', searchText: 'senior manager' },
    { value: '6', label: 'Level 6 (Manager)', searchText: 'manager' },
    { value: '7', label: 'Level 7 (Asst. Manager)', searchText: 'assistant manager' },
    { value: '8', label: 'Level 8 (Supervisor)', searchText: 'supervisor' },
    { value: '9', label: 'Level 9 (Sr. Staff)', searchText: 'senior staff' },
    { value: '10', label: 'Level 10 (Staff)', searchText: 'staff' },
    { value: '11', label: 'Level 11 (Jr. Staff)', searchText: 'junior staff' },
];

const statusOptions: SearchableSelectItem[] = [
    { value: '1', label: 'Active', searchText: 'active enabled' },
    { value: '0', label: 'Inactive', searchText: 'inactive disabled' },
];

export default function OrganizationPositionsApi() {
    const [inputValues, setInputValues] = useState({
        position_code: '',
        title: '',
        job_description: '',
        organization_unit_id: '',
        position_level: '',
        is_active: '',
    });

    const [activityLogModal, setActivityLogModal] = useState({
        isOpen: false,
        subjectType: '',
        subjectId: '',
        title: '',
    });

    const {
        data: positions,
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
    } = useApiData<OrganizationPosition>({
        endpoint: '/api/v1/organization-positions',
        initialFilters: {
            position_code: '',
            title: '',
            job_description: '',
            organization_unit_id: '',
            position_level: '',
            is_active: '',
        },
        initialSort: 'title',
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
            position_code: '',
            title: '',
            job_description: '',
            organization_unit_id: '',
            position_level: '',
            is_active: '',
        });
        clearFilters();
    };

    const handleDelete = async (position: OrganizationPosition) => {
        if (!confirm(`Are you sure you want to delete the position "${position.title}"?`)) {
            return;
        }

        try {
            await apiService.delete(`/api/v1/organization-positions/${position.id}`);
            refresh();
        } catch (error) {
            console.error('Error deleting position:', error);
            alert('Failed to delete position');
        }
    };

    const getAvailableSlots = (position: OrganizationPosition) => {
        return position.max_incumbents - position.active_memberships_count;
    };

    const hasActiveFilters = Object.values(inputValues).some(value => value !== '');

    const getActiveFilters = () => {
        const activeFilters: Array<{key: string, value: string, label: string, displayValue: string}> = [];

        Object.entries(inputValues).forEach(([key, value]) => {
            if (value !== '') {
                let label = '';
                let displayValue = value;

                switch (key) {
                    case 'position_code':
                        label = 'Code';
                        break;
                    case 'title':
                        label = 'Title';
                        break;
                    case 'job_description':
                        label = 'Description';
                        break;
                    case 'position_level': {
                        label = 'Level';
                        const level = positionLevels.find(l => l.value === value);
                        displayValue = level?.label || value;
                        break;
                    }
                    case 'is_active':
                        label = 'Status';
                        displayValue = value === '1' ? 'Active' : 'Inactive';
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

    const showActivityLog = (position: OrganizationPosition) => {
        setActivityLogModal({
            isOpen: true,
            subjectType: 'App\\Models\\OrganizationPosition',
            subjectId: position.id,
            title: `${position.title} (${position.position_code})`,
        });
    };

    if (error) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Organization Positions" />
                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center text-destructive">
                                Error loading positions: {error}
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
            <Head title="Organization Positions" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Organization Positions</h1>
                        <p className="text-muted-foreground">
                            Manage organizational positions and their hierarchical levels
                        </p>
                    </div>
                    <PermissionGuard permission="org_position:write">
                        <Link href="/organization-positions/create">
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Position
                            </Button>
                        </Link>
                    </PermissionGuard>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Organization Positions ({total || 0})</CardTitle>
                        <CardDescription>
                            View and manage organizational positions and their hierarchical levels
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4 z-10" />
                                    <Input
                                        placeholder="Search by code..."
                                        value={inputValues.position_code}
                                        onChange={(e) => handleFilterChange('position_code', e.target.value)}
                                        className="pl-10 pr-10"
                                    />
                                    {inputValues.position_code && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleFilterChange('position_code', '')}
                                            className="absolute right-1 top-1/2 transform -translate-y-1/2 h-8 w-8 p-0 hover:bg-muted"
                                        >
                                            <X className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4 z-10" />
                                    <Input
                                        placeholder="Search by title..."
                                        value={inputValues.title}
                                        onChange={(e) => handleFilterChange('title', e.target.value)}
                                        className="pl-10 pr-10"
                                    />
                                    {inputValues.title && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleFilterChange('title', '')}
                                            className="absolute right-1 top-1/2 transform -translate-y-1/2 h-8 w-8 p-0 hover:bg-muted"
                                        >
                                            <X className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                                <SearchableSelect
                                    placeholder="Select position level..."
                                    items={positionLevels}
                                    value={inputValues.position_level}
                                    onValueChange={(value) => handleFilterChange('position_level', value)}
                                    emptyLabel="All Levels"
                                    searchPlaceholder="Search levels..."
                                />
                                <SearchableSelect
                                    placeholder="Select status..."
                                    items={statusOptions}
                                    value={inputValues.is_active}
                                    onValueChange={(value) => handleFilterChange('is_active', value)}
                                    emptyLabel="All Status"
                                    searchPlaceholder="Search status..."
                                />
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
                                        <TableHead className="w-[120px] sticky left-[30px] bg-background border-r">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('position_code')}
                                            >
                                                Code
                                                {getSortIcon('position_code')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('title')}
                                            >
                                                Position
                                                {getSortIcon('title')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('level')}
                                            >
                                                Level
                                                {getSortIcon('level')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('unit')}
                                            >
                                                Unit
                                                {getSortIcon('unit')}
                                            </Button>
                                        </TableHead>
                                        <TableHead className="w-[100px]">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('max_incumbents')}
                                            >
                                                Capacity
                                                {getSortIcon('max_incumbents')}
                                            </Button>
                                        </TableHead>
                                        <TableHead>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 data-[state=open]:bg-accent"
                                                onClick={() => handleSort('min_salary')}
                                            >
                                                Salary Range
                                                {getSortIcon('min_salary')}
                                            </Button>
                                        </TableHead>
                                        <TableHead className="w-[80px]">Status</TableHead>
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
                                    {loading && positions.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={11} className="text-center py-12">
                                                <RotateCcw className="h-8 w-8 animate-spin mx-auto mb-2" />
                                                Loading positions...
                                            </TableCell>
                                        </TableRow>
                                    ) : positions.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={11} className="text-center py-12">
                                                <div className="flex flex-col items-center">
                                                    <Briefcase className="h-12 w-12 text-muted-foreground mb-4" />
                                                    <h3 className="text-lg font-semibold mb-2">No positions found</h3>
                                                    <p className="text-muted-foreground mb-4">
                                                        {hasActiveFilters
                                                            ? 'No positions match your current filters.'
                                                            : 'Get started by creating your first organizational position.'
                                                        }
                                                    </p>
                                                    {!hasActiveFilters && (
                                                        <PermissionGuard permission="org_position:write">
                                                            <Link href="/organization-positions/create">
                                                                <Button>
                                                                    <Plus className="mr-2 h-4 w-4" />
                                                                    Create Position
                                                                </Button>
                                                            </Link>
                                                        </PermissionGuard>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        positions.map((position, index) => (
                                            <TableRow key={position.id} className={!position.is_active ? 'opacity-60' : ''}>
                                                <TableCell className="text-center text-muted-foreground sticky left-0 bg-background border-r">
                                                    {(currentPage - 1) * perPage + index + 1}
                                                </TableCell>
                                                <TableCell className="font-medium sticky left-[30px] bg-background border-r">
                                                    {position.position_code}
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{position.title}</div>
                                                        {position.job_description && (
                                                            <div className="text-sm text-muted-foreground line-clamp-1">
                                                                {position.job_description}
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant="outline"
                                                        className={`text-xs ${positionLevelColors[position.organization_position_level?.code] || 'bg-gray-100 text-gray-800 border-gray-200'}`}
                                                    >
                                                        {position.organization_position_level?.name}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        <div className="font-medium">{position.organization_unit.name}</div>
                                                        <div className="text-muted-foreground">{position.organization_unit.organization.name}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Users className="h-3 w-3" />
                                                        <span className="text-sm">
                                                            {position.active_memberships_count}/{position.max_incumbents}
                                                        </span>
                                                        {getAvailableSlots(position) > 0 ? (
                                                            <Badge variant="outline" className="text-xs bg-green-50 text-green-600 border-green-200">
                                                                {getAvailableSlots(position)} open
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="text-xs bg-red-50 text-red-600 border-red-200">
                                                                Full
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {(position.min_salary || position.max_salary) ? (
                                                        <div className="flex items-center gap-1 text-sm">
                                                            <DollarSign className="h-3 w-3" />
                                                            <span>{position.salary_range}</span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">Not specified</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={position.is_active ? "default" : "secondary"} className="text-xs">
                                                        {position.is_active ? 'Active' : 'Inactive'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {new Date(position.updated_at).toLocaleString()}
                                                </TableCell>
                                                <TableCell>
                                                    {position.updated_by ? position.updated_by.name : '-'}
                                                </TableCell>
                                                <TableCell className="sticky right-0 bg-background border-l">
                                                    <div className="flex items-center gap-1">
                                                        <PermissionGuard permission="org_position:read">
                                                            <Link href={`/organization-positions/${position.id}`}>
                                                                <Button variant="ghost" size="sm" title="View Details">
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                            </Link>
                                                        </PermissionGuard>
                                                        <PermissionGuard permission="org_position:write">
                                                            <Link href={`/organization-positions/${position.id}/edit`}>
                                                                <Button variant="ghost" size="sm" title="Edit">
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            </Link>
                                                        </PermissionGuard>
                                                        <PermissionGuard permission="org_position:delete">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                title="Delete"
                                                                onClick={() => handleDelete(position)}
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
                                                                onClick={() => showActivityLog(position)}
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
