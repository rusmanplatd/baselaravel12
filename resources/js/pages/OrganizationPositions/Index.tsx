import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type PaginatedData, type PaginationLink } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { 
    Briefcase, 
    Eye, 
    Edit, 
    Trash2, 
    Plus, 
    Building,
    Users,
    DollarSign,
    Filter,
    UserCheck
} from 'lucide-react';
import { useState } from 'react';

interface Organization {
    id: number;
    name: string;
    organization_type: string;
}

interface OrganizationUnit {
    id: string;
    name: string;
    organization: Organization;
}

interface OrganizationPositionLevel {
    id: string;
    name: string;
    code: string;
    hierarchy_level: number;
}

interface User {
    id: string;
    name: string;
    email: string;
}

interface OrganizationMembership {
    id: string;
    user: User;
    status: string;
}

interface OrganizationPosition {
    id: string;
    organization_id: number;
    organization_unit_id: string;
    position_code: string;
    title: string;
    job_description: string | null;
    qualifications: string[] | null;
    responsibilities: string[] | null;
    min_salary: number | null;
    max_salary: number | null;
    is_active: boolean;
    max_incumbents: number;
    organization_unit: OrganizationUnit;
    organization_position_level: OrganizationPositionLevel;
    active_memberships: OrganizationMembership[];
    active_memberships_count: number;
    salary_range: string;
    full_title: string;
}

