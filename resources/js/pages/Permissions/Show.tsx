import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2, Shield, Users } from 'lucide-react';

interface Role {
    id: string;
    name: string;
    team_id: string | null;
    users_count?: number;
}

interface Permission {
    id: string;
    name: string;
    guard_name: string;
    roles: Role[];
    created_at: string;
    updated_at: string;
}

interface Props {
    permission: Permission;
}

const breadcrumbItems = (permission: Permission): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('permissions.index'), title: 'Permissions' },
    { href: '', title: permission.name },
];

export default function ShowPermission({ permission }: Props) {
    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete the permission "${permission.name}"?`)) {
            router.delete(route('permissions.destroy', permission.id));
        }
    };

    const [resource, action] = permission.name.includes(':') 
        ? permission.name.split(':') 
        : [permission.name, ''];

    return (
        <AppLayout breadcrumbs={breadcrumbItems(permission)}>
            <Head title={`Permission: ${permission.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('permissions.index')}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Permissions
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold">{permission.name}</h1>
                            <p className="text-muted-foreground">
                                {resource && action ? (
                                    <>
                                        <span className="font-medium">{resource.replace(/[-_]/g, ' ')}</span> permission
                                        <span className="mx-2">•</span>
                                        Guard: {permission.guard_name}
                                    </>
                                ) : (
                                    `Guard: ${permission.guard_name}`
                                )}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('permissions.edit', permission.id)}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Permission
                            </Link>
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={permission.roles.length > 0}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete Permission
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Permission Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-5 w-5" />
                                Permission Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Full Name</label>
                                <p className="mt-1 font-mono text-sm bg-muted p-2 rounded">
                                    {permission.name}
                                </p>
                            </div>
                            
                            {resource && action && (
                                <>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Resource</label>
                                        <p className="mt-1">
                                            <Badge variant="outline">
                                                {resource.replace(/[-_]/g, ' ')}
                                            </Badge>
                                        </p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Action</label>
                                        <p className="mt-1">
                                            <Badge variant="secondary">
                                                {action}
                                            </Badge>
                                        </p>
                                    </div>
                                </>
                            )}
                            
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Guard</label>
                                <p className="mt-1">
                                    <Badge variant="outline">{permission.guard_name}</Badge>
                                </p>
                            </div>
                            
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Assigned to Roles</label>
                                <p className="mt-1">
                                    <Badge variant="secondary">
                                        {permission.roles.length} roles
                                    </Badge>
                                </p>
                            </div>
                            
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Created</label>
                                <p className="mt-1 text-sm">
                                    {new Date(permission.created_at).toLocaleString()}
                                </p>
                            </div>
                            
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Last Updated</label>
                                <p className="mt-1 text-sm">
                                    {new Date(permission.updated_at).toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Assigned Roles */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Assigned Roles ({permission.roles.length})
                            </CardTitle>
                            <CardDescription>
                                Roles that have this permission
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {permission.roles.length === 0 ? (
                                <div className="text-center py-8">
                                    <p className="text-sm text-muted-foreground">
                                        This permission is not assigned to any roles
                                    </p>
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Role Name</TableHead>
                                            <TableHead>Scope</TableHead>
                                            <TableHead>Users</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {permission.roles.map((role) => (
                                            <TableRow key={role.id}>
                                                <TableCell className="font-medium">
                                                    <Link
                                                        href={route('roles.show', role.id)}
                                                        className="hover:underline text-blue-600"
                                                    >
                                                        {role.name}
                                                    </Link>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={role.team_id ? "default" : "secondary"}>
                                                        {role.team_id ? 'Organization' : 'Global'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {role.users_count || 0} users
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Usage Information */}
                <Card>
                    <CardHeader>
                        <CardTitle>Usage Information</CardTitle>
                        <CardDescription>
                            How this permission is being used in the system
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="text-center">
                                <div className="text-2xl font-bold text-blue-600">
                                    {permission.roles.length}
                                </div>
                                <p className="text-sm text-muted-foreground">Roles</p>
                            </div>
                            <div className="text-center">
                                <div className="text-2xl font-bold text-green-600">
                                    {permission.roles.reduce((total, role) => total + (role.users_count || 0), 0)}
                                </div>
                                <p className="text-sm text-muted-foreground">Users (via roles)</p>
                            </div>
                            <div className="text-center">
                                <div className="text-2xl font-bold text-purple-600">
                                    {permission.roles.filter(role => role.team_id).length}
                                </div>
                                <p className="text-sm text-muted-foreground">Organization Roles</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}