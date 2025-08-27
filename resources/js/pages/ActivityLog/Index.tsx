import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Activity, User, Organization, PageProps } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Search, Filter, Calendar, Building, User as UserIcon, Eye, Clock, AlertCircle } from 'lucide-react';
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
    };
    permissions: {
        canViewAll: boolean;
        canViewOrganization: boolean;
        canViewOwn: boolean;
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
    permissions 
}: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedResource, setSelectedResource] = useState(filters.resource || '');
    const [selectedOrganization, setSelectedOrganization] = useState(filters.organization_id || '');
    const [selectedUser, setSelectedUser] = useState(filters.causer_id || '');
    const [fromDate, setFromDate] = useState(filters.from_date || '');
    const [toDate, setToDate] = useState(filters.to_date || '');

    const handleFilter = () => {
        const params: Record<string, string> = {};
        if (searchTerm) params.search = searchTerm;
        if (selectedResource) params.resource = selectedResource;
        if (selectedOrganization) params.organization_id = selectedOrganization;
        if (selectedUser) params.causer_id = selectedUser;
        if (fromDate) params.from_date = fromDate;
        if (toDate) params.to_date = toDate;

        router.get(route('activity-log.index'), params, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedResource('');
        setSelectedOrganization('');
        setSelectedUser('');
        setFromDate('');
        setToDate('');

        router.get(route('activity-log.index'), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const hasActiveFilters = searchTerm || selectedResource || selectedOrganization || selectedUser || fromDate || toDate;

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
                                        <SelectItem value="">All resources</SelectItem>
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
                                            <SelectItem value="">All organizations</SelectItem>
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
                                            <SelectItem value="">All users</SelectItem>
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
                            <div className="space-y-4">
                                {activities.data.map((activity) => (
                                    <div
                                        key={activity.id}
                                        className="flex items-start gap-4 p-4 border rounded-lg hover:bg-gray-50 transition-colors"
                                    >
                                        <div className="flex-shrink-0">
                                            <div className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border ${getLogNameColor(activity.log_name)}`}>
                                                {getLogNameIcon(activity.log_name)}
                                                {activity.log_name}
                                            </div>
                                        </div>

                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <p className="text-sm font-medium text-gray-900">
                                                        {activity.description}
                                                    </p>
                                                    
                                                    <div className="flex items-center gap-4 mt-2 text-xs text-gray-500">
                                                        {activity.causer && (
                                                            <span className="flex items-center gap-1">
                                                                <UserIcon className="h-3 w-3" />
                                                                {activity.causer.name}
                                                            </span>
                                                        )}

                                                        {activity.organization && (
                                                            <span className="flex items-center gap-1">
                                                                <Building className="h-3 w-3" />
                                                                {activity.organization.name}
                                                            </span>
                                                        )}

                                                        <span className="flex items-center gap-1">
                                                            <Clock className="h-3 w-3" />
                                                            {format(new Date(activity.created_at), 'MMM dd, yyyy HH:mm')}
                                                        </span>
                                                    </div>

                                                    {activity.event && (
                                                        <div className="mt-2">
                                                            <Badge variant="outline" className="text-xs">
                                                                {activity.event}
                                                            </Badge>
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="flex-shrink-0">
                                                    <Link
                                                        href={route('activity-log.show', activity.id)}
                                                        className="inline-flex items-center gap-1 px-2 py-1 text-xs text-blue-600 hover:text-blue-800"
                                                    >
                                                        <Eye className="h-3 w-3" />
                                                        View
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
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
            </div>
        </AppLayout>
    );
}