import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { 
  Grid3X3, 
  Users, 
  Presentation, 
  Settings, 
  Monitor,
  Palette,
  Accessibility,
  Zap,
  RotateCcw,
  Save,
  Eye,
  EyeOff
} from 'lucide-react';
import apiService from '@/services/ApiService';

interface Layout {
  id: string;
  name: string;
  type: 'grid' | 'speaker' | 'presentation' | 'custom' | 'gallery' | 'focus' | 'sidebar' | 'pip';
  is_default: boolean;
  is_active: boolean;
  config?: any;
}

interface LayoutPreset {
  id: string;
  preset_name: string;
  description: string;
  category: string;
  layout_type: string;
  thumbnail_url?: string;
  is_featured: boolean;
}

interface MeetingLayoutManagerProps {
  meetingId: string;
  onLayoutChange?: (layout: Layout) => void;
  isHost?: boolean;
}

const layoutTypeIcons = {
  grid: Grid3X3,
  speaker: Users,
  presentation: Presentation,
  custom: Settings,
  gallery: Monitor,
  focus: Eye,
  sidebar: Presentation,
  pip: Monitor
};

const layoutTypeNames = {
  grid: 'Grid View',
  speaker: 'Speaker Focus',
  presentation: 'Presentation Mode',
  custom: 'Custom Layout',
  gallery: 'Gallery View',
  focus: 'Focus Mode',
  sidebar: 'Sidebar View',
  pip: 'Picture-in-Picture'
};

