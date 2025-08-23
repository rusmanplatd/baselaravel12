import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit, Building, Users, Briefcase } from 'lucide-react';

interface Department {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    job_positions_count: number;
}

interface Organization {
    id: number;
    name: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    is_active: boolean;
    departments: Department[];
}

interface Props {
    organization: Organization;
}

export default function Show({ organization }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Organizations', href: '/organizations' },
        { title: organization.name, href: `/organizations/${organization.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={organization.name} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <Building className="h-6 w-6" />
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        {organization.name}
                                        <Badge variant={organization.is_active ? 'default' : 'secondary'}>
                                            {organization.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription>
                                        Organization details and departments
                                    </CardDescription>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <Link href={`/organizations/${organization.id}/edit`}>
                                    <Button variant="outline">
                                        <Edit className="mr-2 h-4 w-4" />
                                        Edit
                                    </Button>
                                </Link>
                                <Link href="/organizations">
                                    <Button variant="outline">
                                        <ArrowLeft className="mr-2 h-4 w-4" />
                                        Back
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            <div className="space-y-4">
                                <div>
                                    <h4 className="font-semibold">Contact Information</h4>
                                    <div className="mt-2 space-y-2 text-sm">
                                        {organization.email && (
                                            <div>
                                                <span className="font-medium">Email:</span> {organization.email}
                                            </div>
                                        )}
                                        {organization.phone && (
                                            <div>
                                                <span className="font-medium">Phone:</span> {organization.phone}
                                            </div>
                                        )}
                                        {organization.website && (
                                            <div>
                                                <span className="font-medium">Website:</span>{' '}
                                                <a 
                                                    href={organization.website} 
                                                    target="_blank" 
                                                    rel="noopener noreferrer"
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {organization.website}
                                                </a>
                                            </div>
                                        )}
                                    </div>
                                </div>
                                
                                {organization.address && (
                                    <div>
                                        <h4 className="font-semibold">Address</h4>
                                        <p className="mt-2 text-sm">{organization.address}</p>
                                    </div>
                                )}
                            </div>
                            
                            {organization.description && (
                                <div>
                                    <h4 className="font-semibold">Description</h4>
                                    <p className="mt-2 text-sm">{organization.description}</p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                <CardTitle>Departments</CardTitle>
                            </div>
                            <Link href="/departments/create">
                                <Button>
                                    Add Department
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {organization.departments.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">
                                No departments found for this organization.
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Description</TableHead>
                                            <TableHead>Job Positions</TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {organization.departments.map((dept) => (
                                            <TableRow key={dept.id}>
                                                <TableCell className="font-medium">
                                                    <Link 
                                                        href={`/departments/${dept.id}`}
                                                        className="hover:underline"
                                                    >
                                                        {dept.name}
                                                    </Link>
                                                </TableCell>
                                                <TableCell>{dept.description || '-'}</TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Briefcase className="h-4 w-4" />
                                                        {dept.job_positions_count}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={dept.is_active ? 'default' : 'secondary'}>
                                                        {dept.is_active ? 'Active' : 'Inactive'}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}