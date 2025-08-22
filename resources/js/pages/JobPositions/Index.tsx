import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Eye, Edit, Trash2, Briefcase, Building, Users } from 'lucide-react';

interface Organization {
    id: number;
    name: string;
}

interface Department {
    id: number;
    name: string;
    organization: Organization;
}

interface JobLevel {
    id: number;
    name: string;
    level_order: number;
}

interface JobPosition {
    id: number;
    title: string;
    description: string | null;
    openings: number;
    employment_type: string;
    status: string;
    is_active: boolean;
    department: Department;
    job_level: JobLevel;
    created_at: string;
    updated_at: string;
}

interface Props {
    jobPositions: {
        data: JobPosition[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Job Positions', href: '/job-positions' },
];

export default function Index({ jobPositions }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this job position?')) {
            router.delete(`/job-positions/${id}`);
        }
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'open':
                return 'default';
            case 'closed':
                return 'secondary';
            case 'on_hold':
                return 'destructive';
            default:
                return 'secondary';
        }
    };

    const getEmploymentTypeLabel = (type: string) => {
        switch (type) {
            case 'full_time':
                return 'Full Time';
            case 'part_time':
                return 'Part Time';
            case 'contract':
                return 'Contract';
            case 'internship':
                return 'Internship';
            default:
                return type;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Job Positions" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Job Positions</CardTitle>
                                <CardDescription>
                                    Manage job positions across all departments and organizations.
                                </CardDescription>
                            </div>
                            <Link href="/job-positions/create">
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Job Position
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Department</TableHead>
                                        <TableHead>Level</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Openings</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {jobPositions.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="text-center text-muted-foreground">
                                                No job positions found.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        jobPositions.data.map((position) => (
                                            <TableRow key={position.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Briefcase className="h-4 w-4" />
                                                        <div>
                                                            <div className="font-medium">{position.title}</div>
                                                            <div className="text-sm text-muted-foreground">
                                                                {position.department.organization.name}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Users className="h-4 w-4" />
                                                        <Link 
                                                            href={`/departments/${position.department.id}`}
                                                            className="hover:underline"
                                                        >
                                                            {position.department.name}
                                                        </Link>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{position.job_level.name}</TableCell>
                                                <TableCell>{getEmploymentTypeLabel(position.employment_type)}</TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{position.openings}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={getStatusColor(position.status)}>
                                                        {position.status.charAt(0).toUpperCase() + position.status.slice(1).replace('_', ' ')}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Link href={`/job-positions/${position.id}`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Link href={`/job-positions/${position.id}/edit`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(position.id)}
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