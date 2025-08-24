import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
    Network, 
    Edit, 
    Building, 
    MapPin, 
    Users, 
    CheckCircle, 
    XCircle,
    ArrowLeft,
    UserCheck,
    Calendar,
    Briefcase,
    Shield
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

interface ChildUnit {
    id: string;
    name: string;
    unit_type: string;
    is_active: boolean;
    positions?: Position[];
}

interface Position {
    id: string;
    title: string;
    level: number;
    active_memberships?: Membership[];
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

interface OrganizationUnit {
    id: string;
    organization_id: number;
    unit_code: string;
    name: string;
    unit_type: string;
    description: string | null;
    parent_unit_id: string | null;
    responsibilities: string[] | null;
    authorities: string[] | null;
    is_active: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
    organization: Organization;
    parent_unit?: ParentUnit;
    child_units?: ChildUnit[];
    positions?: Position[];
    memberships?: Membership[];
}

interface Props {
    unit: OrganizationUnit;
}

const breadcrumbs = (unit: OrganizationUnit): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organizational Units', href: '/organization-units' },
    { title: unit.name, href: `/organization-units/${unit.id}` },
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

export default function Show({ unit }: Props) {
    const isGovernanceUnit = ['board_of_commissioners', 'board_of_directors', 'executive_committee', 'audit_committee', 'risk_committee', 'nomination_committee', 'remuneration_committee'].includes(unit.unit_type);

    return (
        <AppLayout breadcrumbs={breadcrumbs(unit)}>
            <Head title={unit.name} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-start gap-4">
                                <div className={`p-3 rounded-lg ${isGovernanceUnit ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600'}`}>
                                    <Network className="h-6 w-6" />
                                </div>
                                <div>
                                    <div className="flex items-center gap-3 mb-2">
                                        <CardTitle className="text-2xl">{unit.name}</CardTitle>
                                        {unit.unit_code && (
                                            <Badge variant="outline" className="text-sm">
                                                {unit.unit_code}
                                            </Badge>
                                        )}
                                        <Badge 
                                            variant="outline"
                                            className={`text-sm ${unitTypeColors[unit.unit_type] || 'bg-gray-100 text-gray-800 border-gray-200'}`}
                                        >
                                            {unitTypeLabels[unit.unit_type] || unit.unit_type}
                                        </Badge>
                                        {unit.is_active ? (
                                            <Badge variant="outline" className="text-sm bg-green-100 text-green-800 border-green-200">
                                                <CheckCircle className="mr-1 h-3 w-3" />
                                                Active
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-sm bg-red-100 text-red-800 border-red-200">
                                                <XCircle className="mr-1 h-3 w-3" />
                                                Inactive
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                        <div className="flex items-center gap-1">
                                            <Building className="h-4 w-4" />
                                            <span>{unit.organization.name}</span>
                                        </div>
                                        {unit.parent_unit && (
                                            <div className="flex items-center gap-1">
                                                <MapPin className="h-4 w-4" />
                                                <span>Parent: {unit.parent_unit.name}</span>
                                            </div>
                                        )}
                                        <div className="flex items-center gap-1">
                                            <Calendar className="h-4 w-4" />
                                            <span>Created {new Date(unit.created_at).toLocaleDateString()}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link href={`/organization-units/${unit.id}/edit`}>
                                    <Button size="sm">
                                        <Edit className="mr-2 h-4 w-4" />
                                        Edit Unit
                                    </Button>
                                </Link>
                                <Link href="/organization-units">
                                    <Button variant="outline" size="sm">
                                        <ArrowLeft className="mr-2 h-4 w-4" />
                                        Back to Units
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardHeader>
                    {unit.description && (
                        <CardContent>
                            <p className="text-muted-foreground">{unit.description}</p>
                        </CardContent>
                    )}
                </Card>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    {/* Left Column */}
                    <div className="lg:col-span-2 space-y-4">
                        {/* Responsibilities & Authorities */}
                        {(unit.responsibilities && unit.responsibilities.length > 0) || (unit.authorities && unit.authorities.length > 0) ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Shield className="h-5 w-5" />
                                        Responsibilities & Authorities
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {unit.responsibilities && unit.responsibilities.length > 0 && (
                                        <div>
                                            <h4 className="font-semibold mb-2">Responsibilities</h4>
                                            <ul className="space-y-2">
                                                {unit.responsibilities.map((responsibility, index) => (
                                                    <li key={index} className="flex items-start gap-2">
                                                        <div className="w-2 h-2 rounded-full bg-blue-500 mt-2 shrink-0" />
                                                        <span className="text-sm">{responsibility}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                    
                                    {unit.authorities && unit.authorities.length > 0 && (
                                        <>
                                            {unit.responsibilities && unit.responsibilities.length > 0 && <Separator />}
                                            <div>
                                                <h4 className="font-semibold mb-2">Authorities</h4>
                                                <ul className="space-y-2">
                                                    {unit.authorities.map((authority, index) => (
                                                        <li key={index} className="flex items-start gap-2">
                                                            <div className="w-2 h-2 rounded-full bg-green-500 mt-2 shrink-0" />
                                                            <span className="text-sm">{authority}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        ) : null}

                        {/* Child Units */}
                        {unit.child_units && unit.child_units.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Network className="h-5 w-5" />
                                        Sub-Units ({unit.child_units.length})
                                    </CardTitle>
                                    <CardDescription>
                                        Units that report to {unit.name}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {unit.child_units.map((childUnit) => (
                                            <div key={childUnit.id} className="flex items-center justify-between p-3 border rounded-lg">
                                                <div className="flex items-center gap-3">
                                                    <div className={`p-2 rounded-lg ${childUnit.is_active ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400'}`}>
                                                        <Network className="h-4 w-4" />
                                                    </div>
                                                    <div>
                                                        <h4 className="font-semibold">{childUnit.name}</h4>
                                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                            <Badge 
                                                                variant="outline"
                                                                className={`text-xs ${unitTypeColors[childUnit.unit_type] || 'bg-gray-100 text-gray-800 border-gray-200'}`}
                                                            >
                                                                {unitTypeLabels[childUnit.unit_type] || childUnit.unit_type}
                                                            </Badge>
                                                            {childUnit.positions && childUnit.positions.length > 0 && (
                                                                <span>{childUnit.positions.length} positions</span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                                <Link href={`/organization-units/${childUnit.id}`}>
                                                    <Button variant="ghost" size="sm">
                                                        View
                                                    </Button>
                                                </Link>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Positions */}
                        {unit.positions && unit.positions.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Briefcase className="h-5 w-5" />
                                        Positions ({unit.positions.length})
                                    </CardTitle>
                                    <CardDescription>
                                        Organizational positions within {unit.name}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {unit.positions.map((position) => (
                                            <div key={position.id} className="p-3 border rounded-lg">
                                                <div className="flex items-center justify-between mb-2">
                                                    <h4 className="font-semibold">{position.title}</h4>
                                                    <Badge variant="outline" className="text-xs">
                                                        Level {position.level}
                                                    </Badge>
                                                </div>
                                                {position.active_memberships && position.active_memberships.length > 0 ? (
                                                    <div className="space-y-2">
                                                        {position.active_memberships.map((membership) => (
                                                            <div key={membership.id} className="flex items-center gap-3 p-2 bg-muted/30 rounded">
                                                                <Avatar className="h-8 w-8">
                                                                    <AvatarImage src={membership.user.avatar} />
                                                                    <AvatarFallback>
                                                                        {membership.user.name.split(' ').map(n => n[0]).join('').toUpperCase()}
                                                                    </AvatarFallback>
                                                                </Avatar>
                                                                <div className="flex-1 min-w-0">
                                                                    <p className="text-sm font-medium">{membership.user.name}</p>
                                                                    <p className="text-xs text-muted-foreground">{membership.role}</p>
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    Since {new Date(membership.start_date).toLocaleDateString()}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <p className="text-sm text-muted-foreground">No current assignments</p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Right Column */}
                    <div className="space-y-4">
                        {/* Quick Stats */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Quick Stats</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Network className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm">Sub-Units</span>
                                    </div>
                                    <span className="font-semibold">{unit.child_units?.length || 0}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Briefcase className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm">Positions</span>
                                    </div>
                                    <span className="font-semibold">{unit.positions?.length || 0}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Users className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm">Members</span>
                                    </div>
                                    <span className="font-semibold">{unit.memberships?.length || 0}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Shield className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm">Responsibilities</span>
                                    </div>
                                    <span className="font-semibold">{unit.responsibilities?.length || 0}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <UserCheck className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm">Authorities</span>
                                    </div>
                                    <span className="font-semibold">{unit.authorities?.length || 0}</span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Direct Members */}
                        {unit.memberships && unit.memberships.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <Users className="h-5 w-5" />
                                        Direct Members
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {unit.memberships.map((membership) => (
                                            <div key={membership.id} className="flex items-center gap-3">
                                                <Avatar className="h-8 w-8">
                                                    <AvatarImage src={membership.user.avatar} />
                                                    <AvatarFallback>
                                                        {membership.user.name.split(' ').map(n => n[0]).join('').toUpperCase()}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-medium">{membership.user.name}</p>
                                                    <p className="text-xs text-muted-foreground">{membership.role}</p>
                                                </div>
                                                {membership.is_active ? (
                                                    <Badge variant="outline" className="text-xs bg-green-100 text-green-800 border-green-200">
                                                        Active
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-xs bg-gray-100 text-gray-800 border-gray-200">
                                                        Inactive
                                                    </Badge>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}