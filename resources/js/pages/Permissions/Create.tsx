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

const formSchema = z.object({
    name: z.string()
        .min(1, 'Permission name is required')
        .max(255, 'Permission name is too long')
        .regex(/^[a-z0-9:_-]+$/, 'Permission name can only contain lowercase letters, numbers, colons, underscores, and hyphens'),
    guard_name: z.string().optional(),
});

type FormData = z.infer<typeof formSchema>;

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('permissions.index'), title: 'Permissions' },
    { href: '', title: 'Create Permission' },
];

const commonPermissionExamples = [
    'org:read',
    'org:write',
    'org:delete',
    'user:read',
    'user:write',
    'user:delete',
    'role:read',
    'role:write',
    'role:delete',
    'permission:read',
    'permission:write',
    'permission:delete',
];

export default function CreatePermission() {
    const form = useForm<FormData>({
        resolver: zodResolver(formSchema),
        defaultValues: {
            name: '',
            guard_name: 'web',
        },
    });

    const onSubmit = (data: FormData) => {
        router.post(route('permissions.store'), data, {
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
        <AppLayout breadcrumbs={breadcrumbItems}>
            <Head title="Create Permission" />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('permissions.index')}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Permissions
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold">Create Permission</h1>
                        <p className="text-muted-foreground">
                            Create a new permission for the system
                        </p>
                    </div>
                </div>

                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertDescription>
                        Permission names should follow the format: <code className="bg-muted px-1 rounded">resource:action</code>.
                        For example: <code className="bg-muted px-1 rounded">org:read</code> or <code className="bg-muted px-1 rounded">user:delete</code>.
                        Use lowercase letters, numbers, colons, underscores, and hyphens only.
                    </AlertDescription>
                </Alert>

                <Form {...form}>
                    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Permission Information</CardTitle>
                                <CardDescription>
                                    Basic information about the permission
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
                                                    placeholder="e.g., org:read, user:write, role:delete"
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
                                <CardTitle>Common Permission Examples</CardTitle>
                                <CardDescription>
                                    Click on any example to use it as your permission name
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                    {commonPermissionExamples.map((example) => (
                                        <Button
                                            key={example}
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => form.setValue('name', example)}
                                            className="justify-start font-mono text-xs"
                                        >
                                            {example}
                                        </Button>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex items-center justify-end space-x-4">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('permissions.index')}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={form.formState.isSubmitting}>
                                <Save className="mr-2 h-4 w-4" />
                                Create Permission
                            </Button>
                        </div>
                    </form>
                </Form>
            </div>
        </AppLayout>
    );
}