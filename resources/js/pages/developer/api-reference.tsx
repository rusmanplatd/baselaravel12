import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Book, Code, Key, Shield } from 'lucide-react';

interface OAuthScope {
    identifier: string;
    name: string;
    description: string;
    is_default: boolean;
    category: string;
}

interface Permission {
    name: string;
    guard_name: string;
    module: string;
    action: string;
    description: string;
}

interface ApiReferenceProps {
    scopes: Record<string, OAuthScope[]>;
    permissions: Record<string, Permission[]>;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Developer',
        href: '/developer',
    },
    {
        title: 'API Reference',
        href: '/developer/api-reference',
    },
];

export default function ApiReference({ scopes, permissions }: ApiReferenceProps) {
    return (
        <>
            <Head title="API Reference - OAuth Scopes & Permissions" />
            <div className="min-h-screen bg-background">
                {/* Header */}
                <div className="border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="container mx-auto max-w-6xl px-4 py-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-4">
                                <div className="rounded-lg bg-primary/10 p-2">
                                    <Book className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h1 className="text-2xl font-bold tracking-tight">API Reference</h1>
                                    <p className="text-muted-foreground">
                                        OAuth scopes and system permissions for API integration
                                    </p>
                                </div>
                            </div>
                            <Link
                                href={route('home')}
                                className="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                ← Back to Home
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Content */}
                <div className="container mx-auto max-w-6xl px-4 py-8">
                    <Tabs defaultValue="oauth-scopes" className="space-y-6">
                        <TabsList className="grid w-full grid-cols-2 lg:w-[400px]">
                            <TabsTrigger value="oauth-scopes" className="flex items-center space-x-2">
                                <Key className="h-4 w-4" />
                                <span>OAuth Scopes</span>
                            </TabsTrigger>
                            <TabsTrigger value="permissions" className="flex items-center space-x-2">
                                <Shield className="h-4 w-4" />
                                <span>Permissions</span>
                            </TabsTrigger>
                        </TabsList>

                        {/* OAuth Scopes Tab */}
                        <TabsContent value="oauth-scopes" className="space-y-6">
                            <div className="rounded-lg border border-border bg-card p-6">
                                <div className="mb-4 flex items-center space-x-2">
                                    <Code className="h-5 w-5 text-primary" />
                                    <h2 className="text-xl font-semibold">OAuth 2.0 Scopes</h2>
                                </div>
                                <p className="text-muted-foreground mb-6">
                                    OAuth scopes define the level of access that your application can request when users
                                    authorize your app. Use these scopes in your OAuth authorization requests.
                                </p>
                                
                                <div className="space-y-6">
                                    {Object.entries(scopes).map(([category, categoryScopes]) => (
                                        <div key={category}>
                                            <h3 className="mb-3 text-lg font-medium">{category}</h3>
                                            <div className="grid gap-3">
                                                {categoryScopes.map((scope) => (
                                                    <Card key={scope.identifier} className="border-l-4 border-l-primary/20">
                                                        <CardHeader className="pb-3">
                                                            <div className="flex items-center justify-between">
                                                                <CardTitle className="text-base font-mono text-primary">
                                                                    {scope.identifier}
                                                                </CardTitle>
                                                                {scope.is_default && (
                                                                    <Badge variant="secondary">Default</Badge>
                                                                )}
                                                            </div>
                                                            <CardDescription className="font-medium">
                                                                {scope.name}
                                                            </CardDescription>
                                                        </CardHeader>
                                                        <CardContent className="pt-0">
                                                            <p className="text-sm text-muted-foreground">
                                                                {scope.description}
                                                            </p>
                                                        </CardContent>
                                                    </Card>
                                                ))}
                                            </div>
                                            {Object.keys(scopes).indexOf(category) < Object.keys(scopes).length - 1 && (
                                                <Separator className="my-6" />
                                            )}
                                        </div>
                                    ))}
                                </div>

                                <div className="mt-8 rounded-lg bg-muted/50 p-4">
                                    <h4 className="mb-2 font-medium">Usage Example</h4>
                                    <div className="rounded bg-background p-3 font-mono text-sm">
                                        <code>
                                            GET /oauth/authorize?<br />
                                            &nbsp;&nbsp;client_id=your_client_id&<br />
                                            &nbsp;&nbsp;response_type=code&<br />
                                            &nbsp;&nbsp;scope=openid profile email&<br />
                                            &nbsp;&nbsp;redirect_uri=https://your-app.com/callback
                                        </code>
                                    </div>
                                </div>
                            </div>
                        </TabsContent>

                        {/* Permissions Tab */}
                        <TabsContent value="permissions" className="space-y-6">
                            <div className="rounded-lg border border-border bg-card p-6">
                                <div className="mb-4 flex items-center space-x-2">
                                    <Shield className="h-5 w-5 text-primary" />
                                    <h2 className="text-xl font-semibold">System Permissions</h2>
                                </div>
                                <p className="text-muted-foreground mb-6">
                                    System permissions control what actions users can perform within the application.
                                    These are managed through roles and can be assigned to users or API clients.
                                </p>

                                <div className="space-y-6">
                                    {Object.entries(permissions).map(([module, modulePermissions]) => (
                                        <div key={module}>
                                            <h3 className="mb-3 text-lg font-medium capitalize">
                                                {module.replace('_', ' ')} Module
                                            </h3>
                                            <div className="grid gap-3">
                                                {modulePermissions.map((permission) => (
                                                    <Card key={permission.name} className="border-l-4 border-l-primary/20">
                                                        <CardHeader className="pb-3">
                                                            <div className="flex items-center justify-between">
                                                                <CardTitle className="text-base font-mono text-primary">
                                                                    {permission.name}
                                                                </CardTitle>
                                                                <div className="flex items-center space-x-2">
                                                                    <Badge variant="outline" className="text-xs">
                                                                        {permission.action}
                                                                    </Badge>
                                                                    <Badge variant="secondary" className="text-xs">
                                                                        {permission.guard_name}
                                                                    </Badge>
                                                                </div>
                                                            </div>
                                                        </CardHeader>
                                                        <CardContent className="pt-0">
                                                            <p className="text-sm text-muted-foreground">
                                                                {permission.description}
                                                            </p>
                                                        </CardContent>
                                                    </Card>
                                                ))}
                                            </div>
                                            {Object.keys(permissions).indexOf(module) < Object.keys(permissions).length - 1 && (
                                                <Separator className="my-6" />
                                            )}
                                        </div>
                                    ))}
                                </div>

                                <div className="mt-8 rounded-lg bg-muted/50 p-4">
                                    <h4 className="mb-2 font-medium">Permission Structure</h4>
                                    <p className="text-sm text-muted-foreground mb-3">
                                        Permissions follow a consistent structure: <code>module:action</code>
                                    </p>
                                    <div className="rounded bg-background p-3 font-mono text-sm">
                                        <code>
                                            Examples:<br />
                                            • user:read - View users<br />
                                            • org:admin - Full organization administration<br />
                                            • oauth_app:write - Create and update OAuth applications
                                        </code>
                                    </div>
                                </div>
                            </div>
                        </TabsContent>
                    </Tabs>
                </div>

                {/* Footer */}
                <div className="border-t border-border/40 bg-muted/30 py-6">
                    <div className="container mx-auto max-w-6xl px-4">
                        <div className="text-center text-sm text-muted-foreground">
                            <p>
                                For more information about OAuth 2.0 implementation, visit our{' '}
                                <Link href={route('home')} className="text-primary hover:underline">
                                    documentation
                                </Link>{' '}
                                or check the{' '}
                                <a 
                                    href="/.well-known/openid_configuration" 
                                    className="text-primary hover:underline"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    OpenID Connect discovery endpoint
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}