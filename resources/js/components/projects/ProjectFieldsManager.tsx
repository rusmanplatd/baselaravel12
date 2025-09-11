import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { 
    Plus, 
    Type, 
    Hash, 
    Calendar, 
    List, 
    Users as UsersIcon, 
    Archive,
    MoreHorizontal,
    Edit3,
    Trash2,
    ArrowUp,
    ArrowDown
} from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import apiService from '@/services/ApiService';

interface ProjectField {
    id: string;
    name: string;
    type: string;
    options?: string[];
    is_required: boolean;
    is_system: boolean;
    sort_order: number;
}

interface ProjectFieldsManagerProps {
    projectId: string;
    fields: ProjectField[];
    canAdmin: boolean;
    onFieldsUpdate: (fields: ProjectField[]) => void;
}

const FIELD_TYPES = [
    { value: 'text', label: 'Text', icon: Type },
    { value: 'number', label: 'Number', icon: Hash },
    { value: 'date', label: 'Date', icon: Calendar },
    { value: 'single_select', label: 'Single Select', icon: List },
    { value: 'multi_select', label: 'Multi Select', icon: List },
    { value: 'assignees', label: 'Assignees', icon: UsersIcon },
];

export function ProjectFieldsManager({ 
    projectId, 
    fields, 
    canAdmin, 
    onFieldsUpdate 
}: ProjectFieldsManagerProps) {
    const [showAddDialog, setShowAddDialog] = useState(false);
    const [fieldName, setFieldName] = useState('');
    const [fieldType, setFieldType] = useState('text');
    const [fieldOptions, setFieldOptions] = useState<string[]>(['']);
    const [isRequired, setIsRequired] = useState(false);
    const [loading, setLoading] = useState(false);

    const handleAddField = async () => {
        if (!fieldName.trim()) return;

        setLoading(true);
        try {
            const fieldData = {
                name: fieldName.trim(),
                type: fieldType,
                is_required: isRequired,
                ...(fieldType === 'single_select' || fieldType === 'multi_select' ? {
                    options: fieldOptions.filter(opt => opt.trim() !== '')
                } : {})
            };

            await apiService.post(`/api/v1/projects/${projectId}/fields`, fieldData);

            // Refresh fields list
            const updatedProject = await apiService.get(`/api/v1/projects/${projectId}`);
            onFieldsUpdate(updatedProject.fields);

            setShowAddDialog(false);
            resetForm();
        } catch (error: any) {
            console.error('Failed to add field:', error);
            if (error.response?.data?.error) {
                alert(error.response.data.error);
            }
        } finally {
            setLoading(false);
        }
    };

    const resetForm = () => {
        setFieldName('');
        setFieldType('text');
        setFieldOptions(['']);
        setIsRequired(false);
    };

    const addOption = () => {
        setFieldOptions([...fieldOptions, '']);
    };

    const updateOption = (index: number, value: string) => {
        const newOptions = [...fieldOptions];
        newOptions[index] = value;
        setFieldOptions(newOptions);
    };

    const removeOption = (index: number) => {
        if (fieldOptions.length > 1) {
            setFieldOptions(fieldOptions.filter((_, i) => i !== index));
        }
    };

    const getFieldTypeIcon = (type: string) => {
        const fieldType = FIELD_TYPES.find(ft => ft.value === type);
        return fieldType?.icon || Type;
    };

    const getFieldTypeBadge = (type: string) => {
        const fieldType = FIELD_TYPES.find(ft => ft.value === type);
        const Icon = fieldType?.icon || Type;
        
        return (
            <Badge variant="secondary" className="flex items-center gap-1">
                <Icon className="h-3 w-3" />
                {fieldType?.label || type}
            </Badge>
        );
    };

    const customFields = fields.filter(field => !field.is_system);
    const systemFields = fields.filter(field => field.is_system);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-medium">Project Fields</h3>
                {canAdmin && (
                    <Dialog open={showAddDialog} onOpenChange={setShowAddDialog}>
                        <DialogTrigger asChild>
                            <Button size="sm">
                                <Plus className="h-4 w-4 mr-2" />
                                Add Field
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Add Custom Field</DialogTitle>
                                <DialogDescription>
                                    Create a custom field to track additional information for project items.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="field-name">Field Name</Label>
                                    <Input
                                        id="field-name"
                                        placeholder="Enter field name..."
                                        value={fieldName}
                                        onChange={(e) => setFieldName(e.target.value)}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="field-type">Field Type</Label>
                                    <Select value={fieldType} onValueChange={setFieldType}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {FIELD_TYPES.map((type) => {
                                                const Icon = type.icon;
                                                return (
                                                    <SelectItem key={type.value} value={type.value}>
                                                        <div className="flex items-center gap-2">
                                                            <Icon className="h-4 w-4" />
                                                            {type.label}
                                                        </div>
                                                    </SelectItem>
                                                );
                                            })}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {(fieldType === 'single_select' || fieldType === 'multi_select') && (
                                    <div className="space-y-2">
                                        <Label>Options</Label>
                                        <div className="space-y-2">
                                            {fieldOptions.map((option, index) => (
                                                <div key={index} className="flex items-center gap-2">
                                                    <Input
                                                        placeholder={`Option ${index + 1}`}
                                                        value={option}
                                                        onChange={(e) => updateOption(index, e.target.value)}
                                                    />
                                                    {fieldOptions.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => removeOption(index)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            ))}
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={addOption}
                                                className="w-full"
                                            >
                                                <Plus className="h-4 w-4 mr-2" />
                                                Add Option
                                            </Button>
                                        </div>
                                    </div>
                                )}

                                <div className="flex items-center space-x-2">
                                    <Switch 
                                        id="required" 
                                        checked={isRequired}
                                        onCheckedChange={setIsRequired}
                                    />
                                    <Label htmlFor="required">Required field</Label>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button 
                                    variant="outline" 
                                    onClick={() => setShowAddDialog(false)}
                                    disabled={loading}
                                >
                                    Cancel
                                </Button>
                                <Button 
                                    onClick={handleAddField} 
                                    disabled={!fieldName.trim() || loading}
                                >
                                    {loading ? 'Adding...' : 'Add Field'}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </div>

            {/* System Fields */}
            <div className="space-y-3">
                <h4 className="text-sm font-medium text-muted-foreground">System Fields</h4>
                {systemFields.map((field) => (
                    <Card key={field.id}>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <div className="flex items-center gap-2 mb-1">
                                        <h4 className="font-medium">{field.name}</h4>
                                        {field.is_required && (
                                            <Badge variant="destructive" className="text-xs">Required</Badge>
                                        )}
                                        <Badge variant="outline" className="text-xs">System</Badge>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {getFieldTypeBadge(field.type)}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Custom Fields */}
            {customFields.length > 0 && (
                <div className="space-y-3">
                    <h4 className="text-sm font-medium text-muted-foreground">Custom Fields</h4>
                    {customFields.map((field) => (
                        <Card key={field.id}>
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="flex items-center gap-2 mb-1">
                                            <h4 className="font-medium">{field.name}</h4>
                                            {field.is_required && (
                                                <Badge variant="destructive" className="text-xs">Required</Badge>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {getFieldTypeBadge(field.type)}
                                            {field.options && field.options.length > 0 && (
                                                <span className="text-xs text-muted-foreground">
                                                    ({field.options.length} options)
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    {canAdmin && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem>
                                                    <Edit3 className="h-4 w-4 mr-2" />
                                                    Edit Field
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem>
                                                    <ArrowUp className="h-4 w-4 mr-2" />
                                                    Move Up
                                                </DropdownMenuItem>
                                                <DropdownMenuItem>
                                                    <ArrowDown className="h-4 w-4 mr-2" />
                                                    Move Down
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem className="text-destructive">
                                                    <Trash2 className="h-4 w-4 mr-2" />
                                                    Delete Field
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {customFields.length === 0 && (
                <Card>
                    <CardContent className="p-8 text-center">
                        <div className="mx-auto w-12 h-12 mb-4 text-muted-foreground/20">
                            <Archive className="w-full h-full" />
                        </div>
                        <h3 className="text-sm font-semibold mb-2">No custom fields yet</h3>
                        <p className="text-sm text-muted-foreground mb-4">
                            Add custom fields to track additional information for your project items.
                        </p>
                        {canAdmin && (
                            <Button 
                                variant="outline" 
                                size="sm"
                                onClick={() => setShowAddDialog(true)}
                            >
                                <Plus className="h-4 w-4 mr-2" />
                                Add Custom Field
                            </Button>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}