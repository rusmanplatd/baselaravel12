import { useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { Shield, User, Eye, Edit3, Globe } from 'lucide-react'

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
      <Head title={`Authorize ${client.name}`} />
      
      <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div className="sm:mx-auto sm:w-full sm:max-w-md">
          <div className="flex justify-center">
            <Shield className="h-12 w-12 text-blue-600" />
          </div>
          <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">
            Authorization Required
          </h2>
          <p className="mt-2 text-center text-sm text-gray-600">
            {client.name} wants to access your account
          </p>
        </div>

        <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center space-x-2">
                <span>{client.name}</span>
              </CardTitle>
              <CardDescription>
                This application wants to access your account with the following permissions:
              </CardDescription>
            </CardHeader>

            <CardContent className="space-y-6">
              <div className="bg-gray-50 p-4 rounded-lg">
                <h3 className="text-sm font-medium text-gray-700 mb-2">Account Information</h3>
                <p className="text-sm text-gray-600">{user.name}</p>
                <p className="text-sm text-gray-500">{user.email}</p>
              </div>

              <div>
                <h3 className="text-sm font-medium text-gray-700 mb-3">Permissions Requested</h3>
                <div className="space-y-3">
                  {scopes.map((scope) => {
                    const IconComponent = scopeIcons[scope.id as keyof typeof scopeIcons] || scopeIcons.default
                    const isSelected = selectedScopes.includes(scope.id)
                    
                    return (
                      <div key={scope.id} className="flex items-start space-x-3">
                        <Checkbox
                          id={scope.id}
                          checked={isSelected}
                          onCheckedChange={() => toggleScope(scope.id)}
                          className="mt-1"
                        />
                        <div className="flex-1">
                          <label
                            htmlFor={scope.id}
                            className="flex items-center space-x-2 cursor-pointer"
                          >
                            <IconComponent className="h-4 w-4 text-gray-400" />
                            <span className="text-sm font-medium text-gray-700">
                              {scope.name}
                            </span>
                            <Badge variant="outline" className="text-xs">
                              {scope.id}
                            </Badge>
                          </label>
                          <p className="mt-1 text-xs text-gray-500">{scope.description}</p>
                        </div>
                      </div>
                    )
                  })}
                </div>
              </div>

              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p className="text-sm text-yellow-800">
                  <strong>Important:</strong> Only authorize applications you trust. 
                  This will give {client.name} access to the selected information in your account.
                </p>
              </div>

              <div className="flex space-x-3">
                <Button
                  onClick={handleApprove}
                  disabled={approveForm.processing || selectedScopes.length === 0}
                  className="flex-1"
                >
                  {approveForm.processing ? 'Authorizing...' : 'Authorize'}
                </Button>
                <Button
                  onClick={handleDeny}
                  variant="outline"
                  disabled={denyForm.processing}
                  className="flex-1"
                >
                  Cancel
                </Button>
              </div>

              <div className="text-xs text-gray-500 text-center">
                By clicking "Authorize", you allow {client.name} to access your account 
                according to their <a href="#" className="underline">Terms of Service</a> and{' '}
                <a href="#" className="underline">Privacy Policy</a>.
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  )
}