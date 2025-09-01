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
    country: Country;
}

interface City {
    id: string;
    name: string;
    code: string;
    province_id: string;
    province: Province;
}

interface District {
    id: string;
    city_id: string;
    code: string;
    name: string;
}

interface Props {
    district: District;
    cities: City[];
}

const breadcrumbItems = (district: District): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('geography.districts'), title: 'Districts' },
    { href: '', title: `Edit ${district.name}` },
];

export default function DistrictEdit({ district, cities }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        city_id: district.city_id || '',
        code: district.code || '',
        name: district.name || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('geography.districts.update', district.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems(district)}>
            <Head title={`Edit ${district.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Edit District</h1>
                        <p className="text-muted-foreground">
                            Update district information
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => router.get(route('geography.districts'))}
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Districts
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>District Information</CardTitle>
                        <CardDescription>
                            Update the details for {district.name}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="city_id">City *</Label>
                                    <select 
                                        id="city_id"
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                        value={data.city_id}
                                        onChange={(e) => setData('city_id', e.target.value)}
                                        required
                                    >
                                        <option value="">Select a city</option>
                                        {cities.map((city) => (
                                            <option key={city.id} value={city.id}>
                                                {city.name} ({city.province.name}, {city.province.country.name})
                                            </option>
                                        ))}
                                    </select>
                                    {errors.city_id && (
                                        <p className="text-sm text-destructive">{errors.city_id}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="code">District Code *</Label>
                                    <Input
                                        id="code"
                                        type="text"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                        placeholder="e.g., DT1, SOUTH, CENTRAL"
                                        maxLength={10}
                                        required
                                    />
                                    {errors.code && (
                                        <p className="text-sm text-destructive">{errors.code}</p>
                                    )}
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="name">District Name *</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g., Central District, South District"
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
                                    {processing ? 'Updating...' : 'Update District'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.get(route('geography.districts'))}
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