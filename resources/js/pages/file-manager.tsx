import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import FileManager from '@/components/files/FileManager';
import { Card } from '@/components/ui/card';

interface FileManagerPageProps {
  auth: {
    user: {
      id: string;
      name: string;
      email: string;
    };
  };
}

export default function FileManagerPage({ auth }: FileManagerPageProps) {
  return (
    <AppLayout 
      user={auth.user}
      header={
        <div className="flex items-center justify-between">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">
            File Manager
          </h2>
        </div>
      }
    >
      <Head title="File Manager" />

      <div className="py-12">
        <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
          <Card className="overflow-hidden">
            <FileManager className="h-[calc(100vh-200px)]" />
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}