import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { 
  Download,
  FileText,
  Shield,
  Calendar,
  Users,
  Settings,
  Trash2,
  AlertCircle,
  CheckCircle,
  Clock,
  X,
  Archive,
  Database,
  Loader2
} from 'lucide-react';
import { useMessageExport, ExportOptions, ExportFormat } from '@/hooks/useMessageExport';
import { formatDistanceToNow, formatBytes } from 'date-fns';
import { cn } from '@/lib/utils';

interface MessageExportDialogProps {
  conversationId?: string;
  conversationName?: string;
  trigger?: React.ReactNode;
  className?: string;
}

export function MessageExportDialog({ 
  conversationId, 
  conversationName, 
  trigger,
  className 
}: MessageExportDialogProps) {
  const [open, setOpen] = useState(false);
  const [activeTab, setActiveTab] = useState<'export' | 'backup' | 'jobs'>('export');
  
  const {
    availableFormats,
    exportJobs,
    backupJobs,
    isExporting,
    isBackingUp,
    exportProgress,
    backupProgress,
    startExport,
    cancelExport,
    downloadExport,
    deleteExport,
    startBackup,
    cancelBackup,
    downloadBackup,
    deleteBackup,
    getExportJobs,
    getBackupJobs,
    cleanupExpiredJobs,
  } = useMessageExport();

  useEffect(() => {
    if (open) {
      getExportJobs();
      getBackupJobs();
    }
  }, [open, getExportJobs, getBackupJobs]);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {trigger || (
          <Button variant="outline" size="sm">
            <Download className="h-4 w-4 mr-2" />
            Export Messages
          </Button>
        )}
      </DialogTrigger>
      
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-hidden">
        <DialogHeader>
          <DialogTitle>Export & Backup</DialogTitle>
          {conversationName && (
            <p className="text-sm text-muted-foreground">
              {conversationName}
            </p>
          )}
        </DialogHeader>

        <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="flex-1 overflow-hidden">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="export" className="flex items-center">
              <FileText className="h-4 w-4 mr-2" />
              Export
            </TabsTrigger>
            <TabsTrigger value="backup" className="flex items-center">
              <Archive className="h-4 w-4 mr-2" />
              Backup
            </TabsTrigger>
            <TabsTrigger value="jobs" className="flex items-center">
              <Database className="h-4 w-4 mr-2" />
              Jobs
              {(exportJobs.length + backupJobs.length > 0) && (
                <Badge variant="secondary" className="ml-2">
                  {exportJobs.length + backupJobs.length}
                </Badge>
              )}
            </TabsTrigger>
          </TabsList>

          <div className="flex-1 overflow-hidden">
            <TabsContent value="export" className="h-full overflow-hidden">
              <ExportTab 
                conversationId={conversationId}
                availableFormats={availableFormats}
                isExporting={isExporting}
                exportProgress={exportProgress}
                onStartExport={startExport}
                onCancel={() => {
                  const activeJob = exportJobs.find(j => j.status === 'processing');
                  if (activeJob) cancelExport(activeJob.id);
                }}
              />
            </TabsContent>

            <TabsContent value="backup" className="h-full overflow-hidden">
              <BackupTab 
                isBackingUp={isBackingUp}
                backupProgress={backupProgress}
                onStartBackup={startBackup}
                onCancel={() => {
                  const activeJob = backupJobs.find(j => j.status === 'processing');
                  if (activeJob) cancelBackup(activeJob.id);
                }}
              />
            </TabsContent>

            <TabsContent value="jobs" className="h-full overflow-hidden">
              <JobsTab
                exportJobs={exportJobs}
                backupJobs={backupJobs}
                onDownloadExport={downloadExport}
                onDeleteExport={deleteExport}
                onDownloadBackup={downloadBackup}
                onDeleteBackup={deleteBackup}
                onCleanup={cleanupExpiredJobs}
              />
            </TabsContent>
          </div>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
}

