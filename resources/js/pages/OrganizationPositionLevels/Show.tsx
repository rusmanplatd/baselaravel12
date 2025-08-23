import { Head } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { ArrowLeft, Edit } from 'lucide-react'
import AppLayout from '@/layouts/app-layout'
import { Link } from '@inertiajs/react'

interface OrganizationPosition {
  id: string
  position_code: string
  title: string
  is_active: boolean
  organization_unit: {
    name: string
  }
}

interface OrganizationPositionLevel {
  id: string
  code: string
  name: string
  description?: string
  hierarchy_level: number
  is_active: boolean
  sort_order: number
  organization_positions: OrganizationPosition[]
}

interface Props {
  organizationPositionLevel: OrganizationPositionLevel
}

export default function Show({ organizationPositionLevel }: Props) {
  return (
    <AppLayout>
      <Head title={organizationPositionLevel.name} />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <Link href={route('organization-position-levels.index')}>
              <Button variant="outline" size="sm">
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back
              </Button>
            </Link>
            <div>
              <h1 className="text-3xl font-bold tracking-tight">{organizationPositionLevel.name}</h1>
              <p className="text-muted-foreground">
                Organization position level details and associated positions.
              </p>
            </div>
          </div>
          <Link href={route('organization-position-levels.edit', organizationPositionLevel.id)}>
            <Button>
              <Edit className="mr-2 h-4 w-4" />
              Edit
            </Button>
          </Link>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card>
            <CardHeader>
              <CardTitle>Organization Position Level Information</CardTitle>
              <CardDescription>
                Basic information about this organization position level.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <label className="text-sm font-medium text-muted-foreground">Code</label>
                <div className="mt-1">
                  <code className="text-sm bg-muted px-2 py-1 rounded">
                    {organizationPositionLevel.code}
                  </code>
                </div>
              </div>

              <div>
                <label className="text-sm font-medium text-muted-foreground">Name</label>
                <div className="mt-1 font-medium">{organizationPositionLevel.name}</div>
              </div>

              {organizationPositionLevel.description && (
                <div>
                  <label className="text-sm font-medium text-muted-foreground">Description</label>
                  <div className="mt-1">{organizationPositionLevel.description}</div>
                </div>
              )}

              <div>
                <label className="text-sm font-medium text-muted-foreground">Status</label>
                <div className="mt-1">
                  <Badge variant={organizationPositionLevel.is_active ? 'default' : 'secondary'}>
                    {organizationPositionLevel.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Hierarchy Information</CardTitle>
              <CardDescription>
                Organization position level hierarchy and ordering details.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <label className="text-sm font-medium text-muted-foreground">Hierarchy Level</label>
                <div className="mt-1 font-medium">{organizationPositionLevel.hierarchy_level}</div>
                <p className="text-xs text-muted-foreground">Lower numbers = higher hierarchy</p>
              </div>

              <div>
                <label className="text-sm font-medium text-muted-foreground">Sort Order</label>
                <div className="mt-1 font-medium">{organizationPositionLevel.sort_order}</div>
              </div>

              <div>
                <label className="text-sm font-medium text-muted-foreground">Positions Count</label>
                <div className="mt-1 font-medium">{organizationPositionLevel.organization_positions.length}</div>
              </div>
            </CardContent>
          </Card>
        </div>

        {organizationPositionLevel.organization_positions.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>Associated Positions</CardTitle>
              <CardDescription>
                Organization positions that use this level.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Position Code</TableHead>
                    <TableHead>Title</TableHead>
                    <TableHead>Organization Unit</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {organizationPositionLevel.organization_positions.map((position) => (
                    <TableRow key={position.id}>
                      <TableCell>
                        <code className="text-sm bg-muted px-2 py-1 rounded">
                          {position.position_code}
                        </code>
                      </TableCell>
                      <TableCell className="font-medium">{position.title}</TableCell>
                      <TableCell>{position.organization_unit.name}</TableCell>
                      <TableCell>
                        <Badge variant={position.is_active ? 'default' : 'secondary'}>
                          {position.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}
      </div>
    </AppLayout>
  )
}