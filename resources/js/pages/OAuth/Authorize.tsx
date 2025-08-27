import { useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Shield, User, Eye, Edit3, Globe, ExternalLink } from 'lucide-react'

interface Scope {
  id: string
  name: string
  description: string
}

interface Client {
  id: string
  name: string
  redirect: string
}

interface User {
  name: string
  email: string
}

interface Props {
  client: Client
  scopes: Scope[]
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

export default function Authorize({ client, scopes, user, state, response_type, code_challenge, code_challenge_method }: Props) {
  const [selectedScopes, setSelectedScopes] = useState<string[]>(
    scopes.map(scope => scope.id)
  )

  const approveForm = useForm({
    client_id: client.id,
    redirect_uri: client.redirect,
    scopes: selectedScopes,
    state,
    response_type,
    code_challenge,
    code_challenge_method,
  })

  const denyForm = useForm({
    redirect_uri: client.redirect,
    state,
  })

  const handleApprove = () => {
    approveForm.setData('scopes', selectedScopes)
    approveForm.post(route('oauth.approve'))
  }

  const handleDeny = () => {
    denyForm.delete(route('oauth.deny'))
  }

  const toggleScope = (scopeId: string) => {
    setSelectedScopes(prev =>
      prev.includes(scopeId)
        ? prev.filter(id => id !== scopeId)
        : [...prev, scopeId]
    )
  }

  return (
    <>
      <Head title={`Sign in - ${client.name}`} />
      
      <div className="min-h-screen bg-white flex items-center justify-center p-4">
        <div className="w-full max-w-md">
          <Card className="border-0 shadow-lg">
            <CardContent className="p-8">
              {/* Header with app branding */}
              <div className="text-center mb-8">
                <div className="mb-6">
                  <div className="w-16 h-16 mx-auto bg-blue-50 rounded-full flex items-center justify-center mb-4">
                    <Shield className="w-8 h-8 text-blue-600" />
                  </div>
                  <h1 className="text-xl font-medium text-gray-900 mb-2">
                    Sign in to {client.name}
                  </h1>
                  <p className="text-sm text-gray-600">
                    to continue to {client.name}
                  </p>
                </div>
              </div>

              {/* User account card */}
              <div className="mb-8">
                <div className="border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition-colors">
                  <div className="flex items-center space-x-3">
                    <div className="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center">
                      <User className="w-5 h-5 text-white" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-gray-900 truncate">
                        {user.name}
                      </p>
                      <p className="text-sm text-gray-500 truncate">
                        {user.email}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Permissions section */}
              <div className="mb-8">
                <div className="mb-6">
                  <h2 className="text-lg font-medium text-gray-900 mb-2">
                    {client.name} wants to access your account
                  </h2>
                  <p className="text-sm text-gray-600">
                    This will allow {client.name} to:
                  </p>
                </div>

                <div className="space-y-4">
                  {scopes.map((scope) => {
                    const IconComponent = scopeIcons[scope.id as keyof typeof scopeIcons] || scopeIcons.default
                    const isSelected = selectedScopes.includes(scope.id)
                    
                    return (
                      <div key={scope.id} className="flex items-start space-x-3">
                        <div className="mt-1">
                          <Checkbox
                            id={scope.id}
                            checked={isSelected}
                            onCheckedChange={() => toggleScope(scope.id)}
                          />
                        </div>
                        <div className="flex-1">
                          <label
                            htmlFor={scope.id}
                            className="block cursor-pointer"
                          >
                            <div className="flex items-center space-x-2 mb-1">
                              <IconComponent className="w-4 h-4 text-gray-400" />
                              <span className="text-sm font-medium text-gray-900">
                                {scope.name}
                              </span>
                            </div>
                            <p className="text-xs text-gray-600 leading-relaxed">
                              {scope.description}
                            </p>
                          </label>
                        </div>
                      </div>
                    )
                  })}
                </div>

                <div className="mt-6 p-3 bg-blue-50 rounded-lg">
                  <p className="text-xs text-blue-800 leading-relaxed">
                    Make sure you trust {client.name}. You may be sharing sensitive info with this site or app.{' '}
                    <a href="#" className="underline hover:no-underline">
                      Learn about how {client.name} will handle your data
                    </a>
                  </p>
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
                  disabled={approveForm.processing || selectedScopes.length === 0}
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