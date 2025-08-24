import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Eye, Users, Building, Calendar } from 'lucide-react';

interface User {
    id: string;
    name: string;
    email: string;
}

interface Organization {
    id: string;
    name: string;
}

interface OrganizationUnit {
    id: string;
    name: string;
}

interface OrganizationPosition {
    id: string;
    title: string;
}

interface BoardMember {
    id: string;
    user_id: string;
    organization_id: string;
    organization_unit_id: string | null;
    organization_position_id: string | null;
    membership_type: string;
    start_date: string;
    end_date: string | null;
    status: string;
    additional_roles: string[] | null;
    user: User;
    organization: Organization;
    organization_unit?: OrganizationUnit;
    organization_position?: OrganizationPosition;
}

interface Props {
    boardMembers: BoardMember[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Memberships', href: '/organization-memberships' },
    { title: 'Board Members', href: '/board-members' },
];

export default function BoardMembers({ boardMembers }: Props) {
    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'Ongoing';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    };

    const calculateTenure = (startDate: string, endDate: string | null) => {
        const start = new Date(startDate);
        const end = endDate ? new Date(endDate) : new Date();
        const diffTime = Math.abs(end.getTime() - start.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays < 365) {
            const months = Math.floor(diffDays / 30);
            return `${months} month${months !== 1 ? 's' : ''}`;
        } else {
            const years = Math.floor(diffDays / 365);
            const remainingMonths = Math.floor((diffDays % 365) / 30);
            return `${years}y ${remainingMonths}m`;
        }
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map(part => part[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const groupedByOrganization = boardMembers.reduce((acc, member) => {
        const orgName = member.organization.name;
        if (!acc[orgName]) {
            acc[orgName] = [];
        }
        acc[orgName].push(member);
        return acc;
    }, {} as Record<string, BoardMember[]>);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Board Members" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Users className="h-5 w-5" />
                                    Board Members
                                </CardTitle>
                                <CardDescription>
                                    Active board members across all organizations.
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Users className="h-4 w-4" />
                                <span>{boardMembers.length} members</span>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {Object.keys(groupedByOrganization).length === 0 ? (
                            <div className="text-center py-12">
                                <Users className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No Board Members Found</h3>
                                <p className="text-muted-foreground mb-4">
                                    There are currently no active board members in the system.
                                </p>
                                <Link href="/organization-memberships/create">
                                    <Button>Add First Board Member</Button>
                                </Link>
                            </div>
                        ) : (
                            <div className="space-y-6">
                                {Object.entries(groupedByOrganization).map(([orgName, members]) => (
                                    <Card key={orgName} className="border-l-4 border-l-blue-500">
                                        <CardHeader className="pb-3">
                                            <CardTitle className="text-lg flex items-center gap-2">
                                                <Building className="h-4 w-4" />
                                                {orgName}
                                            </CardTitle>
                                            <CardDescription>
                                                {members.length} board member{members.length !== 1 ? 's' : ''}
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="rounded-md border">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Member</TableHead>
                                                            <TableHead>Position</TableHead>
                                                            <TableHead>Unit</TableHead>
                                                            <TableHead>Additional Roles</TableHead>
                                                            <TableHead>Tenure</TableHead>
                                                            <TableHead>Period</TableHead>
                                                            <TableHead className="text-right">Actions</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {members.map((member) => (
                                                            <TableRow key={member.id}>
                                                                <TableCell>
                                                                    <div className="flex items-center gap-3">
                                                                        <Avatar className="h-8 w-8">
                                                                            <AvatarFallback>{getInitials(member.user.name)}</AvatarFallback>
                                                                        </Avatar>
                                                                        <div className="flex flex-col">
                                                                            <span className="font-medium">{member.user.name}</span>
                                                                            <span className="text-sm text-muted-foreground">{member.user.email}</span>
                                                                        </div>
                                                                    </div>
                                                                </TableCell>
                                                                <TableCell>
                                                                    {member.organization_position ? (
                                                                        <Badge variant="outline" className="font-medium">
                                                                            {member.organization_position.title}
                                                                        </Badge>
                                                                    ) : (
                                                                        <Badge variant="secondary">
                                                                            Board Member
                                                                        </Badge>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {member.organization_unit ? (
                                                                        <span className="text-sm">{member.organization_unit.name}</span>
                                                                    ) : (
                                                                        <span className="text-sm text-muted-foreground">-</span>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {member.additional_roles && member.additional_roles.length > 0 ? (
                                                                        <div className="flex flex-wrap gap-1">
                                                                            {member.additional_roles.slice(0, 2).map((role, index) => (
                                                                                <Badge key={index} variant="outline" className="text-xs">
                                                                                    {role}
                                                                                </Badge>
                                                                            ))}
                                                                            {member.additional_roles.length > 2 && (
                                                                                <Badge variant="outline" className="text-xs">
                                                                                    +{member.additional_roles.length - 2}
                                                                                </Badge>
                                                                            )}
                                                                        </div>
                                                                    ) : (
                                                                        <span className="text-sm text-muted-foreground">-</span>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    <div className="flex items-center gap-1 text-sm">
                                                                        <Calendar className="h-3 w-3" />
                                                                        <span className="font-medium">
                                                                            {calculateTenure(member.start_date, member.end_date)}
                                                                        </span>
                                                                    </div>
                                                                </TableCell>
                                                                <TableCell>
                                                                    <div className="text-sm">
                                                                        <div>{formatDate(member.start_date)}</div>
                                                                        <div className="text-muted-foreground">
                                                                            to {formatDate(member.end_date)}
                                                                        </div>
                                                                    </div>
                                                                </TableCell>
                                                                <TableCell className="text-right">
                                                                    <Link href={`/organization-memberships/${member.id}`}>
                                                                        <Button variant="ghost" size="sm">
                                                                            <Eye className="h-4 w-4" />
                                                                        </Button>
                                                                    </Link>
                                                                </TableCell>
                                                            </TableRow>
                                                        ))}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}