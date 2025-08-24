import { Head, useForm } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Save, ArrowLeft } from 'lucide-react'
import AppLayout from '@/layouts/app-layout'
import { Link } from '@inertiajs/react'
import { type BreadcrumbItem } from '@/types'

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Position Levels', href: '/organization-position-levels' },
  { title: 'Create', href: '/organization-position-levels/create' },
];

export default function Create() {
  const { data, setData, post, processing, errors } = useForm({
    code: '',
    name: '',
    description: '',
    hierarchy_level: 1,
    is_active: true,
    sort_order: 1,
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post(route('organization-position-levels.store'))
  }

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Create Organization Position Level" />

      <div className="space-y-6">
        <div className="flex items-center space-x-4">
          <Link href={route('organization-position-levels.index')}>
            <Button variant="outline" size="sm">
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back
            </Button>
          </Link>
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Create Organization Position Level</h1>
            <p className="text-muted-foreground">
              Add a new organization position level to the organizational hierarchy.
            </p>
          </div>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Organization Position Level Details</CardTitle>
            <CardDescription>
              Configure the new organization position level information and hierarchy.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <Label htmlFor="code">Code *</Label>
                  <Input
                    id="code"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                    placeholder="e.g., senior_manager"
                    className={errors.code ? 'border-destructive' : ''}
                  />
                  {errors.code && (
                    <p className="text-sm text-destructive">{errors.code}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="name">Name *</Label>
                  <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="e.g., Senior Manager"
                    className={errors.name ? 'border-destructive' : ''}
                  />
                  {errors.name && (
                    <p className="text-sm text-destructive">{errors.name}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="hierarchy_level">Hierarchy Level *</Label>
                  <Input
                    id="hierarchy_level"
                    type="number"
                    min="1"
                    value={data.hierarchy_level}
                    onChange={(e) => setData('hierarchy_level', parseInt(e.target.value))}
                    className={errors.hierarchy_level ? 'border-destructive' : ''}
                  />
                  <p className="text-sm text-muted-foreground">
                    Lower numbers = higher hierarchy (1 = highest)
                  </p>
                  {errors.hierarchy_level && (
                    <p className="text-sm text-destructive">{errors.hierarchy_level}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="sort_order">Sort Order *</Label>
                  <Input
                    id="sort_order"
                    type="number"
                    min="1"
                    value={data.sort_order}
                    onChange={(e) => setData('sort_order', parseInt(e.target.value))}
                    className={errors.sort_order ? 'border-destructive' : ''}
                  />
                  {errors.sort_order && (
                    <p className="text-sm text-destructive">{errors.sort_order}</p>
                  )}
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                  id="description"
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  placeholder="Optional description of this organization position level..."
                  className={errors.description ? 'border-destructive' : ''}
                />
                {errors.description && (
                  <p className="text-sm text-destructive">{errors.description}</p>
                )}
              </div>

              <div className="flex items-center space-x-2">
                <Checkbox
                  id="is_active"
                  checked={data.is_active}
                  onCheckedChange={(checked) => setData('is_active', !!checked)}
                />
                <Label htmlFor="is_active">Active</Label>
              </div>

              <div className="flex justify-end space-x-4">
                <Link href={route('organization-position-levels.index')}>
                  <Button type="button" variant="outline">
                    Cancel
                  </Button>
                </Link>
                <Button type="submit" disabled={processing}>
                  <Save className="mr-2 h-4 w-4" />
                  {processing ? 'Creating...' : 'Create Organization Position Level'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  )
}