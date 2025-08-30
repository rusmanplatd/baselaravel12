import { Head } from '@inertiajs/react';
import { User } from '@/types';
import ChatLayout from '@/components/chat/ChatLayout';
import AppLayout from '@/layouts/app-layout';

interface ChatPageProps {
  auth: {
    user: User;
  };
  inviteCode?: string;
}

export default function Chat({ auth, inviteCode }: ChatPageProps) {
  return (
    <AppLayout>
      <Head title="Chat" />
      
      <div className="h-screen">
        <ChatLayout user={auth.user} inviteCode={inviteCode} />
      </div>
    </AppLayout>
  );
}