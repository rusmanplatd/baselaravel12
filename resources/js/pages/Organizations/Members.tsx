import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/app';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Plus, Edit, Trash2, UserPlus } from 'lucide-react';

interface User {
  id: string;
  name: string;
  email: string;
}

interface Role {
  id: string;
  name: string;
}

interface Member {
  id: string;
  user: User;
  membership_type: string;
  start_date: string;
  end_date?: string;
  status: string;
  roles: Role[];
}

interface Organization {
  id: string;
  name: string;
  organization_type: string;
}

interface Props {
  organization: Organization;
  members: {
    data: Member[];
    links: any[];
    meta: any;
  };
  availableUsers: User[];
  organizationRoles: Role[];
}

export default function Members({ organization, members, availableUsers, organizationRoles }: Props) {
  const [showAddMember, setShowAddMember] = useState(false);
  const [editingMember, setEditingMember] = useState<Member | null>(null);

  const { data, setData, post, processing, errors, reset } = useForm({
    user_id: '',
    membership_type: 'employee',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    roles: [] as string[],
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route('organizations.members.add', organization.id), {
      onSuccess: () => {
        setShowAddMember(false);
        reset();
      },
    });
  };

  const removeMember = (membership: Member) => {
    if (confirm('Are you sure you want to remove this member?')) {
      router.delete(route('organizations.members.remove', [organization.id, membership.id]));
    }
  };

  const getStatusBadge = (status: string) => {
    const colors = {
      active: 'bg-green-100 text-green-800',
      inactive: 'bg-yellow-100 text-yellow-800',
      terminated: 'bg-red-100 text-red-800',
    };
    return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
  };

  return (
    <AuthenticatedLayout>
      <Head title={`${organization.name} - Members`} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <div>
                  <h2 className="text-2xl font-semibold text-gray-900">
                    {organization.name} - Members
                  </h2>
                  <p className="text-sm text-gray-600 mt-1">
                    Manage organization members and their roles
                  </p>
                </div>
                <Dialog open={showAddMember} onOpenChange={setShowAddMember}>
                  <DialogTrigger asChild>
                    <Button>
                      <UserPlus className="h-4 w-4 mr-2" />
                      Add Member
                    </Button>
                  </DialogTrigger>
                  <DialogContent>
                    <DialogHeader>
                      <DialogTitle>Add New Member</DialogTitle>
                      <DialogDescription>
                        Add a user as a member of this organization.
                      </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                      <div>
                        <Label htmlFor="user_id">User</Label>
                        <Select
                          value={data.user_id}
                          onValueChange={(value) => setData('user_id', value)}
                        >
                          <SelectTrigger>
                            <SelectValue placeholder="Select a user" />
                          </SelectTrigger>
                          <SelectContent>
                            {availableUsers.map((user) => (
                              <SelectItem key={user.id} value={user.id}>
                                {user.name} ({user.email})
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        {errors.user_id && (
                          <p className="text-sm text-red-600 mt-1">{errors.user_id}</p>
                        )}
                      </div>

                      <div>
                        <Label htmlFor="membership_type">Membership Type</Label>
                        <Select
                          value={data.membership_type}
                          onValueChange={(value) => setData('membership_type', value)}
                        >
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="employee">Employee</SelectItem>
                            <SelectItem value="contractor">Contractor</SelectItem>
                            <SelectItem value="board_member">Board Member</SelectItem>
                            <SelectItem value="executive">Executive</SelectItem>
                          </SelectContent>
                        </Select>
                        {errors.membership_type && (
                          <p className="text-sm text-red-600 mt-1">{errors.membership_type}</p>
                        )}
                      </div>

                      <div>
                        <Label htmlFor="start_date">Start Date</Label>
                        <Input
                          id="start_date"
                          type="date"
                          value={data.start_date}
                          onChange={(e) => setData('start_date', e.target.value)}
                        />
                        {errors.start_date && (
                          <p className="text-sm text-red-600 mt-1">{errors.start_date}</p>
                        )}
                      </div>

                      <div>
                        <Label htmlFor="end_date">End Date (Optional)</Label>
                        <Input
                          id="end_date"
                          type="date"
                          value={data.end_date}
                          onChange={(e) => setData('end_date', e.target.value)}
                        />
                        {errors.end_date && (
                          <p className="text-sm text-red-600 mt-1">{errors.end_date}</p>
                        )}
                      </div>

                      <div className="flex justify-end space-x-2">
                        <Button
                          type="button"
                          variant="outline"
                          onClick={() => setShowAddMember(false)}
                        >
                          Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                          {processing ? 'Adding...' : 'Add Member'}
                        </Button>
                      </div>
                    </form>
                  </DialogContent>
                </Dialog>
              </div>

              <Card>
                <CardHeader>
                  <CardTitle>Current Members</CardTitle>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Start Date</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Roles</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {members.data.length === 0 ? (
                        <TableRow>
                          <TableCell colSpan={7} className="text-center py-8">
                            No members found. Add your first member to get started.
                          </TableCell>
                        </TableRow>
                      ) : (
                        members.data.map((member) => (
                          <TableRow key={member.id}>
                            <TableCell className="font-medium">
                              {member.user.name}
                            </TableCell>
                            <TableCell>{member.user.email}</TableCell>
                            <TableCell>
                              <span className="capitalize">
                                {member.membership_type.replace('_', ' ')}
                              </span>
                            </TableCell>
                            <TableCell>{member.start_date}</TableCell>
                            <TableCell>
                              <Badge className={getStatusBadge(member.status)}>
                                {member.status}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              <div className="flex flex-wrap gap-1">
                                {member.roles.map((role) => (
                                  <Badge key={role.id} variant="secondary">
                                    {role.name}
                                  </Badge>
                                ))}
                              </div>
                            </TableCell>
                            <TableCell className="text-right">
                              <div className="flex justify-end space-x-2">
                                <Button
                                  variant="outline"
                                  size="sm"
                                  onClick={() => setEditingMember(member)}
                                >
                                  <Edit className="h-4 w-4" />
                                </Button>
                                <Button
                                  variant="outline"
                                  size="sm"
                                  onClick={() => removeMember(member)}
                                  className="text-red-600 hover:text-red-800"
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
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}