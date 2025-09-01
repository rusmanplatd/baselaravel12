import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Activity, User, Organization, PageProps } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { apiService } from '@/services/ApiService';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Search, Filter, Building, User as UserIcon, Eye, Clock, AlertCircle, Download, FileText, File, FileSpreadsheet, Loader2, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { format } from 'date-fns';

interface ActivityWithRelations extends Activity {
    causer?: User;
    subject?: any;
    organization?: Organization;
}

interface Props extends PageProps {
    activities: {
        data: ActivityWithRelations[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    resources: Array<{ value: string; label: string }>;
    organizations: Array<{ value: string; label: string }>;
    users: Array<{ value: string; label: string }>;
    filters: {
        resource?: string;
        organization_id?: string;
        from_date?: string;
        to_date?: string;
        causer_id?: string;
        search?: string;
        sort?: string;
    };
    permissions: {
        canViewAll: boolean;
        canViewOrganization: boolean;
        canViewOwn: boolean;
        canExport: boolean;
    };
    exportColumns: {
        basic: Record<string, string>;
        user_info: Record<string, string>;
        subject_info: Record<string, string>;
        organization_info: Record<string, string>;
        metadata: Record<string, string>;
    };
}

const getLogNameColor = (logName: string): string => {
    const colors: Record<string, string> = {
        auth: 'bg-blue-100 text-blue-800 border-blue-200',
        organization: 'bg-green-100 text-green-800 border-green-200',
        oauth: 'bg-purple-100 text-purple-800 border-purple-200',
        system: 'bg-red-100 text-red-800 border-red-200',
        tenant: 'bg-orange-100 text-orange-800 border-orange-200',
        user: 'bg-indigo-100 text-indigo-800 border-indigo-200',
    };
    return colors[logName] || 'bg-gray-100 text-gray-800 border-gray-200';
};

const getLogNameIcon = (logName: string) => {
    const icons: Record<string, typeof UserIcon> = {
        auth: UserIcon,
        organization: Building,
        oauth: AlertCircle,
        system: AlertCircle,
        tenant: Building,
        user: UserIcon,
    };
    const IconComponent = icons[logName] || AlertCircle;
    return <IconComponent className="h-3 w-3" />;
};

export default function ActivityLogIndex({ 
    activities, 
    resources, 
    organizations, 
    users, 
    filters, 
    permissions,
    exportColumns 
}: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedResource, setSelectedResource] = useState(filters.resource || 'all');
    const [selectedOrganization, setSelectedOrganization] = useState(filters.organization_id || 'all');
    const [selectedUser, setSelectedUser] = useState(filters.causer_id || 'all');
    const [fromDate, setFromDate] = useState(filters.from_date || '');
    const [toDate, setToDate] = useState(filters.to_date || '');
    const [currentSort, setCurrentSort] = useState(filters.sort || '-created_at');

    // Export states
    const [isExportDialogOpen, setIsExportDialogOpen] = useState(false);
    const [exportFormat, setExportFormat] = useState<'csv' | 'json' | 'excel' | 'pdf'>('csv');
    const [selectedColumns, setSelectedColumns] = useState<string[]>(['id', 'log_name', 'description', 'causer_name', 'created_at']);
    const [isExporting, setIsExporting] = useState(false);
    const [exportValidation, setExportValidation] = useState<{
        valid: boolean;
        errors: string[];
        estimated_records: number;
        max_records: number;
    } | null>(null);

    const handleFilter = () => {
        const params: Record<string, string> = {};
        if (searchTerm) params.search = searchTerm;
        if (selectedResource && selectedResource !== 'all') params.resource = selectedResource;
        if (selectedOrganization && selectedOrganization !== 'all') params.organization_id = selectedOrganization;
        if (selectedUser && selectedUser !== 'all') params.causer_id = selectedUser;
        if (fromDate) params.from_date = fromDate;
        if (toDate) params.to_date = toDate;
        if (currentSort) params.sort = currentSort;

        router.get(route('activity-log.index'), params, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleSort = (field: string) => {
        let newSort = field;
        
        // Toggle direction if clicking the same field
        if (currentSort === field) {
            newSort = `-${field}`;
        } else if (currentSort === `-${field}`) {
            newSort = field;
        }
        
        setCurrentSort(newSort);
        
        const params: Record<string, string> = {};
        if (searchTerm) params.search = searchTerm;
        if (selectedResource && selectedResource !== 'all') params.resource = selectedResource;
        if (selectedOrganization && selectedOrganization !== 'all') params.organization_id = selectedOrganization;
        if (selectedUser && selectedUser !== 'all') params.causer_id = selectedUser;
        if (fromDate) params.from_date = fromDate;
        if (toDate) params.to_date = toDate;
        params.sort = newSort;

        router.get(route('activity-log.index'), params, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedResource('all');
        setSelectedOrganization('all');
        setSelectedUser('all');
        setFromDate('');
        setToDate('');
        setCurrentSort('-created_at');

        router.get(route('activity-log.index'), { sort: '-created_at' }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const hasActiveFilters = searchTerm || (selectedResource && selectedResource !== 'all') || (selectedOrganization && selectedOrganization !== 'all') || (selectedUser && selectedUser !== 'all') || fromDate || toDate;

    const getSortIcon = (field: string) => {
        if (currentSort === field) {
            return <ArrowUp className="h-3 w-3" />;
        } else if (currentSort === `-${field}`) {
            return <ArrowDown className="h-3 w-3" />;
        }
        return <ArrowUpDown className="h-3 w-3" />;
    };

    const SortableHeader = ({ field, children }: { field: string; children: React.ReactNode }) => (
        <TableHead 
            className="cursor-pointer select-none hover:bg-gray-50" 
            onClick={() => handleSort(field)}
        >
            <div className="flex items-center gap-2">
                {children}
                {getSortIcon(field)}
            </div>
        </TableHead>
    );

    const validateExport = async () => {
        const currentFilters = {
            resource: selectedResource && selectedResource !== 'all' ? selectedResource : undefined,
            organization_id: selectedOrganization && selectedOrganization !== 'all' ? selectedOrganization : undefined,
            causer_id: selectedUser && selectedUser !== 'all' ? selectedUser : undefined,
            from_date: fromDate,
            to_date: toDate,
            search: searchTerm,
        };

        try {
            const data = await apiService.post(route('activity-log.export.validate'), { filters: currentFilters });
            setExportValidation(data);
        } catch (error) {
            console.error('Export validation failed:', error);
            setExportValidation({
                valid: false,
                errors: ['Failed to validate export request'],
                estimated_records: 0,
                max_records: 50000,
            });
        }
    };

    const handleExportAll = async () => {
        setIsExporting(true);
        try {
            const response = await fetch(route('activity-log.export.all'), {
                method: 'POST',
                headers: await apiService.getHeaders(),
                body: JSON.stringify({
                    format: exportFormat,
                    columns: selectedColumns,
                }),
            });

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = response.headers.get('Content-Disposition')?.split('filename="')[1]?.split('"')[0] || `activity_log_export_all.${exportFormat}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                setIsExportDialogOpen(false);
            } else {
                const errorData = await response.json();
                alert(`Export failed: ${errorData.message || 'Unknown error'}`);
            }
        } catch (error) {
            console.error('Export failed:', error);
            alert('Export failed. Please try again.');
        } finally {
            setIsExporting(false);
        }
    };

    const handleExportFiltered = async () => {
        setIsExporting(true);
        try {
            const currentFilters = {
                resource: selectedResource && selectedResource !== 'all' ? selectedResource : undefined,
                organization_id: selectedOrganization && selectedOrganization !== 'all' ? selectedOrganization : undefined,
                causer_id: selectedUser && selectedUser !== 'all' ? selectedUser : undefined,
                from_date: fromDate,
                to_date: toDate,
                search: searchTerm,
            };

            const response = await fetch(route('activity-log.export.filtered'), {
                method: 'POST',
                headers: await apiService.getHeaders(),
                body: JSON.stringify({
                    format: exportFormat,
                    columns: selectedColumns,
                    filters: currentFilters,
                }),
            });

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = response.headers.get('Content-Disposition')?.split('filename="')[1]?.split('"')[0] || `activity_log_export_filtered.${exportFormat}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                setIsExportDialogOpen(false);
            } else {
                const errorData = await response.json();
                alert(`Export failed: ${errorData.message || 'Unknown error'}`);
            }
        } catch (error) {
            console.error('Export failed:', error);
            alert('Export failed. Please try again.');
        } finally {
            setIsExporting(false);
        }
    };

    const toggleColumn = (columnKey: string) => {
        setSelectedColumns(prev => 
            prev.includes(columnKey) 
                ? prev.filter(col => col !== columnKey)
                : [...prev, columnKey]
        );
    };

    const openExportDialog = () => {
        setIsExportDialogOpen(true);
        validateExport();
    };

    useEffect(() => {
        if (isExportDialogOpen) {
            validateExport();
        }
    }, [isExportDialogOpen, selectedResource, selectedOrganization, selectedUser, fromDate, toDate, searchTerm]);

    return (
        <AppLayout>
            <Head title="Activity Log" />

            <div className="p-6">
                <div className="mb-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900">Activity Log</h1>
                            <p className="text-sm text-gray-600 mt-1">
                                {permissions.canViewAll && "View all system activities"}
                                {permissions.canViewOrganization && !permissions.canViewAll && "View organization activities"}
                                {permissions.canViewOwn && !permissions.canViewOrganization && !permissions.canViewAll && "View your activities"}
                            </p>
                        </div>
                        {permissions.canExport && (
                            <div className="flex gap-2">
                                <Button
                                    onClick={openExportDialog}
                                    variant="outline"
                                    className="flex items-center gap-2"
                                >
                                    <Download className="h-4 w-4" />
                                    Export
                                </Button>
                            </div>
                        )}
                    </div>
                </div>

                {/* Filters */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Filter className="h-4 w-4" />
                            Filters
                        </CardTitle>
                        <CardDescription>
                            Filter activities by resource, organization, user, date range, or search terms
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {/* Search */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Search
                                </label>
                                <div className="relative">
                                    <Search className="h-4 w-4 absolute left-3 top-3 text-gray-400" />
                                    <Input
                                        placeholder="Search activities..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                            </div>

                            {/* Resource Filter */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Resource/Menu
                                </label>
                                <Select value={selectedResource} onValueChange={setSelectedResource}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All resources" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All resources</SelectItem>
                                        {resources.map((resource) => (
                                            <SelectItem key={resource.value} value={resource.value}>
                                                {resource.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Organization Filter */}
                            {(permissions.canViewAll || permissions.canViewOrganization) && organizations.length > 0 && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Organization
                                    </label>
                                    <Select value={selectedOrganization} onValueChange={setSelectedOrganization}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="All organizations" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All organizations</SelectItem>
                                            {organizations.map((org) => (
                                                <SelectItem key={org.value} value={org.value}>
                                                    {org.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {/* User Filter */}
                            {(permissions.canViewAll || permissions.canViewOrganization) && users.length > 0 && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        User
                                    </label>
                                    <Select value={selectedUser} onValueChange={setSelectedUser}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="All users" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All users</SelectItem>
                                            {users.map((user) => (
                                                <SelectItem key={user.value} value={user.value}>
                                                    {user.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {/* From Date */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    From Date
                                </label>
                                <Input
                                    type="date"
                                    value={fromDate}
                                    onChange={(e) => setFromDate(e.target.value)}
                                />
                            </div>

                            {/* To Date */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    To Date
                                </label>
                                <Input
                                    type="date"
                                    value={toDate}
                                    onChange={(e) => setToDate(e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="flex gap-2">
                            <Button onClick={handleFilter}>
                                Apply Filters
                            </Button>
                            {hasActiveFilters && (
                                <Button variant="outline" onClick={clearFilters}>
                                    Clear Filters
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Activities List */}
                <Card>
                    <CardHeader>
                        <CardTitle>Activities</CardTitle>
                        <CardDescription>
                            {activities.total} activities found
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {activities.data.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <SortableHeader field="log_name">Type</SortableHeader>
                                        <SortableHeader field="description">Description</SortableHeader>
                                        <SortableHeader field="causer_name">User</SortableHeader>
                                        <SortableHeader field="organization_name">Organization</SortableHeader>
                                        <SortableHeader field="event">Event</SortableHeader>
                                        <SortableHeader field="created_at">Date</SortableHeader>
                                        <TableHead>Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {activities.data.map((activity) => (
                                        <TableRow key={activity.id}>
                                            <TableCell>
                                                <div className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border ${getLogNameColor(activity.log_name)}`}>
                                                    {getLogNameIcon(activity.log_name)}
                                                    {activity.log_name}
                                                </div>
                                            </TableCell>
                                            <TableCell className="max-w-md">
                                                <p className="text-sm font-medium text-gray-900 truncate">
                                                    {activity.description}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {activity.causer ? (
                                                    <span className="flex items-center gap-1 text-sm">
                                                        <UserIcon className="h-3 w-3" />
                                                        {activity.causer.name}
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-400 text-sm">System</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {activity.organization ? (
                                                    <span className="flex items-center gap-1 text-sm">
                                                        <Building className="h-3 w-3" />
                                                        {activity.organization.name}
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-400 text-sm">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {activity.event ? (
                                                    <Badge variant="outline" className="text-xs">
                                                        {activity.event}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-gray-400 text-sm">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span className="flex items-center gap-1 text-sm text-gray-600">
                                                    <Clock className="h-3 w-3" />
                                                    {format(new Date(activity.created_at), 'MMM dd, yyyy HH:mm')}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={route('activity-log.show', activity.id)}
                                                    className="inline-flex items-center gap-1 px-2 py-1 text-xs text-blue-600 hover:text-blue-800 rounded hover:bg-blue-50"
                                                >
                                                    <Eye className="h-3 w-3" />
                                                    View
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-center py-8 text-gray-500">
                                <AlertCircle className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                <p className="text-lg font-medium">No activities found</p>
                                <p className="text-sm">Try adjusting your filters to see more results.</p>
                            </div>
                        )}

                        {/* Pagination */}
                        {activities.last_page > 1 && (
                            <div className="flex items-center justify-between mt-6 pt-6 border-t">
                                <div className="flex items-center text-sm text-gray-500">
                                    Showing {((activities.current_page - 1) * activities.per_page) + 1} to {Math.min(activities.current_page * activities.per_page, activities.total)} of {activities.total} results
                                </div>

                                <div className="flex gap-1">
                                    {activities.links.map((link, index) => (
                                        <Link
                                            key={index}
                                            href={link.url || '#'}
                                            className={`px-3 py-1 text-sm border rounded ${
                                                link.active
                                                    ? 'bg-blue-50 border-blue-200 text-blue-700'
                                                    : link.url
                                                    ? 'border-gray-200 text-gray-700 hover:bg-gray-50'
                                                    : 'border-gray-200 text-gray-400 cursor-not-allowed'
                                            }`}
                                            preserveState
                                            preserveScroll
                                        >
                                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Export Dialog */}
                <Dialog open={isExportDialogOpen} onOpenChange={setIsExportDialogOpen}>
                    <DialogContent className="max-w-4xl max-h-[80vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Download className="h-5 w-5" />
                                Export Activity Log
                            </DialogTitle>
                            <DialogDescription>
                                Configure your export settings to download activity log data in your preferred format.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-6">
                            {/* Validation Results */}
                            {exportValidation && (
                                <div>
                                    {!exportValidation.valid ? (
                                        <Alert className="border-red-200 bg-red-50">
                                            <AlertCircle className="h-4 w-4 text-red-600" />
                                            <AlertDescription className="text-red-800">
                                                <div className="space-y-1">
                                                    {exportValidation.errors.map((error, index) => (
                                                        <div key={index}>{error}</div>
                                                    ))}
                                                </div>
                                            </AlertDescription>
                                        </Alert>
                                    ) : (
                                        <Alert className="border-green-200 bg-green-50">
                                            <AlertDescription className="text-green-800">
                                                Ready to export {exportValidation.estimated_records.toLocaleString()} records.
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                </div>
                            )}

                            {/* Export Options */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Format Selection */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Export Format
                                    </label>
                                    <div className="space-y-2">
                                        <label className="flex items-center space-x-3 cursor-pointer">
                                            <input
                                                type="radio"
                                                value="csv"
                                                checked={exportFormat === 'csv'}
                                                onChange={(e) => setExportFormat(e.target.value as 'csv' | 'json' | 'excel' | 'pdf')}
                                                className="h-4 w-4 text-blue-600"
                                            />
                                            <div className="flex items-center gap-2">
                                                <FileText className="h-4 w-4" />
                                                <div>
                                                    <div className="font-medium">CSV</div>
                                                    <div className="text-sm text-gray-500">Comma-separated values, Excel compatible</div>
                                                </div>
                                            </div>
                                        </label>
                                        <label className="flex items-center space-x-3 cursor-pointer">
                                            <input
                                                type="radio"
                                                value="excel"
                                                checked={exportFormat === 'excel'}
                                                onChange={(e) => setExportFormat(e.target.value as 'csv' | 'json' | 'excel' | 'pdf')}
                                                className="h-4 w-4 text-blue-600"
                                            />
                                            <div className="flex items-center gap-2">
                                                <FileSpreadsheet className="h-4 w-4" />
                                                <div>
                                                    <div className="font-medium">Excel</div>
                                                    <div className="text-sm text-gray-500">Excel spreadsheet with formatting</div>
                                                </div>
                                            </div>
                                        </label>
                                        <label className="flex items-center space-x-3 cursor-pointer">
                                            <input
                                                type="radio"
                                                value="pdf"
                                                checked={exportFormat === 'pdf'}
                                                onChange={(e) => setExportFormat(e.target.value as 'csv' | 'json' | 'excel' | 'pdf')}
                                                className="h-4 w-4 text-blue-600"
                                            />
                                            <div className="flex items-center gap-2">
                                                <File className="h-4 w-4" />
                                                <div>
                                                    <div className="font-medium">PDF</div>
                                                    <div className="text-sm text-gray-500">Printable document format</div>
                                                </div>
                                            </div>
                                        </label>
                                        <label className="flex items-center space-x-3 cursor-pointer">
                                            <input
                                                type="radio"
                                                value="json"
                                                checked={exportFormat === 'json'}
                                                onChange={(e) => setExportFormat(e.target.value as 'csv' | 'json' | 'excel' | 'pdf')}
                                                className="h-4 w-4 text-blue-600"
                                            />
                                            <div className="flex items-center gap-2">
                                                <File className="h-4 w-4" />
                                                <div>
                                                    <div className="font-medium">JSON</div>
                                                    <div className="text-sm text-gray-500">Structured data format for developers</div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                {/* Column Selection */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Select Columns to Export
                                    </label>
                                    <div className="space-y-4 max-h-64 overflow-y-auto">
                                        {Object.entries(exportColumns).map(([categoryKey, categoryColumns]) => (
                                            <div key={categoryKey}>
                                                <h4 className="font-medium text-gray-900 mb-2 capitalize">
                                                    {categoryKey.replace('_', ' ')}
                                                </h4>
                                                <div className="space-y-2 ml-4">
                                                    {Object.entries(categoryColumns).map(([columnKey, columnLabel]) => (
                                                        <label key={columnKey} className="flex items-center space-x-2 cursor-pointer">
                                                            <Checkbox
                                                                checked={selectedColumns.includes(columnKey)}
                                                                onCheckedChange={() => toggleColumn(columnKey)}
                                                            />
                                                            <span className="text-sm">{columnLabel}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {/* Export Actions */}
                            <div className="space-y-3">
                                <div className="text-sm text-gray-600">
                                    Choose which data to export:
                                </div>
                                <div className="flex flex-col sm:flex-row gap-3">
                                    <Button
                                        onClick={handleExportAll}
                                        disabled={!exportValidation?.valid || isExporting || selectedColumns.length === 0}
                                        className="flex items-center gap-2"
                                    >
                                        {isExporting ? (
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                        ) : (
                                            <Download className="h-4 w-4" />
                                        )}
                                        Export All Records
                                        {!hasActiveFilters && exportValidation && (
                                            <span className="text-xs opacity-75">
                                                ({exportValidation.estimated_records.toLocaleString()})
                                            </span>
                                        )}
                                    </Button>
                                    <Button
                                        onClick={handleExportFiltered}
                                        variant="outline"
                                        disabled={!exportValidation?.valid || isExporting || selectedColumns.length === 0}
                                        className="flex items-center gap-2"
                                    >
                                        {isExporting ? (
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                        ) : (
                                            <Filter className="h-4 w-4" />
                                        )}
                                        Export Current Filter
                                        {hasActiveFilters && exportValidation && (
                                            <span className="text-xs opacity-75">
                                                ({exportValidation.estimated_records.toLocaleString()})
                                            </span>
                                        )}
                                    </Button>
                                </div>
                                {selectedColumns.length === 0 && (
                                    <p className="text-sm text-red-600">
                                        Please select at least one column to export.
                                    </p>
                                )}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button variant="outline" onClick={() => setIsExportDialogOpen(false)}>
                                Cancel
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}