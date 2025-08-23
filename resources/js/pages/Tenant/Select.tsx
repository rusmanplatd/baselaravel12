import React from 'react'
import { Head, useForm } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Building2, ChevronRight } from 'lucide-react'

interface Organization {
  id: string
  name: string
  organization_code: string
  organization_type: string
  level: number
  path?: string
}

interface Props {
  tenants: Organization[]
  currentTenant: Organization | null
}

export default function TenantSelect({ tenants, currentTenant }: Props) {
  const { post, processing } = useForm()

  const handleTenantSwitch = (organizationId: string) => {
    post(route('tenant.switch', { organization_id: organizationId }), {
      preserveScroll: true,
    })
  }

  const getTypeColor = (type: string) => {
    const colors = {
      holding_company: 'bg-purple-100 text-purple-800',
      subsidiary: 'bg-blue-100 text-blue-800',
      division: 'bg-green-100 text-green-800',
      branch: 'bg-orange-100 text-orange-800',
      department: 'bg-yellow-100 text-yellow-800',
      unit: 'bg-gray-100 text-gray-800',
    }
    return colors[type as keyof typeof colors] || colors.unit
  }

  return (
    <AppLayout>
      <Head title="Select Organization" />

      <div className="py-12">
        <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
          <div className="mb-8">
            <h1 className="text-3xl font-bold text-gray-900">Select Organization</h1>
            <p className="mt-2 text-gray-600">
              Choose an organization to continue. You can switch between organizations at any time.
            </p>
          </div>

          {currentTenant && (
            <Card className="mb-6 border-green-200 bg-green-50">
              <CardHeader>
                <CardTitle className="text-green-800 flex items-center gap-2">
                  <Building2 className="h-5 w-5" />
                  Current Organization
                </CardTitle>
                <CardDescription>
                  You are currently working in this organization context
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-semibold text-green-900">{currentTenant.name}</p>
                    <p className="text-sm text-green-700">
                      {currentTenant.organization_code} • Level {currentTenant.level}
                    </p>
                  </div>
                  <Badge className={getTypeColor(currentTenant.organization_type)}>
                    {currentTenant.organization_type.replace('_', ' ')}
                  </Badge>
                </div>
              </CardContent>
            </Card>
          )}

          {tenants.length === 0 ? (
            <Card>
              <CardContent className="text-center py-12">
                <Building2 className="h-12 w-12 mx-auto text-gray-400 mb-4" />
                <h3 className="text-lg font-semibold text-gray-900 mb-2">No Organizations Available</h3>
                <p className="text-gray-600">
                  You don't have access to any organizations. Please contact your administrator.
                </p>
              </CardContent>
            </Card>
          ) : (
            <div className="grid gap-4">
              {tenants.map((tenant) => (
                <Card
                  key={tenant.id}
                  className={`transition-all hover:shadow-md cursor-pointer ${
                    currentTenant?.id === tenant.id
                      ? 'ring-2 ring-green-500 bg-green-50'
                      : 'hover:ring-2 hover:ring-blue-500'
                  }`}
                  onClick={() => !processing && handleTenantSwitch(tenant.id)}
                >
                  <CardContent className="p-6">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-4">
                        <div className="p-3 bg-blue-100 rounded-lg">
                          <Building2 className="h-6 w-6 text-blue-600" />
                        </div>
                        <div>
                          <h3 className="font-semibold text-gray-900">{tenant.name}</h3>
                          <div className="flex items-center gap-2 mt-1">
                            <span className="text-sm text-gray-500">
                              {tenant.organization_code}
                            </span>
                            <span className="text-gray-300">•</span>
                            <span className="text-sm text-gray-500">
                              Level {tenant.level}
                            </span>
                          </div>
                        </div>
                      </div>
                      <div className="flex items-center gap-3">
                        <Badge className={getTypeColor(tenant.organization_type)}>
                          {tenant.organization_type.replace('_', ' ')}
                        </Badge>
                        <ChevronRight className="h-5 w-5 text-gray-400" />
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  )
}
