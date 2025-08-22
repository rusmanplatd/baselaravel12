import { useEffect, useState } from 'react'
import { Head } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { CheckCircle, XCircle, Copy } from 'lucide-react'
import { toast } from 'sonner'

interface Props {
  params: {
    code?: string
    state?: string
    error?: string
    error_description?: string
  }
}

export default function TestCallback({ params }: Props) {
  const [copied, setCopied] = useState(false)

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text)
    setCopied(true)
    toast.success('Authorization code copied to clipboard')
    setTimeout(() => setCopied(false), 2000)
  }

  const isSuccess = params.code && !params.error
  const isError = params.error

  return (
    <>
      <Head title="OAuth Callback" />
      
      <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div className="sm:mx-auto sm:w-full sm:max-w-md">
          <div className="flex justify-center">
            {isSuccess ? (
              <CheckCircle className="h-12 w-12 text-green-600" />
            ) : (
              <XCircle className="h-12 w-12 text-red-600" />
            )}
          </div>
          <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">
            OAuth Callback
          </h2>
          <p className="mt-2 text-center text-sm text-gray-600">
            {isSuccess ? 'Authorization successful!' : 'Authorization failed'}
          </p>
        </div>

        <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-lg">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center space-x-2">
                <span>Callback Parameters</span>
                {isSuccess && <Badge className="bg-green-600">Success</Badge>}
                {isError && <Badge variant="destructive">Error</Badge>}
              </CardTitle>
              <CardDescription>
                Parameters received from the authorization server
              </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
              {isSuccess && (
                <>
                  <div>
                    <label className="text-sm font-medium text-gray-700">
                      Authorization Code
                    </label>
                    <div className="mt-1 flex items-center space-x-2">
                      <code className="bg-gray-100 px-3 py-2 rounded text-sm font-mono flex-1 break-all">
                        {params.code}
                      </code>
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => copyToClipboard(params.code!)}
                      >
                        {copied ? 'Copied!' : <Copy className="h-3 w-3" />}
                      </Button>
                    </div>
                  </div>

                  {params.state && (
                    <div>
                      <label className="text-sm font-medium text-gray-700">
                        State
                      </label>
                      <code className="mt-1 block bg-gray-100 px-3 py-2 rounded text-sm font-mono">
                        {params.state}
                      </code>
                    </div>
                  )}

                  <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p className="text-sm text-green-800">
                      <strong>Next steps:</strong>
                    </p>
                    <ol className="text-sm text-green-700 mt-2 space-y-1">
                      <li>1. Copy the authorization code above</li>
                      <li>2. Go back to the test client</li>
                      <li>3. Paste the code and exchange it for an access token</li>
                    </ol>
                  </div>
                </>
              )}

              {isError && (
                <>
                  <div>
                    <label className="text-sm font-medium text-gray-700">
                      Error
                    </label>
                    <code className="mt-1 block bg-red-100 px-3 py-2 rounded text-sm font-mono text-red-800">
                      {params.error}
                    </code>
                  </div>

                  {params.error_description && (
                    <div>
                      <label className="text-sm font-medium text-gray-700">
                        Error Description
                      </label>
                      <div className="mt-1 bg-red-100 px-3 py-2 rounded text-sm text-red-800">
                        {params.error_description}
                      </div>
                    </div>
                  )}

                  <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p className="text-sm text-red-800">
                      <strong>Common causes:</strong>
                    </p>
                    <ul className="text-sm text-red-700 mt-2 space-y-1">
                      <li>• Invalid client ID</li>
                      <li>• Mismatched redirect URI</li>
                      <li>• User denied authorization</li>
                      <li>• Invalid scope requested</li>
                    </ul>
                  </div>
                </>
              )}

              <div className="flex space-x-3">
                <Button 
                  onClick={() => window.close()} 
                  variant="outline"
                  className="flex-1"
                >
                  Close Window
                </Button>
                <Button 
                  onClick={() => window.location.href = '/oauth/test'}
                  className="flex-1"
                >
                  Back to Test Client
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  )
}