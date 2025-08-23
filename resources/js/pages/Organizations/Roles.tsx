import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { Plus, Settings, Users } from 'lucide-react';
import React, { useState } from 'react';

interface Permission {
    id: string;
    name: string;
}

interface Role {
    id: string;
    name: string;
    permissions: Permission[];
    users_count?: number;
}

interface Organization {
    id: string;
    name: string;
    organization_type: string;
}

interface Props {
    organization: Organization;
    roles: Role[];
    permissions?: Permission[];
}

export default function Roles({ organization, roles, permissions = [] }: Props) {
    const [showCreateRole, setShowCreateRole] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        permissions: [] as string[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('organizations.roles.create', organization.id), {
            onSuccess: () => {
                setShowCreateRole(false);
                reset();
            },
        });
    };

    const togglePermission = (permissionName: string) => {
        const currentPermissions = data.permissions;
        if (currentPermissions.includes(permissionName)) {
            setData(
                'permissions',
                currentPermissions.filter((p) => p !== permissionName),
            );
        } else {
            setData('permissions', [...currentPermissions, permissionName]);
        }
    };

    const groupPermissions = (perms: Permission[]) => {
        const groups: { [key: string]: Permission[] } = {};
        perms.forEach((permission) => {
            const [action, ...resourceParts] = permission.name.split(' ');
            const resource = resourceParts.join(' ');
            if (!groups[resource]) {
                groups[resource] = [];
            }
            groups[resource].push(permission);
        });
        return groups;
    };

    const permissionGroups = groupPermissions(permissions);

    return (
        <AppLayout>
            <Head title={`${organization.name} - Roles`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-6 flex items-center justify-between">
                                <div>
                                    <h2 className="text-2xl font-semibold text-gray-900">{organization.name} - Roles</h2>
                                    <p className="mt-1 text-sm text-gray-600">Manage organization-specific roles and permissions</p>
                                </div>
                                <Dialog open={showCreateRole} onOpenChange={setShowCreateRole}>
                                    <DialogTrigger asChild>
                                        <Button>
                                            <Plus className="mr-2 h-4 w-4" />
                                            Create Role
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-2xl">
                                        <DialogHeader>
                                            <DialogTitle>Create New Role</DialogTitle>
                                            <DialogDescription>Create a new role specific to this organization.</DialogDescription>
                                        </DialogHeader>
                                        <form onSubmit={handleSubmit} className="space-y-6">
                                            <div>
                                                <Label htmlFor="name">Role Name</Label>
                                                <Input
                                                    id="name"
                                                    value={data.name}
                                                    onChange={(e) => setData('name', e.target.value)}
                                                    placeholder="e.g. Department Manager"
                                                />
                                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                            </div>

                                            {permissions.length > 0 && (
                                                <div>
                                                    <Label>Permissions</Label>
                                                    <div className="max-h-64 overflow-y-auto rounded-lg border p-4">
                                                        {Object.entries(permissionGroups).map(([resource, perms]) => (
                                                            <div key={resource} className="mb-4">
                                                                <h4 className="mb-2 text-sm font-medium text-gray-900 capitalize">{resource}</h4>
                                                                <div className="grid grid-cols-2 gap-2">
                                                                    {perms.map((permission) => (
                                                                        <label key={permission.id} className="flex items-center space-x-2 text-sm">
                                                                            <Checkbox
                                                                                checked={data.permissions.includes(permission.name)}
                                                                                onCheckedChange={() => togglePermission(permission.name)}
                                                                            />
                                                                            <span className="capitalize">{permission.name.split(' ')[0]}</span>
                                                                        </label>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                    {errors.permissions && <p className="mt-1 text-sm text-red-600">{errors.permissions}</p>}
                                                </div>
                                            )}

                                            <div className="flex justify-end space-x-2">
                                                <Button type="button" variant="outline" onClick={() => setShowCreateRole(false)}>
                                                    Cancel
                                                </Button>
                                                <Button type="submit" disabled={processing}>
                                                    {processing ? 'Creating...' : 'Create Role'}
                                                </Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </div>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Organization Roles</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {roles.length === 0 ? (
                                        <div className="py-8 text-center">
                                            <Settings className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                            <h3 className="mb-2 text-lg font-medium text-gray-900">No roles defined</h3>
                                            <p className="mb-4 text-gray-600">Create your first organization-specific role to get started.</p>
                                            <Button onClick={() => setShowCreateRole(true)}>
                                                <Plus className="mr-2 h-4 w-4" />
                                                Create Role
                                            </Button>
                                        </div>
                                    ) : (
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Role Name</TableHead>
                                                    <TableHead>Permissions</TableHead>
                                                    <TableHead>Users</TableHead>
                                                    <TableHead className="text-right">Actions</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {roles.map((role) => (
                                                    <TableRow key={role.id}>
                                                        <TableCell className="font-medium">{role.name}</TableCell>
                                                        <TableCell>
                                                            <div className="flex flex-wrap gap-1">
                                                                {role.permissions.slice(0, 3).map((permission) => (
                                                                    <Badge key={permission.id} variant="secondary" className="text-xs">
                                                                        {permission.name.split(' ')[0]}
                                                                    </Badge>
                                                                ))}
                                                                {role.permissions.length > 3 && (
                                                                    <Badge variant="secondary" className="text-xs">
                                                                        +{role.permissions.length - 3} more
                                                                    </Badge>
                                                                )}
                                                                {role.permissions.length === 0 && (
                                                                    <span className="text-sm text-gray-500">No permissions</span>
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex items-center space-x-1">
                                                                <Users className="h-4 w-4 text-gray-400" />
                                                                <span>{role.users_count || 0}</span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Button variant="outline" size="sm">
                                                                Edit
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
