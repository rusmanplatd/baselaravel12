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
    province: Province;
}

interface District {
    id: string;
    name: string;
    code: string;
    city_id: string;
    city: City;
}

interface Village {
    id: string;
    district_id: string;
    code: string;
    name: string;
}

interface Props {
    village: Village;
    districts: District[];
}

const breadcrumbItems = (village: Village): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('geography.villages'), title: 'Villages' },
    { href: '', title: `Edit ${village.name}` },
];

export default function VillageEdit({ village, districts }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        district_id: village.district_id || '',
        code: village.code || '',
        name: village.name || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('geography.villages.update', village.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems(village)}>
            <Head title={`Edit ${village.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Edit Village</h1>
                        <p className="text-muted-foreground">
                            Update village information
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => router.get(route('geography.villages'))}
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Villages
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Village Information</CardTitle>
                        <CardDescription>
                            Update the details for {village.name}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="district_id">District *</Label>
                                    <select 
                                        id="district_id"
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                        value={data.district_id}
                                        onChange={(e) => setData('district_id', e.target.value)}
                                        required
                                    >
                                        <option value="">Select a district</option>
                                        {districts.map((district) => (
                                            <option key={district.id} value={district.id}>
                                                {district.name} ({district.city.name}, {district.city.province.name}, {district.city.province.country.name})
                                            </option>
                                        ))}
                                    </select>
                                    {errors.district_id && (
                                        <p className="text-sm text-destructive">{errors.district_id}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="code">Village Code *</Label>
                                    <Input
                                        id="code"
                                        type="text"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                        placeholder="e.g., VL1, NORTH, RIVERSIDE"
                                        maxLength={10}
                                        required
                                    />
                                    {errors.code && (
                                        <p className="text-sm text-destructive">{errors.code}</p>
                                    )}
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="name">Village Name *</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g., Green Valley Village, Riverside Village"
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
                                    {processing ? 'Updating...' : 'Update Village'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.get(route('geography.villages'))}
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