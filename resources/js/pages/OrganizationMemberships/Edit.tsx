import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Save, ArrowLeft } from 'lucide-react';
import { useState, useEffect } from 'react';

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
    organization_id: string;
}

interface OrganizationPosition {
    id: string;
    title: string;
    organization_unit_id: string;
}

interface OrganizationMembership {
    id: string;
    user_id: string;
    organization_id: string;
    organization_unit_id: string | null;
    organization_position_id: string | null;
    membership_type: 'board_member' | 'executive' | 'management' | 'employee' | 'consultant' | 'volunteer';
    start_date: string;
    end_date: string | null;
    status: 'active' | 'inactive' | 'terminated' | 'pending';
    additional_roles: string[] | null;
    user: User;
    organization: Organization;
    organization_unit?: OrganizationUnit;
    organization_position?: OrganizationPosition;
}

interface Props {
    membership: OrganizationMembership;
    users: User[];
    organizations: Organization[];
    organizationUnits: OrganizationUnit[];
    organizationPositions: OrganizationPosition[];
}

const membershipTypes = [
    { value: 'board_member', label: 'Board Member' },
    { value: 'executive', label: 'Executive' },
    { value: 'management', label: 'Management' },
    { value: 'employee', label: 'Employee' },
    { value: 'consultant', label: 'Consultant' },
    { value: 'volunteer', label: 'Volunteer' },
];

