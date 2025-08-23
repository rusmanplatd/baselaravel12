import { useState } from 'react'
import { Head, useForm, router } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog'
import { Plus, Copy, Eye, EyeOff, MoreHorizontal, Trash2, RotateCcw } from 'lucide-react'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { toast } from 'sonner'

interface Client {
  id: string
  name: string
  secret: string
  redirect: string[]
  revoked: boolean
  created_at: string
}

interface Props {
  clients: Client[]
}

export default function Clients({ clients }: Props) {
  const [showCreateDialog, setShowCreateDialog] = useState(false)
  const [showSecrets, setShowSecrets] = useState<Record<string, boolean>>({})

  const createForm = useForm({
    name: '',
    description: '',
    website: '',
    logo_url: '',
    redirect_uris: [''],
  })

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault()
    createForm.post(route('clients.store'), {
      onSuccess: () => {
        setShowCreateDialog(false)
        createForm.reset()
        toast.success('OAuth client created successfully')
      },
    })
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
            <h1 className="text-2xl font-semibold text-gray-900">OAuth Clients</h1>
            <p className="text-sm text-gray-600">
              Manage OAuth applications that can access user accounts
            </p>
          </div>
          
          <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Create Client
              </Button>
            </DialogTrigger>
            <DialogContent className="max-w-2xl">
              <DialogHeader>
                <DialogTitle>Create OAuth Client</DialogTitle>
                <DialogDescription>
                  Create a new OAuth client application to allow third-party access to user accounts.
                </DialogDescription>
              </DialogHeader>
              
              <form onSubmit={handleCreate} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="name">Application Name *</Label>
                    <Input
                      id="name"
                      value={createForm.data.name}
                      onChange={e => createForm.setData('name', e.target.value)}
                      required
                    />
                    {createForm.errors.name && (
                      <p className="text-sm text-red-600 mt-1">{createForm.errors.name}</p>
                    )}
                  </div>
                  
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
              <Card key={client.id}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle className="flex items-center space-x-2">
                        <span>{client.name}</span>
                        {client.revoked && (
                          <Badge variant="destructive">Revoked</Badge>
                        )}
                      </CardTitle>
                      <CardDescription>
                        Created {new Date(client.created_at).toLocaleDateString()}
                      </CardDescription>
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
                
                <CardContent className="space-y-4">
                  <div>
                    <Label className="text-sm font-medium">Client ID</Label>
                    <div className="flex items-center space-x-2 mt-1">
                      <code className="bg-gray-100 px-2 py-1 rounded text-sm font-mono flex-1">
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
                    <div className="flex items-center space-x-2 mt-1">
                      <code className="bg-gray-100 px-2 py-1 rounded text-sm font-mono flex-1">
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
                  
                  <div>
                    <Label className="text-sm font-medium">Redirect URIs</Label>
                    <div className="mt-1 space-y-1">
                      {client.redirect.map((uri, index) => (
                        <code key={index} className="bg-gray-100 px-2 py-1 rounded text-sm font-mono block">
                          {uri}
                        </code>
                      ))}
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))
          )}
        </div>
      </div>
    </AppLayout>
  )
}