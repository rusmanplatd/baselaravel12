import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { PermissionGuard } from '@/components/permission-guard';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2, Eye, MapPin, Building, Globe, Calendar, TreePine } from 'lucide-react';
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

interface Village {
    id: string;
    code: string;
    name: string;
    created_at: string;
}

interface District {
    id: string;
    city_id: string;
    code: string;
    name: string;
    city: City;
    villages: Village[];
    created_at: string;
    updated_at: string;
}

interface Props {
    district: District;
}

const breadcrumbItems = (district: District): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('geography.districts'), title: 'Districts' },
    { href: '', title: district.name },
];

export default function DistrictShow({ district }: Props) {
    const [isActivityLogOpen, setIsActivityLogOpen] = useState(false);

    const handleEdit = () => {
        router.get(route('geography.districts.edit', district.id));
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete the district "${district.name}"?`)) {
            router.delete(route('geography.districts.destroy', district.id), {
                onSuccess: () => {
                    router.get(route('geography.districts'));
                }
            });
        }
    };

    const handleViewLogs = () => {
        setIsActivityLogOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems(district)}>
            <Head title={`${district.name} - District Details`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            onClick={() => router.get(route('geography.districts'))}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Districts
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold flex items-center gap-2">
                                <MapPin className="h-8 w-8" />
                                {district.name}
                            </h1>
                            <p className="text-muted-foreground">
                                District Details and Information
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
                        <PermissionGuard permission="geo_district:write">
                            <Button onClick={handleEdit}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Button>
                        </PermissionGuard>
                        <PermissionGuard permission="geo_district:delete">
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
                                    District Information
                                </CardTitle>
                                <CardDescription>
                                    Basic information about this district
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            District Code
                                        </h4>
                                        <Badge variant="outline" className="text-base">
                                            {district.code}
                                        </Badge>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            District Name
                                        </h4>
                                        <p className="text-base font-medium">{district.name}</p>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            City
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            <Building className="h-4 w-4" />
                                            <Badge variant="secondary">{district.city.code}</Badge>
                                            <span className="font-medium">{district.city.name}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Province
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            <MapPin className="h-4 w-4" />
                                            <Badge variant="secondary">{district.city.province.code}</Badge>
                                            <span className="font-medium">{district.city.province.name}</span>
                                        </div>
                                    </div>
                                    <div className="col-span-2">
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Country
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            <Globe className="h-4 w-4" />
                                            <Badge variant="secondary">{district.city.province.country.code}</Badge>
                                            <span className="font-medium">{district.city.province.country.name}</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Villages */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <TreePine className="h-5 w-5" />
                                    Villages ({district.villages.length})
                                </CardTitle>
                                <CardDescription>
                                    Villages within this district
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {district.villages.length === 0 ? (
                                    <p className="text-muted-foreground text-center py-4">
                                        No villages found for this district
                                    </p>
                                ) : (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        {district.villages.map((village) => (
                                            <div
                                                key={village.id}
                                                className="p-3 border rounded-lg hover:bg-muted/50 cursor-pointer"
                                                onClick={() => router.get(route('geography.villages.show', village.id))}
                                            >
                                                <div className="flex items-center justify-between mb-2">
                                                    <Badge variant="outline">{village.code}</Badge>
                                                </div>
                                                <p className="font-medium">{village.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    Created {new Date(village.created_at).toLocaleDateString()}
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
                                        {new Date(district.created_at).toLocaleString()}
                                    </p>
                                </div>
                                <div>
                                    <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                        Updated At
                                    </h4>
                                    <p className="text-sm">
                                        {new Date(district.updated_at).toLocaleString()}
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
                                    <div className="text-2xl font-bold">{district.villages.length}</div>
                                    <p className="text-sm text-muted-foreground">Total Villages</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <ActivityLogModal
                isOpen={isActivityLogOpen}
                onClose={() => setIsActivityLogOpen(false)}
                subjectType="App\\Models\\Master\\Geo\\District"
                subjectId={district.id}
                title={`Activity Log - ${district.name}`}
            />
        </AppLayout>
    );
}
