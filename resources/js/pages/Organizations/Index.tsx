import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Eye, Edit, Trash2 } from 'lucide-react';

interface Organization {
    id: number;
    name: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    is_active: boolean;
    departments_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    organizations: {
        data: Organization[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organizations', href: '/organizations' },
];

export default function Index({ organizations }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this organization?')) {
            router.delete(`/organizations/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organizations" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Organizations</CardTitle>
                                <CardDescription>
                                    Manage your organizations and their departments.
                                </CardDescription>
                            </div>
                            <Link href="/organizations/create">
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Organization
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Departments</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {organizations.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={5} className="text-center text-muted-foreground">
                                                No organizations found.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        organizations.data.map((org) => (
                                            <TableRow key={org.id}>
                                                <TableCell className="font-medium">{org.name}</TableCell>
                                                <TableCell>{org.email || '-'}</TableCell>
                                                <TableCell>{org.departments_count}</TableCell>
                                                <TableCell>
                                                    <Badge variant={org.is_active ? 'default' : 'secondary'}>
                                                        {org.is_active ? 'Active' : 'Inactive'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Link href={`/organizations/${org.id}`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Link href={`/organizations/${org.id}/edit`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(org.id)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}