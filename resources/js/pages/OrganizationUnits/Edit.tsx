import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Network, Save, ArrowLeft, Building, Plus, Trash2 } from 'lucide-react';
import { useState, useEffect } from 'react';

interface Organization {
    id: number;
    name: string;
    organization_type: string;
}

interface ParentUnit {
    id: string;
    name: string;
    organization_id: number;
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
}

interface Props {
    unit: OrganizationUnit;
    organizations: Organization[];
    parentUnits: ParentUnit[];
}

const breadcrumbs = (unit: OrganizationUnit): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organizational Units', href: '/organization-units' },
    { title: unit.name, href: `/organization-units/${unit.id}` },
    { title: 'Edit', href: `/organization-units/${unit.id}/edit` },
];

const unitTypes = [
    { value: 'board_of_commissioners', label: 'Board of Commissioners', category: 'governance' },
    { value: 'board_of_directors', label: 'Board of Directors', category: 'governance' },
    { value: 'executive_committee', label: 'Executive Committee', category: 'governance' },
    { value: 'audit_committee', label: 'Audit Committee', category: 'governance' },
    { value: 'risk_committee', label: 'Risk Committee', category: 'governance' },
    { value: 'nomination_committee', label: 'Nomination Committee', category: 'governance' },
    { value: 'remuneration_committee', label: 'Remuneration Committee', category: 'governance' },
    { value: 'division', label: 'Division', category: 'operational' },
    { value: 'department', label: 'Department', category: 'operational' },
    { value: 'section', label: 'Section', category: 'operational' },
    { value: 'team', label: 'Team', category: 'operational' },
    { value: 'branch_office', label: 'Branch Office', category: 'operational' },
    { value: 'representative_office', label: 'Representative Office', category: 'operational' },
];