export default function Edit({ membership, users, organizations, organizationUnits, organizationPositions }: Props) {
    const [filteredUnits, setFilteredUnits] = useState<OrganizationUnit[]>([]);
    const [filteredPositions, setFilteredPositions] = useState<OrganizationPosition[]>([]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Memberships', href: '/organization-memberships' },
        { title: membership.user.name, href: `/organization-memberships/${membership.id}` },
        { title: 'Edit', href: `/organization-memberships/${membership.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        user_id: membership.user_id,
        organization_id: membership.organization_id,
        organization_unit_id: membership.organization_unit_id || '',
        organization_position_id: membership.organization_position_id || '',
        membership_type: membership.membership_type as 'board_member' | 'executive' | 'management' | 'employee' | 'consultant' | 'volunteer',
        start_date: membership.start_date,
        end_date: membership.end_date || '',
        status: membership.status as 'active' | 'inactive' | 'terminated' | 'pending',
        additional_roles: membership.additional_roles ? membership.additional_roles.join(', ') : '',
    });

    useEffect(() => {
        // Initialize filtered units and positions based on current membership
        const units = organizationUnits.filter(unit => unit.organization_id === membership.organization_id);
        setFilteredUnits(units);
        
        if (membership.organization_unit_id) {
            const positions = organizationPositions.filter(position => position.organization_unit_id === membership.organization_unit_id);
            setFilteredPositions(positions);
        }
    }, [membership, organizationUnits, organizationPositions]);

    const handleOrganizationChange = (organizationId: string) => {
        setData({
            ...data,
            organization_id: organizationId,
            organization_unit_id: '',
            organization_position_id: '',
        });
        
        const units = organizationUnits.filter(unit => unit.organization_id === organizationId);
        setFilteredUnits(units);
        setFilteredPositions([]);
    };

    const handleUnitChange = (unitId: string) => {
        setData({
            ...data,
            organization_unit_id: unitId,
            organization_position_id: '',
        });

        const positions = organizationPositions.filter(position => position.organization_unit_id === unitId);
        setFilteredPositions(positions);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/organization-memberships/${membership.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Membership - ${membership.user.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-4">
                            <Link href={`/organization-memberships/${membership.id}`}>
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft className="h-4 w-4" />
                                </Button>
                            </Link>
                            <div>
                                <CardTitle>Edit Organization Membership</CardTitle>
                                <CardDescription>
                                    Update membership details for {membership.user.name}.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* User Selection */}
                                <div className="space-y-2">
                                    <Label htmlFor="user_id">User *</Label>
                                    <Select 
                                        value={data.user_id} 
                                        onValueChange={(value) => setData('user_id', value)}
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a user" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {users.map((user) => (
                                                <SelectItem key={user.id} value={user.id}>
                                                    <div className="flex flex-col">
                                                        <span className="font-medium">{user.name}</span>
                                                        <span className="text-sm text-muted-foreground">{user.email}</span>
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.user_id && <p className="text-sm text-red-600">{errors.user_id}</p>}
                                </div>

                                {/* Organization Selection */}
                                <div className="space-y-2">
                                    <Label htmlFor="organization_id">Organization *</Label>
                                    <Select 
                                        value={data.organization_id} 
                                        onValueChange={handleOrganizationChange}
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select an organization" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {organizations.map((org) => (
                                                <SelectItem key={org.id} value={org.id}>
                                                    {org.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.organization_id && <p className="text-sm text-red-600">{errors.organization_id}</p>}
                                </div>

                                {/* Organization Unit Selection */}
                                <div className="space-y-2">
                                    <Label htmlFor="organization_unit_id">Organization Unit</Label>
                                    <Select 
                                        value={data.organization_unit_id} 
                                        onValueChange={handleUnitChange}
                                        disabled={!data.organization_id}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a unit (optional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">No specific unit</SelectItem>
                                            {filteredUnits.map((unit) => (
                                                <SelectItem key={unit.id} value={unit.id}>
                                                    {unit.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.organization_unit_id && <p className="text-sm text-red-600">{errors.organization_unit_id}</p>}
                                </div>

                                {/* Position Selection */}
                                <div className="space-y-2">
                                    <Label htmlFor="organization_position_id">Position</Label>
                                    <Select 
                                        value={data.organization_position_id} 
                                        onValueChange={(value) => setData('organization_position_id', value)}
                                        disabled={!data.organization_unit_id}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a position (optional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">No specific position</SelectItem>
                                            {filteredPositions.map((position) => (
                                                <SelectItem key={position.id} value={position.id}>
                                                    {position.title}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.organization_position_id && <p className="text-sm text-red-600">{errors.organization_position_id}</p>}
                                </div>

                                {/* Membership Type */}
                                <div className="space-y-2">
                                    <Label htmlFor="membership_type">Membership Type *</Label>
                                    <Select 
                                        value={data.membership_type} 
                                        onValueChange={(value) => setData('membership_type', value as 'board_member' | 'executive' | 'management' | 'employee' | 'consultant' | 'volunteer')}
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select membership type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {membershipTypes.map((type) => (
                                                <SelectItem key={type.value} value={type.value}>
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.membership_type && <p className="text-sm text-red-600">{errors.membership_type}</p>}
                                </div>

                                {/* Status */}
                                <div className="space-y-2">
                                    <Label htmlFor="status">Status</Label>
                                    <Select 
                                        value={data.status} 
                                        onValueChange={(value) => setData('status', value as 'active' | 'inactive' | 'terminated' | 'pending')}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="pending">Pending</SelectItem>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="inactive">Inactive</SelectItem>
                                            <SelectItem value="terminated">Terminated</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.status && <p className="text-sm text-red-600">{errors.status}</p>}
                                </div>

                                {/* Start Date */}
                                <div className="space-y-2">
                                    <Label htmlFor="start_date">Start Date *</Label>
                                    <Input
                                        id="start_date"
                                        type="date"
                                        value={data.start_date}
                                        onChange={(e) => setData('start_date', e.target.value)}
                                        required
                                    />
                                    {errors.start_date && <p className="text-sm text-red-600">{errors.start_date}</p>}
                                </div>

                                {/* End Date */}
                                <div className="space-y-2">
                                    <Label htmlFor="end_date">End Date</Label>
                                    <Input
                                        id="end_date"
                                        type="date"
                                        value={data.end_date}
                                        onChange={(e) => setData('end_date', e.target.value)}
                                    />
                                    <p className="text-sm text-muted-foreground">Leave empty for indefinite membership</p>
                                    {errors.end_date && <p className="text-sm text-red-600">{errors.end_date}</p>}
                                </div>
                            </div>

                            {/* Additional Roles */}
                            <div className="space-y-2">
                                <Label htmlFor="additional_roles">Additional Roles</Label>
                                <Textarea
                                    id="additional_roles"
                                    value={data.additional_roles}
                                    onChange={(e) => setData('additional_roles', e.target.value)}
                                    placeholder="Enter additional roles separated by commas (e.g., Committee Chair, Board Secretary)"
                                    rows={3}
                                />
                                <p className="text-sm text-muted-foreground">
                                    Comma-separated list of additional roles or responsibilities
                                </p>
                                {errors.additional_roles && <p className="text-sm text-red-600">{errors.additional_roles}</p>}
                            </div>

                            <div className="flex items-center gap-4 pt-4">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Updating...' : 'Update Membership'}
                                </Button>
                                <Link href={`/organization-memberships/${membership.id}`}>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}