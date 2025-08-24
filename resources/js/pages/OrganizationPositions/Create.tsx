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
import { Briefcase, Save, ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Organization {
    id: number;
    name: string;
    organization_type: string;
}

interface OrganizationUnit {
    id: string;
    name: string;
    organization_id: number;
    organization: Organization;
}

interface Props {
    organizationUnits: OrganizationUnit[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Organization Positions', href: '/organization-positions' },
    { title: 'Create Position', href: '/organization-positions/create' },
];

const positionLevels = [
    { value: 'board_member', label: 'Board Member' },
    { value: 'c_level', label: 'C-Level Executive' },
    { value: 'vice_president', label: 'Vice President' },
    { value: 'director', label: 'Director' },
    { value: 'senior_manager', label: 'Senior Manager' },
    { value: 'manager', label: 'Manager' },
    { value: 'assistant_manager', label: 'Assistant Manager' },
    { value: 'supervisor', label: 'Supervisor' },
    { value: 'senior_staff', label: 'Senior Staff' },
    { value: 'staff', label: 'Staff' },
    { value: 'junior_staff', label: 'Junior Staff' },
];

export default function Create({ organizationUnits }: Props) {
    const [qualifications, setQualifications] = useState<string[]>(['']);
    const [responsibilities, setResponsibilities] = useState<string[]>(['']);
    
    const { data, setData, post, processing, errors, reset } = useForm<{
        organization_unit_id: string | null;
        position_code: string;
        title: string;
        position_level: string | null;
        job_description: string;
        qualifications: string[];
        responsibilities: string[];
        min_salary: string;
        max_salary: string;
        is_active: boolean;
        max_incumbents: number;
    }>({
        organization_unit_id: null,
        position_code: '',
        title: '',
        position_level: null,
        job_description: '',
        qualifications: [] as string[],
        responsibilities: [] as string[],
        min_salary: '',
        max_salary: '',
        is_active: true,
        max_incumbents: 1,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('organization-positions.store'), {
            onSuccess: () => reset(),
        });
    };

    const addQualification = () => {
        setQualifications([...qualifications, '']);
    };

    const removeQualification = (index: number) => {
        const newQualifications = qualifications.filter((_, i) => i !== index);
        setQualifications(newQualifications);
        setData('qualifications', newQualifications.filter(q => q.trim() !== ''));
    };

    const updateQualification = (index: number, value: string) => {
        const newQualifications = [...qualifications];
        newQualifications[index] = value;
        setQualifications(newQualifications);
        setData('qualifications', newQualifications.filter(q => q.trim() !== ''));
    };

    const addResponsibility = () => {
        setResponsibilities([...responsibilities, '']);
    };

    const removeResponsibility = (index: number) => {
        const newResponsibilities = responsibilities.filter((_, i) => i !== index);
        setResponsibilities(newResponsibilities);
        setData('responsibilities', newResponsibilities.filter(r => r.trim() !== ''));
    };

    const updateResponsibility = (index: number, value: string) => {
        const newResponsibilities = [...responsibilities];
        newResponsibilities[index] = value;
        setResponsibilities(newResponsibilities);
        setData('responsibilities', newResponsibilities.filter(r => r.trim() !== ''));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Organization Position" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Briefcase className="h-5 w-5" />
                                    Create Organization Position
                                </CardTitle>
                                <CardDescription>
                                    Define a new position within your organizational structure
                                </CardDescription>
                            </div>
                            <Link href="/organization-positions">
                                <Button variant="outline" size="sm">
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Back to Positions
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Basic Information */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="organization_unit_id">Organization Unit *</Label>
                                    <Select 
                                        value={data.organization_unit_id || undefined} 
                                        onValueChange={(value) => setData('organization_unit_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select organization unit" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {organizationUnits.map((unit) => (
                                                <SelectItem key={unit.id} value={unit.id}>
                                                    {unit.name} ({unit.organization.name})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.organization_unit_id && (
                                        <div className="text-sm text-destructive">{errors.organization_unit_id}</div>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="position_code">Position Code *</Label>
                                    <Input
                                        id="position_code"
                                        value={data.position_code}
                                        onChange={(e) => setData('position_code', e.target.value)}
                                        placeholder="e.g., HR-DIR-001"
                                        className={errors.position_code ? 'border-destructive' : ''}
                                    />
                                    {errors.position_code && (
                                        <div className="text-sm text-destructive">{errors.position_code}</div>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="title">Position Title *</Label>
                                    <Input
                                        id="title"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        placeholder="e.g., HR Director"
                                        className={errors.title ? 'border-destructive' : ''}
                                    />
                                    {errors.title && (
                                        <div className="text-sm text-destructive">{errors.title}</div>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="position_level">Position Level *</Label>
                                    <Select 
                                        value={data.position_level || undefined} 
                                        onValueChange={(value) => setData('position_level', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select position level" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {positionLevels.map((level) => (
                                                <SelectItem key={level.value} value={level.value}>
                                                    {level.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.position_level && (
                                        <div className="text-sm text-destructive">{errors.position_level}</div>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="max_incumbents">Maximum Incumbents</Label>
                                    <Input
                                        id="max_incumbents"
                                        type="number"
                                        min="1"
                                        value={data.max_incumbents}
                                        onChange={(e) => setData('max_incumbents', parseInt(e.target.value) || 1)}
                                        className={errors.max_incumbents ? 'border-destructive' : ''}
                                    />
                                    {errors.max_incumbents && (
                                        <div className="text-sm text-destructive">{errors.max_incumbents}</div>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox 
                                            id="is_active" 
                                            checked={data.is_active}
                                            onCheckedChange={(checked) => setData('is_active', checked as boolean)}
                                        />
                                        <Label htmlFor="is_active">Active Position</Label>
                                    </div>
                                    {errors.is_active && (
                                        <div className="text-sm text-destructive">{errors.is_active}</div>
                                    )}
                                </div>
                            </div>

                            {/* Job Description */}
                            <div className="space-y-2">
                                <Label htmlFor="job_description">Job Description</Label>
                                <Textarea
                                    id="job_description"
                                    value={data.job_description}
                                    onChange={(e) => setData('job_description', e.target.value)}
                                    placeholder="Describe the role, duties, and expectations..."
                                    rows={4}
                                    className={errors.job_description ? 'border-destructive' : ''}
                                />
                                {errors.job_description && (
                                    <div className="text-sm text-destructive">{errors.job_description}</div>
                                )}
                            </div>

                            {/* Salary Range */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="min_salary">Minimum Salary</Label>
                                    <Input
                                        id="min_salary"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={data.min_salary || ''}
                                        onChange={(e) => setData('min_salary', e.target.value)}
                                        placeholder="0.00"
                                        className={errors.min_salary ? 'border-destructive' : ''}
                                    />
                                    {errors.min_salary && (
                                        <div className="text-sm text-destructive">{errors.min_salary}</div>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="max_salary">Maximum Salary</Label>
                                    <Input
                                        id="max_salary"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={data.max_salary || ''}
                                        onChange={(e) => setData('max_salary', e.target.value)}
                                        placeholder="0.00"
                                        className={errors.max_salary ? 'border-destructive' : ''}
                                    />
                                    {errors.max_salary && (
                                        <div className="text-sm text-destructive">{errors.max_salary}</div>
                                    )}
                                </div>
                            </div>

                            {/* Qualifications */}
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <Label>Qualifications</Label>
                                    <Button type="button" onClick={addQualification} variant="outline" size="sm">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Qualification
                                    </Button>
                                </div>
                                {qualifications.map((qualification, index) => (
                                    <div key={index} className="flex gap-2 items-center">
                                        <Input
                                            value={qualification}
                                            onChange={(e) => updateQualification(index, e.target.value)}
                                            placeholder="Enter qualification requirement..."
                                            className="flex-1"
                                        />
                                        {qualifications.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => removeQualification(index)}
                                                className="shrink-0"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                                {errors.qualifications && (
                                    <div className="text-sm text-destructive">{errors.qualifications}</div>
                                )}
                            </div>

                            {/* Responsibilities */}
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <Label>Key Responsibilities</Label>
                                    <Button type="button" onClick={addResponsibility} variant="outline" size="sm">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Responsibility
                                    </Button>
                                </div>
                                {responsibilities.map((responsibility, index) => (
                                    <div key={index} className="flex gap-2 items-center">
                                        <Input
                                            value={responsibility}
                                            onChange={(e) => updateResponsibility(index, e.target.value)}
                                            placeholder="Enter key responsibility..."
                                            className="flex-1"
                                        />
                                        {responsibilities.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => removeResponsibility(index)}
                                                className="shrink-0"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                                {errors.responsibilities && (
                                    <div className="text-sm text-destructive">{errors.responsibilities}</div>
                                )}
                            </div>

                            {/* Submit Buttons */}
                            <div className="flex items-center gap-4 pt-6">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Creating...' : 'Create Position'}
                                </Button>
                                <Link href="/organization-positions">
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