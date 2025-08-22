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
    organization_code: string | null;
    name: string;
    organization_type: 'holding_company' | 'subsidiary' | 'division' | 'branch' | 'department' | 'unit';
    parent_organization_id: number | null;
    description: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    registration_number: string | null;
    tax_number: string | null;
    authorized_capital: string | null;
    paid_capital: string | null;
    establishment_date: string | null;
    legal_status: string | null;
    business_activities: string | null;
    level: number;
    path: string | null;
    is_active: boolean;
    departments_count: number;
    organization_units_count: number;
    child_organizations_count: number;
    parent_organization?: {
        id: number;
        name: string;
        organization_type: string;
    } | null;
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
                                        <TableHead>Type</TableHead>
                                        <TableHead>Parent</TableHead>
                                        <TableHead>Units</TableHead>
                                        <TableHead>Children</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {organizations.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="text-center text-muted-foreground">
                                                No organizations found.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        organizations.data.map((org) => (
                                            <TableRow key={org.id}>
                                                <TableCell className="font-medium">
                                                    <div className="flex flex-col">
                                                        <span className="font-semibold">{org.name}</span>
                                                        {org.organization_code && (
                                                            <span className="text-sm text-muted-foreground">{org.organization_code}</span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {org.organization_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {org.parent_organization ? (
                                                        <span className="text-sm">
                                                            {org.parent_organization.name}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex gap-1 text-sm">
                                                        <span className="font-medium">{org.organization_units_count}</span>
                                                        <span className="text-muted-foreground">units</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex gap-1 text-sm">
                                                        <span className="font-medium">{org.child_organizations_count}</span>
                                                        <span className="text-muted-foreground">children</span>
                                                    </div>
                                                </TableCell>
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