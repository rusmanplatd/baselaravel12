import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { PermissionGuard } from '@/components/permission-guard';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2, Eye, Globe, MapPin, Calendar, User } from 'lucide-react';
import ActivityLogModal from '@/components/ActivityLogModal';
import { useState } from 'react';

interface Province {
    id: string;
    code: string;
    name: string;
    created_at: string;
}

interface Country {
    id: string;
    code: string;
    name: string;
    iso_code: string | null;
    phone_code: string | null;
    provinces: Province[];
    created_at: string;
    updated_at: string;
    created_by: string | null;
    updated_by: string | null;
}

interface Props {
    country: Country;
}

const breadcrumbItems = (country: Country): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('geography.countries'), title: 'Countries' },
    { href: '', title: country.name },
];

export default function CountryShow({ country }: Props) {
    const [isActivityLogOpen, setIsActivityLogOpen] = useState(false);

    const handleEdit = () => {
        router.get(route('geography.countries.edit', country.id));
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete the country "${country.name}"?`)) {
            router.delete(route('geography.countries.destroy', country.id), {
                onSuccess: () => {
                    router.get(route('geography.countries'));
                }
            });
        }
    };

    const handleViewLogs = () => {
        setIsActivityLogOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems(country)}>
            <Head title={`${country.name} - Country Details`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            onClick={() => router.get(route('geography.countries'))}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Countries
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold flex items-center gap-2">
                                <Globe className="h-8 w-8" />
                                {country.name}
                            </h1>
                            <p className="text-muted-foreground">
                                Country Details and Information
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
                        <PermissionGuard permission="geo_country:write">
                            <Button onClick={handleEdit}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Button>
                        </PermissionGuard>
                        <PermissionGuard permission="geo_country:delete">
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
                                    <Globe className="h-5 w-5" />
                                    Country Information
                                </CardTitle>
                                <CardDescription>
                                    Basic information about this country
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Country Code
                                        </h4>
                                        <Badge variant="outline" className="text-base">
                                            {country.code}
                                        </Badge>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Full Name
                                        </h4>
                                        <p className="text-base font-medium">{country.name}</p>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            ISO Code
                                        </h4>
                                        {country.iso_code ? (
                                            <Badge variant="secondary" className="text-base">
                                                {country.iso_code}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">Not set</span>
                                        )}
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Phone Code
                                        </h4>
                                        {country.phone_code ? (
                                            <Badge variant="secondary" className="text-base">
                                                {country.phone_code}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">Not set</span>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Provinces */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MapPin className="h-5 w-5" />
                                    Provinces ({country.provinces.length})
                                </CardTitle>
                                <CardDescription>
                                    Provinces within this country
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {country.provinces.length === 0 ? (
                                    <p className="text-muted-foreground text-center py-4">
                                        No provinces found for this country
                                    </p>
                                ) : (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        {country.provinces.map((province) => (
                                            <div
                                                key={province.id}
                                                className="p-3 border rounded-lg hover:bg-muted/50 cursor-pointer"
                                                onClick={() => router.get(route('geography.provinces.show', province.id))}
                                            >
                                                <div className="flex items-center justify-between mb-2">
                                                    <Badge variant="outline">{province.code}</Badge>
                                                </div>
                                                <p className="font-medium">{province.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    Created {new Date(province.created_at).toLocaleDateString()}
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
                                        {new Date(country.created_at).toLocaleString()}
                                    </p>
                                </div>
                                <div>
                                    <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                        Updated At
                                    </h4>
                                    <p className="text-sm">
                                        {new Date(country.updated_at).toLocaleString()}
                                    </p>
                                </div>
                                {country.created_by && (
                                    <div>
                                        <h4 className="font-semibold text-sm text-muted-foreground mb-1">
                                            Created By
                                        </h4>
                                        <p className="text-sm flex items-center gap-1">
                                            <User className="h-3 w-3" />
                                            {country.created_by}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Stats</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="text-center">
                                    <div className="text-2xl font-bold">{country.provinces.length}</div>
                                    <p className="text-sm text-muted-foreground">Total Provinces</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <ActivityLogModal
                isOpen={isActivityLogOpen}
                onClose={() => setIsActivityLogOpen(false)}
                subjectType="App\\Models\\Master\\Geo\\Country"
                subjectId={country.id}
                title={`Activity Log - ${country.name}`}
            />
        </AppLayout>
    );
}