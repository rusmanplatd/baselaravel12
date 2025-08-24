import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
    Briefcase, 
    Edit, 
    Building, 
    Users, 
    DollarSign,
    ArrowLeft,
    UserCheck,
    Calendar,
    CheckCircle,
    XCircle,
    FileText,
    Award,
    Target
} from 'lucide-react';

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
    avatar?: string;
}

interface OrganizationMembership {
    id: string;
    user: User;
    status: string;
    start_date: string;
    end_date?: string;
    created_at: string;
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
    created_at: string;
    updated_at: string;
    organization_unit: OrganizationUnit;
    organization_position_level: OrganizationPositionLevel;
    active_memberships: OrganizationMembership[];
    memberships: OrganizationMembership[];
    salary_range: string;
    full_title: string;
}

interface Props {
    position: OrganizationPosition;
}

const breadcrumbs = (position: OrganizationPosition): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization Positions', href: '/organization-positions' },
    { title: position.title, href: `/organization-positions/${position.id}` },
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

export default function Show({ position }: Props) {
    const getAvailableSlots = () => {
        return position.max_incumbents - position.active_memberships.length;
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString();
    };

    const getInitials = (name: string) => {
        return name.split(' ').map(word => word[0]).join('').toUpperCase();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(position)}>
            <Head title={position.title} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Briefcase className="h-5 w-5" />
                                    {position.title}
                                </CardTitle>
                                <CardDescription>
                                    {position.organization_unit.name} • {position.organization_unit.organization.name}
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link href="/organization-positions">
                                    <Button variant="outline" size="sm">
                                        <ArrowLeft className="mr-2 h-4 w-4" />
                                        Back to Positions
                                    </Button>
                                </Link>
                                <Link href={`/organization-positions/${position.id}/edit`}>
                                    <Button size="sm">
                                        <Edit className="mr-2 h-4 w-4" />
                                        Edit Position
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Position Overview */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div className="space-y-4">
                                <div className="flex items-center gap-3">
                                    <div className="p-2 rounded-lg bg-blue-100 text-blue-600">
                                        <Briefcase className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <div className="font-medium">Position Code</div>
                                        <div className="text-sm text-muted-foreground">{position.position_code}</div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <div className="p-2 rounded-lg bg-green-100 text-green-600">
                                        <Building className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <div className="font-medium">Organization Unit</div>
                                        <div className="text-sm text-muted-foreground">{position.organization_unit.name}</div>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="flex items-center gap-3">
                                    <div className="p-2 rounded-lg bg-purple-100 text-purple-600">
                                        <Award className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <div className="font-medium">Position Level</div>
                                        <Badge 
                                            variant="outline"
                                            className={`text-xs ${positionLevelColors[position.organization_position_level?.code] || 'bg-gray-100 text-gray-800 border-gray-200'}`}
                                        >
                                            {position.organization_position_level?.name}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <div className="p-2 rounded-lg bg-orange-100 text-orange-600">
                                        <Users className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <div className="font-medium">Capacity</div>
                                        <div className="text-sm text-muted-foreground">
                                            {position.active_memberships.length}/{position.max_incumbents} filled
                                            {getAvailableSlots() > 0 ? (
                                                <Badge variant="outline" className="ml-2 bg-green-50 text-green-600 border-green-200">
                                                    {getAvailableSlots()} available
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline" className="ml-2 bg-red-50 text-red-600 border-red-200">
                                                    Full
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="flex items-center gap-3">
                                    <div className="p-2 rounded-lg bg-yellow-100 text-yellow-600">
                                        <DollarSign className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <div className="font-medium">Salary Range</div>
                                        <div className="text-sm text-muted-foreground">{position.salary_range}</div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <div className="p-2 rounded-lg bg-cyan-100 text-cyan-600">
                                        {position.is_active ? <CheckCircle className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
                                    </div>
                                    <div>
                                        <div className="font-medium">Status</div>
                                        <Badge variant={position.is_active ? "default" : "secondary"}>
                                            {position.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Separator className="my-6" />

                        {/* Job Description */}
                        {position.job_description && (
                            <>
                                <div className="mb-6">
                                    <h3 className="flex items-center gap-2 font-semibold mb-3">
                                        <FileText className="h-4 w-4" />
                                        Job Description
                                    </h3>
                                    <div className="text-sm text-muted-foreground bg-muted/50 p-4 rounded-lg">
                                        {position.job_description}
                                    </div>
                                </div>
                                <Separator className="my-6" />
                            </>
                        )}

                        {/* Qualifications and Responsibilities */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            {/* Qualifications */}
                            {position.qualifications && position.qualifications.length > 0 && (
                                <div>
                                    <h3 className="flex items-center gap-2 font-semibold mb-3">
                                        <Award className="h-4 w-4" />
                                        Qualifications
                                    </h3>
                                    <ul className="space-y-2">
                                        {position.qualifications.map((qualification, index) => (
                                            <li key={index} className="flex items-start gap-2 text-sm">
                                                <CheckCircle className="h-3 w-3 text-green-500 mt-1 shrink-0" />
                                                <span>{qualification}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {/* Responsibilities */}
                            {position.responsibilities && position.responsibilities.length > 0 && (
                                <div>
                                    <h3 className="flex items-center gap-2 font-semibold mb-3">
                                        <Target className="h-4 w-4" />
                                        Key Responsibilities
                                    </h3>
                                    <ul className="space-y-2">
                                        {position.responsibilities.map((responsibility, index) => (
                                            <li key={index} className="flex items-start gap-2 text-sm">
                                                <Target className="h-3 w-3 text-blue-500 mt-1 shrink-0" />
                                                <span>{responsibility}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>

                        {(position.qualifications?.length || position.responsibilities?.length) && (
                            <Separator className="my-6" />
                        )}

                        {/* Current Incumbents */}
                        {position.active_memberships.length > 0 && (
                            <>
                                <div className="mb-6">
                                    <h3 className="flex items-center gap-2 font-semibold mb-4">
                                        <UserCheck className="h-4 w-4" />
                                        Current Incumbents ({position.active_memberships.length})
                                    </h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {position.active_memberships.map((membership) => (
                                            <div key={membership.id} className="flex items-center gap-3 p-3 border rounded-lg">
                                                <Avatar>
                                                    <AvatarImage src={membership.user.avatar} />
                                                    <AvatarFallback>{getInitials(membership.user.name)}</AvatarFallback>
                                                </Avatar>
                                                <div className="flex-1 min-w-0">
                                                    <div className="font-medium">{membership.user.name}</div>
                                                    <div className="text-sm text-muted-foreground truncate">
                                                        {membership.user.email}
                                                    </div>
                                                    <div className="flex items-center gap-2 text-xs text-muted-foreground mt-1">
                                                        <Calendar className="h-3 w-3" />
                                                        <span>Since {formatDate(membership.start_date)}</span>
                                                        <Badge variant="outline" className="bg-green-50 text-green-600 border-green-200">
                                                            {membership.status}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <Separator className="my-6" />
                            </>
                        )}

                        {/* Historical Memberships */}
                        {position.memberships.filter(m => m.status !== 'active').length > 0 && (
                            <div className="mb-6">
                                <h3 className="flex items-center gap-2 font-semibold mb-4">
                                    <Users className="h-4 w-4" />
                                    Historical Memberships ({position.memberships.filter(m => m.status !== 'active').length})
                                </h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {position.memberships.filter(m => m.status !== 'active').map((membership) => (
                                        <div key={membership.id} className="flex items-center gap-3 p-3 border rounded-lg bg-muted/20">
                                            <Avatar>
                                                <AvatarImage src={membership.user.avatar} />
                                                <AvatarFallback>{getInitials(membership.user.name)}</AvatarFallback>
                                            </Avatar>
                                            <div className="flex-1 min-w-0">
                                                <div className="font-medium">{membership.user.name}</div>
                                                <div className="text-sm text-muted-foreground truncate">
                                                    {membership.user.email}
                                                </div>
                                                <div className="flex items-center gap-2 text-xs text-muted-foreground mt-1">
                                                    <Calendar className="h-3 w-3" />
                                                    <span>
                                                        {formatDate(membership.start_date)} - {membership.end_date ? formatDate(membership.end_date) : 'Present'}
                                                    </span>
                                                    <Badge variant="secondary">
                                                        {membership.status}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Metadata */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs text-muted-foreground bg-muted/30 p-4 rounded-lg">
                            <div>
                                <strong>Created:</strong> {formatDate(position.created_at)}
                            </div>
                            <div>
                                <strong>Last Updated:</strong> {formatDate(position.updated_at)}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}