export default function Edit({ unit, organizations, parentUnits }: Props) {
    const [responsibilities, setResponsibilities] = useState<string[]>(
        unit.responsibilities && unit.responsibilities.length > 0 ? unit.responsibilities : ['']
    );
    const [authorities, setAuthorities] = useState<string[]>(
        unit.authorities && unit.authorities.length > 0 ? unit.authorities : ['']
    );
    
    const { data, setData, put, processing, errors } = useForm({
        unit_code: unit.unit_code,
        name: unit.name,
        unit_type: unit.unit_type,
        description: unit.description || '',
        parent_unit_id: unit.parent_unit_id || '',
        responsibilities: unit.responsibilities || [],
        authorities: unit.authorities || [],
        is_active: unit.is_active,
        sort_order: unit.sort_order,
    });

    // Update form data when responsibilities/authorities change
    useEffect(() => {
        setData('responsibilities', responsibilities.filter(r => r.trim() !== ''));
    }, [responsibilities, setData]);

    useEffect(() => {
        setData('authorities', authorities.filter(a => a.trim() !== ''));
    }, [authorities, setData]);

    const filteredParentUnits = parentUnits.filter(parentUnit => 
        parentUnit.organization_id === unit.organization_id
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('organization-units.update', unit.id));
    };

    const addResponsibility = () => {
        setResponsibilities([...responsibilities, '']);
    };

    const removeResponsibility = (index: number) => {
        const newResponsibilities = responsibilities.filter((_, i) => i !== index);
        setResponsibilities(newResponsibilities);
    };

    const updateResponsibility = (index: number, value: string) => {
        const newResponsibilities = [...responsibilities];
        newResponsibilities[index] = value;
        setResponsibilities(newResponsibilities);
    };

    const addAuthority = () => {
        setAuthorities([...authorities, '']);
    };

    const removeAuthority = (index: number) => {
        const newAuthorities = authorities.filter((_, i) => i !== index);
        setAuthorities(newAuthorities);
    };

    const updateAuthority = (index: number, value: string) => {
        const newAuthorities = [...authorities];
        newAuthorities[index] = value;
        setAuthorities(newAuthorities);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(unit)}>
            <Head title={`Edit ${unit.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Network className="h-5 w-5" />
                                    Edit Organizational Unit
                                </CardTitle>
                                <CardDescription>
                                    Update the details of {unit.name}
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link href={`/organization-units/${unit.id}`}>
                                    <Button variant="outline" size="sm">
                                        View Details
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
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Organization (Read-only) */}
                                <div className="space-y-2">
                                    <Label>Organization</Label>
                                    <div className="flex items-center gap-2 p-3 border rounded-lg bg-muted/50">
                                        <Building className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm">
                                            {organizations.find(org => org.id === unit.organization_id)?.name}
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Organization cannot be changed after creation
                                    </p>
                                </div>

                                {/* Unit Code */}
                                <div className="space-y-2">
                                    <Label htmlFor="unit_code">Unit Code *</Label>
                                    <Input
                                        id="unit_code"
                                        value={data.unit_code}
                                        onChange={(e) => setData('unit_code', e.target.value)}
                                        placeholder="e.g., HR-001, IT-DEPT"
                                    />
                                    {errors.unit_code && (
                                        <p className="text-sm text-destructive">{errors.unit_code}</p>
                                    )}
                                </div>

                                {/* Unit Name */}
                                <div className="space-y-2">
                                    <Label htmlFor="name">Unit Name *</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g., Human Resources Department"
                                    />
                                    {errors.name && (
                                        <p className="text-sm text-destructive">{errors.name}</p>
                                    )}
                                </div>

                                {/* Unit Type */}
                                <div className="space-y-2">
                                    <Label htmlFor="unit_type">Unit Type *</Label>
                                    <Select
                                        value={data.unit_type}
                                        onValueChange={(value) => setData('unit_type', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select unit type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <div className="p-2">
                                                <h4 className="text-sm font-semibold text-muted-foreground mb-2">Governance Units</h4>
                                                {unitTypes.filter(t => t.category === 'governance').map((type) => (
                                                    <SelectItem key={type.value} value={type.value}>
                                                        {type.label}
                                                    </SelectItem>
                                                ))}
                                            </div>
                                            <div className="p-2 border-t">
                                                <h4 className="text-sm font-semibold text-muted-foreground mb-2">Operational Units</h4>
                                                {unitTypes.filter(t => t.category === 'operational').map((type) => (
                                                    <SelectItem key={type.value} value={type.value}>
                                                        {type.label}
                                                    </SelectItem>
                                                ))}
                                            </div>
                                        </SelectContent>
                                    </Select>
                                    {errors.unit_type && (
                                        <p className="text-sm text-destructive">{errors.unit_type}</p>
                                    )}
                                </div>

                                {/* Parent Unit */}
                                <div className="space-y-2">
                                    <Label htmlFor="parent_unit_id">Parent Unit</Label>
                                    <Select
                                        value={data.parent_unit_id}
                                        onValueChange={(value) => setData('parent_unit_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select parent unit (optional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">No parent unit</SelectItem>
                                            {filteredParentUnits.map((parentUnit) => (
                                                <SelectItem key={parentUnit.id} value={parentUnit.id}>
                                                    <div className="flex items-center gap-2">
                                                        <Network className="h-4 w-4" />
                                                        {parentUnit.name}
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.parent_unit_id && (
                                        <p className="text-sm text-destructive">{errors.parent_unit_id}</p>
                                    )}
                                </div>

                                {/* Sort Order */}
                                <div className="space-y-2">
                                    <Label htmlFor="sort_order">Sort Order</Label>
                                    <Input
                                        id="sort_order"
                                        type="number"
                                        min="0"
                                        value={data.sort_order}
                                        onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                        placeholder="0"
                                    />
                                    {errors.sort_order && (
                                        <p className="text-sm text-destructive">{errors.sort_order}</p>
                                    )}
                                </div>
                            </div>

                            {/* Description */}
                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Describe the purpose and role of this organizational unit..."
                                    rows={3}
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">{errors.description}</p>
                                )}
                            </div>

                            {/* Responsibilities */}
                            <div className="space-y-2">
                                <Label>Responsibilities</Label>
                                <div className="space-y-2">
                                    {responsibilities.map((responsibility, index) => (
                                        <div key={index} className="flex gap-2">
                                            <Input
                                                value={responsibility}
                                                onChange={(e) => updateResponsibility(index, e.target.value)}
                                                placeholder="Enter a responsibility..."
                                                className="flex-1"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => removeResponsibility(index)}
                                                disabled={responsibilities.length === 1}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    ))}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addResponsibility}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Responsibility
                                    </Button>
                                </div>
                                {errors.responsibilities && (
                                    <p className="text-sm text-destructive">{errors.responsibilities}</p>
                                )}
                            </div>

                            {/* Authorities */}
                            <div className="space-y-2">
                                <Label>Authorities</Label>
                                <div className="space-y-2">
                                    {authorities.map((authority, index) => (
                                        <div key={index} className="flex gap-2">
                                            <Input
                                                value={authority}
                                                onChange={(e) => updateAuthority(index, e.target.value)}
                                                placeholder="Enter an authority..."
                                                className="flex-1"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => removeAuthority(index)}
                                                disabled={authorities.length === 1}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    ))}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addAuthority}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Authority
                                    </Button>
                                </div>
                                {errors.authorities && (
                                    <p className="text-sm text-destructive">{errors.authorities}</p>
                                )}
                            </div>

                            {/* Active Status */}
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked as boolean)}
                                />
                                <Label htmlFor="is_active">Active</Label>
                                {errors.is_active && (
                                    <p className="text-sm text-destructive">{errors.is_active}</p>
                                )}
                            </div>

                            {/* Submit Button */}
                            <div className="flex items-center justify-end gap-4 pt-4 border-t">
                                <Link href={`/organization-units/${unit.id}`}>
                                    <Button variant="outline" type="button">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}