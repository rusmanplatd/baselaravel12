import { Head } from '@inertiajs/react';
import MfaChallenge from '@/components/MFA/MfaChallenge';

export default function MfaChallengePage() {
    return (
        <>
            <Head title="Two-Factor Authentication" />
            <MfaChallenge />
        </>
    );
}