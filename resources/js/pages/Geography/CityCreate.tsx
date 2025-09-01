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
    name: string;
    code: string;
}

interface Province {
    id: string;
    name: string;
    code: string;
    country_id: string;
    country: Country;
}

interface Props {
    provinces: Province[];
}

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('geography.cities'), title: 'Cities' },
    { href: '', title: 'Create' },
];

export default function CityCreate({ provinces }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        province_id: '',
        code: '',
        name: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('geography.cities.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems}>
            <Head title="Create City" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Create City</h1>
                        <p className="text-muted-foreground">
                            Add a new city to the system
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => router.get(route('geography.cities'))}
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Cities
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>City Information</CardTitle>
                        <CardDescription>
                            Enter the details for the new city
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="province_id">Province *</Label>
                                    <select 
                                        id="province_id"
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                        value={data.province_id}
                                        onChange={(e) => setData('province_id', e.target.value)}
                                        required
                                    >
                                        <option value="">Select a province</option>
                                        {provinces.map((province) => (
                                            <option key={province.id} value={province.id}>
                                                {province.name} ({province.country.name})
                                            </option>
                                        ))}
                                    </select>
                                    {errors.province_id && (
                                        <p className="text-sm text-destructive">{errors.province_id}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="code">City Code *</Label>
                                    <Input
                                        id="code"
                                        type="text"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                        placeholder="e.g., LA, NYC, JKT"
                                        maxLength={10}
                                        required
                                    />
                                    {errors.code && (
                                        <p className="text-sm text-destructive">{errors.code}</p>
                                    )}
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="name">City Name *</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g., Los Angeles, New York City, Jakarta"
                                        required
                                    />
                                    {errors.name && (
                                        <p className="text-sm text-destructive">{errors.name}</p>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Creating...' : 'Create City'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.get(route('geography.cities'))}
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