import React, { useState, useRef } from 'react';
import { 
    Send, 
    Clock, 
    Image, 
    File, 
    X, 
    Calendar, 
    Pin,
    VolumeX,
    Eye,
    MessageSquare
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle, 
    DialogTrigger 
} from '@/components/ui/dialog';
import { 
    Popover, 
    PopoverContent, 
    PopoverTrigger 
} from '@/components/ui/popover';
import { Calendar as CalendarComponent } from '@/components/ui/calendar';
import { format } from 'date-fns';
import { toast } from 'sonner';
import { useChannelBroadcasts } from '@/hooks/useChannelBroadcasts';

interface Attachment {
    id: string;
    type: 'image' | 'video' | 'file';
    url: string;
    name: string;
    size: number;
    preview?: string;
}

interface BroadcastComposerProps {
    channelId: string;
    onBroadcastSent: (broadcast: any) => void;
    className?: string;
}

export default function BroadcastComposer({ 
    channelId, 
    onBroadcastSent, 
    className 
}: BroadcastComposerProps) {
    const [isExpanded, setIsExpanded] = useState(false);
    const [content, setContent] = useState('');
    const [title, setTitle] = useState('');
    const [attachments, setAttachments] = useState<Attachment[]>([]);
    const [scheduledDate, setScheduledDate] = useState<Date | undefined>(undefined);
    const [scheduledTime, setScheduledTime] = useState('');
    const [isSilent, setIsSilent] = useState(false);
    const [pinMessage, setPinMessage] = useState(false);
    const [disableNotifications, setDisableNotifications] = useState(false);
    const [showScheduleDialog, setShowScheduleDialog] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { createBroadcast } = useChannelBroadcasts(channelId);

    const handleFileUpload = (event: React.ChangeEvent<HTMLInputElement>) => {
        const files = event.target.files;
        if (!files) return;

        Array.from(files).forEach((file) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const attachment: Attachment = {
                    id: Math.random().toString(36).substr(2, 9),
                    type: file.type.startsWith('image/') ? 'image' : 
                          file.type.startsWith('video/') ? 'video' : 'file',
                    url: e.target?.result as string,
                    name: file.name,
                    size: file.size,
                    preview: file.type.startsWith('image/') ? e.target?.result as string : undefined,
                };
                
                setAttachments(prev => [...prev, attachment]);
            };
            reader.readAsDataURL(file);
        });
        
        // Clear the input
        event.target.value = '';
    };

    const removeAttachment = (id: string) => {
        setAttachments(prev => prev.filter(att => att.id !== id));
    };

    const handleSchedule = () => {
        if (!scheduledDate || !scheduledTime) {
            toast.error('Please select both date and time');
            return;
        }

        const [hours, minutes] = scheduledTime.split(':').map(Number);
        const scheduled = new Date(scheduledDate);
        scheduled.setHours(hours, minutes, 0, 0);

        if (scheduled <= new Date()) {
            toast.error('Scheduled time must be in the future');
            return;
        }

        setShowScheduleDialog(false);
        handleSubmit(scheduled);
    };

    const handleSubmit = async (scheduledAt?: Date) => {
        if (!content.trim()) {
            toast.error('Please enter broadcast content');
            return;
        }

        setIsSubmitting(true);

        try {
            const broadcastData = {
                title: title.trim() || undefined,
                content: content.trim(),
                media_attachments: attachments.length > 0 ? attachments.map(att => att.url) : undefined,
                scheduled_at: scheduledAt?.toISOString(),
                broadcast_settings: {
                    silent: isSilent,
                    pin_message: pinMessage,
                    disable_notifications: disableNotifications,
                },
            };

            const broadcast = await createBroadcast(broadcastData);
            
            if (broadcast) {
                // Reset form
                setContent('');
                setTitle('');
                setAttachments([]);
                setScheduledDate(undefined);
                setScheduledTime('');
                setIsSilent(false);
                setPinMessage(false);
                setDisableNotifications(false);
                setIsExpanded(false);
                
                onBroadcastSent(broadcast);
                toast.success(scheduledAt ? 'Broadcast scheduled successfully' : 'Broadcast sent successfully');
            }
        } catch (error) {
            toast.error('Failed to send broadcast');
        } finally {
            setIsSubmitting(false);
        }
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    return (
        <Card className={className}>
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-lg">
                    <MessageSquare className="h-5 w-5" />
                    Broadcast Message
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Title Input */}
                {isExpanded && (
                    <div>
                        <Label htmlFor="title" className="text-sm font-medium">
                            Title (optional)
                        </Label>
                        <Input
                            id="title"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            placeholder="Broadcast title..."
                            className="mt-1"
                        />
                    </div>
                )}

                {/* Content Input */}
                <div>
                    <Textarea
                        value={content}
                        onChange={(e) => setContent(e.target.value)}
                        placeholder={isExpanded ? "Write your broadcast message..." : "Click to compose broadcast..."}
                        className="min-h-[80px] resize-none"
                        onFocus={() => setIsExpanded(true)}
                        maxLength={4000}
                    />
                    {isExpanded && (
                        <div className="flex justify-between items-center mt-1 text-xs text-gray-500">
                            <span>{content.length}/4000 characters</span>
                            {attachments.length > 0 && (
                                <span>{attachments.length} attachment{attachments.length > 1 ? 's' : ''}</span>
                            )}
                        </div>
                    )}
                </div>

                {/* Attachments Preview */}
                {attachments.length > 0 && (
                    <div className="space-y-2">
                        <Label className="text-sm font-medium">Attachments</Label>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                            {attachments.map((attachment) => (
                                <div key={attachment.id} className="relative group">
                                    {attachment.type === 'image' ? (
                                        <div className="relative">
                                            <img
                                                src={attachment.preview}
                                                alt={attachment.name}
                                                className="w-full h-20 object-cover rounded border"
                                            />
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                className="absolute -top-1 -right-1 h-6 w-6 rounded-full p-0 opacity-0 group-hover:opacity-100 transition-opacity"
                                                onClick={() => removeAttachment(attachment.id)}
                                            >
                                                <X className="h-3 w-3" />
                                            </Button>
                                        </div>
                                    ) : (
                                        <div className="flex items-center space-x-2 p-2 border rounded bg-gray-50 group-hover:bg-gray-100">
                                            <File className="h-4 w-4 text-gray-600" />
                                            <div className="flex-1 min-w-0">
                                                <p className="text-xs font-medium truncate">{attachment.name}</p>
                                                <p className="text-xs text-gray-500">{formatFileSize(attachment.size)}</p>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-6 w-6 p-0 opacity-0 group-hover:opacity-100"
                                                onClick={() => removeAttachment(attachment.id)}
                                            >
                                                <X className="h-3 w-3" />
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Broadcast Options */}
                {isExpanded && (
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2">
                                <VolumeX className="h-4 w-4 text-gray-500" />
                                <Label className="text-sm">Silent broadcast</Label>
                            </div>
                            <Switch
                                checked={isSilent}
                                onCheckedChange={setIsSilent}
                            />
                        </div>

                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2">
                                <Pin className="h-4 w-4 text-gray-500" />
                                <Label className="text-sm">Pin message</Label>
                            </div>
                            <Switch
                                checked={pinMessage}
                                onCheckedChange={setPinMessage}
                            />
                        </div>

                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2">
                                <Eye className="h-4 w-4 text-gray-500" />
                                <Label className="text-sm">Disable previews</Label>
                            </div>
                            <Switch
                                checked={disableNotifications}
                                onCheckedChange={setDisableNotifications}
                            />
                        </div>
                    </div>
                )}

                {/* Action Buttons */}
                {isExpanded && (
                    <div className="flex items-center justify-between pt-2">
                        <div className="flex items-center space-x-2">
                            {/* File Upload */}
                            <input
                                ref={fileInputRef}
                                type="file"
                                multiple
                                accept="image/*,video/*,.pdf,.doc,.docx,.txt"
                                onChange={handleFileUpload}
                                className="hidden"
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => fileInputRef.current?.click()}
                                className="px-3"
                            >
                                <Image className="h-4 w-4" />
                            </Button>

                            {/* Schedule Dialog */}
                            <Dialog open={showScheduleDialog} onOpenChange={setShowScheduleDialog}>
                                <DialogTrigger asChild>
                                    <Button variant="outline" size="sm" className="px-3">
                                        <Clock className="h-4 w-4" />
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle>Schedule Broadcast</DialogTitle>
                                    </DialogHeader>
                                    <div className="space-y-4">
                                        <div>
                                            <Label>Date</Label>
                                            <Popover>
                                                <PopoverTrigger asChild>
                                                    <Button variant="outline" className="w-full justify-start text-left font-normal">
                                                        <Calendar className="mr-2 h-4 w-4" />
                                                        {scheduledDate ? format(scheduledDate, "PPP") : "Pick a date"}
                                                    </Button>
                                                </PopoverTrigger>
                                                <PopoverContent className="w-auto p-0">
                                                    <CalendarComponent
                                                        mode="single"
                                                        selected={scheduledDate}
                                                        onSelect={setScheduledDate}
                                                        disabled={(date) => date <= new Date()}
                                                        initialFocus
                                                    />
                                                </PopoverContent>
                                            </Popover>
                                        </div>
                                        
                                        <div>
                                            <Label>Time</Label>
                                            <Input
                                                type="time"
                                                value={scheduledTime}
                                                onChange={(e) => setScheduledTime(e.target.value)}
                                            />
                                        </div>
                                        
                                        <div className="flex space-x-2">
                                            <Button 
                                                variant="outline" 
                                                onClick={() => setShowScheduleDialog(false)}
                                                className="flex-1"
                                            >
                                                Cancel
                                            </Button>
                                            <Button 
                                                onClick={handleSchedule}
                                                disabled={!scheduledDate || !scheduledTime}
                                                className="flex-1"
                                            >
                                                Schedule
                                            </Button>
                                        </div>
                                    </div>
                                </DialogContent>
                            </Dialog>
                        </div>

                        <div className="flex items-center space-x-2">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setIsExpanded(false);
                                    setContent('');
                                    setTitle('');
                                    setAttachments([]);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={() => handleSubmit()}
                                disabled={isSubmitting || !content.trim()}
                            >
                                {isSubmitting ? 'Sending...' : (
                                    <>
                                        <Send className="h-4 w-4 mr-2" />
                                        Send Now
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}