interface ExportTabProps {
  conversationId?: string;
  availableFormats: ExportFormat[];
  isExporting: boolean;
  exportProgress: number;
  onStartExport: (conversationId: string, options: ExportOptions) => Promise<any>;
  onCancel: () => void;
}

function ExportTab({ 
  conversationId, 
  availableFormats, 
  isExporting, 
  exportProgress, 
  onStartExport, 
  onCancel 
}: ExportTabProps) {
  const [selectedFormat, setSelectedFormat] = useState<string>('json');
  const [includeMedia, setIncludeMedia] = useState(true);
  const [includeReactions, setIncludeReactions] = useState(true);
  const [includeReadReceipts, setIncludeReadReceipts] = useState(false);
  const [includeThreads, setIncludeThreads] = useState(true);
  const [encryptExport, setEncryptExport] = useState(true);
  const [password, setPassword] = useState('');
  const [dateRange, setDateRange] = useState<{ start: Date; end: Date } | null>(null);
  const [compressionLevel, setCompressionLevel] = useState<'none' | 'standard' | 'maximum'>('standard');

  const selectedFormatInfo = availableFormats.find(f => f.id === selectedFormat);

  const handleExport = async () => {
    if (!conversationId) return;

    const options: ExportOptions = {
      format: selectedFormat,
      includeMedia,
      includeReactions,
      includeReadReceipts,
      includeThreads,
      dateRange,
      encryptExport: encryptExport && selectedFormatInfo?.supportsEncryption,
      password: encryptExport ? password : undefined,
      compressionLevel,
    };

    try {
      await onStartExport(conversationId, options);
    } catch (error) {
      console.error('Failed to start export:', error);
    }
  };

  return (
    <ScrollArea className="h-full pr-4">
      <div className="space-y-6">
        {/* Export in progress */}
        {isExporting && (
          <Card>
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <CardTitle className="text-lg flex items-center">
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Exporting Messages...
                </CardTitle>
                <Button variant="outline" size="sm" onClick={onCancel}>
                  <X className="h-4 w-4 mr-2" />
                  Cancel
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span>Progress</span>
                  <span>{Math.round(exportProgress)}%</span>
                </div>
                <Progress value={exportProgress} className="h-2" />
                <p className="text-xs text-muted-foreground">
                  Processing messages and media files...
                </p>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Format selection */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <FileText className="h-4 w-4 mr-2" />
              Export Format
            </CardTitle>
          </CardHeader>
          <CardContent>
            <RadioGroup value={selectedFormat} onValueChange={setSelectedFormat}>
              <div className="grid gap-4">
                {availableFormats.map((format) => (
                  <div key={format.id} className="flex items-start space-x-3">
                    <RadioGroupItem value={format.id} id={format.id} className="mt-1" />
                    <div className="flex-1">
                      <Label htmlFor={format.id} className="font-medium cursor-pointer">
                        {format.name}
                      </Label>
                      <p className="text-sm text-muted-foreground mt-1">
                        {format.description}
                      </p>
                      <div className="flex items-center space-x-2 mt-2">
                        {format.supportsEncryption && (
                          <Badge variant="secondary" className="text-xs">
                            <Shield className="h-3 w-3 mr-1" />
                            Encryption
                          </Badge>
                        )}
                        {format.supportsMedia && (
                          <Badge variant="secondary" className="text-xs">
                            Media
                          </Badge>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </RadioGroup>
          </CardContent>
        </Card>

        {/* Export options */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <Settings className="h-4 w-4 mr-2" />
              Export Options
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {/* Content options */}
            <div className="space-y-3">
              <Label className="text-sm font-medium">Include Content</Label>
              
              <div className="flex items-center space-x-2">
                <Checkbox
                  id="includeMedia"
                  checked={includeMedia}
                  onCheckedChange={setIncludeMedia}
                  disabled={!selectedFormatInfo?.supportsMedia}
                />
                <Label htmlFor="includeMedia" className="text-sm cursor-pointer">
                  Media files and attachments
                </Label>
              </div>

              <div className="flex items-center space-x-2">
                <Checkbox
                  id="includeReactions"
                  checked={includeReactions}
                  onCheckedChange={setIncludeReactions}
                />
                <Label htmlFor="includeReactions" className="text-sm cursor-pointer">
                  Message reactions
                </Label>
              </div>

              <div className="flex items-center space-x-2">
                <Checkbox
                  id="includeReadReceipts"
                  checked={includeReadReceipts}
                  onCheckedChange={setIncludeReadReceipts}
                />
                <Label htmlFor="includeReadReceipts" className="text-sm cursor-pointer">
                  Read receipts
                </Label>
              </div>

              <div className="flex items-center space-x-2">
                <Checkbox
                  id="includeThreads"
                  checked={includeThreads}
                  onCheckedChange={setIncludeThreads}
                />
                <Label htmlFor="includeThreads" className="text-sm cursor-pointer">
                  Message threads
                </Label>
              </div>
            </div>

            <Separator />

            {/* Security options */}
            {selectedFormatInfo?.supportsEncryption && (
              <>
                <div className="space-y-3">
                  <div className="flex items-center space-x-2">
                    <Checkbox
                      id="encryptExport"
                      checked={encryptExport}
                      onCheckedChange={setEncryptExport}
                    />
                    <Label htmlFor="encryptExport" className="text-sm cursor-pointer flex items-center">
                      <Shield className="h-3 w-3 mr-1" />
                      Password-protect export
                    </Label>
                  </div>

                  {encryptExport && (
                    <div className="space-y-2">
                      <Label htmlFor="password" className="text-sm">
                        Export Password
                      </Label>
                      <Input
                        id="password"
                        type="password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        placeholder="Enter a strong password"
                        className="text-sm"
                      />
                      <p className="text-xs text-muted-foreground">
                        This password will be required to access the exported data
                      </p>
                    </div>
                  )}
                </div>

                <Separator />
              </>
            )}

            {/* Compression options */}
            <div className="space-y-3">
              <Label className="text-sm font-medium">Compression</Label>
              <Select value={compressionLevel} onValueChange={(v: any) => setCompressionLevel(v)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">None (Fastest)</SelectItem>
                  <SelectItem value="standard">Standard (Balanced)</SelectItem>
                  <SelectItem value="maximum">Maximum (Smallest file)</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        {/* Export button */}
        <div className="flex justify-end">
          <Button 
            onClick={handleExport} 
            disabled={isExporting || !conversationId || (encryptExport && !password)}
            size="lg"
          >
            {isExporting ? (
              <>
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                Exporting...
              </>
            ) : (
              <>
                <Download className="h-4 w-4 mr-2" />
                Start Export
              </>
            )}
          </Button>
        </div>
      </div>
    </ScrollArea>
  );
}

interface BackupTabProps {
  isBackingUp: boolean;
  backupProgress: number;
  onStartBackup: (type: 'full' | 'incremental' | 'conversations' | 'media', options?: any) => Promise<any>;
  onCancel: () => void;
}

function BackupTab({ isBackingUp, backupProgress, onStartBackup, onCancel }: BackupTabProps) {
  const [backupType, setBackupType] = useState<'full' | 'incremental' | 'conversations' | 'media'>('full');
  const [encryptBackup, setEncryptBackup] = useState(true);
  const [backupPassword, setBackupPassword] = useState('');

  const handleBackup = async () => {
    try {
      await onStartBackup(backupType, {
        encryptExport: encryptBackup,
        password: encryptBackup ? backupPassword : undefined,
      });
    } catch (error) {
      console.error('Failed to start backup:', error);
    }
  };

  return (
    <ScrollArea className="h-full pr-4">
      <div className="space-y-6">
        {/* Backup in progress */}
        {isBackingUp && (
          <Card>
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <CardTitle className="text-lg flex items-center">
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Creating Backup...
                </CardTitle>
                <Button variant="outline" size="sm" onClick={onCancel}>
                  <X className="h-4 w-4 mr-2" />
                  Cancel
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span>Progress</span>
                  <span>{Math.round(backupProgress)}%</span>
                </div>
                <Progress value={backupProgress} className="h-2" />
                <p className="text-xs text-muted-foreground">
                  Backing up conversations and media...
                </p>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Backup type selection */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <Archive className="h-4 w-4 mr-2" />
              Backup Type
            </CardTitle>
          </CardHeader>
          <CardContent>
            <RadioGroup value={backupType} onValueChange={(v: any) => setBackupType(v)}>
              <div className="space-y-4">
                <div className="flex items-start space-x-3">
                  <RadioGroupItem value="full" id="full" className="mt-1" />
                  <div>
                    <Label htmlFor="full" className="font-medium cursor-pointer">
                      Full Backup
                    </Label>
                    <p className="text-sm text-muted-foreground">
                      Complete backup of all conversations, messages, and media
                    </p>
                  </div>
                </div>

                <div className="flex items-start space-x-3">
                  <RadioGroupItem value="incremental" id="incremental" className="mt-1" />
                  <div>
                    <Label htmlFor="incremental" className="font-medium cursor-pointer">
                      Incremental Backup
                    </Label>
                    <p className="text-sm text-muted-foreground">
                      Backup only new or modified data since last backup
                    </p>
                  </div>
                </div>

                <div className="flex items-start space-x-3">
                  <RadioGroupItem value="conversations" id="conversations" className="mt-1" />
                  <div>
                    <Label htmlFor="conversations" className="font-medium cursor-pointer">
                      Messages Only
                    </Label>
                    <p className="text-sm text-muted-foreground">
                      Backup conversations and messages without media files
                    </p>
                  </div>
                </div>

                <div className="flex items-start space-x-3">
                  <RadioGroupItem value="media" id="media" className="mt-1" />
                  <div>
                    <Label htmlFor="media" className="font-medium cursor-pointer">
                      Media Only
                    </Label>
                    <p className="text-sm text-muted-foreground">
                      Backup only media files and attachments
                    </p>
                  </div>
                </div>
              </div>
            </RadioGroup>
          </CardContent>
        </Card>

        {/* Backup options */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <Shield className="h-4 w-4 mr-2" />
              Security Options
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center space-x-2">
              <Checkbox
                id="encryptBackup"
                checked={encryptBackup}
                onCheckedChange={setEncryptBackup}
              />
              <Label htmlFor="encryptBackup" className="text-sm cursor-pointer">
                Encrypt backup with password
              </Label>
            </div>

            {encryptBackup && (
              <div className="space-y-2">
                <Label htmlFor="backupPassword" className="text-sm">
                  Backup Password
                </Label>
                <Input
                  id="backupPassword"
                  type="password"
                  value={backupPassword}
                  onChange={(e) => setBackupPassword(e.target.value)}
                  placeholder="Enter a strong password"
                />
                <p className="text-xs text-muted-foreground">
                  Keep this password safe - it cannot be recovered if lost
                </p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Backup button */}
        <div className="flex justify-end">
          <Button 
            onClick={handleBackup} 
            disabled={isBackingUp || (encryptBackup && !backupPassword)}
            size="lg"
          >
            {isBackingUp ? (
              <>
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                Creating Backup...
              </>
            ) : (
              <>
                <Archive className="h-4 w-4 mr-2" />
                Start Backup
              </>
            )}
          </Button>
        </div>
      </div>
    </ScrollArea>
  );
}

interface JobsTabProps {
  exportJobs: any[];
  backupJobs: any[];
  onDownloadExport: (jobId: string) => Promise<void>;
  onDeleteExport: (jobId: string) => Promise<void>;
  onDownloadBackup: (jobId: string) => Promise<void>;
  onDeleteBackup: (jobId: string) => Promise<void>;
  onCleanup: () => Promise<void>;
}

function JobsTab({
  exportJobs,
  backupJobs,
  onDownloadExport,
  onDeleteExport,
  onDownloadBackup,
  onDeleteBackup,
  onCleanup,
}: JobsTabProps) {
  return (
    <div className="space-y-4">
      {/* Cleanup button */}
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-semibold">Recent Jobs</h3>
        <Button variant="outline" size="sm" onClick={onCleanup}>
          <Trash2 className="h-4 w-4 mr-2" />
          Cleanup Expired
        </Button>
      </div>

      <ScrollArea className="h-96">
        <div className="space-y-4">
          {/* Export jobs */}
          {exportJobs.length > 0 && (
            <div>
              <h4 className="font-medium mb-2">Export Jobs</h4>
              {exportJobs.map((job) => (
                <JobCard
                  key={job.id}
                  job={job}
                  type="export"
                  onDownload={() => onDownloadExport(job.id)}
                  onDelete={() => onDeleteExport(job.id)}
                />
              ))}
            </div>
          )}

          {/* Backup jobs */}
          {backupJobs.length > 0 && (
            <div>
              <h4 className="font-medium mb-2">Backup Jobs</h4>
              {backupJobs.map((job) => (
                <JobCard
                  key={job.id}
                  job={job}
                  type="backup"
                  onDownload={() => onDownloadBackup(job.id)}
                  onDelete={() => onDeleteBackup(job.id)}
                />
              ))}
            </div>
          )}

          {exportJobs.length === 0 && backupJobs.length === 0 && (
            <div className="text-center py-8">
              <Database className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
              <p className="text-muted-foreground">No export or backup jobs yet</p>
            </div>
          )}
        </div>
      </ScrollArea>
    </div>
  );
}

interface JobCardProps {
  job: any;
  type: 'export' | 'backup';
  onDownload: () => void;
  onDelete: () => void;
}

function JobCard({ job, type, onDownload, onDelete }: JobCardProps) {
  const getStatusIcon = () => {
    switch (job.status) {
      case 'completed':
        return <CheckCircle className="h-4 w-4 text-green-500" />;
      case 'processing':
        return <Loader2 className="h-4 w-4 text-blue-500 animate-spin" />;
      case 'failed':
        return <AlertCircle className="h-4 w-4 text-red-500" />;
      default:
        return <Clock className="h-4 w-4 text-yellow-500" />;
    }
  };

  return (
    <Card className="mb-2">
      <CardContent className="p-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            {getStatusIcon()}
            <div>
              <div className="font-medium">
                {type === 'export' ? `Export (${job.format})` : `Backup (${job.backup_type})`}
              </div>
              <div className="text-sm text-muted-foreground">
                {formatDistanceToNow(new Date(job.created_at), { addSuffix: true })}
                {job.file_size && ` • ${formatBytes(job.file_size)}`}
              </div>
            </div>
          </div>

          <div className="flex items-center space-x-2">
            {job.status === 'processing' && (
              <Progress value={job.progress} className="w-20 h-2" />
            )}
            
            {job.status === 'completed' && job.download_url && (
              <TooltipProvider>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button size="sm" variant="outline" onClick={onDownload}>
                      <Download className="h-4 w-4" />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>
                    <p>Download {type}</p>
                  </TooltipContent>
                </Tooltip>
              </TooltipProvider>
            )}

            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button size="sm" variant="outline" onClick={onDelete}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>
                  <p>Delete {type}</p>
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
          </div>
        </div>

        {job.error_message && (
          <div className="mt-2 p-2 bg-red-50 dark:bg-red-900/20 rounded text-sm text-red-600 dark:text-red-400">
            {job.error_message}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export default MessageExportDialog;