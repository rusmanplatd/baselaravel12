import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, DialogClose, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { 
  Template, 
  Plus, 
  Edit3, 
  Trash2, 
  Copy, 
  Star, 
  StarOff,
  Calendar,
  Clock,
  Users,
  Shield,
  Video,
  Mic,
  Settings,
  Save,
  Search,
  Filter,
  MoreHorizontal,
  Eye,
  EyeOff,
  BookOpen,
  Folder,
  Tag
} from 'lucide-react';
import apiService from '@/services/ApiService';

interface MeetingTemplate {
  id: string;
  name: string;
  description?: string;
  category_id: string;
  category?: MeetingTemplateCategory;
  is_public: boolean;
  is_featured: boolean;
  is_default: boolean;
  settings: {
    duration: number;
    max_participants?: number;
    auto_recording: boolean;
    recording_type?: string;
    waiting_room: boolean;
    password_required: boolean;
    participant_approval: boolean;
    screen_sharing_enabled: boolean;
    chat_enabled: boolean;
    file_sharing_enabled: boolean;
    breakout_rooms_enabled: boolean;
    layout_type: string;
    encryption_required: boolean;
    watermark_enabled: boolean;
    audio_settings: {
      mute_on_join: boolean;
      allow_unmute: boolean;
      background_noise_suppression: boolean;
    };
    video_settings: {
      camera_on_join: boolean;
      hd_video: boolean;
      virtual_backgrounds: boolean;
    };
    meeting_options: {
      lobby_enabled: boolean;
      join_before_host: boolean;
      host_video: boolean;
      participant_video: boolean;
    };
  };
  usage_count: number;
  created_by: string;
  created_by_name: string;
  created_at: string;
  updated_at: string;
}

interface MeetingTemplateCategory {
  id: string;
  name: string;
  description?: string;
  color: string;
  icon?: string;
  sort_order: number;
  is_system: boolean;
  created_at: string;
}

interface MeetingTemplatesManagerProps {
  onTemplateSelect?: (template: MeetingTemplate) => void;
  showCreateButton?: boolean;
  selectionMode?: boolean;
  className?: string;
}

const defaultTemplate: Partial<MeetingTemplate> = {
  name: '',
  description: '',
  is_public: false,
  is_featured: false,
  is_default: false,
  settings: {
    duration: 60,
    max_participants: 100,
    auto_recording: false,
    recording_type: 'cloud',
    waiting_room: false,
    password_required: false,
    participant_approval: false,
    screen_sharing_enabled: true,
    chat_enabled: true,
    file_sharing_enabled: true,
    breakout_rooms_enabled: false,
    layout_type: 'gallery',
    encryption_required: true,
    watermark_enabled: false,
    audio_settings: {
      mute_on_join: true,
      allow_unmute: true,
      background_noise_suppression: true,
    },
    video_settings: {
      camera_on_join: false,
      hd_video: true,
      virtual_backgrounds: true,
    },
    meeting_options: {
      lobby_enabled: false,
      join_before_host: false,
      host_video: true,
      participant_video: true,
    },
  },
};

