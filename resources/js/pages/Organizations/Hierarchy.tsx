import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
    Building, 
    ChevronRight, 
    ChevronDown, 
    Eye, 
    Edit, 
    Users, 
    Network, 
    Plus,
    Building2,
    Briefcase,
    MapPin,
    Settings
} from 'lucide-react';
import { useState } from 'react';

interface Organization {
    id: number;
    organization_code: string | null;
    name: string;
    organization_type: 'holding_company' | 'subsidiary' | 'division' | 'branch' | 'department' | 'unit';
    parent_organization_id: number | null;
    description: string | null;
    level: number;
    is_active: boolean;
    organization_units_count?: number;
    child_organizations_count?: number;
    memberships_count?: number;
    child_organizations?: Organization[];
}

interface Props {
    organizations: Organization[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organizations', href: '/organizations' },
    { title: 'Hierarchy View', href: '/organizations-hierarchy' },
];

const organizationTypeIcons = {
    holding_company: Building2,
    subsidiary: Building,
    division: Briefcase,
    branch: MapPin,
    department: Settings,
    unit: Network,
};

const organizationTypeColors = {
    holding_company: 'bg-purple-100 text-purple-800 border-purple-200',
    subsidiary: 'bg-blue-100 text-blue-800 border-blue-200',
    division: 'bg-green-100 text-green-800 border-green-200',
    branch: 'bg-orange-100 text-orange-800 border-orange-200',
    department: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    unit: 'bg-gray-100 text-gray-800 border-gray-200',
};

interface OrganizationNodeProps {
    organization: Organization;
    level: number;
}

function OrganizationNode({ organization, level }: OrganizationNodeProps) {
    const [isExpanded, setIsExpanded] = useState(level < 2); // Auto-expand first 2 levels
    const hasChildren = organization.child_organizations && organization.child_organizations.length > 0;
    const Icon = organizationTypeIcons[organization.organization_type];

    return (
        <div className="relative">
            {/* Connection lines for visual hierarchy */}
            {level > 0 && (
                <div className="absolute -left-6 top-0 h-full w-px bg-border" />
            )}
            {level > 0 && (
                <div className="absolute -left-6 top-6 h-px w-4 bg-border" />
            )}

            <div className="mb-3">
                <div 
                    className={`flex items-center gap-3 p-4 rounded-lg border transition-all hover:shadow-md ${
                        organization.is_active ? 'bg-background' : 'bg-muted/50'
                    }`}
                    style={{ marginLeft: `${level * 24}px` }}
                >
                    {/* Expand/Collapse Button */}
                    {hasChildren && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setIsExpanded(!isExpanded)}
                            className="h-6 w-6 p-0 shrink-0"
                        >
                            {isExpanded ? (
                                <ChevronDown className="h-4 w-4" />
                            ) : (
                                <ChevronRight className="h-4 w-4" />
                            )}
                        </Button>
                    )}
                    
                    {!hasChildren && <div className="w-6" />}

                    {/* Organization Icon */}
                    <div className={`p-2 rounded-lg ${organizationTypeColors[organization.organization_type]}`}>
                        <Icon className="h-4 w-4" />
                    </div>

                    {/* Organization Details */}
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-3 mb-2">
                            <h3 className="font-semibold text-foreground truncate">
                                {organization.name}
                            </h3>
                            {organization.organization_code && (
                                <Badge variant="outline" className="shrink-0 text-xs">
                                    {organization.organization_code}
                                </Badge>
                            )}
                            <Badge 
                                variant="outline"
                                className={`shrink-0 text-xs ${organizationTypeColors[organization.organization_type]}`}
                            >
                                {organization.organization_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                            </Badge>
                            {!organization.is_active && (
                                <Badge variant="secondary" className="shrink-0 text-xs">
                                    Inactive
                                </Badge>
                            )}
                        </div>
                        
                        {organization.description && (
                            <p className="text-sm text-muted-foreground mb-2 line-clamp-2">
                                {organization.description}
                            </p>
                        )}

                        <div className="flex items-center gap-4 text-xs text-muted-foreground">
                            {organization.organization_units_count !== undefined && (
                                <div className="flex items-center gap-1">
                                    <Network className="h-3 w-3" />
                                    <span>{organization.organization_units_count} units</span>
                                </div>
                            )}
                            {organization.child_organizations_count !== undefined && (
                                <div className="flex items-center gap-1">
                                    <Building className="h-3 w-3" />
                                    <span>{organization.child_organizations_count} children</span>
                                </div>
                            )}
                            {organization.memberships_count !== undefined && (
                                <div className="flex items-center gap-1">
                                    <Users className="h-3 w-3" />
                                    <span>{organization.memberships_count} members</span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-1 shrink-0">
                        <Link href={`/organizations/${organization.id}`}>
                            <Button variant="ghost" size="sm" title="View Details">
                                <Eye className="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link href={`/organizations/${organization.id}/edit`}>
                            <Button variant="ghost" size="sm" title="Edit">
                                <Edit className="h-4 w-4" />
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Child Organizations */}
                {hasChildren && isExpanded && (
                    <div className="relative">
                        {organization.child_organizations!.map((childOrg) => (
                            <OrganizationNode
                                key={childOrg.id}
                                organization={childOrg}
                                level={level + 1}
                            />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

export default function Hierarchy({ organizations }: Props) {
    const totalOrganizations = organizations.reduce((count, org) => {
        const countChildren = (children: Organization[]): number => {
            return children.reduce((sum, child) => {
                return sum + 1 + (child.child_organizations ? countChildren(child.child_organizations) : 0);
            }, 0);
        };
        return count + 1 + (org.child_organizations ? countChildren(org.child_organizations) : 0);
    }, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organization Hierarchy" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Building className="h-5 w-5" />
                                    Organization Hierarchy
                                </CardTitle>
                                <CardDescription>
                                    Interactive view of your organizational structure with {totalOrganizations} organizations
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link href="/organizations">
                                    <Button variant="outline" size="sm">
                                        <Building className="mr-2 h-4 w-4" />
                                        List View
                                    </Button>
                                </Link>
                                <Link href="/organizations/create">
                                    <Button size="sm">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Organization
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {organizations.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Building className="h-12 w-12 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No organizations found</h3>
                                <p className="text-muted-foreground mb-4">
                                    Get started by creating your first organization.
                                </p>
                                <Link href="/organizations/create">
                                    <Button>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Create Organization
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <div className="space-y-1">
                                {/* Legend */}
                                <div className="mb-6 p-4 bg-muted/30 rounded-lg">
                                    <h4 className="text-sm font-semibold mb-3">Organization Types</h4>
                                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                        {Object.entries(organizationTypeIcons).map(([type, Icon]) => (
                                            <div key={type} className="flex items-center gap-2">
                                                <div className={`p-1 rounded ${organizationTypeColors[type as keyof typeof organizationTypeColors]}`}>
                                                    <Icon className="h-3 w-3" />
                                                </div>
                                                <span className="text-xs text-muted-foreground">
                                                    {type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Hierarchy Tree */}
                                <div className="relative">
                                    {organizations.map((org) => (
                                        <OrganizationNode
                                            key={org.id}
                                            organization={org}
                                            level={0}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}