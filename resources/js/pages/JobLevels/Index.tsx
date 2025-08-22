import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Eye, Edit, Trash2, TrendingUp } from 'lucide-react';

interface JobLevel {
    id: number;
    name: string;
    description: string | null;
    level_order: number;
    min_salary: number | null;
    max_salary: number | null;
    is_active: boolean;
    job_positions_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    jobLevels: {
        data: JobLevel[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Job Levels', href: '/job-levels' },
];

export default function Index({ jobLevels }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this job level?')) {
            router.delete(`/job-levels/${id}`);
        }
    };

    const formatCurrency = (amount: number | null) => {
        if (!amount) return '-';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(amount);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Job Levels" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Job Levels</CardTitle>
                                <CardDescription>
                                    Manage job levels and their salary ranges.
                                </CardDescription>
                            </div>
                            <Link href="/job-levels/create">
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Job Level
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Level</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Salary Range</TableHead>
                                        <TableHead>Positions</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {jobLevels.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center text-muted-foreground">
                                                No job levels found.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        jobLevels.data.map((level) => (
                                            <TableRow key={level.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10">
                                                            <TrendingUp className="h-4 w-4" />
                                                        </div>
                                                        {level.level_order}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-medium">{level.name}</TableCell>
                                                <TableCell>
                                                    {level.min_salary || level.max_salary ? (
                                                        <div className="text-sm">
                                                            {formatCurrency(level.min_salary)} - {formatCurrency(level.max_salary)}
                                                        </div>
                                                    ) : (
                                                        '-'
                                                    )}
                                                </TableCell>
                                                <TableCell>{level.job_positions_count}</TableCell>
                                                <TableCell>
                                                    <Badge variant={level.is_active ? 'default' : 'secondary'}>
                                                        {level.is_active ? 'Active' : 'Inactive'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Link href={`/job-levels/${level.id}`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Link href={`/job-levels/${level.id}/edit`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(level.id)}
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