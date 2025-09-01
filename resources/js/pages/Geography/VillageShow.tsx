import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { PermissionGuard } from '@/components/permission-guard';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2, Eye, TreePine, MapPin, Building, Globe, Calendar } from 'lucide-react';
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

interface City {
    id: string;
    code: string;
    name: string;
    province: Province;
}

interface District {
    id: string;
    code: string;
    name: string;
    city: City;
}

interface Village {
    id: string;
    district_id: string;
    code: string;
    name: string;
    district: District;
    created_at: string;
    updated_at: string;
}

interface Props {
    village: Village;
}

const breadcrumbItems = (village: Village): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('geography.villages'), title: 'Villages' },
    { href: '', title: village.name },
];

export default function VillageShow({ village }: Props) {
    const [isActivityLogOpen, setIsActivityLogOpen] = useState(false);

    const handleEdit = () => {
        router.get(route('geography.villages.edit', village.id));
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete the village "${village.name}"?`)) {
            router.delete(route('geography.villages.destroy', village.id), {
                onSuccess: () => {
                    router.get(route('geography.villages'));
                }
            });
        }
    };

    const handleViewLogs = () => {
        setIsActivityLogOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems(village)}>
            <Head title={`${village.name} - Village Details`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            onClick={() => router.get(route('geography.villages'))}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Villages
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold flex items-center gap-2">
                                <TreePine className="h-8 w-8" />
                                {village.name}
                            </h1>
                            <p className="text-muted-foreground">
                                Village Details and Information
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
                        <PermissionGuard permission="geo_village:write">
                            <Button onClick={handleEdit}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Button>
                        </PermissionGuard>
                        <PermissionGuard permission="geo_village:delete">
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
                                    <TreePine className="h-5 w-5" />
                                    Village Information
                                </CardTitle>
                                <CardDescription>
                                    Basic information about this village
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Village Code
                                        </h4>
                                        <Badge variant="outline" className="text-base">
                                            {village.code}
                                        </Badge>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Village Name
                                        </h4>
                                        <p className="text-base font-medium">{village.name}</p>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            District
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            <MapPin className="h-4 w-4" />
                                            <Badge variant="secondary">{village.district.code}</Badge>
                                            <span className="font-medium">{village.district.name}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            City
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            <Building className="h-4 w-4" />
                                            <Badge variant="secondary">{village.district.city.code}</Badge>
                                            <span className="font-medium">{village.district.city.name}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Province
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            <MapPin className="h-4 w-4" />
                                            <Badge variant="secondary">{village.district.city.province.code}</Badge>
                                            <span className="font-medium">{village.district.city.province.name}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Country
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            <Globe className="h-4 w-4" />
                                            <Badge variant="secondary">{village.district.city.province.country.code}</Badge>
                                            <span className="font-medium">{village.district.city.province.country.name}</span>
                                        </div>
                                    </div>
                                </div>
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
                                        {new Date(village.created_at).toLocaleString()}
                                    </p>
                                </div>
                                <div>
                                    <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                        Updated At
                                    </h4>
                                    <p className="text-sm">
                                        {new Date(village.updated_at).toLocaleString()}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Location Hierarchy</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="text-sm">
                                    <div className="flex items-center gap-2 mb-1">
                                        <Globe className="h-4 w-4" />
                                        <span className="font-medium">Country:</span>
                                    </div>
                                    <p className="pl-6 text-muted-foreground">
                                        {village.district.city.province.country.name}
                                    </p>
                                </div>
                                <div className="text-sm">
                                    <div className="flex items-center gap-2 mb-1">
                                        <MapPin className="h-4 w-4" />
                                        <span className="font-medium">Province:</span>
                                    </div>
                                    <p className="pl-6 text-muted-foreground">
                                        {village.district.city.province.name}
                                    </p>
                                </div>
                                <div className="text-sm">
                                    <div className="flex items-center gap-2 mb-1">
                                        <Building className="h-4 w-4" />
                                        <span className="font-medium">City:</span>
                                    </div>
                                    <p className="pl-6 text-muted-foreground">
                                        {village.district.city.name}
                                    </p>
                                </div>
                                <div className="text-sm">
                                    <div className="flex items-center gap-2 mb-1">
                                        <MapPin className="h-4 w-4" />
                                        <span className="font-medium">District:</span>
                                    </div>
                                    <p className="pl-6 text-muted-foreground">
                                        {village.district.name}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <ActivityLogModal
                isOpen={isActivityLogOpen}
                onClose={() => setIsActivityLogOpen(false)}
                subjectType="App\\Models\\Master\\Geo\\Village"
                subjectId={village.id}
                title={`Activity Log - ${village.name}`}
            />
        </AppLayout>
    );
}