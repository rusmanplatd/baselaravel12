import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, Link } from '@inertiajs/react';
import { Save, ArrowLeft } from 'lucide-react';

interface Organization {
    id: number;
    name: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    is_active: boolean;
}

interface Props {
    organization: Organization;
}

export default function Edit({ organization }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Organizations', href: '/organizations' },
        { title: organization.name, href: `/organizations/${organization.id}` },
        { title: 'Edit', href: `/organizations/${organization.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        name: organization.name,
        description: organization.description || '',
        address: organization.address || '',
        phone: organization.phone || '',
        email: organization.email || '',
        website: organization.website || '',
        is_active: organization.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/organizations/${organization.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${organization.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Edit Organization</CardTitle>
                                <CardDescription>
                                    Update the organization information.
                                </CardDescription>
                            </div>
                            <Link href="/organizations">
                                <Button variant="outline">
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Back to Organizations
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
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
                                
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                    <InputError message={errors.email} />
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

                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                                
                                <div className="space-y-2">
                                    <Label htmlFor="website">Website</Label>
                                    <Input
                                        id="website"
                                        type="url"
                                        value={data.website}
                                        onChange={(e) => setData('website', e.target.value)}
                                    />
                                    <InputError message={errors.website} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="address">Address</Label>
                                <Textarea
                                    id="address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    rows={2}
                                />
                                <InputError message={errors.address} />
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
                                    Update Organization
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}