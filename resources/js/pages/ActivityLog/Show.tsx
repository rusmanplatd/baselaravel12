import { Head, Link } from '@inertiajs/react';
import { Activity, User, Organization, PageProps } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Calendar, User as UserIcon, Building, FileText, Code, Eye } from 'lucide-react';
import { format } from 'date-fns';

interface ActivityWithRelations extends Activity {
    causer?: User;
    subject?: any;
    organization?: Organization;
}

interface Props extends PageProps {
    activity: ActivityWithRelations;
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

export default function ActivityLogShow({ activity }: Props) {
    const formatValue = (value: any): string => {
        if (value === null || value === undefined) {
            return 'N/A';
        }

        if (typeof value === 'object') {
            return JSON.stringify(value, null, 2);
        }

        return String(value);
    };

    return (
        <AppLayout>
            <Head title={`Activity: ${activity.description}`} />

            <div className="p-6">
                <div className="mb-6">
                    <div className="flex items-center gap-4 mb-4">
                        <Link
                            href={route('activity-log.index')}
                            className="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back to Activity Log
                        </Link>
                    </div>

                    <div className="flex items-start justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900 mb-2">
                                Activity Details
                            </h1>
                            <div className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border ${getLogNameColor(activity.log_name)}`}>
                                {activity.log_name}
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Activity Details */}
                    <div className="lg:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-5 w-5" />
                                    Activity Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Description
                                    </label>
                                    <p className="text-sm text-gray-900 p-3 bg-gray-50 rounded-md">
                                        {activity.description}
                                    </p>
                                </div>

                                {activity.event && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Event
                                        </label>
                                        <Badge variant="outline">
                                            {activity.event}
                                        </Badge>
                                    </div>
                                )}

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Log Name
                                        </label>
                                        <p className="text-sm text-gray-900">
                                            {activity.log_name}
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Created At
                                        </label>
                                        <p className="text-sm text-gray-900 flex items-center gap-1">
                                            <Calendar className="h-4 w-4" />
                                            {format(new Date(activity.created_at), 'MMM dd, yyyy HH:mm:ss')}
                                        </p>
                                    </div>
                                </div>

                                {activity.batch_ulid && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Batch UUID
                                        </label>
                                        <p className="text-sm text-gray-900 font-mono">
                                            {activity.batch_ulid}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Properties */}
                        {activity.properties && Object.keys(activity.properties).length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Code className="h-5 w-5" />
                                        Properties
                                    </CardTitle>
                                    <CardDescription>
                                        Additional data associated with this activity
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {Object.entries(activity.properties).map(([key, value]) => (
                                            <div key={key}>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    {key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                                </label>
                                                <pre className="text-sm text-gray-900 p-3 bg-gray-50 rounded-md overflow-auto">
                                                    {formatValue(value)}
                                                </pre>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Subject Details */}
                        {activity.subject && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Subject</CardTitle>
                                    <CardDescription>
                                        The model or entity this activity was performed on
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Type
                                            </label>
                                            <p className="text-sm text-gray-900">
                                                {activity.subject_type}
                                            </p>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                ID
                                            </label>
                                            <p className="text-sm text-gray-900 font-mono">
                                                {activity.subject_id}
                                            </p>
                                        </div>

                                        {activity.subject && typeof activity.subject === 'object' && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Details
                                                </label>
                                                <pre className="text-sm text-gray-900 p-3 bg-gray-50 rounded-md overflow-auto">
                                                    {JSON.stringify(activity.subject, null, 2)}
                                                </pre>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar with Context Info */}
                    <div className="space-y-6">
                        {/* User Context */}
                        {activity.causer && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <UserIcon className="h-5 w-5" />
                                        User
                                    </CardTitle>
                                    <CardDescription>
                                        User who performed this activity
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Name
                                            </label>
                                            <p className="text-sm text-gray-900">
                                                {activity.causer.name}
                                            </p>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Email
                                            </label>
                                            <p className="text-sm text-gray-900">
                                                {activity.causer.email}
                                            </p>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                ID
                                            </label>
                                            <p className="text-sm text-gray-900 font-mono">
                                                {activity.causer.id}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Organization Context */}
                        {activity.organization && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building className="h-5 w-5" />
                                        Organization
                                    </CardTitle>
                                    <CardDescription>
                                        Organization context for this activity
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Name
                                            </label>
                                            <p className="text-sm text-gray-900">
                                                {activity.organization.name}
                                            </p>
                                        </div>

                                        {activity.organization.organization_code && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">
                                                    Code
                                                </label>
                                                <p className="text-sm text-gray-900 font-mono">
                                                    {activity.organization.organization_code}
                                                </p>
                                            </div>
                                        )}

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                ID
                                            </label>
                                            <p className="text-sm text-gray-900 font-mono">
                                                {activity.organization.id}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Technical Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Eye className="h-5 w-5" />
                                    Technical Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Activity ID
                                        </label>
                                        <p className="text-sm text-gray-900 font-mono">
                                            {activity.id}
                                        </p>
                                    </div>

                                    {activity.tenant_id && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Tenant ID
                                            </label>
                                            <p className="text-sm text-gray-900 font-mono">
                                                {activity.tenant_id}
                                            </p>
                                        </div>
                                    )}

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Created
                                        </label>
                                        <p className="text-sm text-gray-900">
                                            {format(new Date(activity.created_at), 'PPpp')}
                                        </p>
                                    </div>

                                    {activity.updated_at && activity.updated_at !== activity.created_at && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Updated
                                            </label>
                                            <p className="text-sm text-gray-900">
                                                {format(new Date(activity.updated_at), 'PPpp')}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
