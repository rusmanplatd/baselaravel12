import { Head, Link, router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Plus, Edit, Trash2 } from 'lucide-react'
import AppLayout from '@/layouts/app-layout'
import { type BreadcrumbItem } from '@/types'

interface OrganizationPositionLevel {
  id: string
  code: string
  name: string
  description?: string
  hierarchy_level: number
  is_active: boolean
  sort_order: number
  organization_positions_count?: number
}

interface Props {
  organizationPositionLevels: OrganizationPositionLevel[]
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Position Levels', href: '/organization-position-levels' },
];

export default function Index({ organizationPositionLevels }: Props) {
  const handleDelete = (id: string) => {
    if (confirm('Are you sure you want to delete this organization position level?')) {
      router.delete(route('organization-position-levels.destroy', id))
    }
  }

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Organization Position Levels" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Organization Position Levels</h1>
            <p className="text-muted-foreground">
              Manage organizational position levels and hierarchies.
            </p>
          </div>
          <Link href={route('organization-position-levels.create')}>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Add Organization Position Level
            </Button>
          </Link>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Organization Position Levels</CardTitle>
            <CardDescription>
              Configure the organizational hierarchy and organization position levels.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Code</TableHead>
                  <TableHead>Hierarchy Level</TableHead>
                  <TableHead>Sort Order</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Positions Count</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {organizationPositionLevels.map((level) => (
                  <TableRow key={level.id}>
                    <TableCell>
                      <div>
                        <div className="font-medium">{level.name}</div>
                        {level.description && (
                          <div className="text-sm text-muted-foreground">
                            {level.description}
                          </div>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <code className="text-sm bg-muted px-2 py-1 rounded">
                        {level.code}
                      </code>
                    </TableCell>
                    <TableCell>{level.hierarchy_level}</TableCell>
                    <TableCell>{level.sort_order}</TableCell>
                    <TableCell>
                      <Badge variant={level.is_active ? 'default' : 'secondary'}>
                        {level.is_active ? 'Active' : 'Inactive'}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      {level.organization_positions_count ?? 0}
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex items-center justify-end space-x-2">
                        <Link href={route('organization-position-levels.show', level.id)}>
                          <Button variant="outline" size="sm">
                            View
                          </Button>
                        </Link>
                        <Link href={route('organization-position-levels.edit', level.id)}>
                          <Button variant="outline" size="sm">
                            <Edit className="h-4 w-4" />
                          </Button>
                        </Link>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleDelete(level.id)}
                          disabled={(level.organization_positions_count ?? 0) > 0}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  )
}