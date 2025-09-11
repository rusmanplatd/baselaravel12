import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, AlertCircle } from 'lucide-react';
import apiService from '@/services/ApiService';

interface Organization {
    id: string;
    name: string;
    organization_code: string;
}

interface CreateProjectModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    organizations: Organization[];
    onProjectCreated?: (project: any) => void;
}

interface ProjectFormData {
    title: string;
    description: string;
    visibility: 'public' | 'private';
    organization_id: string;
    settings: {
        auto_close_items: boolean;
        allow_public_items: boolean;
        default_item_type: 'issue' | 'pull_request' | 'draft_issue';
    };
}

export function CreateProjectModal({ 
    open, 
    onOpenChange, 
    organizations, 
    onProjectCreated 
}: CreateProjectModalProps) {
    const [formData, setFormData] = useState<ProjectFormData>({
        title: '',
        description: '',
        visibility: 'private',
        organization_id: '',
        settings: {
            auto_close_items: false,
            allow_public_items: false,
            default_item_type: 'issue'
        }
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        setErrors({});

        try {
            const response = await apiService.post('/api/v1/projects', formData);
            onProjectCreated?.(response);
            onOpenChange(false);
            
            // Reset form
            setFormData({
                title: '',
                description: '',
                visibility: 'private',
                organization_id: '',
                settings: {
                    auto_close_items: false,
                    allow_public_items: false,
                    default_item_type: 'issue'
                }
            });
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                setErrors({ general: 'Failed to create project. Please try again.' });
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const updateFormData = (field: string, value: any) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));
        
        // Clear error for this field
        if (errors[field]) {
            setErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    const updateSettings = (setting: string, value: any) => {
        setFormData(prev => ({
            ...prev,
            settings: {
                ...prev.settings,
                [setting]: value
            }
        }));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>Create New Project</DialogTitle>
                    <DialogDescription>
                        Create a new project to organize and track your work.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {errors.general && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{errors.general}</AlertDescription>
                        </Alert>
                    )}

                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Project Title *</Label>
                            <Input
                                id="title"
                                value={formData.title}
                                onChange={(e) => updateFormData('title', e.target.value)}
                                placeholder="Enter project title"
                                className={errors.title ? 'border-destructive' : ''}
                            />
                            {errors.title && (
                                <p className="text-sm text-destructive">{errors.title}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={formData.description}
                                onChange={(e) => updateFormData('description', e.target.value)}
                                placeholder="Describe your project (optional)"
                                rows={3}
                                className={errors.description ? 'border-destructive' : ''}
                            />
                            {errors.description && (
                                <p className="text-sm text-destructive">{errors.description}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="organization">Organization *</Label>
                            <Select 
                                value={formData.organization_id} 
                                onValueChange={(value) => updateFormData('organization_id', value)}
                            >
                                <SelectTrigger className={errors.organization_id ? 'border-destructive' : ''}>
                                    <SelectValue placeholder="Select an organization" />
                                </SelectTrigger>
                                <SelectContent>
                                    {organizations.map((org) => (
                                        <SelectItem key={org.id} value={org.id}>
                                            {org.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.organization_id && (
                                <p className="text-sm text-destructive">{errors.organization_id}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="visibility">Visibility *</Label>
                            <Select 
                                value={formData.visibility} 
                                onValueChange={(value: 'public' | 'private') => updateFormData('visibility', value)}
                            >
                                <SelectTrigger className={errors.visibility ? 'border-destructive' : ''}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="private">Private - Only members can access</SelectItem>
                                    <SelectItem value="public">Public - Anyone in organization can view</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.visibility && (
                                <p className="text-sm text-destructive">{errors.visibility}</p>
                            )}
                        </div>

                        <div className="space-y-4">
                            <Label className="text-base font-medium">Project Settings</Label>
                            
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label htmlFor="auto-close">Auto-close completed items</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Automatically archive items when marked as done
                                        </p>
                                    </div>
                                    <Switch
                                        id="auto-close"
                                        checked={formData.settings.auto_close_items}
                                        onCheckedChange={(checked) => updateSettings('auto_close_items', checked)}
                                    />
                                </div>

                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label htmlFor="public-items">Allow public items</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Allow items to be visible to non-members
                                        </p>
                                    </div>
                                    <Switch
                                        id="public-items"
                                        checked={formData.settings.allow_public_items}
                                        onCheckedChange={(checked) => updateSettings('allow_public_items', checked)}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="default-type">Default Item Type</Label>
                                    <Select 
                                        value={formData.settings.default_item_type} 
                                        onValueChange={(value: 'issue' | 'pull_request' | 'draft_issue') => 
                                            updateSettings('default_item_type', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="issue">Issue</SelectItem>
                                            <SelectItem value="draft_issue">Draft Issue</SelectItem>
                                            <SelectItem value="pull_request">Pull Request</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button 
                            type="button" 
                            variant="outline" 
                            onClick={() => onOpenChange(false)}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Create Project
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}