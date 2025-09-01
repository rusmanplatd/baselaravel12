import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Calendar, User, Clock, FileText, Loader2, AlertCircle } from 'lucide-react';

interface ActivityLog {
    id: string;
    log_name: string;
    description: string;
    subject_type: string;
    subject_id: string;
    causer_type: string | null;
    causer_id: string | null;
    properties: {
        attributes?: Record<string, any>;
        old?: Record<string, any>;
    };
    created_at: string;
    causer?: {
        id: string;
        name: string;
        email: string;
    };
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    subjectType: string;
    subjectId: string;
    title: string;
}

export default function ActivityLogModal({ isOpen, onClose, subjectType, subjectId, title }: Props) {
    const [logs, setLogs] = useState<ActivityLog[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (isOpen && subjectType && subjectId) {
            fetchLogs();
        }
    }, [isOpen, subjectType, subjectId]);

    const fetchLogs = async () => {
        setLoading(true);
        setError(null);
        
        try {
            const response = await fetch(`/api/activity-logs?filter[subject_type]=${encodeURIComponent(subjectType)}&filter[subject_id]=${subjectId}&sort=-created_at`);
            
            if (!response.ok) {
                throw new Error('Failed to fetch activity logs');
            }
            
            const data = await response.json();
            setLogs(data.data || []);
        } catch (err) {
            setError('Failed to load activity logs');
            console.error('Error fetching activity logs:', err);
        } finally {
            setLoading(false);
        }
    };

    const formatDateTime = (dateTime: string) => {
        return new Date(dateTime).toLocaleString();
    };

    const formatChanges = (properties: any) => {
        if (!properties.attributes && !properties.old) return null;

        const changes = [];
        const attributes = properties.attributes || {};
        const old = properties.old || {};

        for (const key in attributes) {
            if (attributes[key] !== old[key]) {
                changes.push({
                    field: key,
                    old: old[key],
                    new: attributes[key]
                });
            }
        }

        return changes;
    };

    const getEventBadgeVariant = (description: string) => {
        if (description.toLowerCase().includes('created')) return 'default';
        if (description.toLowerCase().includes('updated')) return 'secondary';
        if (description.toLowerCase().includes('deleted')) return 'destructive';
        return 'outline';
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[80vh]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        Activity Log - {title}
                    </DialogTitle>
                    <DialogDescription>
                        Complete activity history for this record
                    </DialogDescription>
                </DialogHeader>

                <ScrollArea className="h-[60vh] pr-4">
                    {loading && (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-8 w-8 animate-spin" />
                            <span className="ml-2">Loading activity logs...</span>
                        </div>
                    )}

                    {error && (
                        <div className="flex items-center justify-center py-8 text-destructive">
                            <AlertCircle className="h-5 w-5 mr-2" />
                            {error}
                        </div>
                    )}

                    {!loading && !error && logs.length === 0 && (
                        <div className="text-center py-8 text-muted-foreground">
                            <FileText className="h-12 w-12 mx-auto mb-4 opacity-50" />
                            <p>No activity logs found for this record</p>
                        </div>
                    )}

                    {!loading && !error && logs.length > 0 && (
                        <div className="space-y-4">
                            {logs.map((log, index) => {
                                const changes = formatChanges(log.properties);
                                
                                return (
                                    <div key={log.id} className="relative">
                                        {/* Timeline line */}
                                        {index < logs.length - 1 && (
                                            <div className="absolute left-4 top-12 bottom-0 w-px bg-border" />
                                        )}
                                        
                                        {/* Timeline dot */}
                                        <div className="absolute left-2 top-3 w-4 h-4 bg-background border-2 border-primary rounded-full" />
                                        
                                        {/* Content */}
                                        <div className="ml-10 pb-4">
                                            <div className="flex items-center gap-2 mb-2">
                                                <Badge variant={getEventBadgeVariant(log.description)}>
                                                    {log.description}
                                                </Badge>
                                                <div className="flex items-center text-sm text-muted-foreground gap-4">
                                                    <span className="flex items-center gap-1">
                                                        <Calendar className="h-3 w-3" />
                                                        {formatDateTime(log.created_at)}
                                                    </span>
                                                    {log.causer && (
                                                        <span className="flex items-center gap-1">
                                                            <User className="h-3 w-3" />
                                                            {log.causer.name}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            {changes && changes.length > 0 && (
                                                <div className="mt-2 p-3 bg-muted/50 rounded-md">
                                                    <h5 className="text-sm font-medium mb-2 flex items-center gap-1">
                                                        <Clock className="h-3 w-3" />
                                                        Changes Made:
                                                    </h5>
                                                    <div className="space-y-1 text-sm">
                                                        {changes.map((change, idx) => (
                                                            <div key={idx} className="grid grid-cols-3 gap-2">
                                                                <span className="font-medium capitalize">
                                                                    {change.field.replace('_', ' ')}:
                                                                </span>
                                                                <span className="text-muted-foreground">
                                                                    {change.old || <em>empty</em>}
                                                                </span>
                                                                <span className="text-foreground">
                                                                    → {change.new || <em>empty</em>}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </ScrollArea>

                <Separator />

                <div className="flex justify-between items-center">
                    <div className="text-sm text-muted-foreground">
                        {logs.length > 0 && `${logs.length} activity log${logs.length !== 1 ? 's' : ''} found`}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={onClose}>
                            Close
                        </Button>
                        {logs.length > 0 && (
                            <Button
                                onClick={() => {
                                    router.get(route('activity-log.index'), {
                                        'filter[subject_type]': subjectType,
                                        'filter[subject_id]': subjectId,
                                    });
                                }}
                            >
                                View Full Log
                            </Button>
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}