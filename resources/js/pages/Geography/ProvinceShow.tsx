import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { PermissionGuard } from '@/components/permission-guard';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2, Eye, MapPin, Globe, Building, Calendar } from 'lucide-react';
import ActivityLogModal from '@/components/ActivityLogModal';
import { useState } from 'react';

interface Country {
    id: string;
    code: string;
    name: string;
}

interface City {
    id: string;
    code: string;
    name: string;
    created_at: string;
}

interface Province {
    id: string;
    country_id: string;
    code: string;
    name: string;
    country: Country;
    cities: City[];
    created_at: string;
    updated_at: string;
}

interface Props {
    province: Province;
}

const breadcrumbItems = (province: Province): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('geography.provinces'), title: 'Provinces' },
    { href: '', title: province.name },
];

export default function ProvinceShow({ province }: Props) {
    const [isActivityLogOpen, setIsActivityLogOpen] = useState(false);

    const handleEdit = () => {
        router.get(route('geography.provinces.edit', province.id));
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete the province "${province.name}"?`)) {
            router.delete(route('geography.provinces.destroy', province.id), {
                onSuccess: () => {
                    router.get(route('geography.provinces'));
                }
            });
        }
    };

    const handleViewLogs = () => {
        setIsActivityLogOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems(province)}>
            <Head title={`${province.name} - Province Details`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            onClick={() => router.get(route('geography.provinces'))}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Provinces
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold flex items-center gap-2">
                                <MapPin className="h-8 w-8" />
                                {province.name}
                            </h1>
                            <p className="text-muted-foreground">
                                Province Details and Information
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
                        <PermissionGuard permission="geo_province:write">
                            <Button onClick={handleEdit}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Button>
                        </PermissionGuard>
                        <PermissionGuard permission="geo_province:delete">
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
                                    <MapPin className="h-5 w-5" />
                                    Province Information
                                </CardTitle>
                                <CardDescription>
                                    Basic information about this province
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Province Code
                                        </h4>
                                        <Badge variant="outline" className="text-base">
                                            {province.code}
                                        </Badge>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Province Name
                                        </h4>
                                        <p className="text-base font-medium">{province.name}</p>
                                    </div>
                                    <div className="col-span-2">
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Country
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            <Globe className="h-4 w-4" />
                                            <Badge variant="secondary">{province.country.code}</Badge>
                                            <span className="font-medium">{province.country.name}</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Cities */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Building className="h-5 w-5" />
                                    Cities ({province.cities.length})
                                </CardTitle>
                                <CardDescription>
                                    Cities within this province
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {province.cities.length === 0 ? (
                                    <p className="text-muted-foreground text-center py-4">
                                        No cities found for this province
                                    </p>
                                ) : (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        {province.cities.map((city) => (
                                            <div
                                                key={city.id}
                                                className="p-3 border rounded-lg hover:bg-muted/50 cursor-pointer"
                                                onClick={() => router.get(route('geography.cities.show', city.id))}
                                            >
                                                <div className="flex items-center justify-between mb-2">
                                                    <Badge variant="outline">{city.code}</Badge>
                                                </div>
                                                <p className="font-medium">{city.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    Created {new Date(city.created_at).toLocaleDateString()}
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
                                        {new Date(province.created_at).toLocaleString()}
                                    </p>
                                </div>
                                <div>
                                    <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                        Updated At
                                    </h4>
                                    <p className="text-sm">
                                        {new Date(province.updated_at).toLocaleString()}
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
                                    <div className="text-2xl font-bold">{province.cities.length}</div>
                                    <p className="text-sm text-muted-foreground">Total Cities</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <ActivityLogModal
                isOpen={isActivityLogOpen}
                onClose={() => setIsActivityLogOpen(false)}
                subjectType="App\\Models\\Master\\Geo\\Province"
                subjectId={province.id}
                title={`Activity Log - ${province.name}`}
            />
        </AppLayout>
    );
}