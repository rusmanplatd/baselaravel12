import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Eye, Crown, Building, Calendar } from 'lucide-react';

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

interface Executive {
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
    executives: Executive[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Memberships', href: '/organization-memberships' },
    { title: 'Executives', href: '/executives' },
];

export default function Executives({ executives }: Props) {
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

    const getPositionLevel = (title: string): { level: string; color: string } => {
        const lowerTitle = title.toLowerCase();
        
        if (lowerTitle.includes('ceo') || lowerTitle.includes('chief executive')) {
            return { level: 'CEO', color: 'bg-red-500' };
        } else if (lowerTitle.includes('cfo') || lowerTitle.includes('chief financial')) {
            return { level: 'CFO', color: 'bg-green-500' };
        } else if (lowerTitle.includes('cto') || lowerTitle.includes('chief technology')) {
            return { level: 'CTO', color: 'bg-blue-500' };
        } else if (lowerTitle.includes('coo') || lowerTitle.includes('chief operating')) {
            return { level: 'COO', color: 'bg-orange-500' };
        } else if (lowerTitle.includes('cmo') || lowerTitle.includes('chief marketing')) {
            return { level: 'CMO', color: 'bg-purple-500' };
        } else if (lowerTitle.includes('chief')) {
            return { level: 'C-Level', color: 'bg-indigo-500' };
        } else if (lowerTitle.includes('president') || lowerTitle.includes('vp') || lowerTitle.includes('vice president')) {
            return { level: 'VP', color: 'bg-yellow-500' };
        } else {
            return { level: 'Exec', color: 'bg-gray-500' };
        }
    };

    const groupedByOrganization = executives.reduce((acc, executive) => {
        const orgName = executive.organization.name;
        if (!acc[orgName]) {
            acc[orgName] = [];
        }
        acc[orgName].push(executive);
        return acc;
    }, {} as Record<string, Executive[]>);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Executives" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Crown className="h-5 w-5" />
                                    Executive Leadership
                                </CardTitle>
                                <CardDescription>
                                    Active executives and senior leadership across all organizations.
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Crown className="h-4 w-4" />
                                <span>{executives.length} executives</span>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {Object.keys(groupedByOrganization).length === 0 ? (
                            <div className="text-center py-12">
                                <Crown className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No Executives Found</h3>
                                <p className="text-muted-foreground mb-4">
                                    There are currently no active executives in the system.
                                </p>
                                <Link href="/organization-memberships/create">
                                    <Button>Add First Executive</Button>
                                </Link>
                            </div>
                        ) : (
                            <div className="space-y-6">
                                {Object.entries(groupedByOrganization).map(([orgName, execs]) => (
                                    <Card key={orgName} className="border-l-4 border-l-purple-500">
                                        <CardHeader className="pb-3">
                                            <CardTitle className="text-lg flex items-center gap-2">
                                                <Building className="h-4 w-4" />
                                                {orgName}
                                            </CardTitle>
                                            <CardDescription>
                                                {execs.length} executive{execs.length !== 1 ? 's' : ''}
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="rounded-md border">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Executive</TableHead>
                                                            <TableHead>Position</TableHead>
                                                            <TableHead>Level</TableHead>
                                                            <TableHead>Unit</TableHead>
                                                            <TableHead>Additional Roles</TableHead>
                                                            <TableHead>Tenure</TableHead>
                                                            <TableHead>Period</TableHead>
                                                            <TableHead className="text-right">Actions</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {execs.map((executive) => {
                                                            const positionLevel = executive.organization_position 
                                                                ? getPositionLevel(executive.organization_position.title)
                                                                : { level: 'Exec', color: 'bg-gray-500' };
                                                            
                                                            return (
                                                                <TableRow key={executive.id}>
                                                                    <TableCell>
                                                                        <div className="flex items-center gap-3">
                                                                            <div className="relative">
                                                                                <Avatar className="h-10 w-10">
                                                                                    <AvatarFallback>{getInitials(executive.user.name)}</AvatarFallback>
                                                                                </Avatar>
                                                                                <div className={`absolute -bottom-1 -right-1 w-4 h-4 rounded-full ${positionLevel.color} border-2 border-background flex items-center justify-center`}>
                                                                                    <Crown className="h-2 w-2 text-white" />
                                                                                </div>
                                                                            </div>
                                                                            <div className="flex flex-col">
                                                                                <span className="font-medium">{executive.user.name}</span>
                                                                                <span className="text-sm text-muted-foreground">{executive.user.email}</span>
                                                                            </div>
                                                                        </div>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {executive.organization_position ? (
                                                                            <div className="flex flex-col">
                                                                                <span className="font-medium text-sm">{executive.organization_position.title}</span>
                                                                            </div>
                                                                        ) : (
                                                                            <Badge variant="secondary">
                                                                                Executive
                                                                            </Badge>
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <Badge 
                                                                            variant="outline" 
                                                                            className="font-bold border-2"
                                                                            style={{ borderColor: positionLevel.color.replace('bg-', ''), color: positionLevel.color.replace('bg-', '') }}
                                                                        >
                                                                            {positionLevel.level}
                                                                        </Badge>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {executive.organization_unit ? (
                                                                            <span className="text-sm">{executive.organization_unit.name}</span>
                                                                        ) : (
                                                                            <span className="text-sm text-muted-foreground">Corporate</span>
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {executive.additional_roles && executive.additional_roles.length > 0 ? (
                                                                            <div className="flex flex-wrap gap-1">
                                                                                {executive.additional_roles.slice(0, 2).map((role, index) => (
                                                                                    <Badge key={index} variant="outline" className="text-xs">
                                                                                        {role}
                                                                                    </Badge>
                                                                                ))}
                                                                                {executive.additional_roles.length > 2 && (
                                                                                    <Badge variant="outline" className="text-xs">
                                                                                        +{executive.additional_roles.length - 2}
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
                                                                                {calculateTenure(executive.start_date, executive.end_date)}
                                                                            </span>
                                                                        </div>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <div className="text-sm">
                                                                            <div>{formatDate(executive.start_date)}</div>
                                                                            <div className="text-muted-foreground">
                                                                                to {formatDate(executive.end_date)}
                                                                            </div>
                                                                        </div>
                                                                    </TableCell>
                                                                    <TableCell className="text-right">
                                                                        <Link href={`/organization-memberships/${executive.id}`}>
                                                                            <Button variant="ghost" size="sm">
                                                                                <Eye className="h-4 w-4" />
                                                                            </Button>
                                                                        </Link>
                                                                    </TableCell>
                                                                </TableRow>
                                                            );
                                                        })}
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