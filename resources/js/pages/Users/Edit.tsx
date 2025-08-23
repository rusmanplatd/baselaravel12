import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save, Users } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';

interface Role {
    id: string;
    name: string;
    guard_name: string;
}

interface User {
    id: string;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles: Role[];
    created_at: string;
    updated_at: string;
}

interface Props {
    user: User;
    roles: Role[];
}

const formSchema = z.object({
    roles: z.array(z.string()).optional(),
});

type FormData = z.infer<typeof formSchema>;

const breadcrumbItems = (user: User): BreadcrumbItem[] => [
    { href: route('dashboard'), title: 'Dashboard' },
    { href: route('users.index'), title: 'Users' },
    { href: route('users.show', user.id), title: user.name },
    { href: '', title: 'Edit Roles' },
];

export default function EditUser({ user, roles }: Props) {
    const form = useForm<FormData>({
        resolver: zodResolver(formSchema),
        defaultValues: {
            roles: user.roles.map(role => role.name),
        },
    });

    const onSubmit = (data: FormData) => {
        router.post(route('users.assignRoles', user.id), data, {
            onError: (errors) => {
                Object.keys(errors).forEach((key) => {
                    form.setError(key as keyof FormData, {
                        message: errors[key],
                    });
                });
            },
        });
    };

    // Group roles for better organization
    const globalRoles = roles.filter(role => !role.name.includes('.'));
    const organizationRoles = roles.filter(role => role.name.includes('.'));

    return (
        <AppLayout breadcrumbs={breadcrumbItems(user)}>
            <Head title={`Edit User Roles: ${user.name}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('users.show', user.id)}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to User
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold">Edit Roles: {user.name}</h1>
                        <p className="text-muted-foreground">
                            {user.email} • Joined {new Date(user.created_at).toLocaleDateString()}
                        </p>
                    </div>
                </div>

                <Form {...form}>
                    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Users className="h-5 w-5" />
                                    Role Assignment
                                </CardTitle>
                                <CardDescription>
                                    Select the roles to assign to this user
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FormField
                                    control={form.control}
                                    name="roles"
                                    render={({ field }) => (
                                        <FormItem>
                                            <div className="space-y-6">
                                                {/* Global Roles */}
                                                {globalRoles.length > 0 && (
                                                    <div className="space-y-3">
                                                        <div className="flex items-center space-x-2">
                                                            <h3 className="font-medium text-sm uppercase text-muted-foreground">
                                                                System Roles
                                                            </h3>
                                                        </div>
                                                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                            {globalRoles.map((role) => (
                                                                <div key={role.name} className="flex items-center space-x-2">
                                                                    <Checkbox
                                                                        id={role.name}
                                                                        checked={field.value?.includes(role.name) || false}
                                                                        onCheckedChange={(checked) => {
                                                                            const currentValue = field.value || [];
                                                                            if (checked) {
                                                                                field.onChange([...currentValue, role.name]);
                                                                            } else {
                                                                                field.onChange(currentValue.filter(r => r !== role.name));
                                                                            }
                                                                        }}
                                                                    />
                                                                    <label
                                                                        htmlFor={role.name}
                                                                        className="text-sm cursor-pointer"
                                                                    >
                                                                        {role.name}
                                                                    </label>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Separator if both sections exist */}
                                                {globalRoles.length > 0 && organizationRoles.length > 0 && (
                                                    <Separator />
                                                )}

                                                {/* Organization-specific Roles */}
                                                {organizationRoles.length > 0 && (
                                                    <div className="space-y-3">
                                                        <div className="flex items-center space-x-2">
                                                            <h3 className="font-medium text-sm uppercase text-muted-foreground">
                                                                Organization Roles
                                                            </h3>
                                                        </div>
                                                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                            {organizationRoles.map((role) => (
                                                                <div key={role.name} className="flex items-center space-x-2">
                                                                    <Checkbox
                                                                        id={role.name}
                                                                        checked={field.value?.includes(role.name) || false}
                                                                        onCheckedChange={(checked) => {
                                                                            const currentValue = field.value || [];
                                                                            if (checked) {
                                                                                field.onChange([...currentValue, role.name]);
                                                                            } else {
                                                                                field.onChange(currentValue.filter(r => r !== role.name));
                                                                            }
                                                                        }}
                                                                    />
                                                                    <label
                                                                        htmlFor={role.name}
                                                                        className="text-sm cursor-pointer"
                                                                    >
                                                                        {role.name}
                                                                    </label>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                            </CardContent>
                        </Card>

                        <div className="flex items-center justify-end space-x-4">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('users.show', user.id)}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={form.formState.isSubmitting}>
                                <Save className="mr-2 h-4 w-4" />
                                Update Roles
                            </Button>
                        </div>
                    </form>
                </Form>
            </div>
        </AppLayout>
    );
}