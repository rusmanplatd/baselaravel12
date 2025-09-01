import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import { apiService } from '@/services/ApiService';

import { AvatarUpload } from '@/components/avatar-upload';
import DeleteUser from '@/components/delete-user';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

export default function Profile({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
    const { auth } = usePage<SharedData>().props;
    const [avatarUploading, setAvatarUploading] = useState(false);

    const handleAvatarUpload = useCallback(async (file: File) => {
        setAvatarUploading(true);
        
        const formData = new FormData();
        formData.append('avatar', file);

        try {
            await apiService.postFormData(route('profile.avatar.upload'), formData);

            // Redirect to profile page to update the avatar
            router.visit(route('profile.edit'));
        } catch (error) {
            console.error('Avatar upload error:', error);
            throw error;
        } finally {
            setAvatarUploading(false);
        }
    }, []);

    const handleAvatarDelete = useCallback(async () => {
        try {
            await apiService.delete(route('profile.avatar.delete'));

            // Redirect to profile page to update the avatar
            router.visit(route('profile.edit'));
        } catch (error) {
            console.error('Avatar delete error:', error);
            throw error;
        }
    }, []);

    const avatarUrl = auth.user.avatar ? `/storage/${auth.user.avatar}` : undefined;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Profile picture" description="Upload or change your profile picture" />
                    
                    <AvatarUpload
                        currentAvatar={avatarUrl}
                        userName={auth.user.name}
                        onUpload={handleAvatarUpload}
                        onDelete={handleAvatarDelete}
                        disabled={avatarUploading}
                        size="lg"
                    />
                    
                    <HeadingSmall title="Profile information" description="Update your name and email address" />

                    <Form
                        method="patch"
                        action={route('profile.update')}
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>

                                    <Input
                                        id="name"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Full name"
                                    />

                                    <InputError className="mt-2" message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="username">Username</Label>

                                    <Input
                                        id="username"
                                        type="text"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.username || ''}
                                        name="username"
                                        autoComplete="username"
                                        placeholder="Username (optional)"
                                    />

                                    <InputError className="mt-2" message={errors.username} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="email"
                                        placeholder="Email address"
                                    />

                                    <InputError className="mt-2" message={errors.email} />
                                </div>

                                {mustVerifyEmail && auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            Your email address is unverified.{' '}
                                            <Link
                                                href={route('verification.send')}
                                                method="post"
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Click here to resend the verification email.
                                            </Link>
                                        </p>

                                        {status === 'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                A new verification link has been sent to your email address.
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div className="flex items-center gap-4">
                                    <Button disabled={processing}>Save</Button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-neutral-600">Saved</p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}
