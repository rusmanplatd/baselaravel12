import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
    Building, 
    Eye, 
    Edit, 
    Users, 
    Network,
    Plus,
    UserCheck,
    CheckCircle,
    XCircle,
    MapPin,
    Briefcase,
    Settings,
    Target
} from 'lucide-react';

interface Organization {
    id: number;
    name: string;
    organization_type: string;
}

interface ParentUnit {
    id: string;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
}

interface Membership {
    id: string;
    role: string;
    start_date: string;
    end_date?: string;
    is_active: boolean;
    user: User;
}

interface Position {
    id: string;
    title: string;
    level: number;
    active_memberships?: Membership[];
}

interface OrganizationUnit {
    id: string;
    organization_id: number;
    unit_code: string;
    name: string;
    unit_type: string;
    description: string | null;
    parent_unit_id: string | null;
    is_active: boolean;
    sort_order: number;
    organization: Organization;
    parent_unit?: ParentUnit;
    positions?: Position[];
}

interface Props {
    units: OrganizationUnit[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organizational Units', href: '/organization-units' },
    { title: 'Operational Units', href: '/organization-units-operational' },
];

const unitTypeLabels: Record<string, string> = {
    division: 'Division',
    department: 'Department',
    section: 'Section',
    team: 'Team',
    branch_office: 'Branch Office',
    representative_office: 'Representative Office',
};

const unitTypeColors: Record<string, string> = {
    division: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    department: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    section: 'bg-amber-100 text-amber-800 border-amber-200',
    team: 'bg-rose-100 text-rose-800 border-rose-200',
    branch_office: 'bg-pink-100 text-pink-800 border-pink-200',
    representative_office: 'bg-gray-100 text-gray-800 border-gray-200',
};

const unitTypeIcons: Record<string, React.ComponentType<{ className?: string }>> = {
    division: Building,
    department: Settings,
    section: Target,
    team: Users,
    branch_office: MapPin,
    representative_office: Briefcase,
};

export default function Operational({ units }: Props) {
    const totalMembers = units.reduce((total, unit) => {
        return total + (unit.positions?.reduce((posTotal, position) => {
            return posTotal + (position.active_memberships?.length || 0);
        }, 0) || 0);
    }, 0);

    const activeUnits = units.filter(unit => unit.is_active).length;

    // Group units by organization for better organization
    const unitsByOrganization = units.reduce((acc, unit) => {
        const orgName = unit.organization.name;
        if (!acc[orgName]) {
            acc[orgName] = [];
        }
        acc[orgName].push(unit);
        return acc;
    }, {} as Record<string, OrganizationUnit[]>);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Operational Units" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Building className="h-5 w-5" />
                                    Operational Units
                                </CardTitle>
                                <CardDescription>
                                    Day-to-day operational structure with {activeUnits} active units and {totalMembers} total members
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link href="/organization-units">
                                    <Button variant="outline" size="sm">
                                        <Network className="mr-2 h-4 w-4" />
                                        All Units
                                    </Button>
                                </Link>
                                <Link href="/organization-units-governance">
                                    <Button variant="outline" size="sm">
                                        <UserCheck className="mr-2 h-4 w-4" />
                                        Governance
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
                        {units.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Building className="h-12 w-12 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No operational units found</h3>
                                <p className="text-muted-foreground mb-4">
                                    Create operational structures like divisions, departments, teams, and offices.
                                </p>
                                <Link href="/organization-units/create">
                                    <Button>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Create Operational Unit
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <div className="space-y-6">
                                {/* Units by Organization */}
                                {Object.entries(unitsByOrganization).map(([orgName, orgUnits]) => (
                                    <div key={orgName} className="space-y-4">
                                        <div className="flex items-center gap-2">
                                            <Building className="h-5 w-5 text-muted-foreground" />
                                            <h3 className="font-semibold text-lg">{orgName}</h3>
                                            <Badge variant="outline" className="text-xs">
                                                {orgUnits.length} units
                                            </Badge>
                                        </div>
                                        
                                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            {orgUnits.map((unit) => {
                                                const IconComponent = unitTypeIcons[unit.unit_type] || Building;
                                                const totalPositions = unit.positions?.length || 0;
                                                const totalActiveMembers = unit.positions?.reduce((total, position) => 
                                                    total + (position.active_memberships?.length || 0), 0) || 0;
                                                
                                                return (
                                                    <Card key={unit.id} className={`transition-all hover:shadow-lg ${
                                                        unit.is_active ? '' : 'opacity-75'
                                                    }`}>
                                                        <CardHeader className="pb-3">
                                                            <div className="flex items-start justify-between">
                                                                <div className="flex items-start gap-3">
                                                                    <div className={`p-2 rounded-lg ${unitTypeColors[unit.unit_type] || 'bg-gray-100 text-gray-600'}`}>
                                                                        <IconComponent className="h-5 w-5" />
                                                                    </div>
                                                                    <div className="min-w-0 flex-1">
                                                                        <h3 className="font-semibold text-sm leading-tight mb-1">
                                                                            {unit.name}
                                                                        </h3>
                                                                        {unit.unit_code && (
                                                                            <Badge variant="outline" className="text-xs mb-2">
                                                                                {unit.unit_code}
                                                                            </Badge>
                                                                        )}
                                                                        <Badge 
                                                                            variant="outline"
                                                                            className={`text-xs ${unitTypeColors[unit.unit_type] || 'bg-gray-100 text-gray-800 border-gray-200'}`}
                                                                        >
                                                                            {unitTypeLabels[unit.unit_type] || unit.unit_type}
                                                                        </Badge>
                                                                    </div>
                                                                </div>
                                                                {unit.is_active ? (
                                                                    <CheckCircle className="h-4 w-4 text-green-500 shrink-0" />
                                                                ) : (
                                                                    <XCircle className="h-4 w-4 text-red-500 shrink-0" />
                                                                )}
                                                            </div>
                                                        </CardHeader>
                                                        <CardContent className="pt-0">
                                                            <div className="space-y-3">
                                                                <div className="text-sm text-muted-foreground">
                                                                    {unit.parent_unit && (
                                                                        <div className="flex items-center gap-1 mb-1">
                                                                            <Network className="h-3 w-3" />
                                                                            <span className="text-xs">Reports to: {unit.parent_unit.name}</span>
                                                                        </div>
                                                                    )}
                                                                </div>

                                                                {unit.description && (
                                                                    <p className="text-xs text-muted-foreground line-clamp-2 mb-3">
                                                                        {unit.description}
                                                                    </p>
                                                                )}

                                                                <div className="flex items-center justify-between text-xs">
                                                                    <div className="flex items-center gap-3">
                                                                        <div className="flex items-center gap-1">
                                                                            <Briefcase className="h-3 w-3" />
                                                                            <span>{totalPositions} positions</span>
                                                                        </div>
                                                                        <div className="flex items-center gap-1">
                                                                            <Users className="h-3 w-3" />
                                                                            <span>{totalActiveMembers} members</span>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                {/* Key Members Preview */}
                                                                {unit.positions && unit.positions.length > 0 && (
                                                                    <div className="border-t pt-3">
                                                                        <h4 className="text-xs font-semibold mb-2">Key Positions</h4>
                                                                        <div className="space-y-2">
                                                                            {unit.positions.slice(0, 2).map((position) => (
                                                                                <div key={position.id} className="flex items-center justify-between">
                                                                                    <span className="text-xs text-muted-foreground truncate">
                                                                                        {position.title}
                                                                                    </span>
                                                                                    <div className="flex items-center gap-1">
                                                                                        {position.active_memberships?.slice(0, 2).map((membership) => (
                                                                                            <Avatar key={membership.id} className="h-5 w-5">
                                                                                                <AvatarImage src={membership.user.avatar} />
                                                                                                <AvatarFallback className="text-xs">
                                                                                                    {membership.user.name.split(' ').map(n => n[0]).join('').toUpperCase()}
                                                                                                </AvatarFallback>
                                                                                            </Avatar>
                                                                                        )) || (
                                                                                            <span className="text-xs text-muted-foreground">Vacant</span>
                                                                                        )}
                                                                                    </div>
                                                                                </div>
                                                                            ))}
                                                                            {unit.positions.length > 2 && (
                                                                                <p className="text-xs text-muted-foreground">
                                                                                    +{unit.positions.length - 2} more positions
                                                                                </p>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                )}

                                                                <div className="flex items-center gap-1 pt-2">
                                                                    <Link href={`/organization-units/${unit.id}`}>
                                                                        <Button variant="ghost" size="sm" className="h-8 px-2">
                                                                            <Eye className="h-3 w-3" />
                                                                        </Button>
                                                                    </Link>
                                                                    <Link href={`/organization-units/${unit.id}/edit`}>
                                                                        <Button variant="ghost" size="sm" className="h-8 px-2">
                                                                            <Edit className="h-3 w-3" />
                                                                        </Button>
                                                                    </Link>
                                                                </div>
                                                            </div>
                                                        </CardContent>
                                                    </Card>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}

                                {/* Summary Statistics */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg">Operational Overview</CardTitle>
                                        <CardDescription>
                                            Summary of your operational structure
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                            <div className="text-center p-4 border rounded-lg">
                                                <Building className="h-6 w-6 mx-auto text-blue-600 mb-2" />
                                                <div className="text-2xl font-bold">{units.length}</div>
                                                <div className="text-sm text-muted-foreground">Total Units</div>
                                            </div>
                                            <div className="text-center p-4 border rounded-lg">
                                                <CheckCircle className="h-6 w-6 mx-auto text-green-600 mb-2" />
                                                <div className="text-2xl font-bold">{activeUnits}</div>
                                                <div className="text-sm text-muted-foreground">Active Units</div>
                                            </div>
                                            <div className="text-center p-4 border rounded-lg">
                                                <Briefcase className="h-6 w-6 mx-auto text-purple-600 mb-2" />
                                                <div className="text-2xl font-bold">
                                                    {units.reduce((total, unit) => total + (unit.positions?.length || 0), 0)}
                                                </div>
                                                <div className="text-sm text-muted-foreground">Total Positions</div>
                                            </div>
                                            <div className="text-center p-4 border rounded-lg">
                                                <Users className="h-6 w-6 mx-auto text-indigo-600 mb-2" />
                                                <div className="text-2xl font-bold">{totalMembers}</div>
                                                <div className="text-sm text-muted-foreground">Total Members</div>
                                            </div>
                                        </div>

                                        {/* Unit Type Breakdown */}
                                        <div className="mt-6">
                                            <h4 className="font-semibold mb-3">Unit Types</h4>
                                            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                                {Object.entries(unitTypeLabels).map(([type, label]) => {
                                                    const count = units.filter(unit => unit.unit_type === type).length;
                                                    const IconComponent = unitTypeIcons[type] || Building;
                                                    
                                                    return (
                                                        <div key={type} className="text-center p-3 border rounded-lg">
                                                            <div className={`p-2 rounded-lg mx-auto mb-2 w-fit ${unitTypeColors[type] || 'bg-gray-100 text-gray-600'}`}>
                                                                <IconComponent className="h-4 w-4" />
                                                            </div>
                                                            <div className="text-lg font-bold">{count}</div>
                                                            <div className="text-xs text-muted-foreground">{label}</div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}