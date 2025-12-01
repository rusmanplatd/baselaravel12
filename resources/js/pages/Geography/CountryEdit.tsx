import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { Save, ArrowLeft } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Country {
    id: string;
    code: string;
    name: string;
    iso_code: string | null;
    phone_code: string | null;
}

interface Props {
    country: Country;
}

const breadcrumbItems = (country: Country): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('geography.countries'), title: 'Countries' },
    { href: '', title: `Edit ${country.name}` },
];

export default function CountryEdit({ country }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        code: country.code || '',
        name: country.name || '',
        iso_code: country.iso_code || '',
        phone_code: country.phone_code || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('geography.countries.update', country.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems(country)}>
            <Head title={`Edit ${country.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Edit Country</h1>
                        <p className="text-muted-foreground">
                            Update country information
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => router.get(route('geography.countries'))}
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Countries
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Country Information</CardTitle>
                        <CardDescription>
                            Update the details for {country.name}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="code">Country Code *</Label>
                                    <Input
                                        id="code"
                                        type="text"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                        placeholder="e.g., US, ID, UK"
                                        maxLength={10}
                                        required
                                    />
                                    {errors.code && (
                                        <p className="text-sm text-destructive">{errors.code}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="name">Country Name *</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g., United States, Indonesia"
                                        required
                                    />
                                    {errors.name && (
                                        <p className="text-sm text-destructive">{errors.name}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="iso_code">ISO Code</Label>
                                    <Input
                                        id="iso_code"
                                        type="text"
                                        value={data.iso_code}
                                        onChange={(e) => setData('iso_code', e.target.value)}
                                        placeholder="e.g., USA, IDN, GBR"
                                        maxLength={3}
                                    />
                                    {errors.iso_code && (
                                        <p className="text-sm text-destructive">{errors.iso_code}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="phone_code">Phone Code</Label>
                                    <Input
                                        id="phone_code"
                                        type="text"
                                        value={data.phone_code}
                                        onChange={(e) => setData('phone_code', e.target.value)}
                                        placeholder="e.g., +1, +62, +44"
                                        maxLength={10}
                                    />
                                    {errors.phone_code && (
                                        <p className="text-sm text-destructive">{errors.phone_code}</p>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Updating...' : 'Update Country'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.get(route('geography.countries'))}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}