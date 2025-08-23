import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';

interface Permission {
    id: string;
    name: string;
    guard_name: string;
}

interface Organization {
    id: string;
    name: string;
}

interface Props {
    permissions: Permission[];
    organizations: Organization[];
}

const formSchema = z.object({
    name: z.string().min(1, 'Role name is required').max(255, 'Role name is too long'),
    team_id: z.string().optional(),
    permissions: z.array(z.string()).optional(),
});

type FormData = z.infer<typeof formSchema>;

const breadcrumbItems: BreadcrumbItem[] = [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('roles.index'), title: 'Roles' },
    { href: '', title: 'Create Role' },
];

export default function CreateRole({ permissions, organizations }: Props) {
    const form = useForm<FormData>({
        resolver: zodResolver(formSchema),
        defaultValues: {
            name: '',
            team_id: '',
            permissions: [],
        },
    });

    const onSubmit = (data: FormData) => {
        router.post(route('roles.store'), {
            ...data,
            team_id: data.team_id || null,
        }, {
            onError: (errors) => {
                Object.keys(errors).forEach((key) => {
                    form.setError(key as keyof FormData, {
                        message: errors[key],
                    });
                });
            },
        });
    };

    const groupedPermissions = permissions.reduce((acc, permission) => {
        const [resource] = permission.name.split(':');
        if (!acc[resource]) {
            acc[resource] = [];
        }
        acc[resource].push(permission);
        return acc;
    }, {} as Record<string, Permission[]>);

    return (
        <AppLayout breadcrumbs={breadcrumbItems}>
            <Head title="Create Role" />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('roles.index')}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Roles
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold">Create Role</h1>
                        <p className="text-muted-foreground">
                            Create a new role with specific permissions
                        </p>
                    </div>
                </div>

                <Form {...form}>
                    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Role Information</CardTitle>
                                <CardDescription>
                                    Basic information about the role
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <FormField
                                    control={form.control}
                                    name="name"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Role Name</FormLabel>
                                            <FormControl>
                                                <Input
                                                    {...field}
                                                    placeholder="Enter role name (e.g., admin, manager, viewer)"
                                                />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <FormField
                                    control={form.control}
                                    name="team_id"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Organization (Optional)</FormLabel>
                                            <FormControl>
                                                <Select onValueChange={field.onChange} value={field.value}>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select organization (leave blank for global role)" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="">Global Role</SelectItem>
                                                        {organizations.map((org) => (
                                                            <SelectItem key={org.id} value={org.id}>
                                                                {org.name}
                                                            </SelectItem>
                                                        ))}
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
                                <CardTitle>Permissions</CardTitle>
                                <CardDescription>
                                    Select the permissions for this role
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FormField
                                    control={form.control}
                                    name="permissions"
                                    render={({ field }) => (
                                        <FormItem>
                                            <div className="space-y-6">
                                                {Object.entries(groupedPermissions).map(([resource, resourcePermissions]) => (
                                                    <div key={resource} className="space-y-3">
                                                        <div className="flex items-center space-x-2">
                                                            <h3 className="font-medium text-sm uppercase text-muted-foreground">
                                                                {resource.replace(/[-_]/g, ' ')}
                                                            </h3>
                                                        </div>
                                                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                                            {resourcePermissions.map((permission) => (
                                                                <div key={permission.name} className="flex items-center space-x-2">
                                                                    <Checkbox
                                                                        id={permission.name}
                                                                        checked={field.value?.includes(permission.name) || false}
                                                                        onCheckedChange={(checked) => {
                                                                            const currentValue = field.value || [];
                                                                            if (checked) {
                                                                                field.onChange([...currentValue, permission.name]);
                                                                            } else {
                                                                                field.onChange(currentValue.filter(p => p !== permission.name));
                                                                            }
                                                                        }}
                                                                    />
                                                                    <label
                                                                        htmlFor={permission.name}
                                                                        className="text-sm cursor-pointer"
                                                                    >
                                                                        {permission.name.split(':')[1] || permission.name}
                                                                    </label>
                                                                </div>
                                                            ))}
                                                        </div>
                                                        {resource !== Object.keys(groupedPermissions)[Object.keys(groupedPermissions).length - 1] && (
                                                            <Separator className="my-4" />
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                            </CardContent>
                        </Card>

                        <div className="flex items-center justify-end space-x-4">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('roles.index')}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={form.formState.isSubmitting}>
                                <Save className="mr-2 h-4 w-4" />
                                Create Role
                            </Button>
                        </div>
                    </form>
                </Form>
            </div>
        </AppLayout>
    );
}