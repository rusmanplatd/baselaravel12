import { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Separator } from '@/components/ui/separator';
import TotpSetup from '@/components/MFA/TotpSetup';
import WebAuthnManager from '@/components/WebAuthn/WebAuthnManager';
import { type BreadcrumbItem } from '@/types';

interface MfaSetupProps {
    mfaEnabled: boolean;
    hasBackupCodes: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security Settings',
        href: '/mfa/setup',
    },
];

export default function MfaSetup({ mfaEnabled: initialMfaEnabled, hasBackupCodes: initialHasBackupCodes }: MfaSetupProps) {
    const [mfaEnabled, setMfaEnabled] = useState(initialMfaEnabled);
    const [hasBackupCodes, setHasBackupCodes] = useState(initialHasBackupCodes);

    const handleMfaStatusChange = () => {
        // Reload the page to get updated MFA status
        window.location.reload();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Security Settings" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
                <div className="space-y-8 max-w-4xl">
                    <div>
                        <h3 className="text-lg font-medium">Multi-Factor Authentication</h3>
                        <p className="text-sm text-muted-foreground mt-1">
                            Secure your account with additional authentication methods
                        </p>
                    </div>

                    <div className="space-y-6">
                        <TotpSetup 
                            mfaEnabled={mfaEnabled}
                            hasBackupCodes={hasBackupCodes}
                            onMfaStatusChange={handleMfaStatusChange}
                        />
                        
                        <Separator />
                        
                        <WebAuthnManager />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}