import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { testSessionTermination, getCurrentSessionInfo, simulateSessionExpiry } from '@/utils/sessionTestUtils';
import { ChevronDown, ChevronRight, Bug, Terminal } from 'lucide-react';
import { toast } from 'sonner';

// This component should only be rendered in development
export function SecurityDebugPanel() {
    const [isOpen, setIsOpen] = useState(false);
    const [testSessionId, setTestSessionId] = useState('');
    const [sessionInfo, setSessionInfo] = useState<any>(null);
    const [loading, setLoading] = useState(false);

    // Don't render in production
    if (import.meta.env.PROD) {
        return null;
    }

    const handleGetSessionInfo = async () => {
        setLoading(true);
        try {
            const info = await getCurrentSessionInfo();
            setSessionInfo(info);
            if (info) {
                toast.success('Session info retrieved');
            } else {
                toast.error('Failed to get session info');
            }
        } catch (error) {
            toast.error('Error getting session info');
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const handleTestTermination = async () => {
        if (!testSessionId.trim()) {
            toast.error('Please enter a session ID');
            return;
        }

        setLoading(true);
        try {
            const success = await testSessionTermination(testSessionId);
            if (success) {
                toast.success('Session termination test completed');
            } else {
                toast.error('Session termination test failed');
            }
        } catch (error) {
            toast.error('Error testing session termination');
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const handleSimulateExpiry = async () => {
        setLoading(true);
        try {
            const success = await simulateSessionExpiry();
            if (success) {
                toast.success('Session expiry simulated - check browser console');
            } else {
                toast.error('Failed to simulate session expiry');
            }
        } catch (error) {
            toast.error('Error simulating session expiry');
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Card className="border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-950">
            <Collapsible open={isOpen} onOpenChange={setIsOpen}>
                <CollapsibleTrigger asChild>
                    <CardHeader className="cursor-pointer hover:bg-orange-100 dark:hover:bg-orange-900 transition-colors">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Bug className="h-5 w-5 text-orange-600" />
                                <CardTitle className="text-orange-800 dark:text-orange-200">
                                    Security Debug Panel
                                </CardTitle>
                                <Badge variant="outline" className="text-xs">
                                    Development Only
                                </Badge>
                            </div>
                            {isOpen ? (
                                <ChevronDown className="h-4 w-4 text-orange-600" />
                            ) : (
                                <ChevronRight className="h-4 w-4 text-orange-600" />
                            )}
                        </div>
                        <CardDescription className="text-orange-700 dark:text-orange-300">
                            Tools for testing session management functionality
                        </CardDescription>
                    </CardHeader>
                </CollapsibleTrigger>
                
                <CollapsibleContent>
                    <CardContent className="space-y-4">
                        {/* Session Info */}
                        <div className="space-y-2">
                            <Button
                                onClick={handleGetSessionInfo}
                                disabled={loading}
                                variant="outline"
                                size="sm"
                                className="w-full"
                            >
                                <Terminal className="h-4 w-4 mr-2" />
                                Get Current Session Info
                            </Button>
                            
                            {sessionInfo && (
                                <div className="p-3 bg-white dark:bg-gray-800 rounded border text-xs">
                                    <pre className="whitespace-pre-wrap overflow-auto">
                                        {JSON.stringify(sessionInfo, null, 2)}
                                    </pre>
                                </div>
                            )}
                        </div>

                        {/* Session Termination Test */}
                        <div className="space-y-2">
                            <Label htmlFor="test-session-id">Test Session Termination</Label>
                            <div className="flex gap-2">
                                <Input
                                    id="test-session-id"
                                    placeholder="Enter session ID to terminate"
                                    value={testSessionId}
                                    onChange={(e) => setTestSessionId(e.target.value)}
                                    className="flex-1"
                                />
                                <Button
                                    onClick={handleTestTermination}
                                    disabled={loading || !testSessionId.trim()}
                                    variant="outline"
                                    size="sm"
                                >
                                    Test
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Use session ID from current session info above
                            </p>
                        </div>

                        {/* Session Expiry Simulation */}
                        <div className="space-y-2">
                            <Button
                                onClick={handleSimulateExpiry}
                                disabled={loading}
                                variant="outline"
                                size="sm"
                                className="w-full"
                            >
                                Simulate Session Expiry
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                This will simulate an expired CSRF token
                            </p>
                        </div>

                        {/* Console Instructions */}
                        <div className="p-3 bg-gray-100 dark:bg-gray-800 rounded text-xs">
                            <p className="font-medium mb-1">Console Commands:</p>
                            <code className="block">window.sessionTestUtils.getCurrentSessionInfo()</code>
                            <code className="block">window.sessionTestUtils.testSessionTermination('session-id')</code>
                            <code className="block">window.sessionTestUtils.simulateSessionExpiry()</code>
                        </div>
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    );
}