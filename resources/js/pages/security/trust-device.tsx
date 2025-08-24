import { TrustDeviceForm } from '@/components/security/TrustDeviceForm';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';

interface TrustDeviceProps {
    currentDevice: {
        user_agent: string;
        ip_address: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security',
        href: '/settings/security',
    },
    {
        title: 'Trust Device',
        href: '/security/trust-device',
    },
];

export default function TrustDevice({ currentDevice }: TrustDeviceProps) {
    const handleSuccess = () => {
        router.visit(route('dashboard'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trust This Device" />
            
            <div className="container mx-auto py-8">
                <TrustDeviceForm 
                    currentDevice={currentDevice}
                    onSuccess={handleSuccess}
                />
            </div>
        </AppLayout>
    );
}