interface Props {
    positions: PaginatedData<OrganizationPosition>;
    organizationUnits: OrganizationUnit[];
    filters: {
        organization_unit_id?: string;
        position_level?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization Positions', href: '/organization-positions' },
];

const positionLevelColors: Record<string, string> = {
    board_member: 'bg-purple-100 text-purple-800 border-purple-200',
    c_level: 'bg-red-100 text-red-800 border-red-200',
    vice_president: 'bg-indigo-100 text-indigo-800 border-indigo-200',
    director: 'bg-blue-100 text-blue-800 border-blue-200',
    senior_manager: 'bg-cyan-100 text-cyan-800 border-cyan-200',
    manager: 'bg-green-100 text-green-800 border-green-200',
    assistant_manager: 'bg-teal-100 text-teal-800 border-teal-200',
    supervisor: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    senior_staff: 'bg-orange-100 text-orange-800 border-orange-200',
    staff: 'bg-amber-100 text-amber-800 border-amber-200',
    junior_staff: 'bg-gray-100 text-gray-800 border-gray-200',
};

export default function Index({ positions, organizationUnits, filters }: Props) {
    const [selectedUnit, setSelectedUnit] = useState<string>(filters.organization_unit_id || 'all');
    const [selectedLevel, setSelectedLevel] = useState<string>(filters.position_level?.toString() || 'all');

    const handleFilter = () => {
        router.get('/organization-positions', {
            organization_unit_id: selectedUnit === 'all' ? undefined : selectedUnit || undefined,
            position_level: selectedLevel === 'all' ? undefined : selectedLevel || undefined,
        });
    };

    const clearFilters = () => {
        setSelectedUnit('all');
        setSelectedLevel('all');
        router.get('/organization-positions');
    };

    const handleDelete = (positionId: string) => {
        if (confirm('Are you sure you want to delete this position?')) {
            router.delete(`/organization-positions/${positionId}`);
        }
    };

    const getAvailableSlots = (position: OrganizationPosition) => {
        return position.max_incumbents - position.active_memberships_count;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organization Positions" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Briefcase className="h-5 w-5" />
                                    Organization Positions
                                </CardTitle>
                                <CardDescription>
                                    Manage organizational positions and their hierarchical levels
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link href="/organization-positions/create">
                                    <Button size="sm">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Position
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Filters */}
                        <div className="mb-6 flex flex-wrap items-end gap-4">
                            <div className="min-w-48">
                                <label className="text-sm font-medium mb-2 block">Organization Unit</label>
                                <Select value={selectedUnit} onValueChange={setSelectedUnit}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All Units" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Units</SelectItem>
                                        {organizationUnits.map((unit) => (
                                            <SelectItem key={unit.id} value={unit.id}>
                                                {unit.name} ({unit.organization.name})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="min-w-48">
                                <label className="text-sm font-medium mb-2 block">Position Level</label>
                                <Select value={selectedLevel} onValueChange={setSelectedLevel}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All Levels" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Levels</SelectItem>
                                        <SelectItem value="1">Level 1 (Board)</SelectItem>
                                        <SelectItem value="2">Level 2 (C-Level)</SelectItem>
                                        <SelectItem value="3">Level 3 (VP)</SelectItem>
                                        <SelectItem value="4">Level 4 (Director)</SelectItem>
                                        <SelectItem value="5">Level 5 (Sr. Manager)</SelectItem>
                                        <SelectItem value="6">Level 6 (Manager)</SelectItem>
                                        <SelectItem value="7">Level 7 (Asst. Manager)</SelectItem>
                                        <SelectItem value="8">Level 8 (Supervisor)</SelectItem>
                                        <SelectItem value="9">Level 9 (Sr. Staff)</SelectItem>
                                        <SelectItem value="10">Level 10 (Staff)</SelectItem>
                                        <SelectItem value="11">Level 11 (Jr. Staff)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex gap-2">
                                <Button onClick={handleFilter} size="sm">
                                    <Filter className="mr-2 h-4 w-4" />
                                    Filter
                                </Button>
                                <Button onClick={clearFilters} variant="outline" size="sm">
                                    Clear
                                </Button>
                            </div>
                        </div>

                        {/* Positions List */}
                        {positions.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Briefcase className="h-12 w-12 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No positions found</h3>
                                <p className="text-muted-foreground mb-4">
                                    {Object.keys(filters).some((key: string) => filters[key as keyof typeof filters]) 
                                        ? 'No positions match your current filters. Try adjusting your search criteria.'
                                        : 'Get started by creating your first organizational position.'
                                    }
                                </p>
                                {!Object.keys(filters).some((key: string) => filters[key as keyof typeof filters]) && (
                                    <Link href="/organization-positions/create">
                                        <Button>
                                            <Plus className="mr-2 h-4 w-4" />
                                            Create Position
                                        </Button>
                                    </Link>
                                )}
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {positions.data.map((position) => (
                                    <div
                                        key={position.id}
                                        className={`p-4 border rounded-lg transition-all hover:shadow-md ${
                                            position.is_active ? 'bg-background' : 'bg-muted/50'
                                        }`}
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-3 mb-2">
                                                    <div className="p-2 rounded-lg bg-blue-100 text-blue-600">
                                                        <Briefcase className="h-4 w-4" />
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2 mb-1">
                                                            <h3 className="font-semibold text-foreground truncate">
                                                                {position.title}
                                                            </h3>
                                                            <Badge variant="outline" className="shrink-0 text-xs">
                                                                {position.position_code}
                                                            </Badge>
                                                            <Badge 
                                                                variant="outline"
                                                                className={`shrink-0 text-xs ${positionLevelColors[position.organization_position_level?.code] || 'bg-gray-100 text-gray-800 border-gray-200'}`}
                                                            >
                                                                {position.organization_position_level?.name}
                                                            </Badge>
                                                            {!position.is_active && (
                                                                <Badge variant="secondary" className="shrink-0 text-xs">
                                                                    Inactive
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                            <div className="flex items-center gap-1">
                                                                <Building className="h-3 w-3" />
                                                                <span>{position.organization_unit.name}</span>
                                                            </div>
                                                            <div className="flex items-center gap-1">
                                                                <Users className="h-3 w-3" />
                                                                <span>
                                                                    {position.active_memberships_count}/{position.max_incumbents} filled
                                                                </span>
                                                                {getAvailableSlots(position) > 0 ? (
                                                                    <Badge variant="outline" className="ml-1 bg-green-50 text-green-600 border-green-200">
                                                                        {getAvailableSlots(position)} available
                                                                    </Badge>
                                                                ) : (
                                                                    <Badge variant="outline" className="ml-1 bg-red-50 text-red-600 border-red-200">
                                                                        Full
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                {position.job_description && (
                                                    <p className="text-sm text-muted-foreground mb-3 line-clamp-2">
                                                        {position.job_description}
                                                    </p>
                                                )}

                                                <div className="flex items-center gap-4 text-xs text-muted-foreground">
                                                    {position.salary_range !== 'Not specified' && (
                                                        <div className="flex items-center gap-1">
                                                            <DollarSign className="h-3 w-3" />
                                                            <span>{position.salary_range}</span>
                                                        </div>
                                                    )}
                                                    {position.active_memberships.length > 0 && (
                                                        <div className="flex items-center gap-1">
                                                            <UserCheck className="h-3 w-3" />
                                                            <span>
                                                                Current: {position.active_memberships.map(m => m.user.name).join(', ')}
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-1 shrink-0">
                                                <Link href={`/organization-positions/${position.id}`}>
                                                    <Button variant="ghost" size="sm" title="View Details">
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                <Link href={`/organization-positions/${position.id}/edit`}>
                                                    <Button variant="ghost" size="sm" title="Edit">
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    title="Delete"
                                                    onClick={() => handleDelete(position.id)}
                                                    className="text-destructive hover:text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                ))}

                                {/* Pagination */}
                                {positions.links && (
                                    <div className="flex items-center justify-between pt-4">
                                        <div className="text-sm text-muted-foreground">
                                            Showing {positions.from} to {positions.to} of {positions.total} results
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {positions.links.map((link: PaginationLink, index: number) => {
                                                if (!link.url) {
                                                    return (
                                                        <Button
                                                            key={index}
                                                            variant="outline"
                                                            size="sm"
                                                            disabled
                                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                                        />
                                                    );
                                                }
                                                
                                                return (
                                                    <Link key={index} href={link.url}>
                                                        <Button
                                                            variant={link.active ? "default" : "outline"}
                                                            size="sm"
                                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                                        />
                                                    </Link>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}