import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2, User, Shield, Building, Mail, Calendar, CheckCircle, XCircle } from 'lucide-react';

interface Permission {
    id: string;
    name: string;
    guard_name: string;
}

interface Role {
    id: string;
    name: string;
    guard_name: string;
    permissions: Permission[];
}

interface Organization {
    id: string;
    name: string;
}

interface OrganizationMembership {
    id: string;
    organization: Organization;
    role: string | null;
    status: string;
}

interface User {
    id: string;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles: Role[];
    organization_memberships?: OrganizationMembership[];
    created_at: string;
    updated_at: string;
}

interface Props {
    user: User;
}

const breadcrumbItems = (user: User): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('users.index'), title: 'Users' },
    { href: '', title: user.name },
];

export default function ShowUser({ user }: Props) {
    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete the user "${user.name}"?`)) {
            router.delete(route('users.destroy', user.id));
        }
    };

    const allPermissions = user.roles.flatMap(role => role.permissions);
    const uniquePermissions = allPermissions.filter((permission, index, self) => 
        self.findIndex(p => p.name === permission.name) === index
    );

    return (
        <AppLayout breadcrumbs={breadcrumbItems(user)}>
            <Head title={`User: ${user.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('users.index')}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Users
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold">{user.name}</h1>
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Mail className="h-4 w-4" />
                                {user.email}
                                {user.email_verified_at ? (
                                    <Badge variant="default" className="ml-2">
                                        <CheckCircle className="mr-1 h-3 w-3" />
                                        Verified
                                    </Badge>
                                ) : (
                                    <Badge variant="destructive" className="ml-2">
                                        <XCircle className="mr-1 h-3 w-3" />
                                        Unverified
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('users.edit', user.id)}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Roles
                            </Link>
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete User
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* User Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5" />
                                User Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Full Name</label>
                                <p className="mt-1">{user.name}</p>
                            </div>
                            
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Email Address</label>
                                <p className="mt-1">{user.email}</p>
                            </div>
                            
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Email Status</label>
                                <p className="mt-1">
                                    {user.email_verified_at ? (
                                        <Badge variant="default">
                                            <CheckCircle className="mr-1 h-3 w-3" />
                                            Verified on {new Date(user.email_verified_at).toLocaleDateString()}
                                        </Badge>
                                    ) : (
                                        <Badge variant="destructive">
                                            <XCircle className="mr-1 h-3 w-3" />
                                            Not Verified
                                        </Badge>
                                    )}
                                </p>
                            </div>
                            
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Member Since</label>
                                <p className="mt-1 text-sm flex items-center gap-2">
                                    <Calendar className="h-4 w-4" />
                                    {new Date(user.created_at).toLocaleString()}
                                </p>
                            </div>
                            
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Last Updated</label>
                                <p className="mt-1 text-sm">
                                    {new Date(user.updated_at).toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Assigned Roles */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-5 w-5" />
                                Assigned Roles ({user.roles.length})
                            </CardTitle>
                            <CardDescription>
                                Roles assigned to this user
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {user.roles.length === 0 ? (
                                <div className="text-center py-8">
                                    <p className="text-sm text-muted-foreground">
                                        No roles assigned to this user
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {user.roles.map((role) => (
                                        <div key={role.id} className="flex items-center justify-between p-3 rounded-lg border">
                                            <div className="space-y-1">
                                                <p className="font-medium">{role.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {role.permissions.length} permissions • Guard: {role.guard_name}
                                                </p>
                                            </div>
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={route('roles.show', role.id)}>
                                                    View Role
                                                </Link>
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Organization Memberships */}
                {user.organization_memberships && user.organization_memberships.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building className="h-5 w-5" />
                                Organization Memberships ({user.organization_memberships.length})
                            </CardTitle>
                            <CardDescription>
                                Organizations this user belongs to
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Organization</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {user.organization_memberships.map((membership) => (
                                        <TableRow key={membership.id}>
                                            <TableCell>
                                                <Link
                                                    href={route('organizations.show', membership.organization.id)}
                                                    className="hover:underline text-blue-600"
                                                >
                                                    {membership.organization.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {membership.role ? (
                                                    <Badge variant="outline">{membership.role}</Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">No specific role</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={membership.status === 'active' ? 'default' : 'secondary'}>
                                                    {membership.status}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {/* Effective Permissions */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Shield className="h-5 w-5" />
                            Effective Permissions ({uniquePermissions.length})
                        </CardTitle>
                        <CardDescription>
                            All permissions granted through assigned roles
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {uniquePermissions.length === 0 ? (
                            <div className="text-center py-8">
                                <p className="text-sm text-muted-foreground">
                                    No permissions granted through roles
                                </p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                {uniquePermissions.map((permission) => (
                                    <Badge key={permission.name} variant="outline" className="justify-start">
                                        {permission.name}
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}