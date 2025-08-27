import { useState } from 'react'
import { Head, useForm, router } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Separator } from '@/components/ui/separator'
import { Plus, Copy, Eye, EyeOff, MoreHorizontal, Trash2, RotateCcw, Globe, Smartphone, Monitor, Shield, BarChart3, ExternalLink } from 'lucide-react'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { toast } from 'sonner'

interface Organization {
  id: string
  name: string
  code: string
}

interface Client {
  id: string
  name: string
  secret: string
  redirect_uris: string[]
  revoked: boolean
  created_at: string
  last_used_at?: string
  client_type: 'web' | 'mobile' | 'desktop' | 'service'
  user_access_scope: string
  access_scope_description: string
  organization: Organization
  description?: string
  website?: string
  logo_url?: string
}

interface Props {
  clients: Client[]
  organizations: Organization[]
  userAccessScopes: Record<string, string>
}

export default function Clients({ clients, organizations, userAccessScopes }: Props) {
  const [showCreateDialog, setShowCreateDialog] = useState(false)
  const [showSecrets, setShowSecrets] = useState<Record<string, boolean>>({})
  const [selectedClientType, setSelectedClientType] = useState<string>('')

  const createForm = useForm({
    name: '',
    description: '',
    website: '',
    logo_url: '',
    redirect_uris: [''],
    client_type: 'web',
    organization_id: '',
    user_access_scope: 'organization_members',
  })

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault()
    createForm.post(route('clients.store'), {
      onSuccess: () => {
        setShowCreateDialog(false)
        createForm.reset()
        setSelectedClientType('')
        toast.success('OAuth client created successfully')
      },
    })
  }

  const getClientTypeIcon = (type: string) => {
    switch (type) {
      case 'web': return Globe
      case 'mobile': return Smartphone
      case 'desktop': return Monitor
      case 'service': return Shield
      default: return Globe
    }
  }

  const getClientTypeDescription = (type: string) => {
    switch (type) {
      case 'web': return 'Web applications running on a server'
      case 'mobile': return 'Mobile applications (iOS, Android)'
      case 'desktop': return 'Desktop applications'
      case 'service': return 'Server-to-server applications'
      default: return 'Standard web application'
    }
  }

  const getStatusBadgeVariant = (client: Client) => {
    if (client.revoked) return 'destructive'
    if (client.last_used_at) return 'default'
    return 'secondary'
  }

  const getStatusText = (client: Client) => {
    if (client.revoked) return 'Revoked'
    if (client.last_used_at) return 'Active'
    return 'Inactive'
  }

  const addRedirectUri = () => {
    createForm.setData('redirect_uris', [...createForm.data.redirect_uris, ''])
  }

  const updateRedirectUri = (index: number, value: string) => {
    const newUris = [...createForm.data.redirect_uris]
    newUris[index] = value
    createForm.setData('redirect_uris', newUris)
  }

  const removeRedirectUri = (index: number) => {
    if (createForm.data.redirect_uris.length > 1) {
      const newUris = createForm.data.redirect_uris.filter((_, i) => i !== index)
      createForm.setData('redirect_uris', newUris)
    }
  }

  const toggleSecret = (clientId: string) => {
    setShowSecrets(prev => ({ ...prev, [clientId]: !prev[clientId] }))
  }

  const copyToClipboard = (text: string, label: string) => {
    navigator.clipboard.writeText(text)
    toast.success(`${label} copied to clipboard`)
  }

  const regenerateSecret = (clientId: string) => {
    router.post(route('clients.regenerate-secret', clientId), {}, {
      onSuccess: () => {
        toast.success('Client secret regenerated')
      },
    })
  }

  const deleteClient = (clientId: string) => {
    router.delete(route('clients.destroy', clientId), {
      onSuccess: () => {
        toast.success('OAuth client deleted')
      },
    })
  }

  return (
    <AppLayout>
      <Head title="OAuth Clients" />
      
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">OAuth Clients</h1>
            <p className="text-sm text-gray-600 dark:text-gray-400">
              Manage OAuth applications and their access to your organization's resources
            </p>
          </div>
          
          <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Create Client
              </Button>
            </DialogTrigger>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
              <DialogHeader>
                <DialogTitle>Create OAuth Client</DialogTitle>
                <DialogDescription>
                  Create a new OAuth client application. Choose the appropriate client type for your application platform.
                </DialogDescription>
              </DialogHeader>
              
              <form onSubmit={handleCreate} className="space-y-6">
                <Tabs value={selectedClientType || createForm.data.client_type} onValueChange={(value) => {
                  setSelectedClientType(value)
                  createForm.setData('client_type', value)
                }}>
                  <TabsList className="grid w-full grid-cols-4">
                    <TabsTrigger value="web" className="flex items-center gap-2">
                      <Globe className="h-4 w-4" />
                      Web
                    </TabsTrigger>
                    <TabsTrigger value="mobile" className="flex items-center gap-2">
                      <Smartphone className="h-4 w-4" />
                      Mobile
                    </TabsTrigger>
                    <TabsTrigger value="desktop" className="flex items-center gap-2">
                      <Monitor className="h-4 w-4" />
                      Desktop
                    </TabsTrigger>
                    <TabsTrigger value="service" className="flex items-center gap-2">
                      <Shield className="h-4 w-4" />
                      Service
                    </TabsTrigger>
                  </TabsList>
                  
                  {['web', 'mobile', 'desktop', 'service'].map((type) => (
                    <TabsContent key={type} value={type} className="space-y-4">
                      <div className="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border">
                        <p className="text-sm text-blue-800 dark:text-blue-200">
                          {getClientTypeDescription(type)}
                        </p>
                      </div>
                    </TabsContent>
                  ))}
                </Tabs>
                
                <Separator />
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="name">Application Name *</Label>
                    <Input
                      id="name"
                      value={createForm.data.name}
                      onChange={e => createForm.setData('name', e.target.value)}
                      placeholder="My Application"
                      required
                    />
                    {createForm.errors.name && (
                      <p className="text-sm text-red-600 mt-1">{createForm.errors.name}</p>
                    )}
                  </div>
                  
                  <div>
                    <Label htmlFor="organization_id">Organization *</Label>
                    <Select value={createForm.data.organization_id} onValueChange={(value) => createForm.setData('organization_id', value)}>
                      <SelectTrigger>
                        <SelectValue placeholder="Select organization" />
                      </SelectTrigger>
                      <SelectContent>
                        {organizations.map((org) => (
                          <SelectItem key={org.id} value={org.id}>
                            {org.name} ({org.code})
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {createForm.errors.organization_id && (
                      <p className="text-sm text-red-600 mt-1">{createForm.errors.organization_id}</p>
                    )}
                  </div>
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="website">Website</Label>
                    <Input
                      id="website"
                      type="url"
                      value={createForm.data.website}
                      onChange={e => createForm.setData('website', e.target.value)}
                      placeholder="https://example.com"
                    />
                  </div>
                  
                  <div>
                    <Label htmlFor="user_access_scope">User Access Scope *</Label>
                    <Select value={createForm.data.user_access_scope} onValueChange={(value) => createForm.setData('user_access_scope', value)}>
                      <SelectTrigger>
                        <SelectValue placeholder="Select access scope" />
                      </SelectTrigger>
                      <SelectContent>
                        {Object.entries(userAccessScopes).map(([key, label]) => (
                          <SelectItem key={key} value={key}>
                            {label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {createForm.errors.user_access_scope && (
                      <p className="text-sm text-red-600 mt-1">{createForm.errors.user_access_scope}</p>
                    )}
                  </div>
                </div>
                
                <div>
                  <Label htmlFor="description">Description</Label>
                  <Textarea
                    id="description"
                    value={createForm.data.description}
                    onChange={e => createForm.setData('description', e.target.value)}
                    placeholder="Brief description of your application"
                  />
                </div>
                
                <div>
                  <Label htmlFor="logo_url">Logo URL</Label>
                  <Input
                    id="logo_url"
                    type="url"
                    value={createForm.data.logo_url}
                    onChange={e => createForm.setData('logo_url', e.target.value)}
                    placeholder="https://example.com/logo.png"
                  />
                </div>
                
                <div>
                  <Label>Redirect URIs *</Label>
                  <div className="space-y-2">
                    {createForm.data.redirect_uris.map((uri, index) => (
                      <div key={index} className="flex gap-2">
                        <Input
                          value={uri}
                          onChange={e => updateRedirectUri(index, e.target.value)}
                          placeholder="https://example.com/callback"
                          required
                        />
                        {createForm.data.redirect_uris.length > 1 && (
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => removeRedirectUri(index)}
                          >
                            Remove
                          </Button>
                        )}
                      </div>
                    ))}
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={addRedirectUri}
                    >
                      Add Redirect URI
                    </Button>
                  </div>
                  {createForm.errors.redirect_uris && (
                    <p className="text-sm text-red-600 mt-1">{createForm.errors.redirect_uris}</p>
                  )}
                </div>
                
                <div className="flex justify-end space-x-2 pt-4">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setShowCreateDialog(false)}
                  >
                    Cancel
                  </Button>
                  <Button type="submit" disabled={createForm.processing}>
                    {createForm.processing ? 'Creating...' : 'Create Client'}
                  </Button>
                </div>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        <div className="grid gap-6">
          {clients.length === 0 ? (
            <Card>
              <CardContent className="text-center py-12">
                <h3 className="text-lg font-medium text-gray-900 mb-2">No OAuth clients</h3>
                <p className="text-sm text-gray-600 mb-4">
                  Create your first OAuth client to get started with API integrations.
                </p>
                <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                  <DialogTrigger asChild>
                    <Button>
                      <Plus className="h-4 w-4 mr-2" />
                      Create Your First Client
                    </Button>
                  </DialogTrigger>
                </Dialog>
              </CardContent>
            </Card>
          ) : (
            clients.map((client) => (
              <Card key={client.id} className="hover:shadow-md transition-shadow">
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div className="flex items-start space-x-3">
                      <div className="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        {(() => {
                          const IconComponent = getClientTypeIcon(client.client_type)
                          return <IconComponent className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        })()}
                      </div>
                      <div>
                        <CardTitle className="flex items-center space-x-2">
                          <span>{client.name}</span>
                          <Badge variant={getStatusBadgeVariant(client)}>
                            {getStatusText(client)}
                          </Badge>
                        </CardTitle>
                        <CardDescription className="space-y-1">
                          <div className="flex items-center space-x-4 text-xs">
                            <span>{client.organization.name}</span>
                            <span>•</span>
                            <span>{getClientTypeDescription(client.client_type)}</span>
                          </div>
                          <div>Created {new Date(client.created_at).toLocaleDateString()}</div>
                          {client.last_used_at && (
                            <div>Last used {new Date(client.last_used_at).toLocaleDateString()}</div>
                          )}
                        </CardDescription>
                      </div>
                    </div>
                    
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm">
                          <MoreHorizontal className="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => regenerateSecret(client.id)}>
                          <RotateCcw className="h-4 w-4 mr-2" />
                          Regenerate Secret
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => window.open(`/oauth/analytics/${client.id}`, '_blank')}>
                          <BarChart3 className="h-4 w-4 mr-2" />
                          View Analytics
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <AlertDialog>
                          <AlertDialogTrigger asChild>
                            <DropdownMenuItem
                              onSelect={(e) => e.preventDefault()}
                              className="text-red-600"
                            >
                              <Trash2 className="h-4 w-4 mr-2" />
                              Delete Client
                            </DropdownMenuItem>
                          </AlertDialogTrigger>
                          <AlertDialogContent>
                            <AlertDialogHeader>
                              <AlertDialogTitle>Delete OAuth Client</AlertDialogTitle>
                              <AlertDialogDescription>
                                This will permanently delete the OAuth client "{client.name}". 
                                All associated access tokens will be revoked. This action cannot be undone.
                              </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                              <AlertDialogCancel>Cancel</AlertDialogCancel>
                              <AlertDialogAction
                                onClick={() => deleteClient(client.id)}
                                className="bg-red-600 hover:bg-red-700"
                              >
                                Delete
                              </AlertDialogAction>
                            </AlertDialogFooter>
                          </AlertDialogContent>
                        </AlertDialog>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </CardHeader>
                
                <CardContent className="space-y-6">
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                      <Label className="text-sm font-medium">Client ID</Label>
                      <div className="flex items-center space-x-2 mt-2">
                        <code className="bg-gray-100 dark:bg-gray-800 px-3 py-2 rounded text-sm font-mono flex-1 border">
                          {client.id}
                        </code>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => copyToClipboard(client.id, 'Client ID')}
                        >
                          <Copy className="h-3 w-3" />
                        </Button>
                      </div>
                    </div>
                    
                    <div>
                      <Label className="text-sm font-medium">Client Secret</Label>
                      <div className="flex items-center space-x-2 mt-2">
                        <code className="bg-gray-100 dark:bg-gray-800 px-3 py-2 rounded text-sm font-mono flex-1 border">
                          {showSecrets[client.id] 
                            ? client.secret 
                            : '•'.repeat(40)
                          }
                        </code>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => toggleSecret(client.id)}
                        >
                          {showSecrets[client.id] ? (
                            <EyeOff className="h-3 w-3" />
                          ) : (
                            <Eye className="h-3 w-3" />
                          )}
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => copyToClipboard(client.secret, 'Client Secret')}
                        >
                          <Copy className="h-3 w-3" />
                        </Button>
                      </div>
                    </div>
                  </div>
                  
                  
                  <div>
                    <Label className="text-sm font-medium">Access Scope</Label>
                    <div className="mt-2">
                      <p className="text-sm text-gray-600 dark:text-gray-400 p-3 bg-gray-50 dark:bg-gray-800 rounded border">
                        {client.access_scope_description}
                      </p>
                    </div>
                  </div>
                  
                  <div>
                    <Label className="text-sm font-medium">Redirect URIs</Label>
                    <div className="mt-2 space-y-2">
                      {client.redirect_uris.map((uri, index) => (
                        <div key={index} className="flex items-center space-x-2">
                          <code className="bg-gray-100 dark:bg-gray-800 px-3 py-2 rounded text-sm font-mono flex-1 border">
                            {uri}
                          </code>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => copyToClipboard(uri, 'Redirect URI')}
                          >
                            <Copy className="h-3 w-3" />
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => window.open(uri, '_blank')}
                          >
                            <ExternalLink className="h-3 w-3" />
                          </Button>
                        </div>
                      ))}
                    </div>
                  </div>
                  
                  {(client.description || client.website) && (
                    <div className="pt-4 border-t">
                      {client.description && (
                        <div className="mb-3">
                          <Label className="text-sm font-medium">Description</Label>
                          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">{client.description}</p>
                        </div>
                      )}
                      {client.website && (
                        <div>
                          <Label className="text-sm font-medium">Website</Label>
                          <div className="mt-1">
                            <a 
                              href={client.website} 
                              target="_blank" 
                              rel="noopener noreferrer" 
                              className="text-sm text-blue-600 dark:text-blue-400 hover:underline flex items-center space-x-1"
                            >
                              <span>{client.website}</span>
                              <ExternalLink className="h-3 w-3" />
                            </a>
                          </div>
                        </div>
                      )}
                    </div>
                  )}
                </CardContent>
              </Card>
            ))
          )}
        </div>
      </div>
    </AppLayout>
  )
}