import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save, Info } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface Permission {
    id: string;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    permission: Permission;
}

const formSchema = z.object({
    name: z.string()
        .min(1, 'Permission name is required')
        .max(255, 'Permission name is too long')
        .regex(/^[a-z0-9:_-]+$/, 'Permission name can only contain lowercase letters, numbers, colons, underscores, and hyphens'),
    guard_name: z.string().optional(),
});

type FormData = z.infer<typeof formSchema>;

const breadcrumbItems = (permission: Permission): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('permissions.index'), title: 'Permissions' },
    { href: route('permissions.show', permission.id), title: permission.name },
    { href: '', title: 'Edit' },
];

export default function EditPermission({ permission }: Props) {
    const form = useForm<FormData>({
        resolver: zodResolver(formSchema),
        defaultValues: {
            name: permission.name,
            guard_name: permission.guard_name,
        },
    });

    const onSubmit = (data: FormData) => {
        router.put(route('permissions.update', permission.id), data, {
            onError: (errors) => {
                Object.keys(errors).forEach((key) => {
                    form.setError(key as keyof FormData, {
                        message: errors[key],
                    });
                });
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbItems(permission)}>
            <Head title={`Edit Permission: ${permission.name}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('permissions.show', permission.id)}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Permission
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold">Edit Permission: {permission.name}</h1>
                        <p className="text-muted-foreground">
                            Update the permission information
                        </p>
                    </div>
                </div>

                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertDescription>
                        Permission names should follow the format: <code className="bg-muted px-1 rounded">resource:action</code>.
                        For example: <code className="bg-muted px-1 rounded">organization:read</code> or <code className="bg-muted px-1 rounded">user:delete</code>.
                        Use lowercase letters, numbers, colons, underscores, and hyphens only.
                    </AlertDescription>
                </Alert>

                <Form {...form}>
                    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Permission Information</CardTitle>
                                <CardDescription>
                                    Update the permission information
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <FormField
                                    control={form.control}
                                    name="name"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Permission Name</FormLabel>
                                            <FormControl>
                                                <Input
                                                    {...field}
                                                    placeholder="e.g., organization:read, user:write, role:delete"
                                                />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <FormField
                                    control={form.control}
                                    name="guard_name"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Guard Name</FormLabel>
                                            <FormControl>
                                                <Select onValueChange={field.onChange} value={field.value}>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select guard name" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="web">web</SelectItem>
                                                        <SelectItem value="api">api</SelectItem>
                                                        <SelectItem value="admin">admin</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Permission History</CardTitle>
                                <CardDescription>
                                    Information about when this permission was created and last modified
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Created</label>
                                    <p className="text-sm">{new Date(permission.created_at).toLocaleString()}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Last Updated</label>
                                    <p className="text-sm">{new Date(permission.updated_at).toLocaleString()}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex items-center justify-end space-x-4">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('permissions.show', permission.id)}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={form.formState.isSubmitting}>
                                <Save className="mr-2 h-4 w-4" />
                                Update Permission
                            </Button>
                        </div>
                    </form>
                </Form>
            </div>
        </AppLayout>
    );
}