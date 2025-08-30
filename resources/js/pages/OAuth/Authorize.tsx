import { Head, useForm } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Shield, User, Eye, Edit3, Globe, ExternalLink, AlertTriangle, Building, Info, CheckCircle } from 'lucide-react'

interface Scope {
  id: string
  name: string
  description: string
  sensitive?: boolean
}

interface ScopeCategory {
  title: string
  description: string
  scopes: Scope[]
  required: boolean
}

interface Client {
  id: string
  name: string
  redirect: string
  description?: string
  website?: string
  logo_url?: string
  organization: {
    id: string
    name: string
    code: string
  }
}

interface User {
  name: string
  email: string
  avatar_url?: string
}

interface Props {
  client: Client
  scopes: Record<string, ScopeCategory>
  existingScopes: string[]
  includeGrantedScopes: boolean
  isIncremental: boolean
  user: User
  state?: string
  response_type: string
  code_challenge?: string
  code_challenge_method?: string
}

const scopeIcons = {
  profile: User,
  email: User,
  read: Eye,
  write: Edit3,
  default: Globe,
}

export default function Authorize({ 
  client, 
  scopes, 
  existingScopes,
  includeGrantedScopes,
  isIncremental,
  user, 
  state, 
  response_type, 
  code_challenge, 
  code_challenge_method 
}: Props) {
  // Google-style: all requested scopes are required
  const allRequestedScopes = Object.values(scopes).flatMap(category => category.scopes.map(s => s.id))

  const approveForm = useForm({
    client_id: client.id,
    redirect_uri: client.redirect,
    scopes: allRequestedScopes,
    state,
    response_type,
    code_challenge,
    code_challenge_method,
    include_granted_scopes: includeGrantedScopes ? 'true' : 'false',
  })

  const denyForm = useForm({
    redirect_uri: client.redirect,
    state,
  })

  const handleApprove = () => {
    // Google-style: no scope selection, all requested scopes are required
    approveForm.setData('scopes', allRequestedScopes)
    approveForm.post(route('oauth.approve'))
  }

  const handleDeny = () => {
    denyForm.delete(route('oauth.deny'))
  }

  // Remove scope toggling - Google doesn't allow selective approval

  const isNewScope = (scopeId: string) => !existingScopes.includes(scopeId)
  const getRequiredScopesCount = () => Object.values(scopes).filter(cat => cat.required).length
  const getNewScopesCount = () => allRequestedScopes.filter(isNewScope).length

  return (
    <>
      <Head title={isIncremental ? `Grant additional permissions - ${client.name}` : `Sign in - ${client.name}`} />
      
      <div className="min-h-screen bg-white flex items-center justify-center p-4">
        <div className="w-full max-w-lg">
          <Card className="border-0 shadow-lg">
            <CardContent className="p-8">
              {/* Header with app branding */}
              <div className="text-center mb-8">
                <div className="mb-6">
                  {client.logo_url ? (
                    <img 
                      src={client.logo_url} 
                      alt={`${client.name} logo`} 
                      className="w-16 h-16 mx-auto rounded-lg mb-4"
                    />
                  ) : (
                    <div className="w-16 h-16 mx-auto bg-blue-50 rounded-full flex items-center justify-center mb-4">
                      <Shield className="w-8 h-8 text-blue-600" />
                    </div>
                  )}
                  <h1 className="text-xl font-medium text-gray-900 mb-2">
                    {isIncremental ? `${client.name} wants additional permissions` : `Sign in to ${client.name}`}
                  </h1>
                  <p className="text-sm text-gray-600">
                    {isIncremental 
                      ? `Continue with your ${client.organization.name} account`
                      : `to continue to ${client.name}`
                    }
                  </p>
                </div>
              </div>

              {/* User account card */}
              <div className="mb-8">
                <div className="border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition-colors">
                  <div className="flex items-center space-x-3">
                    {user.avatar_url ? (
                      <img 
                        src={user.avatar_url} 
                        alt={user.name}
                        className="w-8 h-8 rounded-full"
                      />
                    ) : (
                      <div className="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center">
                        <User className="w-5 h-5 text-white" />
                      </div>
                    )}
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-gray-900 truncate">
                        {user.name}
                      </p>
                      <p className="text-sm text-gray-500 truncate">
                        {user.email}
                      </p>
                    </div>
                    <div className="flex items-center space-x-2">
                      <Building className="w-4 h-4 text-gray-400" />
                      <span className="text-xs text-gray-500">{client.organization.name}</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Incremental authorization notice */}
              {isIncremental && (
                <div className="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                  <div className="flex items-start space-x-3">
                    <Info className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                    <div>
                      <p className="text-sm font-medium text-amber-800 mb-1">
                        Requesting additional permissions
                      </p>
                      <p className="text-xs text-amber-700">
                        {client.name} is asking for {getNewScopesCount()} new permission{getNewScopesCount() !== 1 ? 's' : ''} 
                        {includeGrantedScopes && ' in addition to your existing permissions'}.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* Permissions section by category */}
              <div className="mb-8">
                <div className="mb-6">
                  <h2 className="text-lg font-medium text-gray-900 mb-2">
                    {isIncremental ? 'New permissions requested' : `${client.name} wants to access your account`}
                  </h2>
                  <p className="text-sm text-gray-600">
                    {isIncremental 
                      ? 'Review the additional permissions being requested:'
                      : `This will allow ${client.name} to:`
                    }
                  </p>
                </div>

                <div className="space-y-6">
                  {Object.entries(scopes).map(([categoryKey, category]) => (
                    <div key={categoryKey} className="space-y-3">
                      <div className="flex items-center space-x-2">
                        <h3 className="text-sm font-semibold text-gray-900">{category.title}</h3>
                        {category.required && (
                          <Badge variant="secondary" className="text-xs">
                            Required
                          </Badge>
                        )}
                      </div>
                      <p className="text-xs text-gray-600 mb-3">{category.description}</p>

                      <div className="space-y-3 pl-4 border-l-2 border-gray-100">
                        {category.scopes.map((scope) => {
                          const IconComponent = scopeIcons[scope.id as keyof typeof scopeIcons] || scopeIcons.default
                          const isNew = isNewScope(scope.id)
                          
                          return (
                            <div key={scope.id} className="flex items-start space-x-3">
                              <div className="mt-1">
                                {/* Google-style: show checkmark instead of interactive checkbox */}
                                <div className="w-4 h-4 rounded border border-green-500 bg-green-50 flex items-center justify-center">
                                  <CheckCircle className="w-3 h-3 text-green-600" />
                                </div>
                              </div>
                              <div className="flex-1">
                                <div className="flex items-center space-x-2 mb-1">
                                  <IconComponent className="w-4 h-4 text-gray-400" />
                                  <span className="text-sm font-medium text-gray-900">
                                    {scope.name}
                                  </span>
                                  {isNew && (
                                    <Badge variant="outline" className="text-xs">
                                      New
                                    </Badge>
                                  )}
                                  {scope.sensitive && (
                                    <AlertTriangle className="w-4 h-4 text-amber-500" />
                                  )}
                                  {!isNew && isIncremental && (
                                    <Badge variant="secondary" className="text-xs">
                                      Previously granted
                                    </Badge>
                                  )}
                                </div>
                                <p className="text-xs text-gray-600 leading-relaxed">
                                  {scope.description}
                                </p>
                              </div>
                            </div>
                          )
                        })}
                      </div>
                    </div>
                  ))}
                </div>

                <Separator className="my-6" />

                <div className="p-4 bg-blue-50 rounded-lg">
                  <div className="flex items-start space-x-3">
                    <Shield className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                    <div>
                      <p className="text-sm font-medium text-blue-900 mb-1">
                        Make sure you trust {client.name}
                      </p>
                      <p className="text-xs text-blue-800 leading-relaxed">
                        You may be sharing sensitive info with this site or app. Review {client.name}'s{' '}
                        {client.website && (
                          <a href={client.website} target="_blank" rel="noopener noreferrer" className="underline hover:no-underline">
                            privacy policy
                          </a>
                        )}
                        {!client.website && 'privacy policy'} to learn how they handle your data.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Action buttons - Google style */}
              <div className="flex items-center justify-between">
                <Button
                  onClick={handleDeny}
                  variant="ghost"
                  disabled={denyForm.processing}
                  className="text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                >
                  Cancel
                </Button>
                <Button
                  onClick={handleApprove}
                  disabled={approveForm.processing}
                  className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2"
                >
                  {approveForm.processing ? 'Allowing...' : 'Allow'}
                </Button>
              </div>

              {/* Footer links */}
              <div className="mt-8 pt-6 border-t border-gray-100">
                <div className="flex items-center justify-center space-x-6 text-xs text-gray-500">
                  <a href="#" className="hover:text-gray-700 flex items-center">
                    Privacy Policy
                    <ExternalLink className="w-3 h-3 ml-1" />
                  </a>
                  <a href="#" className="hover:text-gray-700 flex items-center">
                    Terms of Service
                    <ExternalLink className="w-3 h-3 ml-1" />
                  </a>
                </div>
                <p className="text-center text-xs text-gray-500 mt-3">
                  English (United States)
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  )
}