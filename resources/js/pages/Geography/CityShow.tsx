import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { PermissionGuard } from '@/components/permission-guard';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2, Eye, Building, MapPin, Globe, Calendar } from 'lucide-react';
import ActivityLogModal from '@/components/ActivityLogModal';
import { useState } from 'react';

interface Country {
    id: string;
    code: string;
    name: string;
}

interface Province {
    id: string;
    code: string;
    name: string;
    country: Country;
}

interface District {
    id: string;
    code: string;
    name: string;
    created_at: string;
}

interface City {
    id: string;
    province_id: string;
    code: string;
    name: string;
    province: Province;
    districts: District[];
    created_at: string;
    updated_at: string;
}

interface Props {
    city: City;
}

const breadcrumbItems = (city: City): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('geography.cities'), title: 'Cities' },
    { href: '', title: city.name },
];

export default function CityShow({ city }: Props) {
    const [isActivityLogOpen, setIsActivityLogOpen] = useState(false);

    const handleEdit = () => {
        router.get(route('geography.cities.edit', city.id));
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete the city "${city.name}"?`)) {
            router.delete(route('geography.cities.destroy', city.id), {
                onSuccess: () => {
                    router.get(route('geography.cities'));
                }
            });
        }
    };

    const handleViewLogs = () => {
        setIsActivityLogOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems(city)}>
            <Head title={`${city.name} - City Details`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            onClick={() => router.get(route('geography.cities'))}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Cities
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold flex items-center gap-2">
                                <Building className="h-8 w-8" />
                                {city.name}
                            </h1>
                            <p className="text-muted-foreground">
                                City Details and Information
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <PermissionGuard permission="audit_log:read">
                            <Button variant="outline" onClick={handleViewLogs}>
                                <Eye className="mr-2 h-4 w-4" />
                                View Logs
                            </Button>
                        </PermissionGuard>
                        <PermissionGuard permission="geo_city:write">
                            <Button onClick={handleEdit}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Button>
                        </PermissionGuard>
                        <PermissionGuard permission="geo_city:delete">
                            <Button variant="destructive" onClick={handleDelete}>
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete
                            </Button>
                        </PermissionGuard>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Information */}
                    <div className="lg:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Building className="h-5 w-5" />
                                    City Information
                                </CardTitle>
                                <CardDescription>
                                    Basic information about this city
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            City Code
                                        </h4>
                                        <Badge variant="outline" className="text-base">
                                            {city.code}
                                        </Badge>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            City Name
                                        </h4>
                                        <p className="text-base font-medium">{city.name}</p>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Province
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            <MapPin className="h-4 w-4" />
                                            <Badge variant="secondary">{city.province.code}</Badge>
                                            <span className="font-medium">{city.province.name}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Country
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            <Globe className="h-4 w-4" />
                                            <Badge variant="secondary">{city.province.country.code}</Badge>
                                            <span className="font-medium">{city.province.country.name}</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Districts */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MapPin className="h-5 w-5" />
                                    Districts ({city.districts?.length || 0})
                                </CardTitle>
                                <CardDescription>
                                    Districts within this city
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {!city.districts || city.districts.length === 0 ? (
                                    <p className="text-muted-foreground text-center py-4">
                                        No districts found for this city
                                    </p>
                                ) : (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        {city.districts?.map((district) => (
                                            <div
                                                key={district.id}
                                                className="p-3 border rounded-lg hover:bg-muted/50 cursor-pointer"
                                                onClick={() => router.get(route('geography.districts.show', district.id))}
                                            >
                                                <div className="flex items-center justify-between mb-2">
                                                    <Badge variant="outline">{district.code}</Badge>
                                                </div>
                                                <p className="font-medium">{district.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    Created {new Date(district.created_at).toLocaleDateString()}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Metadata Sidebar */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Calendar className="h-5 w-5" />
                                    Timestamps
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                        Created At
                                    </h4>
                                    <p className="text-sm">
                                        {new Date(city.created_at).toLocaleString()}
                                    </p>
                                </div>
                                <div>
                                    <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                        Updated At
                                    </h4>
                                    <p className="text-sm">
                                        {new Date(city.updated_at).toLocaleString()}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Stats</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="text-center">
                                    <div className="text-2xl font-bold">{city.districts?.length || 0}</div>
                                    <p className="text-sm text-muted-foreground">Total Districts</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <ActivityLogModal
                isOpen={isActivityLogOpen}
                onClose={() => setIsActivityLogOpen(false)}
                subjectType="App\\Models\\Master\\Geo\\City"
                subjectId={city.id}
                title={`Activity Log - ${city.name}`}
            />
        </AppLayout>
    );
}