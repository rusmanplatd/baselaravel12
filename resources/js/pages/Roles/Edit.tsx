import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
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

interface Role {
    id: string;
    name: string;
    guard_name: string;
    team_id: string | null;
    permissions: Permission[];
}

interface Props {
    role: Role;
    permissions: Permission[];
    organizations: Organization[];
}

const formSchema = z.object({
    name: z.string().min(1, 'Role name is required').max(255, 'Role name is too long'),
    permissions: z.array(z.string()).optional(),
});

type FormData = z.infer<typeof formSchema>;

const breadcrumbItems = (role: Role): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('roles.index'), title: 'Roles' },
    { href: route('roles.show', role.id), title: role.name },
    { href: '', title: 'Edit' },
];

export default function EditRole({ role, permissions, organizations }: Props) {
    const form = useForm<FormData>({
        resolver: zodResolver(formSchema),
        defaultValues: {
            name: role.name,
            permissions: role.permissions.map(p => p.name),
        },
    });

    const onSubmit = (data: FormData) => {
        router.put(route('roles.update', role.id), data, {
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

    const organization = organizations.find(org => org.id === role.team_id);

    return (
        <AppLayout breadcrumbs={breadcrumbItems(role)}>
            <Head title={`Edit Role: ${role.name}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('roles.show', role.id)}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Role
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold">Edit Role: {role.name}</h1>
                        <p className="text-muted-foreground">
                            {organization ? `Organization: ${organization.name}` : 'Global Role'}
                        </p>
                    </div>
                </div>

                <Form {...form}>
                    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Role Information</CardTitle>
                                <CardDescription>
                                    Update the role information
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
                                                    placeholder="Enter role name"
                                                />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <div>
                                    <FormLabel>Organization</FormLabel>
                                    <div className="mt-2">
                                        <Input
                                            value={organization ? organization.name : 'Global Role'}
                                            disabled
                                            className="bg-muted"
                                        />
                                        <p className="text-sm text-muted-foreground mt-1">
                                            Organization cannot be changed after creation
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Permissions</CardTitle>
                                <CardDescription>
                                    Update the permissions for this role
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
                                <Link href={route('roles.show', role.id)}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={form.formState.isSubmitting}>
                                <Save className="mr-2 h-4 w-4" />
                                Update Role
                            </Button>
                        </div>
                    </form>
                </Form>
            </div>
        </AppLayout>
    );
}