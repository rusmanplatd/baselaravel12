import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type PaginatedData, type PaginationLink } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { 
    Network, 
    Eye, 
    Edit, 
    Trash2, 
    Plus, 
    Search, 
    Building,
    Users,
    MapPin,
    Filter
} from 'lucide-react';
import { useState } from 'react';

interface Organization {
    id: number;
    name: string;
    organization_type: string;
}

interface OrganizationUnit {
    id: string;
    organization_id: number;
    unit_code: string | null;
    name: string;
    unit_type: string;
    description: string | null;
    parent_unit_id: string | null;
    is_active: boolean;
    sort_order: number;
    organization: Organization;
    parent_unit?: {
        id: string;
        name: string;
    };
    child_units_count?: number;
    positions_count?: number;
}

interface Props {
    units: PaginatedData<OrganizationUnit>;
    organizations: Organization[];
    filters: {
        organization_id?: number;
        unit_type?: string;
        search?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organizational Units', href: '/organization-units' },
];

const unitTypeLabels: Record<string, string> = {
    board_of_commissioners: 'Board of Commissioners',
    board_of_directors: 'Board of Directors',
    executive_committee: 'Executive Committee',
    audit_committee: 'Audit Committee',
    risk_committee: 'Risk Committee',
    nomination_committee: 'Nomination Committee',
    remuneration_committee: 'Remuneration Committee',
    division: 'Division',
    department: 'Department',
    section: 'Section',
    team: 'Team',
    branch_office: 'Branch Office',
    representative_office: 'Representative Office',
};

const unitTypeColors: Record<string, string> = {
    board_of_commissioners: 'bg-purple-100 text-purple-800 border-purple-200',
    board_of_directors: 'bg-indigo-100 text-indigo-800 border-indigo-200',
    executive_committee: 'bg-blue-100 text-blue-800 border-blue-200',
    audit_committee: 'bg-cyan-100 text-cyan-800 border-cyan-200',
    risk_committee: 'bg-orange-100 text-orange-800 border-orange-200',
    nomination_committee: 'bg-green-100 text-green-800 border-green-200',
    remuneration_committee: 'bg-teal-100 text-teal-800 border-teal-200',
    division: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    department: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    section: 'bg-amber-100 text-amber-800 border-amber-200',
    team: 'bg-rose-100 text-rose-800 border-rose-200',
    branch_office: 'bg-pink-100 text-pink-800 border-pink-200',
    representative_office: 'bg-gray-100 text-gray-800 border-gray-200',
};

export default function Index({ units, organizations, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedOrganization, setSelectedOrganization] = useState<string>(filters.organization_id?.toString() || '');
    const [selectedUnitType, setSelectedUnitType] = useState<string>(filters.unit_type || '');

    const handleSearch = () => {
        router.get('/organization-units', {
            search: search || undefined,
            organization_id: selectedOrganization || undefined,
            unit_type: selectedUnitType || undefined,
        });
    };

    const clearFilters = () => {
        setSearch('');
        setSelectedOrganization('');
        setSelectedUnitType('');
        router.get('/organization-units');
    };

    const handleDelete = (unitId: string) => {
        if (confirm('Are you sure you want to delete this organizational unit?')) {
            router.delete(`/organization-units/${unitId}`);
        }
    };

    const isGovernanceUnit = (unitType: string) => {
        return ['board_of_commissioners', 'board_of_directors', 'executive_committee', 'audit_committee', 'risk_committee', 'nomination_committee', 'remuneration_committee'].includes(unitType);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organizational Units" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Network className="h-5 w-5" />
                                    Organizational Units
                                </CardTitle>
                                <CardDescription>
                                    Manage organizational units across your organization structure
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link href="/organization-units-governance">
                                    <Button variant="outline" size="sm">
                                        <Network className="mr-2 h-4 w-4" />
                                        Governance
                                    </Button>
                                </Link>
                                <Link href="/organization-units-operational">
                                    <Button variant="outline" size="sm">
                                        <Building className="mr-2 h-4 w-4" />
                                        Operational
                                    </Button>
                                </Link>
                                <Link href="/organization-units/create">
                                    <Button size="sm">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Unit
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Filters */}
                        <div className="mb-6 flex flex-wrap items-end gap-4">
                            <div className="flex-1 min-w-64">
                                <label className="text-sm font-medium mb-2 block">Search</label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Search by name or code..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-10"
                                        onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                                    />
                                </div>
                            </div>
                            <div className="min-w-48">
                                <label className="text-sm font-medium mb-2 block">Organization</label>
                                <Select value={selectedOrganization} onValueChange={setSelectedOrganization}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All Organizations" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">All Organizations</SelectItem>
                                        {organizations.map((org) => (
                                            <SelectItem key={org.id} value={org.id.toString()}>
                                                {org.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="min-w-48">
                                <label className="text-sm font-medium mb-2 block">Unit Type</label>
                                <Select value={selectedUnitType} onValueChange={setSelectedUnitType}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All Types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">All Types</SelectItem>
                                        {Object.entries(unitTypeLabels).map(([value, label]) => (
                                            <SelectItem key={value} value={value}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex gap-2">
                                <Button onClick={handleSearch} size="sm">
                                    <Filter className="mr-2 h-4 w-4" />
                                    Filter
                                </Button>
                                <Button onClick={clearFilters} variant="outline" size="sm">
                                    Clear
                                </Button>
                            </div>
                        </div>

                        {/* Units List */}
                        {units.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Network className="h-12 w-12 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No organizational units found</h3>
                                <p className="text-muted-foreground mb-4">
                                    {Object.keys(filters).some((key: string) => filters[key as keyof typeof filters]) 
                                        ? 'No units match your current filters. Try adjusting your search criteria.'
                                        : 'Get started by creating your first organizational unit.'
                                    }
                                </p>
                                {!Object.keys(filters).some((key: string) => filters[key as keyof typeof filters]) && (
                                    <Link href="/organization-units/create">
                                        <Button>
                                            <Plus className="mr-2 h-4 w-4" />
                                            Create Organizational Unit
                                        </Button>
                                    </Link>
                                )}
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {units.data.map((unit) => (
                                    <div
                                        key={unit.id}
                                        className={`p-4 border rounded-lg transition-all hover:shadow-md ${
                                            unit.is_active ? 'bg-background' : 'bg-muted/50'
                                        }`}
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-3 mb-2">
                                                    <div className={`p-2 rounded-lg ${isGovernanceUnit(unit.unit_type) ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600'}`}>
                                                        <Network className="h-4 w-4" />
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2 mb-1">
                                                            <h3 className="font-semibold text-foreground truncate">
                                                                {unit.name}
                                                            </h3>
                                                            {unit.unit_code && (
                                                                <Badge variant="outline" className="shrink-0 text-xs">
                                                                    {unit.unit_code}
                                                                </Badge>
                                                            )}
                                                            <Badge 
                                                                variant="outline"
                                                                className={`shrink-0 text-xs ${unitTypeColors[unit.unit_type] || 'bg-gray-100 text-gray-800 border-gray-200'}`}
                                                            >
                                                                {unitTypeLabels[unit.unit_type] || unit.unit_type}
                                                            </Badge>
                                                            {!unit.is_active && (
                                                                <Badge variant="secondary" className="shrink-0 text-xs">
                                                                    Inactive
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                            <div className="flex items-center gap-1">
                                                                <Building className="h-3 w-3" />
                                                                <span>{unit.organization.name}</span>
                                                            </div>
                                                            {unit.parent_unit && (
                                                                <div className="flex items-center gap-1">
                                                                    <MapPin className="h-3 w-3" />
                                                                    <span>Parent: {unit.parent_unit.name}</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                {unit.description && (
                                                    <p className="text-sm text-muted-foreground mb-3 line-clamp-2">
                                                        {unit.description}
                                                    </p>
                                                )}

                                                <div className="flex items-center gap-4 text-xs text-muted-foreground">
                                                    {unit.child_units_count !== undefined && (
                                                        <div className="flex items-center gap-1">
                                                            <Network className="h-3 w-3" />
                                                            <span>{unit.child_units_count} sub-units</span>
                                                        </div>
                                                    )}
                                                    {unit.positions_count !== undefined && (
                                                        <div className="flex items-center gap-1">
                                                            <Users className="h-3 w-3" />
                                                            <span>{unit.positions_count} positions</span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-1 shrink-0">
                                                <Link href={`/organization-units/${unit.id}`}>
                                                    <Button variant="ghost" size="sm" title="View Details">
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                <Link href={`/organization-units/${unit.id}/edit`}>
                                                    <Button variant="ghost" size="sm" title="Edit">
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    title="Delete"
                                                    onClick={() => handleDelete(unit.id)}
                                                    className="text-destructive hover:text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                ))}

                                {/* Pagination */}
                                {units.links && (
                                    <div className="flex items-center justify-between pt-4">
                                        <div className="text-sm text-muted-foreground">
                                            Showing {units.from} to {units.to} of {units.total} results
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {units.links.map((link: PaginationLink, index: number) => {
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