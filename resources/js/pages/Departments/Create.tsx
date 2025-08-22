import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft } from 'lucide-react';

interface Organization {
    id: number;
    name: string;
}

interface Department {
    id: number;
    name: string;
    organization: Organization;
}

interface Props {
    organizations: Organization[];
    departments: Department[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Departments', href: '/departments' },
    { title: 'Create', href: '/departments/create' },
];

export default function Create({ organizations, departments }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        organization_id: '',
        name: '',
        description: '',
        parent_department_id: '',
        is_active: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/departments');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Department" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Create Department</CardTitle>
                                <CardDescription>
                                    Add a new department to an organization.
                                </CardDescription>
                            </div>
                            <Link href="/departments">
                                <Button variant="outline">
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Back to Departments
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="organization_id">Organization *</Label>
                                    <Select value={data.organization_id} onValueChange={(value) => setData('organization_id', value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select an organization" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {organizations.map((org) => (
                                                <SelectItem key={org.id} value={org.id.toString()}>
                                                    {org.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.organization_id} />
                                </div>
                                
                                <div className="space-y-2">
                                    <Label htmlFor="name">Name *</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="parent_department_id">Parent Department</Label>
                                <Select value={data.parent_department_id} onValueChange={(value) => setData('parent_department_id', value)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select parent department (optional)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">No Parent</SelectItem>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={dept.id.toString()}>
                                                {dept.name} ({dept.organization.name})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.parent_department_id} />
                            </div>

                            <div className="flex items-center space-x-2">
                                <Switch
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked)}
                                />
                                <Label htmlFor="is_active">Active</Label>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    Create Department
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}