export function MeetingLayoutManager({ meetingId, onLayoutChange, isHost = false }: MeetingLayoutManagerProps) {
  const [layouts, setLayouts] = useState<Layout[]>([]);
  const [presets, setPresets] = useState<LayoutPreset[]>([]);
  const [currentLayout, setCurrentLayout] = useState<Layout | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [selectedPreset, setSelectedPreset] = useState<string>('');
  
  // Layout configuration state
  const [layoutConfig, setLayoutConfig] = useState({
    // Grid settings
    grid_columns: 3,
    grid_rows: 3,
    grid_aspect_ratio: '16:9',
    fill_grid_dynamically: true,
    
    // Speaker settings
    speaker_position: 'center',
    speaker_size: 'large',
    show_speaker_thumbnails: true,
    thumbnail_count: 6,
    
    // Presentation settings
    content_position: 'center',
    content_size: 'large',
    participants_position: 'right',
    participants_size: 'small',
    
    // Behavior settings
    auto_arrange_participants: true,
    max_visible_participants: 25,
    highlight_active_speaker: true,
    show_participant_names: true,
    show_participant_status: true,
    allow_participant_pinning: true,
    enable_spotlight_mode: true,
    
    // Theme settings
    background_type: 'none',
    background_value: '',
    
    // Accessibility
    high_contrast_mode: false,
    reduce_animations: false,
    
    // Performance
    video_quality: 'auto',
    adaptive_quality: true,
    frame_rate: 30
  });

  useEffect(() => {
    loadLayouts();
    loadPresets();
  }, [meetingId]);

  const loadLayouts = async () => {
    try {
      const response = await apiService.get(`/api/meetings/${meetingId}/layouts`);
      setLayouts(response.data);
      
      const defaultLayout = response.data.find((l: Layout) => l.is_default);
      if (defaultLayout) {
        setCurrentLayout(defaultLayout);
        if (defaultLayout.config) {
          setLayoutConfig(prev => ({ ...prev, ...defaultLayout.config }));
        }
      }
    } catch (error) {
      console.error('Failed to load layouts:', error);
    }
  };

  const loadPresets = async () => {
    try {
      const response = await apiService.get('/api/meeting-layout-presets');
      setPresets(response.data);
    } catch (error) {
      console.error('Failed to load presets:', error);
    } finally {
      setLoading(false);
    }
  };

  const switchLayout = async (layoutId: string) => {
    if (!isHost) return;
    
    setSaving(true);
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/layouts/${layoutId}/switch`);
      
      if (response.success) {
        await loadLayouts();
        onLayoutChange?.(response.layout_config);
      }
    } catch (error) {
      console.error('Failed to switch layout:', error);
    } finally {
      setSaving(false);
    }
  };

  const applyPreset = async () => {
    if (!selectedPreset || !isHost) return;
    
    setSaving(true);
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/layouts/preset`, {
        preset_id: selectedPreset
      });
      
      if (response.success) {
        await loadLayouts();
        onLayoutChange?.(response.layout_config);
        setSelectedPreset('');
      }
    } catch (error) {
      console.error('Failed to apply preset:', error);
    } finally {
      setSaving(false);
    }
  };

  const saveLayoutConfig = async () => {
    if (!currentLayout || !isHost) return;
    
    setSaving(true);
    try {
      await apiService.put(`/api/meetings/${meetingId}/layouts/${currentLayout.id}`, layoutConfig);
      
      // Refresh current layout
      await loadLayouts();
      
      // Notify parent of configuration change
      onLayoutChange?.({ ...currentLayout, config: layoutConfig });
    } catch (error) {
      console.error('Failed to save layout configuration:', error);
    } finally {
      setSaving(false);
    }
  };

  const resetToDefaults = () => {
    if (currentLayout?.config) {
      setLayoutConfig(prev => ({ ...prev, ...currentLayout.config }));
    }
  };

  if (loading) {
    return (
      <Card>
        <CardContent className="p-6">
          <div className="animate-pulse space-y-4">
            <div className="h-4 bg-gray-200 rounded w-1/4"></div>
            <div className="space-y-2">
              <div className="h-8 bg-gray-200 rounded"></div>
              <div className="h-8 bg-gray-200 rounded"></div>
            </div>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Monitor className="h-5 w-5" />
          Meeting Layout Manager
          {!isHost && <Badge variant="outline">View Only</Badge>}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <Tabs defaultValue="layouts" className="w-full">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="layouts">Current Layouts</TabsTrigger>
            <TabsTrigger value="presets">Apply Presets</TabsTrigger>
            <TabsTrigger value="customize">Customize</TabsTrigger>
          </TabsList>

          <TabsContent value="layouts" className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {layouts.map((layout) => {
                const IconComponent = layoutTypeIcons[layout.type];
                const isActive = layout.id === currentLayout?.id;
                
                return (
                  <Card key={layout.id} className={`cursor-pointer transition-all ${isActive ? 'ring-2 ring-blue-500' : 'hover:bg-gray-50'}`}>
                    <CardContent className="p-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <IconComponent className="h-6 w-6 text-blue-600" />
                          <div>
                            <h4 className="font-medium">{layout.name}</h4>
                            <p className="text-sm text-gray-600">{layoutTypeNames[layout.type]}</p>
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          {layout.is_default && <Badge>Default</Badge>}
                          {isHost && (
                            <Button
                              size="sm"
                              variant={isActive ? "default" : "outline"}
                              onClick={() => switchLayout(layout.id)}
                              disabled={saving || isActive}
                            >
                              {isActive ? 'Active' : 'Switch'}
                            </Button>
                          )}
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                );
              })}
            </div>
          </TabsContent>

          <TabsContent value="presets" className="space-y-4">
            <div className="flex gap-4">
              <Select value={selectedPreset} onValueChange={setSelectedPreset}>
                <SelectTrigger className="flex-1">
                  <SelectValue placeholder="Choose a layout preset..." />
                </SelectTrigger>
                <SelectContent>
                  {presets.map((preset) => (
                    <SelectItem key={preset.id} value={preset.id}>
                      <div className="flex items-center gap-2">
                        <span>{preset.preset_name}</span>
                        {preset.is_featured && <Badge size="sm">Featured</Badge>}
                      </div>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button 
                onClick={applyPreset} 
                disabled={!selectedPreset || saving || !isHost}
              >
                Apply Preset
              </Button>
            </div>

            {selectedPreset && (
              <Card>
                <CardContent className="p-4">
                  {(() => {
                    const preset = presets.find(p => p.id === selectedPreset);
                    return preset ? (
                      <div>
                        <h4 className="font-medium mb-2">{preset.preset_name}</h4>
                        <p className="text-sm text-gray-600 mb-2">{preset.description}</p>
                        <div className="flex gap-2">
                          <Badge variant="outline">{preset.category}</Badge>
                          <Badge variant="outline">{layoutTypeNames[preset.layout_type as keyof typeof layoutTypeNames]}</Badge>
                        </div>
                      </div>
                    ) : null;
                  })()}
                </CardContent>
              </Card>
            )}
          </TabsContent>

          <TabsContent value="customize" className="space-y-6">
            {!isHost ? (
              <div className="text-center py-8 text-gray-500">
                <EyeOff className="h-12 w-12 mx-auto mb-4 opacity-50" />
                <p>Host permissions required to customize layouts</p>
              </div>
            ) : (
              <>
                {/* Grid Layout Settings */}
                {currentLayout?.type === 'grid' && (
                  <div className="space-y-4">
                    <h4 className="font-medium flex items-center gap-2">
                      <Grid3X3 className="h-4 w-4" />
                      Grid Settings
                    </h4>
                    <div className="grid grid-cols-2 gap-4">
                      <div className="space-y-2">
                        <Label>Columns: {layoutConfig.grid_columns}</Label>
                        <Slider
                          value={[layoutConfig.grid_columns]}
                          onValueChange={([value]) => setLayoutConfig(prev => ({ ...prev, grid_columns: value }))}
                          max={8}
                          min={1}
                          step={1}
                        />
                      </div>
                      <div className="space-y-2">
                        <Label>Max Visible: {layoutConfig.max_visible_participants}</Label>
                        <Slider
                          value={[layoutConfig.max_visible_participants]}
                          onValueChange={([value]) => setLayoutConfig(prev => ({ ...prev, max_visible_participants: value }))}
                          max={100}
                          min={1}
                          step={1}
                        />
                      </div>
                    </div>
                    <div className="flex items-center justify-between">
                      <Label>Fill Grid Dynamically</Label>
                      <Switch
                        checked={layoutConfig.fill_grid_dynamically}
                        onCheckedChange={(checked) => setLayoutConfig(prev => ({ ...prev, fill_grid_dynamically: checked }))}
                      />
                    </div>
                  </div>
                )}

                {/* Speaker Layout Settings */}
                {currentLayout?.type === 'speaker' && (
                  <div className="space-y-4">
                    <h4 className="font-medium flex items-center gap-2">
                      <Users className="h-4 w-4" />
                      Speaker Settings
                    </h4>
                    <div className="grid grid-cols-2 gap-4">
                      <div className="space-y-2">
                        <Label>Speaker Position</Label>
                        <Select 
                          value={layoutConfig.speaker_position} 
                          onValueChange={(value) => setLayoutConfig(prev => ({ ...prev, speaker_position: value }))}
                        >
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="center">Center</SelectItem>
                            <SelectItem value="left">Left</SelectItem>
                            <SelectItem value="right">Right</SelectItem>
                            <SelectItem value="top">Top</SelectItem>
                            <SelectItem value="bottom">Bottom</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="space-y-2">
                        <Label>Speaker Size</Label>
                        <Select 
                          value={layoutConfig.speaker_size} 
                          onValueChange={(value) => setLayoutConfig(prev => ({ ...prev, speaker_size: value }))}
                        >
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="small">Small</SelectItem>
                            <SelectItem value="medium">Medium</SelectItem>
                            <SelectItem value="large">Large</SelectItem>
                            <SelectItem value="full">Full Screen</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>
                    <div className="space-y-2">
                      <Label>Thumbnail Count: {layoutConfig.thumbnail_count}</Label>
                      <Slider
                        value={[layoutConfig.thumbnail_count]}
                        onValueChange={([value]) => setLayoutConfig(prev => ({ ...prev, thumbnail_count: value }))}
                        max={12}
                        min={0}
                        step={1}
                      />
                    </div>
                  </div>
                )}

                <Separator />

                {/* Behavior Settings */}
                <div className="space-y-4">
                  <h4 className="font-medium flex items-center gap-2">
                    <Settings className="h-4 w-4" />
                    Behavior Settings
                  </h4>
                  <div className="space-y-3">
                    {[
                      { key: 'auto_arrange_participants', label: 'Auto-arrange Participants' },
                      { key: 'highlight_active_speaker', label: 'Highlight Active Speaker' },
                      { key: 'show_participant_names', label: 'Show Participant Names' },
                      { key: 'show_participant_status', label: 'Show Status Indicators' },
                      { key: 'allow_participant_pinning', label: 'Allow Participant Pinning' },
                      { key: 'enable_spotlight_mode', label: 'Enable Spotlight Mode' }
                    ].map(({ key, label }) => (
                      <div key={key} className="flex items-center justify-between">
                        <Label>{label}</Label>
                        <Switch
                          checked={layoutConfig[key as keyof typeof layoutConfig] as boolean}
                          onCheckedChange={(checked) => setLayoutConfig(prev => ({ ...prev, [key]: checked }))}
                        />
                      </div>
                    ))}
                  </div>
                </div>

                <Separator />

                {/* Theme Settings */}
                <div className="space-y-4">
                  <h4 className="font-medium flex items-center gap-2">
                    <Palette className="h-4 w-4" />
                    Theme Settings
                  </h4>
                  <div className="space-y-4">
                    <div className="space-y-2">
                      <Label>Background Type</Label>
                      <Select 
                        value={layoutConfig.background_type} 
                        onValueChange={(value) => setLayoutConfig(prev => ({ ...prev, background_type: value }))}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="none">None</SelectItem>
                          <SelectItem value="color">Solid Color</SelectItem>
                          <SelectItem value="image">Background Image</SelectItem>
                          <SelectItem value="blur">Blur Effect</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    {layoutConfig.background_type === 'color' && (
                      <div className="space-y-2">
                        <Label>Background Color</Label>
                        <Input
                          type="color"
                          value={layoutConfig.background_value}
                          onChange={(e) => setLayoutConfig(prev => ({ ...prev, background_value: e.target.value }))}
                        />
                      </div>
                    )}
                  </div>
                </div>

                <Separator />

                {/* Accessibility Settings */}
                <div className="space-y-4">
                  <h4 className="font-medium flex items-center gap-2">
                    <Accessibility className="h-4 w-4" />
                    Accessibility
                  </h4>
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <Label>High Contrast Mode</Label>
                      <Switch
                        checked={layoutConfig.high_contrast_mode}
                        onCheckedChange={(checked) => setLayoutConfig(prev => ({ ...prev, high_contrast_mode: checked }))}
                      />
                    </div>
                    <div className="flex items-center justify-between">
                      <Label>Reduce Animations</Label>
                      <Switch
                        checked={layoutConfig.reduce_animations}
                        onCheckedChange={(checked) => setLayoutConfig(prev => ({ ...prev, reduce_animations: checked }))}
                      />
                    </div>
                  </div>
                </div>

                <Separator />

                {/* Performance Settings */}
                <div className="space-y-4">
                  <h4 className="font-medium flex items-center gap-2">
                    <Zap className="h-4 w-4" />
                    Performance
                  </h4>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label>Video Quality</Label>
                      <Select 
                        value={layoutConfig.video_quality} 
                        onValueChange={(value) => setLayoutConfig(prev => ({ ...prev, video_quality: value }))}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="auto">Auto</SelectItem>
                          <SelectItem value="low">Low</SelectItem>
                          <SelectItem value="medium">Medium</SelectItem>
                          <SelectItem value="high">High</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-2">
                      <Label>Frame Rate: {layoutConfig.frame_rate} fps</Label>
                      <Slider
                        value={[layoutConfig.frame_rate]}
                        onValueChange={([value]) => setLayoutConfig(prev => ({ ...prev, frame_rate: value }))}
                        max={60}
                        min={15}
                        step={15}
                      />
                    </div>
                  </div>
                  <div className="flex items-center justify-between">
                    <Label>Adaptive Quality</Label>
                    <Switch
                      checked={layoutConfig.adaptive_quality}
                      onCheckedChange={(checked) => setLayoutConfig(prev => ({ ...prev, adaptive_quality: checked }))}
                    />
                  </div>
                </div>

                {/* Action Buttons */}
                <div className="flex gap-3 pt-4">
                  <Button onClick={saveLayoutConfig} disabled={saving}>
                    <Save className="h-4 w-4 mr-2" />
                    Save Changes
                  </Button>
                  <Button variant="outline" onClick={resetToDefaults}>
                    <RotateCcw className="h-4 w-4 mr-2" />
                    Reset
                  </Button>
                </div>
              </>
            )}
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  );
}