import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
    Shield, 
    Eye, 
    Edit, 
    Building, 
    Users, 
    Network,
    Plus,
    UserCheck,
    CheckCircle,
    XCircle
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
    { title: 'Governance Units', href: '/organization-units-governance' },
];

const unitTypeLabels: Record<string, string> = {
    board_of_commissioners: 'Board of Commissioners',
    board_of_directors: 'Board of Directors',
    executive_committee: 'Executive Committee',
    audit_committee: 'Audit Committee',
    risk_committee: 'Risk Committee',
    nomination_committee: 'Nomination Committee',
    remuneration_committee: 'Remuneration Committee',
};

const unitTypeColors: Record<string, string> = {
    board_of_commissioners: 'bg-purple-100 text-purple-800 border-purple-200',
    board_of_directors: 'bg-indigo-100 text-indigo-800 border-indigo-200',
    executive_committee: 'bg-blue-100 text-blue-800 border-blue-200',
    audit_committee: 'bg-cyan-100 text-cyan-800 border-cyan-200',
    risk_committee: 'bg-orange-100 text-orange-800 border-orange-200',
    nomination_committee: 'bg-green-100 text-green-800 border-green-200',
    remuneration_committee: 'bg-teal-100 text-teal-800 border-teal-200',
};

const unitTypeIcons: Record<string, React.ComponentType<{ className?: string }>> = {
    board_of_commissioners: Shield,
    board_of_directors: Shield,
    executive_committee: Users,
    audit_committee: CheckCircle,
    risk_committee: Shield,
    nomination_committee: UserCheck,
    remuneration_committee: Users,
};

export default function Governance({ units }: Props) {
    const totalMembers = units.reduce((total, unit) => {
        return total + (unit.positions?.reduce((posTotal, position) => {
            return posTotal + (position.active_memberships?.length || 0);
        }, 0) || 0);
    }, 0);

    const activeUnits = units.filter(unit => unit.is_active).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Governance Units" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    Governance Units
                                </CardTitle>
                                <CardDescription>
                                    Corporate governance structure with {activeUnits} active units and {totalMembers} total members
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link href="/organization-units">
                                    <Button variant="outline" size="sm">
                                        <Network className="mr-2 h-4 w-4" />
                                        All Units
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
                        {units.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Shield className="h-12 w-12 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No governance units found</h3>
                                <p className="text-muted-foreground mb-4">
                                    Create governance structures like boards, committees, and executive units.
                                </p>
                                <Link href="/organization-units/create">
                                    <Button>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Create Governance Unit
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <div className="space-y-6">
                                {/* Units Grid */}
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {units.map((unit) => {
                                        const IconComponent = unitTypeIcons[unit.unit_type] || Shield;
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
                                                            <div className="flex items-center gap-1 mb-1">
                                                                <Building className="h-3 w-3" />
                                                                <span className="text-xs">{unit.organization.name}</span>
                                                            </div>
                                                            {unit.parent_unit && (
                                                                <div className="flex items-center gap-1 mb-1">
                                                                    <Network className="h-3 w-3" />
                                                                    <span className="text-xs">Parent: {unit.parent_unit.name}</span>
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
                                                                    <Users className="h-3 w-3" />
                                                                    <span>{totalPositions} positions</span>
                                                                </div>
                                                                <div className="flex items-center gap-1">
                                                                    <UserCheck className="h-3 w-3" />
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

                                {/* Summary Statistics */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg">Governance Overview</CardTitle>
                                        <CardDescription>
                                            Summary of your governance structure
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                            <div className="text-center p-4 border rounded-lg">
                                                <Shield className="h-6 w-6 mx-auto text-purple-600 mb-2" />
                                                <div className="text-2xl font-bold">{units.length}</div>
                                                <div className="text-sm text-muted-foreground">Total Units</div>
                                            </div>
                                            <div className="text-center p-4 border rounded-lg">
                                                <CheckCircle className="h-6 w-6 mx-auto text-green-600 mb-2" />
                                                <div className="text-2xl font-bold">{activeUnits}</div>
                                                <div className="text-sm text-muted-foreground">Active Units</div>
                                            </div>
                                            <div className="text-center p-4 border rounded-lg">
                                                <Users className="h-6 w-6 mx-auto text-blue-600 mb-2" />
                                                <div className="text-2xl font-bold">
                                                    {units.reduce((total, unit) => total + (unit.positions?.length || 0), 0)}
                                                </div>
                                                <div className="text-sm text-muted-foreground">Total Positions</div>
                                            </div>
                                            <div className="text-center p-4 border rounded-lg">
                                                <UserCheck className="h-6 w-6 mx-auto text-indigo-600 mb-2" />
                                                <div className="text-2xl font-bold">{totalMembers}</div>
                                                <div className="text-sm text-muted-foreground">Total Members</div>
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