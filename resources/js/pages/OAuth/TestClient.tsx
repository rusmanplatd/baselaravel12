import { useState, useEffect } from 'react'
import { Head } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Shield, ExternalLink } from 'lucide-react'
import { apiService } from '@/services/ApiService'

export default function TestClient() {
  const [clientId, setClientId] = useState('')
  const [redirectUri, setRedirectUri] = useState('http://localhost:8000/oauth/test/callback')
  const [scopes, setScopes] = useState('openid profile email')
  const [state] = useState(() => Math.random().toString(36).substring(2, 15))
  const [usePKCE, setUsePKCE] = useState(true)
  const [codeVerifier] = useState(() => {
    const array = new Uint8Array(32)
    crypto.getRandomValues(array)
    return btoa(String.fromCharCode.apply(null, Array.from(array)))
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=/g, '')
  })
  const [codeChallenge, setCodeChallenge] = useState('')
  const [authCode, setAuthCode] = useState('')
  const [accessToken, setAccessToken] = useState('')
  const [userInfo, setUserInfo] = useState(null)

  useEffect(() => {
    if (usePKCE) {
      crypto.subtle.digest('SHA-256', new TextEncoder().encode(codeVerifier))
        .then(hash => {
          const challenge = btoa(String.fromCharCode.apply(null, Array.from(new Uint8Array(hash))))
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=/g, '')
          setCodeChallenge(challenge)
        })
    }
  }, [usePKCE, codeVerifier])

  const handleAuthorize = () => {
    const params = new URLSearchParams({
      client_id: clientId,
      redirect_uri: redirectUri,
      response_type: 'code',
      scope: scopes,
      state: state,
    })
    
    if (usePKCE && codeChallenge) {
      params.append('code_challenge', codeChallenge)
      params.append('code_challenge_method', 'S256')
    }
    
    const authUrl = `/oauth/authorize?${params.toString()}`
    window.open(authUrl, '_blank')
  }

  const handleTokenExchange = async () => {
    if (!authCode || !clientId) return

    try {
      const requestBody: any = {
        grant_type: 'authorization_code',
        client_id: clientId,
        code: authCode,
        redirect_uri: redirectUri,
      }
      
      if (usePKCE) {
        requestBody.code_verifier = codeVerifier
      }
      
      const data = await apiService.post('/oidc/token', requestBody, {
        headers: {
          'Authorization': '', // Override default auth header for OAuth
        }
      })
      if (data.access_token) {
        setAccessToken(data.access_token)
      }
    } catch (error) {
      console.error('Token exchange failed:', error)
    }
  }

  const handleUserInfo = async () => {
    if (!accessToken) return

    try {
      const data = await apiService.get('/oidc/userinfo', {
        headers: {
          'Authorization': `Bearer ${accessToken}`,
        }
      })
      setUserInfo(data)
    } catch (error) {
      console.error('UserInfo request failed:', error)
    }
  }

  return (
    <>
      <Head title="OAuth Test Client" />
      
      <div className="min-h-screen bg-gray-50 py-12 px-4">
        <div className="max-w-4xl mx-auto">
          <div className="text-center mb-8">
            <Shield className="h-12 w-12 text-blue-600 mx-auto" />
            <h1 className="mt-4 text-3xl font-bold text-gray-900">OAuth Test Client</h1>
            <p className="mt-2 text-gray-600">
              Test the OAuth authorization flow with your IdP server
            </p>
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            {/* Configuration */}
            <Card>
              <CardHeader>
                <CardTitle>1. OAuth Configuration</CardTitle>
                <CardDescription>
                  Set up your OAuth client credentials and parameters
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <Label htmlFor="client_id">Client ID</Label>
                  <Input
                    id="client_id"
                    value={clientId}
                    onChange={(e) => setClientId(e.target.value)}
                    placeholder="Enter your OAuth client ID"
                  />
                </div>
                
                <div>
                  <Label htmlFor="redirect_uri">Redirect URI</Label>
                  <Input
                    id="redirect_uri"
                    value={redirectUri}
                    onChange={(e) => setRedirectUri(e.target.value)}
                  />
                </div>
                
                <div>
                  <Label htmlFor="scopes">Scopes</Label>
                  <Input
                    id="scopes"
                    value={scopes}
                    onChange={(e) => setScopes(e.target.value)}
                    placeholder="profile email read"
                  />
                </div>
                
                <div>
                  <Label>State (auto-generated)</Label>
                  <Input value={state} readOnly />
                </div>
                
                <div className="flex items-center space-x-2">
                  <Checkbox
                    id="use_pkce"
                    checked={usePKCE}
                    onCheckedChange={(checked) => setUsePKCE(checked as boolean)}
                  />
                  <Label htmlFor="use_pkce" className="text-sm">
                    Use PKCE (Recommended for mobile/public clients)
                  </Label>
                </div>
                
                {usePKCE && (
                  <div className="space-y-2">
                    <div>
                      <Label className="text-sm text-gray-600">Code Verifier</Label>
                      <code className="block text-xs bg-gray-100 p-2 rounded font-mono break-all">
                        {codeVerifier.substring(0, 30)}...
                      </code>
                    </div>
                    <div>
                      <Label className="text-sm text-gray-600">Code Challenge (SHA256)</Label>
                      <code className="block text-xs bg-gray-100 p-2 rounded font-mono break-all">
                        {codeChallenge || 'Generating...'}
                      </code>
                    </div>
                  </div>
                )}
                
                <Button 
                  onClick={handleAuthorize} 
                  disabled={!clientId}
                  className="w-full"
                >
                  <ExternalLink className="h-4 w-4 mr-2" />
                  Start OAuth Authorization
                </Button>
              </CardContent>
            </Card>

            {/* Authorization Code */}
            <Card>
              <CardHeader>
                <CardTitle>2. Authorization Code</CardTitle>
                <CardDescription>
                  Paste the authorization code from the callback
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <Label htmlFor="auth_code">Authorization Code</Label>
                  <Textarea
                    id="auth_code"
                    value={authCode}
                    onChange={(e) => setAuthCode(e.target.value)}
                    placeholder="Paste the authorization code here..."
                    rows={3}
                  />
                </div>
                
                <Button 
                  onClick={handleTokenExchange}
                  disabled={!authCode || !clientId}
                  className="w-full"
                >
                  Exchange for Access Token
                </Button>
                
                {accessToken && (
                  <div className="p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p className="text-sm font-medium text-green-800">Access Token:</p>
                    <code className="text-xs text-green-700 break-all">
                      {accessToken.substring(0, 50)}...
                    </code>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* User Info */}
            <Card className="lg:col-span-2">
              <CardHeader>
                <CardTitle>3. User Information</CardTitle>
                <CardDescription>
                  Fetch user information using the access token
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <Button 
                  onClick={handleUserInfo}
                  disabled={!accessToken}
                  className="w-full"
                >
                  Fetch User Info
                </Button>
                
                {userInfo && (
                  <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 className="font-medium text-blue-900 mb-3">User Information Response:</h4>
                    <pre className="text-sm text-blue-800 bg-white p-3 rounded border overflow-auto">
                      {JSON.stringify(userInfo, null, 2)}
                    </pre>
                  </div>
                )}
              </CardContent>
            </Card>
          </div>

          {/* Discovery Document */}
          <Card className="mt-6">
            <CardHeader>
              <CardTitle>Discovery Document</CardTitle>
              <CardDescription>
                OAuth authorization server metadata
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                <div className="flex items-center space-x-2">
                  <Badge variant="outline">GET</Badge>
                  <code className="text-sm">/.well-known/oauth-authorization-server</code>
                  <a
                    href="/.well-known/oauth-authorization-server"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-blue-600 hover:text-blue-800"
                  >
                    <ExternalLink className="h-4 w-4" />
                  </a>
                </div>
                
                <div className="text-sm text-gray-600">
                  Endpoints available:
                </div>
                <ul className="text-sm text-gray-600 space-y-1">
                  <li>• Authorization: <code>/oauth/authorize</code></li>
                  <li>• Token: <code>/oauth/token</code></li>
                  <li>• UserInfo: <code>/oauth/userinfo</code></li>
                  <li>• Introspection: <code>/oauth/introspect</code></li>
                  <li>• JWKS: <code>/oauth/jwks</code></li>
                  <li>• Client Registration: <code>/oauth/clients</code></li>
                </ul>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  )
}