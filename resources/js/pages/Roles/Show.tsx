import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2, Users, Shield } from 'lucide-react';

interface Permission {
    id: string;
    name: string;
    guard_name: string;
}

interface User {
    id: string;
    name: string;
    email: string;
}

interface Role {
    id: string;
    name: string;
    guard_name: string;
    team_id: string | null;
    permissions: Permission[];
    users: User[];
    created_at: string;
    updated_at: string;
}

interface Props {
    role: Role;
}

const breadcrumbItems = (role: Role): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('roles.index'), title: 'Roles' },
    { href: '', title: role.name },
];

export default function ShowRole({ role }: Props) {
    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete the role "${role.name}"?`)) {
            router.delete(route('roles.destroy', role.id));
        }
    };

    const groupedPermissions = role.permissions.reduce((acc, permission) => {
        const [resource] = permission.name.split(':');
        if (!acc[resource]) {
            acc[resource] = [];
        }
        acc[resource].push(permission);
        return acc;
    }, {} as Record<string, Permission[]>);

    return (
        <AppLayout breadcrumbs={breadcrumbItems(role)}>
            <Head title={`Role: ${role.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('roles.index')}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Roles
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold">{role.name}</h1>
                            <p className="text-muted-foreground">
                                {role.team_id ? 'Organization Role' : 'Global Role'} • Guard: {role.guard_name}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('roles.edit', role.id)}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Role
                            </Link>
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={role.users.length > 0}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete Role
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Role Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-5 w-5" />
                                Role Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Name</label>
                                <p className="mt-1">{role.name}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Guard</label>
                                <p className="mt-1">
                                    <Badge variant="outline">{role.guard_name}</Badge>
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Scope</label>
                                <p className="mt-1">
                                    <Badge variant={role.team_id ? "default" : "secondary"}>
                                        {role.team_id ? 'Organization' : 'Global'}
                                    </Badge>
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Users Assigned</label>
                                <p className="mt-1">
                                    <Badge variant="secondary">
                                        {role.users.length} users
                                    </Badge>
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Permissions</label>
                                <p className="mt-1">
                                    <Badge variant="outline">
                                        {role.permissions.length} permissions
                                    </Badge>
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Created</label>
                                <p className="mt-1 text-sm">
                                    {new Date(role.created_at).toLocaleString()}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Last Updated</label>
                                <p className="mt-1 text-sm">
                                    {new Date(role.updated_at).toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Assigned Users */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Assigned Users ({role.users.length})
                            </CardTitle>
                            <CardDescription>
                                Users who have this role
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {role.users.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No users assigned to this role</p>
                            ) : (
                                <div className="space-y-2">
                                    {role.users.map((user) => (
                                        <div key={user.id} className="flex items-center justify-between p-2 rounded-lg border">
                                            <div>
                                                <p className="font-medium text-sm">{user.name}</p>
                                                <p className="text-xs text-muted-foreground">{user.email}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Permissions */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-5 w-5" />
                                Permissions ({role.permissions.length})
                            </CardTitle>
                            <CardDescription>
                                What this role can do
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {role.permissions.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No permissions assigned</p>
                            ) : (
                                <div className="space-y-4">
                                    {Object.entries(groupedPermissions).map(([resource, permissions]) => (
                                        <div key={resource}>
                                            <h4 className="font-medium text-sm uppercase text-muted-foreground mb-2">
                                                {resource.replace(/[-_]/g, ' ')}
                                            </h4>
                                            <div className="space-y-1">
                                                {permissions.map((permission) => (
                                                    <Badge key={permission.name} variant="outline" className="mr-1 mb-1">
                                                        {permission.name.split(':')[1] || permission.name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}