export default function MeetingTemplatesManager({ 
  onTemplateSelect, 
  showCreateButton = true,
  selectionMode = false,
  className = '' 
}: MeetingTemplatesManagerProps) {
  const [templates, setTemplates] = useState<MeetingTemplate[]>([]);
  const [categories, setCategories] = useState<MeetingTemplateCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [showOnlyPublic, setShowOnlyPublic] = useState(false);
  const [showOnlyFeatured, setShowOnlyFeatured] = useState(false);
  const [showTemplateDialog, setShowTemplateDialog] = useState(false);
  const [showCategoryDialog, setShowCategoryDialog] = useState(false);
  const [editingTemplate, setEditingTemplate] = useState<MeetingTemplate | null>(null);
  const [editingCategory, setEditingCategory] = useState<MeetingTemplateCategory | null>(null);
  const [templateForm, setTemplateForm] = useState<Partial<MeetingTemplate>>(defaultTemplate);
  const [categoryForm, setCategoryForm] = useState<Partial<MeetingTemplateCategory>>({
    name: '',
    description: '',
    color: '#3b82f6',
    icon: 'folder',
    sort_order: 0,
    is_system: false
  });

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const [templatesResponse, categoriesResponse] = await Promise.all([
        apiService.get('/api/meeting-templates'),
        apiService.get('/api/meeting-template-categories')
      ]);

      if (templatesResponse.templates) {
        setTemplates(templatesResponse.templates);
      }

      if (categoriesResponse.categories) {
        setCategories(categoriesResponse.categories);
      }
    } catch (error) {
      console.error('Failed to load templates and categories:', error);
    } finally {
      setLoading(false);
    }
  };

  const saveTemplate = async () => {
    setSaving(true);
    try {
      if (editingTemplate) {
        const response = await apiService.patch(`/api/meeting-templates/${editingTemplate.id}`, templateForm);
        if (response.success) {
          await loadData();
          setShowTemplateDialog(false);
          resetTemplateForm();
        }
      } else {
        const response = await apiService.post('/api/meeting-templates', templateForm);
        if (response.success) {
          await loadData();
          setShowTemplateDialog(false);
          resetTemplateForm();
        }
      }
    } catch (error) {
      console.error('Failed to save template:', error);
    } finally {
      setSaving(false);
    }
  };

  const saveCategory = async () => {
    setSaving(true);
    try {
      if (editingCategory) {
        const response = await apiService.patch(`/api/meeting-template-categories/${editingCategory.id}`, categoryForm);
        if (response.success) {
          await loadData();
          setShowCategoryDialog(false);
          resetCategoryForm();
        }
      } else {
        const response = await apiService.post('/api/meeting-template-categories', categoryForm);
        if (response.success) {
          await loadData();
          setShowCategoryDialog(false);
          resetCategoryForm();
        }
      }
    } catch (error) {
      console.error('Failed to save category:', error);
    } finally {
      setSaving(false);
    }
  };

  const deleteTemplate = async (templateId: string) => {
    try {
      const response = await apiService.delete(`/api/meeting-templates/${templateId}`);
      if (response.success) {
        await loadData();
      }
    } catch (error) {
      console.error('Failed to delete template:', error);
    }
  };

  const deleteCategory = async (categoryId: string) => {
    try {
      const response = await apiService.delete(`/api/meeting-template-categories/${categoryId}`);
      if (response.success) {
        await loadData();
      }
    } catch (error) {
      console.error('Failed to delete category:', error);
    }
  };

  const duplicateTemplate = async (template: MeetingTemplate) => {
    try {
      const response = await apiService.post('/api/meeting-templates', {
        ...template,
        id: undefined,
        name: `${template.name} (Copy)`,
        is_default: false,
        is_featured: false,
        usage_count: 0
      });
      if (response.success) {
        await loadData();
      }
    } catch (error) {
      console.error('Failed to duplicate template:', error);
    }
  };

  const toggleTemplateFavorite = async (templateId: string, isFeatured: boolean) => {
    try {
      const response = await apiService.patch(`/api/meeting-templates/${templateId}`, {
        is_featured: !isFeatured
      });
      if (response.success) {
        await loadData();
      }
    } catch (error) {
      console.error('Failed to toggle template favorite:', error);
    }
  };

  const resetTemplateForm = () => {
    setTemplateForm(defaultTemplate);
    setEditingTemplate(null);
  };

  const resetCategoryForm = () => {
    setCategoryForm({
      name: '',
      description: '',
      color: '#3b82f6',
      icon: 'folder',
      sort_order: 0,
      is_system: false
    });
    setEditingCategory(null);
  };

  const openTemplateDialog = (template?: MeetingTemplate) => {
    if (template) {
      setEditingTemplate(template);
      setTemplateForm({ ...template });
    } else {
      resetTemplateForm();
    }
    setShowTemplateDialog(true);
  };

  const openCategoryDialog = (category?: MeetingTemplateCategory) => {
    if (category) {
      setEditingCategory(category);
      setCategoryForm({ ...category });
    } else {
      resetCategoryForm();
    }
    setShowCategoryDialog(true);
  };

  const updateTemplateSettings = (path: string, value: any) => {
    setTemplateForm(prev => {
      const newForm = { ...prev };
      const pathParts = path.split('.');
      let current: any = newForm;
      
      for (let i = 0; i < pathParts.length - 1; i++) {
        const part = pathParts[i];
        if (!current[part]) {
          current[part] = {};
        }
        current = current[part];
      }
      
      current[pathParts[pathParts.length - 1]] = value;
      return newForm;
    });
  };

  const filteredTemplates = templates.filter(template => {
    if (searchTerm && !template.name.toLowerCase().includes(searchTerm.toLowerCase())) {
      return false;
    }
    
    if (selectedCategory !== 'all' && template.category_id !== selectedCategory) {
      return false;
    }
    
    if (showOnlyPublic && !template.is_public) {
      return false;
    }
    
    if (showOnlyFeatured && !template.is_featured) {
      return false;
    }
    
    return true;
  });

  if (loading) {
    return (
      <Card className={className}>
        <CardContent className="flex items-center justify-center p-6">
          <div className="text-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900 mx-auto mb-4"></div>
            <p>Loading templates...</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className={className}>
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center space-x-2">
          <Template className="h-5 w-5" />
          <h2 className="text-xl font-semibold">Meeting Templates</h2>
          <Badge variant="secondary">{templates.length} templates</Badge>
        </div>
        {showCreateButton && (
          <div className="flex items-center space-x-2">
            <Button
              variant="outline"
              onClick={() => openCategoryDialog()}
            >
              <Plus className="h-4 w-4" />
              New Category
            </Button>
            <Button onClick={() => openTemplateDialog()}>
              <Plus className="h-4 w-4" />
              New Template
            </Button>
          </div>
        )}
      </div>

      <Tabs defaultValue="templates" className="space-y-4">
        <TabsList>
          <TabsTrigger value="templates">Templates</TabsTrigger>
          <TabsTrigger value="categories">Categories ({categories.length})</TabsTrigger>
        </TabsList>

        <TabsContent value="templates" className="space-y-4">
          {/* Filters */}
          <Card>
            <CardContent className="p-4">
              <div className="flex flex-col sm:flex-row gap-4">
                <div className="flex-1">
                  <div className="relative">
                    <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      placeholder="Search templates..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="pl-8"
                    />
                  </div>
                </div>
                <div className="flex items-center space-x-2">
                  <Select value={selectedCategory} onValueChange={setSelectedCategory}>
                    <SelectTrigger className="w-48">
                      <SelectValue placeholder="All Categories" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Categories</SelectItem>
                      {categories.map(category => (
                        <SelectItem key={category.id} value={category.id}>
                          {category.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <div className="flex items-center space-x-2">
                    <Switch
                      id="public-only"
                      checked={showOnlyPublic}
                      onCheckedChange={setShowOnlyPublic}
                    />
                    <Label htmlFor="public-only" className="text-sm">Public only</Label>
                  </div>
                  <div className="flex items-center space-x-2">
                    <Switch
                      id="featured-only"
                      checked={showOnlyFeatured}
                      onCheckedChange={setShowOnlyFeatured}
                    />
                    <Label htmlFor="featured-only" className="text-sm">Featured only</Label>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Templates Grid */}
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {filteredTemplates.map(template => (
              <Card key={template.id} className="hover:shadow-lg transition-shadow">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between">
                    <div className="space-y-1">
                      <CardTitle className="text-base flex items-center space-x-2">
                        <span>{template.name}</span>
                        {template.is_default && <Badge variant="outline">Default</Badge>}
                        {template.is_featured && <Star className="h-4 w-4 text-yellow-500" />}
                      </CardTitle>
                      <CardDescription className="text-sm">
                        {template.description || 'No description'}
                      </CardDescription>
                    </div>
                    <div className="flex items-center space-x-1">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => toggleTemplateFavorite(template.id, template.is_featured)}
                      >
                        {template.is_featured ? (
                          <Star className="h-4 w-4 text-yellow-500" />
                        ) : (
                          <StarOff className="h-4 w-4" />
                        )}
                      </Button>
                      <Dialog>
                        <DialogTrigger asChild>
                          <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>
                        </DialogTrigger>
                        <DialogContent>
                          <DialogHeader>
                            <DialogTitle>{template.name}</DialogTitle>
                            <DialogDescription>Template actions</DialogDescription>
                          </DialogHeader>
                          <div className="grid gap-2">
                            {selectionMode ? (
                              <Button 
                                onClick={() => onTemplateSelect?.(template)}
                                className="w-full"
                              >
                                <Template className="h-4 w-4" />
                                Use This Template
                              </Button>
                            ) : (
                              <Button 
                                variant="outline"
                                onClick={() => onTemplateSelect?.(template)}
                                className="w-full"
                              >
                                <Eye className="h-4 w-4" />
                                Preview Template
                              </Button>
                            )}
                            <Button 
                              variant="outline"
                              onClick={() => openTemplateDialog(template)}
                              className="w-full"
                            >
                              <Edit3 className="h-4 w-4" />
                              Edit Template
                            </Button>
                            <Button 
                              variant="outline"
                              onClick={() => duplicateTemplate(template)}
                              className="w-full"
                            >
                              <Copy className="h-4 w-4" />
                              Duplicate Template
                            </Button>
                            <AlertDialog>
                              <AlertDialogTrigger asChild>
                                <Button variant="destructive" className="w-full">
                                  <Trash2 className="h-4 w-4" />
                                  Delete Template
                                </Button>
                              </AlertDialogTrigger>
                              <AlertDialogContent>
                                <AlertDialogHeader>
                                  <AlertDialogTitle>Delete Template?</AlertDialogTitle>
                                  <AlertDialogDescription>
                                    This action cannot be undone. This will permanently delete the template "{template.name}".
                                  </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                                  <AlertDialogAction onClick={() => deleteTemplate(template.id)}>
                                    Delete
                                  </AlertDialogAction>
                                </AlertDialogFooter>
                              </AlertDialogContent>
                            </AlertDialog>
                          </div>
                          <DialogClose />
                        </DialogContent>
                      </Dialog>
                    </div>
                  </div>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex items-center justify-between text-sm">
                    <span>Category:</span>
                    <Badge 
                      variant="secondary"
                      style={{ backgroundColor: template.category?.color + '20', color: template.category?.color }}
                    >
                      {template.category?.name || 'Uncategorized'}
                    </Badge>
                  </div>
                  
                  <div className="grid grid-cols-2 gap-2 text-sm">
                    <div className="flex items-center space-x-2">
                      <Clock className="h-3 w-3" />
                      <span>{template.settings.duration} min</span>
                    </div>
                    <div className="flex items-center space-x-2">
                      <Users className="h-3 w-3" />
                      <span>{template.settings.max_participants || '∞'}</span>
                    </div>
                    <div className="flex items-center space-x-2">
                      <Video className="h-3 w-3" />
                      <span>{template.settings.auto_recording ? 'Rec' : 'No Rec'}</span>
                    </div>
                    <div className="flex items-center space-x-2">
                      <Shield className="h-3 w-3" />
                      <span>{template.settings.password_required ? 'Secured' : 'Open'}</span>
                    </div>
                  </div>

                  <div className="flex items-center justify-between text-xs text-gray-500">
                    <span>Used {template.usage_count} times</span>
                    <span>by {template.created_by_name}</span>
                  </div>

                  {template.is_public ? (
                    <Badge variant="outline" className="w-full justify-center">
                      <Eye className="h-3 w-3 mr-1" />
                      Public
                    </Badge>
                  ) : (
                    <Badge variant="secondary" className="w-full justify-center">
                      <EyeOff className="h-3 w-3 mr-1" />
                      Private
                    </Badge>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>

          {filteredTemplates.length === 0 && (
            <Card>
              <CardContent className="flex items-center justify-center p-8">
                <div className="text-center space-y-2">
                  <Template className="h-12 w-12 text-gray-400 mx-auto" />
                  <h3 className="font-medium">No templates found</h3>
                  <p className="text-sm text-gray-500">
                    {searchTerm ? 'Try adjusting your search or filters' : 'Create your first meeting template to get started'}
                  </p>
                  {!searchTerm && showCreateButton && (
                    <Button onClick={() => openTemplateDialog()} className="mt-4">
                      <Plus className="h-4 w-4" />
                      Create Template
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        <TabsContent value="categories" className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {categories.map(category => (
              <Card key={category.id}>
                <CardHeader>
                  <div className="flex items-start justify-between">
                    <div className="flex items-center space-x-2">
                      <div 
                        className="w-4 h-4 rounded"
                        style={{ backgroundColor: category.color }}
                      />
                      <CardTitle className="text-base">{category.name}</CardTitle>
                      {category.is_system && <Badge variant="outline">System</Badge>}
                    </div>
                    {!category.is_system && (
                      <div className="flex items-center space-x-1">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => openCategoryDialog(category)}
                        >
                          <Edit3 className="h-4 w-4" />
                        </Button>
                        <AlertDialog>
                          <AlertDialogTrigger asChild>
                            <Button variant="ghost" size="sm">
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </AlertDialogTrigger>
                          <AlertDialogContent>
                            <AlertDialogHeader>
                              <AlertDialogTitle>Delete Category?</AlertDialogTitle>
                              <AlertDialogDescription>
                                This will delete the category "{category.name}" and may affect templates using this category.
                              </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                              <AlertDialogCancel>Cancel</AlertDialogCancel>
                              <AlertDialogAction onClick={() => deleteCategory(category.id)}>
                                Delete
                              </AlertDialogAction>
                            </AlertDialogFooter>
                          </AlertDialogContent>
                        </AlertDialog>
                      </div>
                    )}
                  </div>
                </CardHeader>
                <CardContent>
                  <p className="text-sm text-gray-600 mb-3">
                    {category.description || 'No description'}
                  </p>
                  <div className="text-xs text-gray-500">
                    {templates.filter(t => t.category_id === category.id).length} templates
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>
      </Tabs>

      {/* Template Dialog */}
      <Dialog open={showTemplateDialog} onOpenChange={setShowTemplateDialog}>
        <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              {editingTemplate ? `Edit "${editingTemplate.name}"` : 'Create New Template'}
            </DialogTitle>
            <DialogDescription>
              Configure meeting settings that can be reused for future meetings.
            </DialogDescription>
          </DialogHeader>
          
          <Tabs defaultValue="basic" className="w-full">
            <TabsList className="grid w-full grid-cols-4">
              <TabsTrigger value="basic">Basic Info</TabsTrigger>
              <TabsTrigger value="meeting">Meeting Settings</TabsTrigger>
              <TabsTrigger value="security">Security</TabsTrigger>
              <TabsTrigger value="media">Audio & Video</TabsTrigger>
            </TabsList>

            <TabsContent value="basic" className="space-y-4">
              <div className="grid gap-4">
                <div>
                  <Label htmlFor="template-name">Template Name</Label>
                  <Input
                    id="template-name"
                    value={templateForm.name || ''}
                    onChange={(e) => setTemplateForm({...templateForm, name: e.target.value})}
                    placeholder="Enter template name"
                  />
                </div>

                <div>
                  <Label htmlFor="template-description">Description</Label>
                  <Textarea
                    id="template-description"
                    value={templateForm.description || ''}
                    onChange={(e) => setTemplateForm({...templateForm, description: e.target.value})}
                    placeholder="Describe this template's purpose"
                    rows={3}
                  />
                </div>

                <div>
                  <Label htmlFor="template-category">Category</Label>
                  <Select 
                    value={templateForm.category_id || ''} 
                    onValueChange={(value) => setTemplateForm({...templateForm, category_id: value})}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select category" />
                    </SelectTrigger>
                    <SelectContent>
                      {categories.map(category => (
                        <SelectItem key={category.id} value={category.id}>
                          {category.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="grid grid-cols-3 gap-4">
                  <div className="flex items-center space-x-2">
                    <Switch
                      id="is-public"
                      checked={templateForm.is_public || false}
                      onCheckedChange={(checked) => setTemplateForm({...templateForm, is_public: checked})}
                    />
                    <Label htmlFor="is-public">Public Template</Label>
                  </div>

                  <div className="flex items-center space-x-2">
                    <Switch
                      id="is-featured"
                      checked={templateForm.is_featured || false}
                      onCheckedChange={(checked) => setTemplateForm({...templateForm, is_featured: checked})}
                    />
                    <Label htmlFor="is-featured">Featured</Label>
                  </div>

                  <div className="flex items-center space-x-2">
                    <Switch
                      id="is-default"
                      checked={templateForm.is_default || false}
                      onCheckedChange={(checked) => setTemplateForm({...templateForm, is_default: checked})}
                    />
                    <Label htmlFor="is-default">Default Template</Label>
                  </div>
                </div>
              </div>
            </TabsContent>

            <TabsContent value="meeting" className="space-y-4">
              <div className="grid gap-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="duration">Duration (minutes)</Label>
                    <Input
                      id="duration"
                      type="number"
                      min="1"
                      max="480"
                      value={templateForm.settings?.duration || 60}
                      onChange={(e) => updateTemplateSettings('settings.duration', parseInt(e.target.value))}
                    />
                  </div>

                  <div>
                    <Label htmlFor="max-participants">Max Participants</Label>
                    <Input
                      id="max-participants"
                      type="number"
                      min="2"
                      max="1000"
                      value={templateForm.settings?.max_participants || ''}
                      onChange={(e) => updateTemplateSettings('settings.max_participants', e.target.value ? parseInt(e.target.value) : undefined)}
                      placeholder="No limit"
                    />
                  </div>
                </div>

                <div>
                  <Label htmlFor="layout-type">Default Layout</Label>
                  <Select 
                    value={templateForm.settings?.layout_type || 'gallery'} 
                    onValueChange={(value) => updateTemplateSettings('settings.layout_type', value)}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="gallery">Gallery View</SelectItem>
                      <SelectItem value="speaker">Speaker View</SelectItem>
                      <SelectItem value="presentation">Presentation Mode</SelectItem>
                      <SelectItem value="custom">Custom Layout</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="flex items-center space-x-2">
                    <Switch
                      id="screen-sharing"
                      checked={templateForm.settings?.screen_sharing_enabled || false}
                      onCheckedChange={(checked) => updateTemplateSettings('settings.screen_sharing_enabled', checked)}
                    />
                    <Label htmlFor="screen-sharing">Enable Screen Sharing</Label>
                  </div>

                  <div className="flex items-center space-x-2">
                    <Switch
                      id="chat-enabled"
                      checked={templateForm.settings?.chat_enabled || false}
                      onCheckedChange={(checked) => updateTemplateSettings('settings.chat_enabled', checked)}
                    />
                    <Label htmlFor="chat-enabled">Enable Chat</Label>
                  </div>

                  <div className="flex items-center space-x-2">
                    <Switch
                      id="file-sharing"
                      checked={templateForm.settings?.file_sharing_enabled || false}
                      onCheckedChange={(checked) => updateTemplateSettings('settings.file_sharing_enabled', checked)}
                    />
                    <Label htmlFor="file-sharing">Enable File Sharing</Label>
                  </div>

                  <div className="flex items-center space-x-2">
                    <Switch
                      id="breakout-rooms"
                      checked={templateForm.settings?.breakout_rooms_enabled || false}
                      onCheckedChange={(checked) => updateTemplateSettings('settings.breakout_rooms_enabled', checked)}
                    />
                    <Label htmlFor="breakout-rooms">Enable Breakout Rooms</Label>
                  </div>
                </div>

                <Separator />

                <div className="space-y-4">
                  <h4 className="font-medium">Recording Settings</h4>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="flex items-center space-x-2">
                      <Switch
                        id="auto-recording"
                        checked={templateForm.settings?.auto_recording || false}
                        onCheckedChange={(checked) => updateTemplateSettings('settings.auto_recording', checked)}
                      />
                      <Label htmlFor="auto-recording">Auto-start Recording</Label>
                    </div>

                    <div>
                      <Label htmlFor="recording-type">Recording Type</Label>
                      <Select 
                        value={templateForm.settings?.recording_type || 'cloud'} 
                        onValueChange={(value) => updateTemplateSettings('settings.recording_type', value)}
                        disabled={!templateForm.settings?.auto_recording}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="cloud">Cloud Recording</SelectItem>
                          <SelectItem value="local">Local Recording</SelectItem>
                          <SelectItem value="both">Both</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                </div>
              </div>
            </TabsContent>

            <TabsContent value="security" className="space-y-4">
              <div className="grid gap-4">
                <div className="grid grid-cols-2 gap-4">
                  <div className="flex items-center space-x-2">
                    <Switch
                      id="waiting-room"
                      checked={templateForm.settings?.waiting_room || false}
                      onCheckedChange={(checked) => updateTemplateSettings('settings.waiting_room', checked)}
                    />
                    <Label htmlFor="waiting-room">Enable Waiting Room</Label>
                  </div>

                  <div className="flex items-center space-x-2">
                    <Switch
                      id="password-required"
                      checked={templateForm.settings?.password_required || false}
                      onCheckedChange={(checked) => updateTemplateSettings('settings.password_required', checked)}
                    />
                    <Label htmlFor="password-required">Require Password</Label>
                  </div>

                  <div className="flex items-center space-x-2">
                    <Switch
                      id="participant-approval"
                      checked={templateForm.settings?.participant_approval || false}
                      onCheckedChange={(checked) => updateTemplateSettings('settings.participant_approval', checked)}
                    />
                    <Label htmlFor="participant-approval">Require Approval</Label>
                  </div>

                  <div className="flex items-center space-x-2">
                    <Switch
                      id="encryption-required"
                      checked={templateForm.settings?.encryption_required || false}
                      onCheckedChange={(checked) => updateTemplateSettings('settings.encryption_required', checked)}
                    />
                    <Label htmlFor="encryption-required">Require Encryption</Label>
                  </div>

                  <div className="flex items-center space-x-2">
                    <Switch
                      id="watermark-enabled"
                      checked={templateForm.settings?.watermark_enabled || false}
                      onCheckedChange={(checked) => updateTemplateSettings('settings.watermark_enabled', checked)}
                    />
                    <Label htmlFor="watermark-enabled">Enable Watermark</Label>
                  </div>
                </div>

                <Separator />

                <div className="space-y-4">
                  <h4 className="font-medium">Meeting Options</h4>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="flex items-center space-x-2">
                      <Switch
                        id="lobby-enabled"
                        checked={templateForm.settings?.meeting_options?.lobby_enabled || false}
                        onCheckedChange={(checked) => updateTemplateSettings('settings.meeting_options.lobby_enabled', checked)}
                      />
                      <Label htmlFor="lobby-enabled">Enable Lobby</Label>
                    </div>

                    <div className="flex items-center space-x-2">
                      <Switch
                        id="join-before-host"
                        checked={templateForm.settings?.meeting_options?.join_before_host || false}
                        onCheckedChange={(checked) => updateTemplateSettings('settings.meeting_options.join_before_host', checked)}
                      />
                      <Label htmlFor="join-before-host">Join Before Host</Label>
                    </div>
                  </div>
                </div>
              </div>
            </TabsContent>

            <TabsContent value="media" className="space-y-4">
              <div className="grid gap-6">
                <div className="space-y-4">
                  <h4 className="font-medium flex items-center space-x-2">
                    <Mic className="h-4 w-4" />
                    <span>Audio Settings</span>
                  </h4>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="flex items-center space-x-2">
                      <Switch
                        id="mute-on-join"
                        checked={templateForm.settings?.audio_settings?.mute_on_join || false}
                        onCheckedChange={(checked) => updateTemplateSettings('settings.audio_settings.mute_on_join', checked)}
                      />
                      <Label htmlFor="mute-on-join">Mute on Join</Label>
                    </div>

                    <div className="flex items-center space-x-2">
                      <Switch
                        id="allow-unmute"
                        checked={templateForm.settings?.audio_settings?.allow_unmute || false}
                        onCheckedChange={(checked) => updateTemplateSettings('settings.audio_settings.allow_unmute', checked)}
                      />
                      <Label htmlFor="allow-unmute">Allow Self Unmute</Label>
                    </div>

                    <div className="flex items-center space-x-2 col-span-2">
                      <Switch
                        id="noise-suppression"
                        checked={templateForm.settings?.audio_settings?.background_noise_suppression || false}
                        onCheckedChange={(checked) => updateTemplateSettings('settings.audio_settings.background_noise_suppression', checked)}
                      />
                      <Label htmlFor="noise-suppression">Background Noise Suppression</Label>
                    </div>
                  </div>
                </div>

                <Separator />

                <div className="space-y-4">
                  <h4 className="font-medium flex items-center space-x-2">
                    <Video className="h-4 w-4" />
                    <span>Video Settings</span>
                  </h4>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="flex items-center space-x-2">
                      <Switch
                        id="camera-on-join"
                        checked={templateForm.settings?.video_settings?.camera_on_join || false}
                        onCheckedChange={(checked) => updateTemplateSettings('settings.video_settings.camera_on_join', checked)}
                      />
                      <Label htmlFor="camera-on-join">Camera on Join</Label>
                    </div>

                    <div className="flex items-center space-x-2">
                      <Switch
                        id="hd-video"
                        checked={templateForm.settings?.video_settings?.hd_video || false}
                        onCheckedChange={(checked) => updateTemplateSettings('settings.video_settings.hd_video', checked)}
                      />
                      <Label htmlFor="hd-video">Enable HD Video</Label>
                    </div>

                    <div className="flex items-center space-x-2 col-span-2">
                      <Switch
                        id="virtual-backgrounds"
                        checked={templateForm.settings?.video_settings?.virtual_backgrounds || false}
                        onCheckedChange={(checked) => updateTemplateSettings('settings.video_settings.virtual_backgrounds', checked)}
                      />
                      <Label htmlFor="virtual-backgrounds">Allow Virtual Backgrounds</Label>
                    </div>
                  </div>
                </div>

                <Separator />

                <div className="space-y-4">
                  <h4 className="font-medium">Host & Participant Defaults</h4>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="flex items-center space-x-2">
                      <Switch
                        id="host-video"
                        checked={templateForm.settings?.meeting_options?.host_video || false}
                        onCheckedChange={(checked) => updateTemplateSettings('settings.meeting_options.host_video', checked)}
                      />
                      <Label htmlFor="host-video">Host Video On</Label>
                    </div>

                    <div className="flex items-center space-x-2">
                      <Switch
                        id="participant-video"
                        checked={templateForm.settings?.meeting_options?.participant_video || false}
                        onCheckedChange={(checked) => updateTemplateSettings('settings.meeting_options.participant_video', checked)}
                      />
                      <Label htmlFor="participant-video">Participant Video On</Label>
                    </div>
                  </div>
                </div>
              </div>
            </TabsContent>
          </Tabs>

          <DialogFooter>
            <Button variant="outline" onClick={() => setShowTemplateDialog(false)}>
              Cancel
            </Button>
            <Button onClick={saveTemplate} disabled={saving || !templateForm.name?.trim()}>
              <Save className="h-4 w-4" />
              {editingTemplate ? 'Update Template' : 'Create Template'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Category Dialog */}
      <Dialog open={showCategoryDialog} onOpenChange={setShowCategoryDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {editingCategory ? `Edit "${editingCategory.name}"` : 'Create New Category'}
            </DialogTitle>
            <DialogDescription>
              Organize your meeting templates with categories.
            </DialogDescription>
          </DialogHeader>
          
          <div className="space-y-4">
            <div>
              <Label htmlFor="category-name">Category Name</Label>
              <Input
                id="category-name"
                value={categoryForm.name || ''}
                onChange={(e) => setCategoryForm({...categoryForm, name: e.target.value})}
                placeholder="Enter category name"
              />
            </div>

            <div>
              <Label htmlFor="category-description">Description</Label>
              <Textarea
                id="category-description"
                value={categoryForm.description || ''}
                onChange={(e) => setCategoryForm({...categoryForm, description: e.target.value})}
                placeholder="Describe this category"
                rows={2}
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="category-color">Color</Label>
                <Input
                  id="category-color"
                  type="color"
                  value={categoryForm.color || '#3b82f6'}
                  onChange={(e) => setCategoryForm({...categoryForm, color: e.target.value})}
                />
              </div>

              <div>
                <Label htmlFor="sort-order">Sort Order</Label>
                <Input
                  id="sort-order"
                  type="number"
                  min="0"
                  value={categoryForm.sort_order || 0}
                  onChange={(e) => setCategoryForm({...categoryForm, sort_order: parseInt(e.target.value)})}
                />
              </div>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setShowCategoryDialog(false)}>
              Cancel
            </Button>
            <Button onClick={saveCategory} disabled={saving || !categoryForm.name?.trim()}>
              <Save className="h-4 w-4" />
              {editingCategory ? 'Update Category' : 'Create Category'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}