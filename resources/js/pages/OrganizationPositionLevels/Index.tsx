import { Head, Link, router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
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
import { Plus, Edit, Trash2, Search, ArrowUpDown, SortAsc, SortDesc, RotateCcw, X, Eye, FileText } from 'lucide-react'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import AppLayout from '@/layouts/app-layout'
import { type BreadcrumbItem } from '@/types'
import { useApiData } from '@/hooks/useApiData'
import { useState, useCallback } from 'react'
import { debounce } from 'lodash'
import ActivityLogModal from '@/components/ActivityLogModal'

interface OrganizationPositionLevel {
  id: string
  code: string
  name: string
  description?: string
  hierarchy_level: number
  is_active: boolean
  sort_order: number
  organization_positions_count?: number
  created_at: string
  updated_at: string
  updated_by?: { name: string } | null
}


const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Position Levels', href: '/organization-position-levels' },
];

export default function Index() {
  const [inputValues, setInputValues] = useState({
    name: '',
    code: '',
    is_active: '',
  });

  const [activityLogModal, setActivityLogModal] = useState({
    isOpen: false,
    subjectType: '',
    subjectId: '',
    title: '',
  });

  const {
    data: organizationPositionLevels,
    loading,
    error,
    sort,
    perPage,
    currentPage,
    totalPages,
    total,
    from,
    to,
    updateFilter,
    updateSort,
    updatePerPage,
    goToPage,
    refresh,
    clearFilters,
  } = useApiData<OrganizationPositionLevel>({
    endpoint: '/api/v1/organization-position-levels',
    initialFilters: {
      name: '',
      code: '',
      is_active: '',
    },
    initialSort: 'sort_order',
    initialPerPage: 15,
  });

  const debouncedUpdateFilter = useCallback(
    debounce((key: string, value: string) => {
      updateFilter(key, value);
    }, 500),
    [updateFilter]
  );

  const handleFilterChange = (key: string, value: string) => {
    setInputValues(prev => ({ ...prev, [key]: value }));
    debouncedUpdateFilter(key, value);
  };

  const handleSort = (field: string) => {
    updateSort(field);
  };

  const getSortIcon = (field: string) => {
    if (sort === field) {
      return <SortAsc className="h-4 w-4" />;
    }
    if (sort === `-${field}`) {
      return <SortDesc className="h-4 w-4" />;
    }
    return <ArrowUpDown className="h-4 w-4 opacity-50" />;
  };

  const handleClearFilters = () => {
    setInputValues({
      name: '',
      code: '',
      is_active: '',
    });
    clearFilters();
  };

  const showActivityLog = (level: OrganizationPositionLevel) => {
    setActivityLogModal({
      isOpen: true,
      subjectType: 'OrganizationPositionLevel',
      subjectId: level.id,
      title: `Activity Log - ${level.name}`,
    });
  };

  const handleDelete = (id: string) => {
    if (confirm('Are you sure you want to delete this organization position level?')) {
      router.delete(route('organization-position-levels.destroy', id), {
        onSuccess: () => {
          refresh();
        },
      });
    }
  };

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
            <div className="flex items-center justify-between">
              <div>
                <CardTitle>Organization Position Levels</CardTitle>
                <CardDescription>
                  Configure the organizational hierarchy and organization position levels.
                </CardDescription>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={handleClearFilters}
                className="gap-2"
              >
                <RotateCcw className="h-4 w-4" />
                Clear Filters
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {/* Filters */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4 z-10" />
                  <Input
                    placeholder="Search by name..."
                    value={inputValues.name}
                    onChange={(e) => handleFilterChange('name', e.target.value)}
                    className="pl-10 pr-10"
                  />
                  {inputValues.name && (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleFilterChange('name', '')}
                      className="absolute right-1 top-1/2 transform -translate-y-1/2 h-8 w-8 p-0 hover:bg-muted"
                    >
                      <X className="h-3 w-3" />
                    </Button>
                  )}
                </div>

                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4 z-10" />
                  <Input
                    placeholder="Search by code..."
                    value={inputValues.code}
                    onChange={(e) => handleFilterChange('code', e.target.value)}
                    className="pl-10 pr-10"
                  />
                  {inputValues.code && (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleFilterChange('code', '')}
                      className="absolute right-1 top-1/2 transform -translate-y-1/2 h-8 w-8 p-0 hover:bg-muted"
                    >
                      <X className="h-3 w-3" />
                    </Button>
                  )}
                </div>

                <Select value={inputValues.is_active || "all"} onValueChange={(value) => handleFilterChange('is_active', value === 'all' ? '' : value)}>
                  <SelectTrigger>
                    <SelectValue placeholder="All statuses" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Statuses</SelectItem>
                    <SelectItem value="1">Active</SelectItem>
                    <SelectItem value="0">Inactive</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <Separator />

              {/* Results Header */}
              <div className="flex items-center justify-between">
                <div className="text-sm text-muted-foreground">
                  {loading ? 'Loading...' : `Showing ${from} to ${to} of ${total} results`}
                </div>
                <div className="flex items-center gap-2">
                  <Label htmlFor="per-page">Show:</Label>
                  <Select value={perPage.toString()} onValueChange={(value) => updatePerPage(parseInt(value))}>
                    <SelectTrigger className="w-20">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="5">5</SelectItem>
                      <SelectItem value="10">10</SelectItem>
                      <SelectItem value="15">15</SelectItem>
                      <SelectItem value="25">25</SelectItem>
                      <SelectItem value="50">50</SelectItem>
                      <SelectItem value="100">100</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              {/* Loading/Error States */}
              {loading && (
                <div className="flex justify-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
              )}

              {error && (
                <div className="text-center py-8 text-red-500">
                  <p>Error loading data: {error}</p>
                  <Button onClick={refresh} className="mt-2">Retry</Button>
                </div>
              )}

              {!loading && !error && (
                <>
                  {/* Table */}
                  <div className="rounded-md border">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="w-[60px]">#</TableHead>
                          <TableHead>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="-ml-3 h-8 data-[state=open]:bg-accent"
                              onClick={() => handleSort('name')}
                            >
                              Name
                              {getSortIcon('name')}
                            </Button>
                          </TableHead>
                          <TableHead className="w-[120px]">
                            <Button
                              variant="ghost"
                              size="sm"
                              className="-ml-3 h-8 data-[state=open]:bg-accent"
                              onClick={() => handleSort('code')}
                            >
                              Code
                              {getSortIcon('code')}
                            </Button>
                          </TableHead>
                          <TableHead>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="-ml-3 h-8 data-[state=open]:bg-accent"
                              onClick={() => handleSort('hierarchy_level')}
                            >
                              Hierarchy Level
                              {getSortIcon('hierarchy_level')}
                            </Button>
                          </TableHead>
                          <TableHead>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="-ml-3 h-8 data-[state=open]:bg-accent"
                              onClick={() => handleSort('sort_order')}
                            >
                              Sort Order
                              {getSortIcon('sort_order')}
                            </Button>
                          </TableHead>
                          <TableHead className="w-[80px]">Status</TableHead>
                          <TableHead>Positions Count</TableHead>
                          <TableHead>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="-ml-3 h-8 data-[state=open]:bg-accent"
                              onClick={() => handleSort('updated_at')}
                            >
                              Updated At
                              {getSortIcon('updated_at')}
                            </Button>
                          </TableHead>
                          <TableHead>Updated By</TableHead>
                          <TableHead className="w-[120px]">Actions</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {loading && organizationPositionLevels.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={10} className="text-center py-12">
                              <RotateCcw className="h-8 w-8 animate-spin mx-auto mb-2" />
                              Loading position levels...
                            </TableCell>
                          </TableRow>
                        ) : organizationPositionLevels.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={10} className="text-center py-12">
                              <div className="flex flex-col items-center">
                                <Plus className="h-12 w-12 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No position levels found</h3>
                                <p className="text-muted-foreground mb-4">
                                  Get started by creating your first organizational position level.
                                </p>
                                <Link href={route('organization-position-levels.create')}>
                                  <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create Position Level
                                  </Button>
                                </Link>
                              </div>
                            </TableCell>
                          </TableRow>
                        ) : (
                          organizationPositionLevels.map((level, index) => (
                            <TableRow key={level.id} className={!level.is_active ? 'opacity-60' : ''}>
                              <TableCell className="text-center text-muted-foreground">
                                {(currentPage - 1) * perPage + index + 1}
                              </TableCell>
                              <TableCell>
                                <div>
                                  <div className="font-medium">{level.name}</div>
                                  {level.description && (
                                    <div className="text-sm text-muted-foreground line-clamp-1">
                                      {level.description}
                                    </div>
                                  )}
                                </div>
                              </TableCell>
                              <TableCell className="font-medium">
                                <code className="text-sm bg-muted px-2 py-1 rounded">
                                  {level.code}
                                </code>
                              </TableCell>
                              <TableCell>{level.hierarchy_level}</TableCell>
                              <TableCell>{level.sort_order}</TableCell>
                              <TableCell>
                                <Badge variant={level.is_active ? 'default' : 'secondary'} className="text-xs">
                                  {level.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                              </TableCell>
                              <TableCell>
                                {level.organization_positions_count ?? 0}
                              </TableCell>
                              <TableCell>
                                {new Date(level.updated_at).toLocaleString()}
                              </TableCell>
                              <TableCell>
                                {level.updated_by ? level.updated_by.name : '-'}
                              </TableCell>
                              <TableCell>
                                <div className="flex items-center gap-1">
                                  <Link href={route('organization-position-levels.show', level.id)}>
                                    <Button variant="ghost" size="sm" title="View Details">
                                      <Eye className="h-4 w-4" />
                                    </Button>
                                  </Link>
                                  <Link href={route('organization-position-levels.edit', level.id)}>
                                    <Button variant="ghost" size="sm" title="Edit">
                                      <Edit className="h-4 w-4" />
                                    </Button>
                                  </Link>
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    title="Activity Log"
                                    onClick={() => showActivityLog(level)}
                                  >
                                    <FileText className="h-4 w-4" />
                                  </Button>
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    title="Delete"
                                    onClick={() => handleDelete(level.id)}
                                    disabled={(level.organization_positions_count ?? 0) > 0}
                                    className="text-destructive hover:text-destructive"
                                  >
                                    <Trash2 className="h-4 w-4" />
                                  </Button>
                                </div>
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </div>

                  {/* Pagination */}
                  {totalPages > 1 && (
                    <div className="flex items-center justify-between pt-4">
                      <div className="text-sm text-muted-foreground">
                        Page {currentPage} of {totalPages} ({total} total results)
                      </div>
                      <div className="flex items-center gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => goToPage(1)}
                          disabled={currentPage === 1 || loading}
                        >
                          First
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => goToPage(currentPage - 1)}
                          disabled={currentPage === 1 || loading}
                        >
                          Previous
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => goToPage(currentPage + 1)}
                          disabled={currentPage === totalPages || loading}
                        >
                          Next
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => goToPage(totalPages)}
                          disabled={currentPage === totalPages || loading}
                        >
                          Last
                        </Button>
                      </div>
                    </div>
                  )}
                </>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      <ActivityLogModal
        isOpen={activityLogModal.isOpen}
        onClose={() => setActivityLogModal(prev => ({ ...prev, isOpen: false }))}
        subjectType={activityLogModal.subjectType}
        subjectId={activityLogModal.subjectId}
        title={activityLogModal.title}
      />
    </AppLayout>
  )
}
