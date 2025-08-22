import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Eye, Edit, Trash2, Building } from 'lucide-react';

interface Organization {
    id: number;
    name: string;
}

interface Department {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    organization: Organization;
    job_positions_count: number;
    child_departments_count: number;
}

interface Props {
    departments: {
        data: Department[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Departments', href: '/departments' },
];

export default function Index({ departments }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this department?')) {
            router.delete(`/departments/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Departments" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Departments</CardTitle>
                                <CardDescription>
                                    Manage departments across all organizations.
                                </CardDescription>
                            </div>
                            <Link href="/departments/create">
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Department
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
                                        <TableHead>Organization</TableHead>
                                        <TableHead>Job Positions</TableHead>
                                        <TableHead>Sub-departments</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {departments.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center text-muted-foreground">
                                                No departments found.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        departments.data.map((dept) => (
                                            <TableRow key={dept.id}>
                                                <TableCell className="font-medium">{dept.name}</TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Building className="h-4 w-4" />
                                                        <Link 
                                                            href={`/organizations/${dept.organization.id}`}
                                                            className="hover:underline"
                                                        >
                                                            {dept.organization.name}
                                                        </Link>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{dept.job_positions_count}</TableCell>
                                                <TableCell>{dept.child_departments_count}</TableCell>
                                                <TableCell>
                                                    <Badge variant={dept.is_active ? 'default' : 'secondary'}>
                                                        {dept.is_active ? 'Active' : 'Inactive'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Link href={`/departments/${dept.id}`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Link href={`/departments/${dept.id}/edit`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(dept.id)}
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